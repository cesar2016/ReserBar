<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TableController;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::get('/restaurant/context', function() {
    return response()->json(app(RestaurantContextService::class)->getContext());
});

Route::get('/menu', [MenuController::class, 'index']);
Route::get('/menu/of-the-day', [MenuController::class, 'ofTheDay']);
Route::get('/menu/search', [MenuController::class, 'search']);
Route::get('/menu/category/{categoryId}', [MenuController::class, 'byCategory']);
Route::get('/menu/{id}', [MenuController::class, 'show']);

Route::post('/chat/reservation', function(Request $request) {
    $service = app(RestaurantContextService::class);
    return response()->json($service->createReservation($request->all()));
});
Route::delete('/chat/reservation/{id}', function($id) {
    $service = app(RestaurantContextService::class);
    return response()->json($service->cancelReservation((int)$id));
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);
    Route::get('/tables', [TableController::class, 'index']);
    Route::post('/tables', [TableController::class, 'store']);
    Route::get('/tables/{table}', [TableController::class, 'show']);
    Route::put('/tables/{table}', [TableController::class, 'update']);
    Route::delete('/tables/{table}', [TableController::class, 'destroy']);
    
    Route::post('/menu/rebuild-embeddings', [MenuController::class, 'rebuildEmbeddings']);
});
