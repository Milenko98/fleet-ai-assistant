<?php

namespace Tests\Unit;

use App\Ai\DocumentIndexer;
use PHPUnit\Framework\TestCase;

class DocumentChunkingTest extends TestCase
{
    public function test_chunking_keeps_sections_separate(): void
    {
        $tekst = "# Pravilnik\n\nUvod.\n\n## Stanka\n\nNakon 4 sata i 30 minuta stanka od 45 minuta.\n\n## Gume\n\nProfil najmanje 4 mm.";

        $chunks = (new DocumentIndexer)->chunk($tekst);

        $this->assertCount(3, $chunks);
        $this->assertStringStartsWith('## Stanka', $chunks[1]);
        $this->assertStringStartsWith('## Gume', $chunks[2]);
    }

    public function test_short_paragraphs_are_merged_and_long_text_is_split(): void
    {
        $tekst = implode("\n\n", array_fill(0, 10, str_repeat('Rečenica o održavanju vozila. ', 10)));

        $chunks = (new DocumentIndexer)->chunk($tekst);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertLessThan(10, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1000, mb_strlen($chunk));
        }
    }

    public function test_empty_text_gives_no_chunks(): void
    {
        $this->assertSame([], (new DocumentIndexer)->chunk("\n\n  \n"));
    }
}
