<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Messages\Message;
use Anthropic\Messages\ToolUseBlock;
use RuntimeException;
use Throwable;

/**
 * AI agent za vozni park — ručno napisana agent petlja.
 *
 * Tok:
 *  1. Pošalji modelu: system prompt + definicije alata + cijeli razgovor
 *  2. Ako model vrati stop_reason "tool_use" → izvrši tražene alate,
 *     dodaj rezultate u razgovor i ponovi korak 1
 *  3. Kad model vrati bilo što drugo (npr. "end_turn") → to je konačni odgovor
 *
 * API ne pamti ništa između zahtjeva: svaki put šaljemo cijeli niz poruka.
 */
class FleetAgent
{
    /** Guardrail: agent ne smije vrtjeti petlju beskonačno (i trošiti tokene). */
    private const MAX_KORAKA = 8;

    private Client $client;

    public function __construct(?Client $client = null)
    {
        if (! $client && blank(config('services.anthropic.key'))) {
            throw new RuntimeException('ANTHROPIC_API_KEY nije postavljen u .env datoteci.');
        }

        $this->client = $client ?? new Client(apiKey: config('services.anthropic.key'));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history  dosadašnji razgovor; zadnja poruka je korisnikovo pitanje
     * @return array{answer: string, steps: array, usage: array{input_tokens: int, output_tokens: int}, model: string}
     */
    public function run(array $history): array
    {
        $messages = $history;
        $steps = [];
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        for ($korak = 1; $korak <= self::MAX_KORAKA; $korak++) {
            // 1) Zahtjev modelu
            $response = $this->client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                system: [['type' => 'text', 'text' => $this->systemPrompt()]],
                tools: FleetTools::definitions(),
                messages: $messages,
            );

            $usage['input_tokens'] += $response->usage->inputTokens;
            $usage['output_tokens'] += $response->usage->outputTokens;

            // 2) Model je gotov → konačni odgovor
            if ($response->stopReason !== 'tool_use') {
                return [
                    'answer' => $this->finalAnswer($response),
                    'steps' => $steps,
                    'usage' => $usage,
                    'model' => $response->model,
                ];
            }

            // 3) Model traži alate → izvrši svaki (može ih tražiti više odjednom)
            $toolResults = [];

            foreach ($response->content as $block) {
                if (! $block instanceof ToolUseBlock) {
                    continue;   // thinking/text blokove samo prenosimo dalje u povijesti
                }

                $start = microtime(true);

                try {
                    $output = FleetTools::execute($block->name, $block->input);
                    $isError = false;
                } catch (Throwable $e) {
                    // Grešku vraćamo modelu: često sam ispravi parametre i pokuša ponovno
                    $output = ['greska' => $e->getMessage()];
                    $isError = true;
                    report($e);
                }

                $steps[] = [
                    'korak' => $korak,
                    'alat' => $block->name,
                    'ulaz' => $block->input,
                    'izlaz' => $output,
                    'greska' => $isError,
                    'trajanje_ms' => (int) round((microtime(true) - $start) * 1000),
                ];

                $toolResults[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,   // povezuje rezultat s pozivom
                    'content' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'isError' => $isError,
                ];
            }

            // 4) U povijest dodajemo odgovor modela (nepromijenjen!) i SVE rezultate u JEDNOJ user poruci
            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return [
            'answer' => 'Nisam uspio završiti zadatak u dozvoljenom broju koraka ('.self::MAX_KORAKA.'). Pokušaj postaviti konkretnije pitanje.',
            'steps' => $steps,
            'usage' => $usage,
            'model' => config('services.anthropic.model'),
        ];
    }

    private function finalAnswer(Message $response): string
    {
        if ($response->stopReason === 'refusal') {
            return 'Model je odbio odgovoriti na ovaj zahtjev.';
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if ($response->stopReason === 'max_tokens') {
            $text .= "\n\n_(Odgovor je prekinut jer je dosegnut limit duljine.)_";
        }

        return trim($text) !== '' ? $text : 'Model nije vratio tekstualni odgovor.';
    }

    private function systemPrompt(): string
    {
        $danas = now()->locale('hr');

        return <<<TXT
            Ti si AI asistent za upravljanje voznim parkom tvrtke. Pomažeš dispečerima i voditeljima voznog parka.

            Današnji datum: {$danas->toDateString()} ({$danas->dayName}).

            Pravila:
            - Za podatke o vozilima, vožnjama, potrošnji i alarmima UVIJEK koristi alate. Nikad ne izmišljaj brojke.
            - Za pitanja o pravilima, obavezama i postupcima pretraži dokumente i navedi iz kojeg dokumenta je informacija.
            - Ako dokumenti ne sadrže odgovor, reci to, nemoj nagađati.
            - Ako korisnik navede registraciju koja ne postoji ili je nepotpuna, provjeri popis vozila.
            - Relativne datume ("jučer", "prošli tjedan", "ovaj mjesec") pretvori u točne datume prema današnjem datumu.
            - Kad pitanje traži kombinaciju (npr. pravilo + podaci), koristi više alata pa poveži rezultate.
            - Odgovaraj na hrvatskom, sažeto i pregledno. Za više vozila koristi tablicu (Markdown).
            TXT;
    }
}
