<?php

namespace Tests\Feature;

use App\Ai\FleetAgent;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeClaude;
use Tests\TestCase;

/**
 * Testira agent petlju bez pravog API-ja: FakeClaude vraća unaprijed zadane odgovore.
 */
class FleetAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FleetSeeder::class);
    }

    public function test_agent_executes_tools_and_returns_final_answer(): void
    {
        $fake = new FakeClaude([
            FakeClaude::toolUse([
                ['popis_vozila', ['tip' => 'kamion']],
                ['potrosnja_goriva', ['registracija' => 'NE-POSTOJI', 'datum_od' => '2026-01-01', 'datum_do' => '2026-01-31']],
            ]),
            FakeClaude::text('Kamion je ZG-5521-KM.'),
        ]);

        $result = (new FleetAgent($fake->client()))->run([['role' => 'user', 'content' => 'Koji je kamion?']]);

        $this->assertSame('Kamion je ZG-5521-KM.', $result['answer']);
        $this->assertSame(['popis_vozila', 'potrosnja_goriva'], array_column($result['steps'], 'alat'));
        $this->assertFalse($result['steps'][0]['greska']);
        $this->assertTrue($result['steps'][1]['greska']);
        $this->assertSame(['input_tokens' => 200, 'output_tokens' => 40], $result['usage']);

        // Prvi zahtjev: alati i system prompt su poslani
        $first = $fake->requests[0];
        $this->assertCount(5, $first['tools']);
        $this->assertArrayHasKey('input_schema', $first['tools'][0]);
        $this->assertStringContainsString(now()->toDateString(), $first['system'][0]['text']);

        // Drugi zahtjev: povijest + SVI rezultati alata u jednoj user poruci, povezani po id-ju
        $messages = $fake->requests[1]['messages'];
        $this->assertCount(3, $messages);
        $this->assertSame('assistant', $messages[1]['role']);
        $results = $messages[2]['content'];
        $this->assertSame(['toolu_0', 'toolu_1'], array_column($results, 'tool_use_id'));
        $this->assertFalse($results[0]['is_error']);
        $this->assertTrue($results[1]['is_error']);
        $this->assertStringContainsString('ZG-5521-KM', $results[0]['content']);
        $this->assertStringContainsString('ne postoji', $results[1]['content']);
    }

    public function test_agent_stops_after_max_steps(): void
    {
        $fake = new FakeClaude(array_fill(0, 8, FakeClaude::toolUse([['popis_vozila', []]])));

        $result = (new FleetAgent($fake->client()))->run([['role' => 'user', 'content' => 'Petlja']]);

        $this->assertCount(8, $fake->requests);
        $this->assertCount(8, $result['steps']);
        $this->assertStringContainsString('dozvoljenom broju koraka', $result['answer']);
    }

    public function test_refusal_and_max_tokens_are_handled(): void
    {
        $refusal = (new FleetAgent((new FakeClaude([FakeClaude::text('', 'refusal')]))->client()))
            ->run([['role' => 'user', 'content' => 'x']]);
        $this->assertStringContainsString('odbio', $refusal['answer']);

        $cut = (new FleetAgent((new FakeClaude([FakeClaude::text('Djelomičan odgovor', 'max_tokens')]))->client()))
            ->run([['role' => 'user', 'content' => 'x']]);
        $this->assertStringContainsString('Djelomičan odgovor', $cut['answer']);
        $this->assertStringContainsString('prekinut', $cut['answer']);
    }

    public function test_agent_requires_api_key(): void
    {
        config(['services.anthropic.key' => null]);

        $this->expectExceptionMessage('ANTHROPIC_API_KEY');
        new FleetAgent;
    }
}
