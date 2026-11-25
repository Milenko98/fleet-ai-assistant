<?php

namespace App\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Embeddingsi preko Voyage AI API-ja.
 *
 * Embedding = niz brojeva (vektor) koji predstavlja ZNAČENJE teksta.
 * Tekstovi sličnog značenja ("pauza u vožnji" i "odmor vozača") dobiju vektore koji su blizu,
 * pa ih možemo naći i kad ne dijele iste riječi.
 */
class Embeddings
{
    public static function enabled(): bool
    {
        return filled(config('services.voyage.key'));
    }

    /**
     * @param  string[]  $texts
     * @param  string  $inputType  'document' kod indeksiranja, 'query' kod pretrage
     * @return array<int, float[]>
     */
    public static function embed(array $texts, string $inputType): array
    {
        $vectors = [];

        // Voyage prima više tekstova u jednom zahtjevu; šaljemo u paketima
        foreach (array_chunk($texts, 64) as $batch) {
            $data = Http::withToken(config('services.voyage.key'))
                ->timeout(30)
                ->retry(2, 500)
                ->post('https://api.voyageai.com/v1/embeddings', [
                    'input' => $batch,
                    'model' => config('services.voyage.model'),
                    'input_type' => $inputType,
                ])
                ->throw()
                ->json('data');

            // API vraća objekte s indeksom; sortiramo za svaki slučaj
            usort($data, fn ($a, $b) => $a['index'] <=> $b['index']);

            foreach ($data as $item) {
                $vectors[] = $item['embedding'];
            }
        }

        return $vectors;
    }

    /** pgvector prima vektor kao tekst oblika "[0.12,-0.83,...]" */
    public static function toPgVector(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
