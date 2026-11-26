<?php

namespace Tests\Support;

use Anthropic\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Lažni Claude API za testove: umjesto HTTP poziva vraća unaprijed zadane odgovore
 * i pamti sve poslane zahtjeve, pa testovi mogu provjeriti što je agent poslao.
 */
class FakeClaude implements ClientInterface
{
    /** @var array<int, array> */
    public array $requests = [];

    /** @param array<int, array> $responses tijela odgovora (Messages API format) */
    public function __construct(private array $responses) {}

    public function client(): Client
    {
        return new Client(apiKey: 'test-key', baseUrl: 'https://fake.anthropic.test', requestOptions: ['transporter' => $this]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = json_decode((string) $request->getBody(), true);

        $body = array_shift($this->responses) ?? throw new RuntimeException('FakeClaude: nema više pripremljenih odgovora.');

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public static function toolUse(array $calls): array
    {
        return self::message('tool_use', array_map(fn ($c, $i) => [
            'type' => 'tool_use',
            'id' => 'toolu_'.$i,
            'name' => $c[0],
            'input' => $c[1],
        ], $calls, array_keys($calls)));
    }

    public static function text(string $text, string $stopReason = 'end_turn'): array
    {
        return self::message($stopReason, [['type' => 'text', 'text' => $text]]);
    }

    private static function message(string $stopReason, array $content): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-test',
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'content' => $content,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ];
    }
}
