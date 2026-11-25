<?php

namespace App\Http\Controllers\Api;

use App\Ai\FleetAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatController extends Controller
{
    /**
     * Prima dosadašnji razgovor (samo tekst) i vraća odgovor agenta + korake koje je napravio.
     * Razgovor čuva frontend; backend je stateless, kao i sam Claude API.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $messages = array_map(
            fn ($m) => ['role' => $m['role'], 'content' => $m['content']],
            $data['messages']
        );

        if (end($messages)['role'] !== 'user') {
            return response()->json(['message' => 'Zadnja poruka mora biti korisnikovo pitanje.'], 422);
        }

        try {
            return response()->json((new FleetAgent)->run($messages));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Greška agenta: '.$e->getMessage()], 500);
        }
    }
}
