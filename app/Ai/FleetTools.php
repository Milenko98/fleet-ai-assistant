<?php

namespace App\Ai;

use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alati (tools) koje agent smije koristiti.
 *
 * Model NIKAD ne izvršava kod sam: on samo vrati "pozovi alat X s parametrima Y",
 * a ovaj kod to izvrši. Zato ovdje kontroliramo što agent smije:
 * samo unaprijed definirani upiti, validacija svakog ulaza, ograničen broj redaka.
 */
class FleetTools
{
    private const MAX_RAZDOBLJE_DANA = 366;

    /**
     * Definicije alata koje šaljemo modelu (name + description + input_schema).
     * Model vidi SAMO ovo, ne i kod ispod — zato su opisi ključni.
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'popis_vozila',
                'description' => 'Vraća popis svih vozila u voznom parku: registracija, marka i model, tip, gorivo, godina, '
                    .'ukupna kilometraža te kilometraža i datum zadnjeg servisa. Koristi kad trebaš znati koja vozila postoje '
                    .'ili kad korisnik vozilo opiše nepotpuno (npr. samo model).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'tip' => [
                            'type' => 'string',
                            'enum' => ['osobno', 'dostavno', 'kamion'],
                            'description' => 'Opcionalno: samo vozila ovog tipa',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'pretrazi_voznje',
                'description' => 'Vraća vožnje grupirane po vozilu i danu za zadano razdoblje: ukupni km, potrošeno gorivo, '
                    .'ukupno trajanje vožnje u minutama, najduža pojedinačna vožnja u minutama, najveća brzina i vozači. '
                    .'Koristi za pitanja o tome koliko je koje vozilo vozilo, tko je vozio i koliko dugo.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'datum_od' => ['type' => 'string', 'description' => 'Početni datum, format YYYY-MM-DD'],
                        'datum_do' => ['type' => 'string', 'description' => 'Završni datum, format YYYY-MM-DD (uključivo). Ako se pita za jedan dan, isti kao datum_od.'],
                        'registracija' => ['type' => 'string', 'description' => 'Opcionalno: samo za ovo vozilo, npr. ZG-1234-AB'],
                        'min_km' => ['type' => 'number', 'description' => 'Opcionalno: samo dani u kojima je vozilo prešlo barem ovoliko km'],
                    ],
                    'required' => ['datum_od', 'datum_do'],
                ],
            ],
            [
                'name' => 'potrosnja_goriva',
                'description' => 'Za jedno vozilo i razdoblje vraća ukupne km, potrošene litre goriva (ili kWh za električna) '
                    .'i prosječnu potrošnju na 100 km. Koristi za pitanja o potrošnji i usporedbe razdoblja '
                    .'(za usporedbu pozovi alat dvaput, za svako razdoblje).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'registracija' => ['type' => 'string', 'description' => 'Registracija vozila, npr. ZG-1234-AB'],
                        'datum_od' => ['type' => 'string', 'description' => 'Početni datum, format YYYY-MM-DD'],
                        'datum_do' => ['type' => 'string', 'description' => 'Završni datum, format YYYY-MM-DD (uključivo)'],
                    ],
                    'required' => ['registracija', 'datum_od', 'datum_do'],
                ],
            ],
            [
                'name' => 'alarmi_vozila',
                'description' => 'Vraća alarme iz GPS uređaja za razdoblje: prekoračenje brzine, oštro kočenje, '
                    .'dug rad motora u praznom hodu, niska razina goriva. Može se filtrirati po vozilu i tipu alarma.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'datum_od' => ['type' => 'string', 'description' => 'Početni datum, format YYYY-MM-DD'],
                        'datum_do' => ['type' => 'string', 'description' => 'Završni datum, format YYYY-MM-DD (uključivo)'],
                        'registracija' => ['type' => 'string', 'description' => 'Opcionalno: samo za ovo vozilo'],
                        'tip' => [
                            'type' => 'string',
                            'enum' => ['prekoracenje_brzine', 'ostro_kocenje', 'prazan_hod', 'niska_razina_goriva'],
                            'description' => 'Opcionalno: samo alarmi ovog tipa',
                        ],
                    ],
                    'required' => ['datum_od', 'datum_do'],
                ],
            ],
            [
                // RAG: model sam odluči da mu treba znanje iz internih dokumenata
                'name' => 'pretrazi_dokumente',
                'description' => 'Pretražuje interne dokumente tvrtke (pravilnik o radnom vremenu vozača, upute za '
                    .'održavanje vozila, pravila korištenja službenih vozila i sve dokumente koje korisnik doda). '
                    .'Vraća najrelevantnije odlomke s nazivom dokumenta. Koristi za SVA pitanja o pravilima, '
                    .'obavezama, rokovima i postupcima. Upit napiši kao kratku rečenicu ili ključne pojmove.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'upit' => ['type' => 'string', 'description' => 'Što tražiš u dokumentima'],
                    ],
                    'required' => ['upit'],
                ],
            ],
        ];
    }

    /**
     * Izvršava alat koji je model zatražio.
     * Iznimka (npr. neispravan ulaz) se u agentu vraća modelu kao greška, pa je model može ispraviti.
     */
    public static function execute(string $name, array $input): array
    {
        return match ($name) {
            'popis_vozila' => self::popisVozila($input),
            'pretrazi_voznje' => self::pretraziVoznje($input),
            'potrosnja_goriva' => self::potrosnjaGoriva($input),
            'alarmi_vozila' => self::alarmiVozila($input),
            'pretrazi_dokumente' => (new DocumentSearch)->search(self::text($input, 'upit')),
            default => throw new InvalidArgumentException("Nepoznat alat: $name"),
        };
    }

