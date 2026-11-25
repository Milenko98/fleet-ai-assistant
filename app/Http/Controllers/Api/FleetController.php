<?php

namespace App\Http\Controllers\Api;

use App\Ai\DocumentSearch;
use App\Ai\Embeddings;
use App\Http\Controllers\Controller;
use App\Models\Alarm;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class FleetController extends Controller
{
    public function summary(): JsonResponse
    {
        $od = now()->subDays(30)->toDateString();

        $vehicles = Vehicle::withSum(['trips as km_30_dana' => fn ($q) => $q->where('datum', '>=', $od)], 'km')
            ->withCount(['alarms as alarmi_30_dana' => fn ($q) => $q->where('vrijeme', '>=', $od)])
            ->orderBy('registracija')
            ->get(['id', 'registracija', 'marka_model', 'tip', 'gorivo']);

        return response()->json([
            'status' => [
                'claude' => filled(config('services.anthropic.key')),
                'model' => config('services.anthropic.model'),
                'embeddings' => Embeddings::enabled(),
                'rag_mode' => DocumentSearch::mode(),
            ],
            'stats' => [
                'vozila' => $vehicles->count(),
                'km_30_dana' => round((float) Trip::where('datum', '>=', $od)->sum('km')),
                'voznji_30_dana' => Trip::where('datum', '>=', $od)->count(),
                'alarmi_30_dana' => Alarm::where('vrijeme', '>=', $od)->count(),
            ],
            'vehicles' => $vehicles,
        ]);
    }
}
