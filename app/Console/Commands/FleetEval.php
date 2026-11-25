<?php

namespace App\Console\Commands;

use App\Ai\Eval\EvalCases;
use App\Ai\Eval\EvalRunner;
use App\Ai\FleetAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FleetEval extends Command
{
    protected $signature = 'fleet:eval
        {--case=* : Pokreni samo slučajeve s ovim ID-jem (može više puta)}
        {--category= : Samo jedna kategorija: podaci, rag, kombinirano, guardrail}
        {--repeat=1 : Koliko puta pokrenuti svaki slučaj (agent nije determinističan)}
        {--list : Samo ispiši slučajeve, bez pokretanja}';

    protected $description = 'Evaluacija agenta nad skupom testnih pitanja (točnost, alati, tokeni, vrijeme)';

    public function handle(): int
    {
        $cases = collect(EvalCases::all())
            ->when($this->option('case'), fn ($c) => $c->whereIn('id', $this->option('case')))
            ->when($this->option('category'), fn ($c) => $c->where('category', $this->option('category')))
            ->values();

        if ($cases->isEmpty()) {
            $this->error('Nema slučajeva za zadane filtere.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            $this->table(['ID', 'Kategorija', 'Pitanje', 'Očekivano'], $cases->map(fn ($c) => [
                $c['id'], $c['category'], $c['question'], $c['note'],
            ]));

            return self::SUCCESS;
        }

        $repeat = max(1, (int) $this->option('repeat'));
        $runner = new EvalRunner(app(FleetAgent::class));
        $results = [];

        $this->info(sprintf('Pokrećem %d slučajeva × %d (model: %s)', $cases->count(), $repeat, config('services.anthropic.model')));
        $this->newLine();

        foreach ($cases as $case) {
            for ($i = 1; $i <= $repeat; $i++) {
                $r = $runner->run($case) + ['run' => $i];
                $results[] = $r;

                $this->line(sprintf(
                    '%s <fg=gray>%-32s</> %s',
                    $r['passed'] ? '<fg=green>✓ PASS</>' : '<fg=red>✗ FAIL</>',
                    $case['id'].($repeat > 1 ? " #$i" : ''),
                    $this->detail($r)
                ));
            }
        }

        $this->summary($results);
        $path = $this->save($results);
        $this->line("<fg=gray>Detaljni izvještaj (s odgovorima): storage/app/private/$path</>");

        return collect($results)->every('passed') ? self::SUCCESS : self::FAILURE;
    }

    private function detail(array $r): string
    {
        if (isset($r['error'])) {
            return "<fg=red>greška: {$r['error']}</>";
        }

        $parts = [sprintf('%d koraka, %.1f s, %s tok.', $r['steps'], $r['duration_s'], number_format($r['input_tokens'] + $r['output_tokens'], 0, ',', '.'))];

        if ($r['missing_tools']) {
            $parts[] = '<fg=yellow>nije pozvao: '.implode(', ', $r['missing_tools']).'</>';
        }
        if ($r['missing_facts']) {
            $parts[] = '<fg=yellow>nedostaje: '.implode('; ', $r['missing_facts']).'</>';
        }

        return implode(' · ', $parts);
    }

    private function summary(array $results): void
    {
        $all = collect($results);
        $ok = $all->whereNull('error');

        $this->newLine();
        $this->table(
            ['Kategorija', 'Prolaz', 'Točnost'],
            $all->groupBy('category')->map(fn ($g, $cat) => [
                $cat,
                $g->where('passed', true)->count().' / '.$g->count(),
                round($g->where('passed', true)->count() / $g->count() * 100).' %',
            ])->values()
        );

        $this->line(sprintf(
            '<options=bold>Ukupno: %d / %d (%d %%)</> · ispravni alati: %d %% · ispravne činjenice: %d %% · prosj. %.1f s i %s tokena po pitanju',
            $all->where('passed', true)->count(),
            $all->count(),
            round($all->where('passed', true)->count() / $all->count() * 100),
            $ok->count() ? round($ok->where('tools_ok', true)->count() / $ok->count() * 100) : 0,
            $ok->count() ? round($ok->where('facts_ok', true)->count() / $ok->count() * 100) : 0,
            $ok->avg('duration_s') ?? 0,
            number_format($ok->avg(fn ($r) => $r['input_tokens'] + $r['output_tokens']) ?? 0, 0, ',', '.')
        ));
    }

    private function save(array $results): string
    {
        $path = 'eval/'.now()->format('Y-m-d_His').'.json';

        Storage::put($path, json_encode([
            'model' => config('services.anthropic.model'),
            'date' => now()->toIso8601String(),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
