<?php

namespace Tests\Feature;

use App\Ai\Eval\EvalCases;
use App\Ai\FleetTools;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvalCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_eval_cases_are_well_formed(): void
    {
        $this->seed(FleetSeeder::class);

        $cases = EvalCases::all();
        $toolNames = array_column(FleetTools::definitions(), 'name');

        $this->assertCount(10, $cases);
        $this->assertCount(10, array_unique(array_column($cases, 'id')));

        foreach ($cases as $case) {
            $this->assertContains($case['category'], ['podaci', 'rag', 'kombinirano', 'guardrail']);
            $this->assertNotEmpty($case['contains'], "{$case['id']} nema očekivanih činjenica");
            $this->assertEmpty(array_diff($case['tools'], $toolNames), "{$case['id']} očekuje nepostojeći alat");

            foreach ($case['contains'] as $group) {
                $this->assertNotEmpty(array_filter($group), "{$case['id']} ima praznu grupu očekivanja");
            }
        }
    }
}
