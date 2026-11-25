<?php

namespace Tests\Feature;

use App\Ai\FleetAgent;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeClaude;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FleetSeeder::class);
    }

    public function test_home_page_renders_vue_app(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertSee('id="app"', false);
    }

    public function test_fleet_summary(): void
    {
        $this->getJson('/api/fleet/summary')
            ->assertOk()
            ->assertJsonPath('stats.vozila', 8)
            ->assertJsonPath('status.claude', false)
            ->assertJsonPath('status.rag_mode', 'fulltext')
            ->assertJsonCount(8, 'vehicles');
    }

    public function test_chat_returns_answer_and_steps(): void
    {
        $fake = new FakeClaude([
            FakeClaude::toolUse([['popis_vozila', []]]),
            FakeClaude::text('Imate 8 vozila.'),
        ]);
        $this->app->instance(FleetAgent::class, new FleetAgent($fake->client()));

        $this->postJson('/api/chat', ['messages' => [
            ['role' => 'user', 'content' => 'Bok'],
            ['role' => 'assistant', 'content' => 'Pozdrav!'],
            ['role' => 'user', 'content' => 'Koliko imamo vozila?'],
        ]])
            ->assertOk()
            ->assertJsonPath('answer', 'Imate 8 vozila.')
            ->assertJsonPath('steps.0.alat', 'popis_vozila');

        // Povijest razgovora je proslijeđena modelu
        $this->assertCount(3, $fake->requests[0]['messages']);
    }

    public function test_chat_validation(): void
    {
        $this->postJson('/api/chat', ['messages' => []])->assertUnprocessable();

        $this->postJson('/api/chat', ['messages' => [['role' => 'system', 'content' => 'x']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('messages.0.role');

        $this->postJson('/api/chat', ['messages' => [['role' => 'assistant', 'content' => 'x']]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Zadnja poruka mora biti korisnikovo pitanje.');
    }

    public function test_chat_without_api_key_returns_readable_error(): void
    {
        $this->postJson('/api/chat', ['messages' => [['role' => 'user', 'content' => 'x']]])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Greška agenta: ANTHROPIC_API_KEY nije postavljen u .env datoteci.');
    }
}
