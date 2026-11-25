<?php

namespace App\Console\Commands;

use App\Ai\DocumentIndexer;
use App\Ai\Embeddings;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DocsIndex extends Command
{
    protected $signature = 'docs:index
        {putanja : Datoteka (.md/.txt) ili mapa s datotekama}
        {--fresh : Prvo obriši sve postojeće dokumente}';

    protected $description = 'Indeksiraj dokumente za RAG (odlomci + embeddingsi)';

    public function handle(DocumentIndexer $indexer): int
    {
        $putanja = $this->argument('putanja');

        $datoteke = is_dir($putanja)
            ? glob(rtrim($putanja, '/').'/*.{md,txt}', GLOB_BRACE)
            : [$putanja];

        if (! $datoteke || ! is_file($datoteke[0])) {
            $this->error("Nema datoteka na putanji: $putanja");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Document::query()->delete();
        }

        $this->info(Embeddings::enabled()
            ? 'Embeddingsi: Voyage AI ('.config('services.voyage.model').')'
            : 'VOYAGE_API_KEY nije postavljen → spremam bez embeddinga (pretraga će biti full-text).');

        foreach ($datoteke as $datoteka) {
            $tekst = file_get_contents($datoteka);
            $naslov = $this->naslov($tekst, $datoteka);
            $doc = $indexer->index($naslov, $tekst);
            $this->line("  ✓ $naslov <fg=gray>({$doc->chunks()->count()} odlomaka)</>");
        }

        return self::SUCCESS;
    }

    /** Naslov = prvi "# Naslov" red u dokumentu, inače ime datoteke. */
    private function naslov(string $tekst, string $datoteka): string
    {
        if (preg_match('/^#\s+(.+)$/m', $tekst, $m)) {
            return trim($m[1]);
        }

        return Str::headline(pathinfo($datoteka, PATHINFO_FILENAME));
    }
}
