<?php

namespace App\Ai;

use Illuminate\Support\Facades\DB;

/**
 * Retrieval dio RAG-a: pronalazi odlomke dokumenata relevantne za upit.
 *
 * - semantic: embedding upita + najbliži vektori u pgvectoru (pretraga po ZNAČENJU)
 * - fulltext: PostgreSQL tsvector (pretraga po RIJEČIMA) — koristi se kad Voyage nije podešen
 */
class DocumentSearch
{
    /** Odlomci slabije sličnosti se odbacuju da model ne dobije nebitan kontekst. Podesi testiranjem. */
    private const MIN_SLICNOST = 0.35;

    public static function mode(): string
    {
        $imaEmbeddinge = DB::table('document_chunks')->whereNotNull('embedding')->exists();

        return Embeddings::enabled() && $imaEmbeddinge ? 'semantic' : 'fulltext';
    }

    /**
     * @return array{nacin: string, rezultati: array<int, array{dokument: string, odlomak: string, score: float}>}
     */
    public function search(string $upit, int $limit = 3): array
    {
        $nacin = self::mode();

        $rezultati = $nacin === 'semantic'
            ? $this->semantic($upit, $limit)
            : $this->fulltext($upit, $limit);

        return ['nacin' => $nacin, 'rezultati' => $rezultati];
    }

    private function semantic(string $upit, int $limit): array
    {
        $vektor = Embeddings::toPgVector(Embeddings::embed([$upit], 'query')[0]);

        // <=> je cosine udaljenost (0 = isto značenje); sličnost = 1 - udaljenost
        $rows = DB::select(
            'SELECT d.naslov, c.sadrzaj, 1 - (c.embedding <=> ?::vector) AS score
               FROM document_chunks c
               JOIN documents d ON d.id = c.document_id
              WHERE c.embedding IS NOT NULL
              ORDER BY c.embedding <=> ?::vector
              LIMIT ?',
            [$vektor, $vektor, $limit]
        );

        return $this->format(array_filter($rows, fn ($r) => $r->score >= self::MIN_SLICNOST));
    }

    private function fulltext(string $upit, int $limit): array
    {
        // Riječi upita spajamo s OR (|) da pogodimo odlomke koji sadrže bilo koju od njih
        $rijeci = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($upit)))
            ->filter(fn ($w) => mb_strlen($w) > 2)
            ->unique()
            ->values();

        if ($rijeci->isEmpty()) {
            return [];
        }

        $rows = DB::select(
            "SELECT d.naslov, c.sadrzaj, ts_rank(c.tsv, to_tsquery('simple', ?)) AS score
               FROM document_chunks c
               JOIN documents d ON d.id = c.document_id
              WHERE c.tsv @@ to_tsquery('simple', ?)
              ORDER BY score DESC
              LIMIT ?",
            [$rijeci->implode(' | '), $rijeci->implode(' | '), $limit]
        );

        return $this->format($rows);
    }

    private function format(array $rows): array
    {
        return array_values(array_map(fn ($r) => [
            'dokument' => $r->naslov,
            'odlomak' => $r->sadrzaj,
            'score' => round((float) $r->score, 3),
        ], $rows));
    }
}
