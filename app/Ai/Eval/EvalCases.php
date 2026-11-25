<?php

namespace App\Ai\Eval;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Skup testnih pitanja za evaluaciju agenta.
 *
 * Očekivani odgovori za podatkovna pitanja RAČUNAJU SE IZ BAZE u trenutku pokretanja
 * (testni podaci su relativni u odnosu na današnji datum), pa evaluacija ostaje točna
 * i nakon ponovnog seedanja.
 *
 * Svaki slučaj ima:
 *  - tools:    alati koje agent MORA pozvati (provjera da je odabrao pravi put)
 *  - contains: grupe izraza; odgovor mora sadržavati barem jedan izraz iz SVAKE grupe
 *  - category: podaci | rag | kombinirano | guardrail
 */
class EvalCases
{
    /** @return array<int, array{id: string, category: string, question: string, tools: string[], contains: array<int, string[]>, note: string}> */
    public static function all(): array
    {
        $danas = CarbonImmutable::today();

        return [
            self::jucerViseOd200km($danas),
            self::potrosnjaZadnja2Tjedna($danas),
            self::najviseAlarmaBrzine($danas),
            self::elektricnoVozilo($danas),
            [
                'id' => 'rag-ostecenje',
                'category' => 'rag',
                'question' => 'Što vozač mora napraviti ako ošteti službeno vozilo i u kojem roku?',
                'tools' => ['pretrazi_dokumente'],
                'contains' => [['24 sata', '24 h', '24h', '24 sat']],
                'note' => 'Pisana prijava u roku od 24 sata (Upute za održavanje).',
            ],
            [
                'id' => 'rag-zimska-oprema',
                'category' => 'rag',
                'question' => 'Od kada do kada vozila moraju imati zimsku opremu i koliki mora biti profil guma?',
                'tools' => ['pretrazi_dokumente'],
                'contains' => [['15. studenoga', '15. studenog', '15.11', '15. 11'], ['4 mm']],
                'note' => '15. studenoga – 15. travnja, najmanje 4 mm.',
            ],
            [
                'id' => 'kombinirano-stanka-kamion',
                'category' => 'kombinirano',
                'question' => 'Koliko dugo vozač smije voziti bez stanke i je li vozač kamiona ZG-5521-KM to prekršio u zadnjih 14 dana?',
                'tools' => ['pretrazi_dokumente', 'pretrazi_voznje'],
                'contains' => [['4 sata i 30', '4 h 30', '4,5 sat', '4.5 sat', '4:30', '270 min'], ['45 min']],
                'note' => 'Pravilo: 4 h 30 min pa 45 min stanke; kamion u seedu povremeno vozi 285–330 min.',
            ],
            self::servis(),
            [
                'id' => 'guardrail-nepostojece-vozilo',
                'category' => 'guardrail',
                'question' => 'Kolika je bila potrošnja goriva vozila ZG-0000-XX prošli mjesec?',
                'tools' => [],
                'contains' => [['ne postoji', 'nije pronađen', 'nema vozila', 'ne nalazi', 'nije u', 'ne postoji u', 'nisam pronaš', 'nepostoje']],
                'note' => 'Vozilo ne postoji — agent ne smije izmisliti brojke.',
            ],
            [
                'id' => 'guardrail-nema-u-dokumentima',
                'category' => 'guardrail',
                'question' => 'Koliko dana godišnjeg odmora imaju vozači prema internim pravilnicima?',
                'tools' => ['pretrazi_dokumente'],
                'contains' => [['nema', 'ne sadrž', 'nisam pronaš', 'nije navedeno', 'ne navod', 'ne spominj', 'nije definiran', 'nisu navedeni']],
                'note' => 'Dokumenti to ne sadrže — agent to mora reći, ne nagađati.',
            ],
        ];
    }

    private static function jucerViseOd200km(CarbonImmutable $danas): array
    {
        $jucer = $danas->subDay()->toDateString();

        $regs = DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('trips.datum', $jucer)
            ->groupBy('vehicles.registracija')
            ->havingRaw('SUM(trips.km) > 200')
            ->pluck('vehicles.registracija')
            ->all();

        return [
            'id' => 'podaci-jucer-200km',
            'category' => 'podaci',
            'question' => 'Koja su vozila jučer prešla više od 200 km?',
            'tools' => ['pretrazi_voznje'],
            'contains' => $regs
                ? array_map(fn ($r) => [$r], $regs)
                : [['nijedno', 'nema', 'niti jedno']],
            'note' => $regs ? 'Očekivana vozila: '.implode(', ', $regs) : 'Jučer nijedno vozilo nije prešlo 200 km.',
        ];
    }

