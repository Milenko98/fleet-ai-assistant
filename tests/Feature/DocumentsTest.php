<?php

namespace Tests\Feature;

use App\Ai\DocumentIndexer;
use App\Ai\DocumentSearch;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const PRAVILNIK = "# Pravilnik\n\nUvodni odlomak o pravilniku za vozače.\n\n## Stanka\n\nNakon 4 sata i 30 minuta upravljanja vozač mora napraviti stanku od 45 minuta.\n\n## Gume\n\nZimske gume moraju imati profil od najmanje 4 mm.";

    public function test_fulltext_search_finds_matching_chunk_without_embeddings(): void
    {
        (new DocumentIndexer)->index('Pravilnik', self::PRAVILNIK);

        $result = (new DocumentSearch)->search('stanka vozač');

        $this->assertSame('fulltext', $result['nacin']);
        $this->assertStringContainsString('45 minuta', $result['rezultati'][0]['odlomak']);
    }

    public function test_api_indexes_lists_searches_and_deletes_documents(): void
    {
        $this->postJson('/api/documents', ['naslov' => 'Pravilnik', 'sadrzaj' => self::PRAVILNIK])
            ->assertCreated()
            ->assertJsonPath('chunks_count', 3);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('rag_mode', 'fulltext')
            ->assertJsonCount(1, 'documents');

        $this->getJson('/api/documents/search?q=zimske gume')
            ->assertOk()
            ->assertJsonPath('rezultati.0.dokument', 'Pravilnik');

        $this->deleteJson('/api/documents/'.Document::first()->id)->assertNoContent();
        $this->assertDatabaseCount('document_chunks', 0);
    }

    public function test_document_validation(): void
    {
        $this->postJson('/api/documents', ['naslov' => '', 'sadrzaj' => 'prekratko'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['naslov', 'sadrzaj']);
    }
}
