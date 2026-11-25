<?php

namespace App\Ai;

use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Indeksiranje dokumenata za RAG:
 * tekst → odlomci (chunking) → embeddingsi → spremanje u bazu.
 */
class DocumentIndexer
{
    /** Ciljana veličina odlomka u znakovima. Manji odlomci = precizniji pogoci, ali manje konteksta. */
    private const CHUNK_SIZE = 900;

    public function index(string $naslov, string $tekst): Document
    {
        $odlomci = $this->chunk($tekst);

        // Ako Voyage nije podešen, spremamo bez embeddinga → pretraga radi full-text
        $vektori = Embeddings::enabled() ? Embeddings::embed($odlomci, 'document') : [];

        return DB::transaction(function () use ($naslov, $odlomci, $vektori) {
            $document = Document::create(['naslov' => $naslov]);

            foreach ($odlomci as $i => $sadrzaj) {
                DB::insert(
                    'INSERT INTO document_chunks (document_id, redni_broj, sadrzaj, embedding) VALUES (?, ?, ?, ?::vector)',
                    [$document->id, $i + 1, $sadrzaj, isset($vektori[$i]) ? Embeddings::toPgVector($vektori[$i]) : null]
                );
            }

            return $document;
        });
    }

    /**
     * Dijeli tekst po odlomcima (prazni redovi) i spaja kratke odlomke
     * dok ne dosegnu CHUNK_SIZE. Naslov sekcije (## ...) ostaje uz svoj sadržaj.
     *
     * @return string[]
     */
    public function chunk(string $tekst): array
    {
        $odlomci = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $tekst))));

        $chunks = [];
        $trenutni = '';

        foreach ($odlomci as $odlomak) {
            $noviNaslov = str_starts_with($odlomak, '#');

            if ($trenutni !== '' && ($noviNaslov || mb_strlen($trenutni) + mb_strlen($odlomak) > self::CHUNK_SIZE)) {
                $chunks[] = $trenutni;
                $trenutni = '';
            }

            $trenutni = $trenutni === '' ? $odlomak : $trenutni."\n\n".$odlomak;
        }

        if ($trenutni !== '') {
            $chunks[] = $trenutni;
        }

        return $chunks;
    }
}
