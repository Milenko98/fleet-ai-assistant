<?php

namespace Tests\Unit;

use App\Ai\Eval\EvalRunner;
use App\Ai\FleetAgent;
use Tests\Support\FakeClaude;
use Tests\TestCase;

class EvalRunnerTest extends TestCase
{
    private function runCase(string $answer, array $case): array
    {
        $fake = new FakeClaude([FakeClaude::text($answer)]);

        return (new EvalRunner(new FleetAgent($fake->client())))->run($case + [
            'id' => 'test', 'category' => 'rag', 'question' => 'Pitanje?', 'note' => '',
        ]);
    }

    public function test_passes_when_facts_present_ignoring_case_and_markdown(): void
    {
        $r = $this->runCase('Prijava u roku od **24 Sata**, profil 4 mm.', [
            'tools' => [],
            'contains' => [['24 sata', '24 h'], ['4 mm']],
        ]);

        $this->assertTrue($r['passed']);
    }

    public function test_reports_missing_facts_and_tools(): void
    {
        $r = $this->runCase('Ne znam.', [
            'tools' => ['pretrazi_dokumente'],
            'contains' => [['24 sata']],
        ]);

        $this->assertFalse($r['passed']);
        $this->assertSame(['pretrazi_dokumente'], $r['missing_tools']);
        $this->assertSame(['24 sata'], $r['missing_facts']);
    }
}
