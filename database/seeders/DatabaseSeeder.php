<?php

namespace Database\Seeders;

use App\Ai\DocumentIndexer;
use App\Ai\Embeddings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(DocumentIndexer $indexer): void
    {
        $this->call(FleetSeeder::class);

        // Primjeri internih dokumenata za RAG (database/docs/*.md)
        foreach (glob(database_path('docs/*.md')) as $datoteka) {
            $tekst = file_get_contents($datoteka);
            preg_match('/^#\s+(.+)$/m', $tekst, $m);
            $indexer->index(trim($m[1] ?? basename($datoteka)), $tekst);
        }

        $this->command->info(Embeddings::enabled()
            ? 'Dokumenti indeksirani s embeddingsima (Voyage AI).'
            : 'Dokumenti indeksirani BEZ embeddinga (nema VOYAGE_API_KEY) → RAG koristi full-text pretragu. '
                .'Kad dodaš ključ: php artisan docs:index database/docs --fresh');
    }
}
