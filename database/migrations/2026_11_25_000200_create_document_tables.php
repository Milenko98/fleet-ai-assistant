<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumenti za RAG. Svaki dokument se dijeli na odlomke (chunkove);
 * za svaki odlomak čuvamo:
 *  - embedding (pgvector)  → semantička pretraga po značenju
 *  - tsv (tsvector)        → klasična full-text pretraga po riječima (fallback / usporedba)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('naslov');
            $t->timestamps();
        });

        Schema::create('document_chunks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('redni_broj');
            $t->text('sadrzaj');
        });

        $dim = (int) config('services.voyage.dimensions', 1024);

        DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector($dim)");
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');

        // 'simple' = bez jezične obrade (nema hrvatskog stemmera), pa se vidi ograničenje pretrage po riječima
        DB::statement("ALTER TABLE document_chunks ADD COLUMN tsv tsvector GENERATED ALWAYS AS (to_tsvector('simple', sadrzaj)) STORED");
        DB::statement('CREATE INDEX document_chunks_tsv_idx ON document_chunks USING gin (tsv)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
