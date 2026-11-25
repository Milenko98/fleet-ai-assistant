<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FleetController;
use Illuminate\Support\Facades\Route;

Route::post('/chat', ChatController::class)->middleware('throttle:20,1');

Route::get('/fleet/summary', [FleetController::class, 'summary']);

Route::get('/documents', [DocumentController::class, 'index']);
Route::get('/documents/search', [DocumentController::class, 'search']);
Route::get('/documents/{document}', [DocumentController::class, 'show']);
Route::post('/documents', [DocumentController::class, 'store']);
Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