    private static function popisVozila(array $in): array
    {
        return Vehicle::query()
            ->when(! empty($in['tip']), fn ($q) => $q->where('tip', $in['tip']))
            ->orderBy('registracija')
            ->get()->map(fn (Vehicle $v) => [
            'registracija' => $v->registracija,
            'marka_model' => $v->marka_model,
            'tip' => $v->tip,
            'gorivo' => $v->gorivo,
            'godina' => $v->godina,
            'km_ukupno' => $v->km_ukupno,
            'km_od_zadnjeg_servisa' => $v->km_ukupno - $v->km_servis,
            'datum_zadnjeg_servisa' => $v->datum_servisa->toDateString(),
        ])->all();
    }

    private static function pretraziVoznje(array $in): array
    {
        [$od, $do] = self::period($in);

        $q = DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->whereBetween('trips.datum', [$od, $do])
            ->groupBy('vehicles.registracija', 'trips.datum')
            ->selectRaw("vehicles.registracija, trips.datum::text AS datum,
                ROUND(SUM(trips.km), 1) AS km,
                ROUND(SUM(trips.gorivo_l), 1) AS gorivo,
                SUM(trips.trajanje_min) AS trajanje_ukupno_min,
                MAX(trips.trajanje_min) AS najduza_voznja_min,
                MAX(trips.max_brzina) AS max_brzina,
                STRING_AGG(DISTINCT trips.vozac, ', ') AS vozaci")
            ->orderBy('trips.datum')
            ->orderBy('vehicles.registracija')
            ->limit(200);

        if ($reg = self::optionalReg($in)) {
            $q->where('vehicles.registracija', $reg);
        }

        if (isset($in['min_km'])) {
            $q->havingRaw('SUM(trips.km) >= ?', [(float) $in['min_km']]);
        }

        $rows = $q->get();

        return ['razdoblje' => "$od – $do", 'broj_redaka' => $rows->count(), 'voznje' => $rows->all()];
    }

    private static function potrosnjaGoriva(array $in): array
    {
        [$od, $do] = self::period($in);
        $reg = self::requiredReg($in);

        $row = DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('vehicles.registracija', $reg)
            ->whereBetween('trips.datum', [$od, $do])
            ->selectRaw('SUM(trips.km) AS km, SUM(trips.gorivo_l) AS gorivo, COUNT(*) AS broj_voznji, MAX(vehicles.gorivo) AS vrsta')
            ->first();

        if (! $row || ! $row->km) {
            return ['registracija' => $reg, 'razdoblje' => "$od – $do", 'poruka' => 'Nema vožnji u tom razdoblju.'];
        }

        $jedinica = $row->vrsta === 'elektro' ? 'kWh' : 'l';

        return [
            'registracija' => $reg,
            'razdoblje' => "$od – $do",
            'broj_voznji' => (int) $row->broj_voznji,
            'km' => round((float) $row->km, 1),
            "potroseno_$jedinica" => round((float) $row->gorivo, 1),
            "prosjek_{$jedinica}_na_100km" => round($row->gorivo / $row->km * 100, 2),
        ];
    }

    private static function alarmiVozila(array $in): array
    {
        [$od, $do] = self::period($in);

        $q = DB::table('alarms')
            ->join('vehicles', 'vehicles.id', '=', 'alarms.vehicle_id')
            ->whereBetween('alarms.vrijeme', ["$od 00:00:00", "$do 23:59:59"])
            ->select('vehicles.registracija', 'alarms.tip', 'alarms.opis', DB::raw("to_char(alarms.vrijeme, 'YYYY-MM-DD HH24:MI') AS vrijeme"))
            ->orderBy('alarms.vrijeme')
            ->limit(200);

        if ($reg = self::optionalReg($in)) {
            $q->where('vehicles.registracija', $reg);
        }

        if (! empty($in['tip'])) {
            $q->where('alarms.tip', $in['tip']);
        }

        $rows = $q->get();

        return [
            'razdoblje' => "$od – $do",
            'ukupno' => $rows->count(),
            'po_tipu' => $rows->countBy('tip')->all(),
            'alarmi' => $rows->all(),
        ];
    }

    // ---- Validacija ulaza (guardrails) ----------------------------------------------------

    /** @return array{0: string, 1: string} */
    private static function period(array $in): array
    {
        $od = self::date($in, 'datum_od');
        $do = self::date($in, 'datum_do');

        if ($od->greaterThan($do)) {
            throw new InvalidArgumentException('datum_od mora biti prije datum_do.');
        }

        if ($od->diffInDays($do) > self::MAX_RAZDOBLJE_DANA) {
            throw new InvalidArgumentException('Razdoblje smije biti najviše '.self::MAX_RAZDOBLJE_DANA.' dana.');
        }

        return [$od->toDateString(), $do->toDateString()];
    }

    private static function date(array $in, string $key): CarbonImmutable
    {
        $value = $in[$key] ?? '';

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("$key mora biti datum u formatu YYYY-MM-DD.");
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }

    private static function text(array $in, string $key): string
    {
        $value = trim((string) ($in[$key] ?? ''));

        if ($value === '' || mb_strlen($value) > 500) {
            throw new InvalidArgumentException("$key je obavezan (najviše 500 znakova).");
        }

        return $value;
    }

    private static function optionalReg(array $in): ?string
    {
        return empty($in['registracija']) ? null : self::requiredReg($in);
    }

    private static function requiredReg(array $in): string
    {
        // Normaliziramo "zg 1234 ab" → "ZG-1234-AB"
        $reg = strtoupper(preg_replace('/[\s-]+/', '-', trim((string) ($in['registracija'] ?? ''))));

        if (! Vehicle::where('registracija', $reg)->exists()) {
            throw new InvalidArgumentException("Vozilo s registracijom '$reg' ne postoji. Pozovi popis_vozila za ispravne registracije.");
        }

        return $reg;
    }
}