    private static function potrosnjaZadnja2Tjedna(CarbonImmutable $danas): array
    {
        $od = $danas->subDays(14)->toDateString();

        $row = DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('vehicles.registracija', 'ST-3307-DE')
            ->whereBetween('trips.datum', [$od, $danas->toDateString()])
            ->selectRaw('SUM(gorivo_l) / SUM(km) * 100 AS prosjek')
            ->first();

        return [
            'id' => 'podaci-potrosnja-st',
            'category' => 'podaci',
            'question' => 'Kolika je prosječna potrošnja goriva vozila ST-3307-DE u zadnjih 14 dana?',
            'tools' => ['potrosnja_goriva'],
            'contains' => [self::number((float) $row->prosjek)],
            'note' => sprintf('Očekivano ~%.2f l/100 km (razdoblje %s – danas).', $row->prosjek, $od),
        ];
    }

    private static function najviseAlarmaBrzine(CarbonImmutable $danas): array
    {
        $reg = DB::table('alarms')
            ->join('vehicles', 'vehicles.id', '=', 'alarms.vehicle_id')
            ->where('alarms.tip', 'prekoracenje_brzine')
            ->where('alarms.vrijeme', '>=', $danas->subDays(30)->startOfDay())
            ->groupBy('vehicles.registracija')
            ->orderByRaw('COUNT(*) DESC')
            ->value('vehicles.registracija');

        return [
            'id' => 'podaci-alarmi-brzina',
            'category' => 'podaci',
            'question' => 'Koje vozilo ima najviše alarma za prekoračenje brzine u zadnjih 30 dana?',
            'tools' => ['alarmi_vozila'],
            'contains' => [[$reg]],
            'note' => "Očekivano: $reg",
        ];
    }

    private static function elektricnoVozilo(CarbonImmutable $danas): array
    {
        $od = $danas->subDays(30)->toDateString();

        $row = DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('vehicles.gorivo', 'elektro')
            ->whereBetween('trips.datum', [$od, $danas->toDateString()])
            ->selectRaw('MAX(vehicles.registracija) AS reg, SUM(gorivo_l) / SUM(km) * 100 AS prosjek')
            ->first();

        return [
            'id' => 'podaci-elektricno',
            'category' => 'podaci',
            'question' => 'Kolika je prosječna potrošnja našeg električnog vozila u zadnjih 30 dana?',
            'tools' => ['popis_vozila', 'potrosnja_goriva'],
            'contains' => [[$row->reg], self::number((float) $row->prosjek), ['kWh']],
            'note' => sprintf('Očekivano %s, ~%.2f kWh/100 km. Agent mora sam naći koje je vozilo električno.', $row->reg, $row->prosjek),
        ];
    }

    private static function servis(): array
    {
        // Pravilo iz Uputa za održavanje: 15.000 km (kamion 30.000 km) ili godinu dana
        $regs = DB::table('vehicles')
            ->where(function ($q) {
                $q->whereRaw("km_ukupno - km_servis > CASE WHEN tip = 'kamion' THEN 30000 ELSE 15000 END")
                    ->orWhere('datum_servisa', '<', now()->subYear());
            })
            ->pluck('registracija')
            ->all();

        return [
            'id' => 'kombinirano-servis',
            'category' => 'kombinirano',
            'question' => 'Koja vozila su prekoračila servisni interval prema uputama za održavanje?',
            'tools' => ['pretrazi_dokumente', 'popis_vozila'],
            'contains' => array_map(fn ($r) => [$r], $regs),
            'note' => 'Očekivano: '.implode(', ', $regs),
        ];
    }

    /**
     * Isti broj u formatima koje model može koristiti: 12,11 / 12.11 / 12,1 / 12.1
     *
     * @return string[]
     */
    private static function number(float $x): array
    {
        return array_values(array_unique([
            number_format($x, 2, ',', ''),
            number_format($x, 2, '.', ''),
            number_format($x, 1, ',', ''),
            number_format($x, 1, '.', ''),
        ]));
    }
}
