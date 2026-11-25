<?php

namespace App\Http\Controllers\Api;

use App\Ai\DocumentIndexer;
use App\Ai\DocumentSearch;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        $documents = Document::withCount('chunks')->latest()->get(['id', 'naslov', 'created_at']);

        return response()->json(['documents' => $documents, 'rag_mode' => DocumentSearch::mode()]);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json($document->load('chunks:id,document_id,redni_broj,sadrzaj'));
    }

    public function store(Request $request, DocumentIndexer $indexer): JsonResponse
    {
        $data = $request->validate([
            'naslov' => ['required', 'string', 'max:200'],
            'sadrzaj' => ['required', 'string', 'min:50', 'max:100000'],
        ]);

        $document = $indexer->index($data['naslov'], $data['sadrzaj'])->loadCount('chunks');

        return response()->json($document, 201);
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(null, 204);
    }

    /** Pretraga bez agenta — korisno za testiranje i usporedbu full-text vs. semantic. */
    public function search(Request $request, DocumentSearch $search): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:500']]);

        return response()->json($search->search($data['q'], 5));
    }
}
