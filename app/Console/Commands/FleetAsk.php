<?php

namespace App\Console\Commands;

use App\Ai\FleetAgent;
use Illuminate\Console\Command;

class FleetAsk extends Command
{
    protected $signature = 'fleet:ask {pitanje : Pitanje za agenta}';

    protected $description = 'Postavi pitanje AI agentu iz terminala i prikaži korake koje je napravio';

    public function handle(): int
    {
        $result = app(FleetAgent::class)->run([['role' => 'user', 'content' => $this->argument('pitanje')]]);

        foreach ($result['steps'] as $step) {
            $this->line(sprintf(
                '<fg=gray>[korak %d]</> <fg=cyan>%s</>(%s)%s',
                $step['korak'],
                $step['alat'],
                json_encode($step['ulaz'], JSON_UNESCAPED_UNICODE),
                $step['greska'] ? ' <fg=red>GREŠKA</>' : ''
            ));
        }

        $this->newLine();
        $this->line($result['answer']);
        $this->newLine();
        $this->line(sprintf('<fg=gray>Tokeni: %d ulaz / %d izlaz</>', $result['usage']['input_tokens'], $result['usage']['output_tokens']));

        return self::SUCCESS;
    }
}
