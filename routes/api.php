<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AdminController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function(){
        Route::post('signup', [AuthController::class, 'signup']);
        Route::post('signin', [AuthController::class, 'sign']);
        Route::post('signout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::middleware(['auth:sanctum'])->group(function(){
        Route::get('admins', [AdminController::class, 'index']);
        Route::post('users', [AdminController::class, 'createUser']);
        Route::get('users', [AdminController::class, 'getUsers']);
        Route::put('users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('users/{id}', [AdminController::class, 'deleteUser']);

        Route::get('games', [GameController::class, 'index']);
        Route::get('users/{username}',[GameController::class, 'getUserDetail']);
        Route::post('games', [GameController::class, 'create']);
        Route::get('games/{game:slug}', [GameController::class, 'detailGame']);
        Route::post('games/{game:slug}/upload', [GameController::class, 'uploadGame']);
        Route::put('games/{game:slug}', [GameController::class, 'updateGame']);
        Route::delete('games/{game:slug}', [GameController::class, 'deleteGame']);
        Route::get('games/{game:slug}/scores', [GameController::class, 'getScores']);
        Route::post('games/{game:slug}/scores', [GameController::class, 'postScores']);
    });
});

Route::fallback(function () {
    return response()->json([
        'status' => 'not found',
        'message' => 'Not Found'
    ], 404);
});
