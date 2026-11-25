<?php

namespace App\Ai\Eval;

use App\Ai\FleetAgent;
use Throwable;

/**
 * Pokreće agenta nad jednim testnim slučajem i ocjenjuje rezultat.
 *
 * Ocjenjivanje je determinističko (bez dodatnog LLM poziva):
 *  1. tool check:    je li agent pozvao sve očekivane alate
 *  2. content check: sadrži li odgovor očekivane činjenice (registracije, brojeve, pravila)
 * Slučaj prolazi samo ako prođu obje provjere.
 */
class EvalRunner
{
    public function __construct(private FleetAgent $agent) {}

    public function run(array $case): array
    {
        $start = microtime(true);

        try {
            $result = $this->agent->run([['role' => 'user', 'content' => $case['question']]]);
        } catch (Throwable $e) {
            return [
                'id' => $case['id'],
                'category' => $case['category'],
                'passed' => false,
                'error' => $e->getMessage(),
                'duration_s' => round(microtime(true) - $start, 1),
            ];
        }

        $usedTools = array_values(array_unique(array_column($result['steps'], 'alat')));
        $missingTools = array_values(array_diff($case['tools'], $usedTools));

        $answer = $this->normalize($result['answer']);
        $missingFacts = [];

        foreach ($case['contains'] as $group) {
            $found = false;
            foreach ($group as $expected) {
                if (str_contains($answer, $this->normalize($expected))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missingFacts[] = implode(' | ', $group);
            }
        }

        return [
            'id' => $case['id'],
            'category' => $case['category'],
            'passed' => ! $missingTools && ! $missingFacts,
            'tools_ok' => ! $missingTools,
            'facts_ok' => ! $missingFacts,
            'used_tools' => $usedTools,
            'missing_tools' => $missingTools,
            'missing_facts' => $missingFacts,
            'tool_errors' => count(array_filter($result['steps'], fn ($s) => $s['greska'])),
            'steps' => count($result['steps']),
            'input_tokens' => $result['usage']['input_tokens'],
            'output_tokens' => $result['usage']['output_tokens'],
            'duration_s' => round(microtime(true) - $start, 1),
            'answer' => $result['answer'],
            'note' => $case['note'],
        ];
    }

    /** Usporedba bez obzira na velika/mala slova, Markdown oznake i vrstu razmaka. */
    private function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", '**', '__', '`'], [' ', ' ', '', '', ''], $text);

        return mb_strtolower(preg_replace('/\s+/u', ' ', $text));
    }
}
