<?php

namespace Database\Seeders;

use App\Models\Alarm;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Izmišljeni vozni park s vožnjama i alarmima za zadnjih 60 dana.
 * Podaci su generirani s fiksnim seedom (uvijek isti) i namjerno sadrže "zanimljive" slučajeve
 * na kojima se može testirati agent:
 *  - ZG-5521-KM vozi duge dionice bez stanke (> 4 h 30 min) → kršenje pravilnika o odmoru
 *  - ST-3307-DE ima povećanu potrošnju zadnja 2 tjedna → odstupanje > 15 %
 *  - RI-8840-TT ima puno alarma za prekoračenje brzine
 *  - ZG-1290-AC je prekoračio servisni interval
 */
class FleetSeeder extends Seeder
{
    private const DANA = 60;

    public function run(): void
    {
        mt_srand(42);

        $vozila = [
            // registracija, marka_model, tip, gorivo, godina, km_ukupno, km od servisa, l/100km, km po vožnji [min,max], vozači
            ['ZG-1290-AC', 'Škoda Octavia 2.0 TDI', 'osobno', 'dizel', 2021, 148_300, 17_400, 5.2, [20, 120], ['Ivan Horvat', 'Marko Kovačević']],
            ['ZG-5521-KM', 'MAN TGL 12.250', 'kamion', 'dizel', 2019, 412_800, 21_000, 19.5, [150, 420], ['Damir Babić']],
            ['ST-3307-DE', 'Renault Master 2.3 dCi', 'dostavno', 'dizel', 2020, 201_500, 6_200, 9.8, [40, 180], ['Ante Marić', 'Josip Jurić']],
            ['RI-8840-TT', 'VW Passat 2.0 TDI', 'osobno', 'dizel', 2022, 96_700, 9_900, 5.6, [60, 260], ['Petra Novak']],
            ['ZG-7714-EV', 'VW ID.4 Pro', 'osobno', 'elektro', 2023, 41_200, 3_100, 17.2, [20, 110], ['Ana Knežević', 'Luka Pavlović']],
            ['OS-2048-BD', 'Iveco Daily 35S16', 'dostavno', 'dizel', 2018, 287_900, 12_800, 11.4, [50, 200], ['Tomislav Vuković']],
            ['ZD-6612-HK', 'Ford Transit Custom', 'dostavno', 'dizel', 2021, 133_400, 4_700, 8.6, [30, 160], ['Nikola Matić', 'Ivana Šarić']],
            ['ZG-3355-MP', 'Toyota Corolla Hybrid', 'osobno', 'benzin', 2023, 38_900, 8_100, 4.4, [15, 90], ['Maja Tomić']],
        ];

        $danas = CarbonImmutable::today();

        foreach ($vozila as [$reg, $model, $tip, $gorivo, $godina, $kmUkupno, $kmOdServisa, $potrosnja, [$minKm, $maxKm], $vozaci]) {
            $vehicle = Vehicle::create([
                'registracija' => $reg,
                'marka_model' => $model,
                'tip' => $tip,
                'gorivo' => $gorivo,
                'godina' => $godina,
                'km_ukupno' => $kmUkupno,
                'km_servis' => $kmUkupno - $kmOdServisa,
                'datum_servisa' => $danas->subDays(mt_rand(40, 330)),
            ]);

            for ($d = self::DANA; $d >= 0; $d--) {
                $datum = $danas->subDays($d);

                // Vikendom vozi samo kamion (subotom), ostali rijetko
                if ($datum->isSunday() || ($datum->isSaturday() && $tip !== 'kamion' && mt_rand(1, 10) > 2)) {
                    continue;
                }

                $brojVoznji = $tip === 'kamion' ? mt_rand(1, 2) : mt_rand(1, 3);
                $sat = mt_rand(6, 8);

                for ($v = 0; $v < $brojVoznji; $v++) {
                    $km = mt_rand($minKm * 10, $maxKm * 10) / 10;

                    // Prosječna brzina: kamion ~60 km/h, ostali ~45–70 km/h
                    $prosjecna = $tip === 'kamion' ? mt_rand(55, 68) : mt_rand(45, 72);
                    $trajanje = (int) round($km / $prosjecna * 60);

                    // Kamion povremeno vozi predugo bez stanke (za test pravilnika o odmoru)
                    if ($reg === 'ZG-5521-KM' && mt_rand(1, 4) === 1) {
                        $trajanje = mt_rand(285, 330);
                        $km = round($trajanje / 60 * $prosjecna, 1);
                    }

                    $faktor = mt_rand(90, 112) / 100;

                    // Dostavno vozilo troši ~20 % više zadnja 2 tjedna (npr. kvar ili loš stil vožnje)
                    if ($reg === 'ST-3307-DE' && $d <= 14) {
                        $faktor *= 1.22;
                    }

                    Trip::create([
                        'vehicle_id' => $vehicle->id,
                        'vozac' => $vozaci[array_rand($vozaci)],
                        'datum' => $datum->toDateString(),
                        'pocetak' => sprintf('%02d:%02d', min($sat, 22), mt_rand(0, 59)),
                        'trajanje_min' => $trajanje,
                        'km' => $km,
                        'gorivo_l' => round($km * $potrosnja / 100 * $faktor, 1),
                        'max_brzina' => $tip === 'kamion' ? mt_rand(80, 92) : mt_rand(85, $reg === 'RI-8840-TT' ? 168 : 138),
                    ]);

                    $sat += intdiv($trajanje, 60) + mt_rand(1, 2);
                }

                $this->alarmi($vehicle, $datum, $reg);
            }
        }
    }

    private function alarmi(Vehicle $vehicle, CarbonImmutable $datum, string $reg): void
    {
        $vjerojatnosti = [
            'prekoracenje_brzine' => $reg === 'RI-8840-TT' ? 45 : 6,
            'ostro_kocenje' => $reg === 'RI-8840-TT' ? 15 : 5,
            'prazan_hod' => $vehicle->tip === 'dostavno' ? 12 : 4,
            'niska_razina_goriva' => 3,
        ];

        foreach ($vjerojatnosti as $tip => $postotak) {
            if (mt_rand(1, 100) > $postotak) {
                continue;
            }

            Alarm::create([
                'vehicle_id' => $vehicle->id,
                'vrijeme' => $datum->setTime(mt_rand(7, 18), mt_rand(0, 59)),
                'tip' => $tip,
                'opis' => match ($tip) {
                    'prekoracenje_brzine' => sprintf('Brzina %d km/h u zoni ograničenja %d km/h', ($lim = [50, 80, 130][mt_rand(0, 2)]) + mt_rand(12, 35), $lim),
                    'ostro_kocenje' => sprintf('Usporenje %.1f m/s²', mt_rand(55, 85) / 10),
                    'prazan_hod' => sprintf('Motor u praznom hodu %d min', mt_rand(11, 40)),
                    'niska_razina_goriva' => sprintf('Razina goriva %d %%', mt_rand(5, 12)),
                },
            ]);
        }
    }
}
