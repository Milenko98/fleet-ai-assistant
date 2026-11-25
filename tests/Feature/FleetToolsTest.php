<?php

namespace Tests\Feature;

use App\Ai\FleetTools;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FleetToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FleetSeeder::class);
    }

    public function test_every_tool_definition_has_required_fields(): void
    {
        foreach (FleetTools::definitions() as $tool) {
            $this->assertNotEmpty($tool['name']);
            $this->assertGreaterThan(50, strlen($tool['description']), "Opis alata {$tool['name']} je prekratak");
            $this->assertSame('object', $tool['inputSchema']['type']);
        }
    }

    public function test_popis_vozila_lists_all_and_filters_by_type(): void
    {
        $this->assertCount(8, FleetTools::execute('popis_vozila', []));

        $dostavna = FleetTools::execute('popis_vozila', ['tip' => 'dostavno']);
        $this->assertSame(['OS-2048-BD', 'ST-3307-DE', 'ZD-6612-HK'], array_column($dostavna, 'registracija'));
    }

    public function test_pretrazi_voznje_filters_by_vehicle_and_min_km(): void
    {
        $od = now()->subDays(14)->toDateString();
        $do = now()->toDateString();

        $result = FleetTools::execute('pretrazi_voznje', [
            'datum_od' => $od, 'datum_do' => $do, 'registracija' => 'zg 5521 km', 'min_km' => 100,
        ]);

        $this->assertNotEmpty($result['voznje']);
        foreach ($result['voznje'] as $row) {
            $this->assertSame('ZG-5521-KM', $row->registracija);   // registracija je normalizirana
            $this->assertGreaterThanOrEqual(100, (float) $row->km);
        }
    }

    public function test_potrosnja_goriva_matches_database(): void
    {
        $od = now()->subDays(30)->toDateString();
        $do = now()->toDateString();

        $result = FleetTools::execute('potrosnja_goriva', ['registracija' => 'ST-3307-DE', 'datum_od' => $od, 'datum_do' => $do]);

        $expected = DB::table('trips')->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('registracija', 'ST-3307-DE')->whereBetween('datum', [$od, $do])
            ->selectRaw('SUM(gorivo_l) / SUM(km) * 100 AS p')->value('p');

        $this->assertEqualsWithDelta($expected, $result['prosjek_l_na_100km'], 0.01);
    }

    public function test_electric_vehicle_reports_kwh(): void
    {
        $result = FleetTools::execute('potrosnja_goriva', [
            'registracija' => 'ZG-7714-EV', 'datum_od' => now()->subDays(30)->toDateString(), 'datum_do' => now()->toDateString(),
        ]);

        $this->assertArrayHasKey('prosjek_kWh_na_100km', $result);
    }

    public function test_alarmi_vozila_filters_by_type(): void
    {
        $result = FleetTools::execute('alarmi_vozila', [
            'datum_od' => now()->subDays(60)->toDateString(), 'datum_do' => now()->toDateString(), 'tip' => 'prekoracenje_brzine',
        ]);

        $this->assertGreaterThan(0, $result['ukupno']);
        $this->assertSame(['prekoracenje_brzine'], array_keys($result['po_tipu']));
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(string $tool, array $input, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        FleetTools::execute($tool, $input);
    }

    public static function invalidInputs(): array
    {
        return [
            'datum nije u formatu' => ['pretrazi_voznje', ['datum_od' => 'jučer', 'datum_do' => '2026-01-01'], 'YYYY-MM-DD'],
            'obrnuto razdoblje' => ['pretrazi_voznje', ['datum_od' => '2026-02-01', 'datum_do' => '2026-01-01'], 'prije'],
            'predugo razdoblje' => ['alarmi_vozila', ['datum_od' => '2020-01-01', 'datum_do' => '2026-01-01'], 'najviše'],
            'nepostojeće vozilo' => ['potrosnja_goriva', ['registracija' => 'XX-0000-XX', 'datum_od' => '2026-01-01', 'datum_do' => '2026-01-31'], 'ne postoji'],
            'prazan upit' => ['pretrazi_dokumente', ['upit' => '  '], 'obavezan'],
            'nepoznat alat' => ['obrisi_sve', [], 'Nepoznat alat'],
        ];
    }
}
