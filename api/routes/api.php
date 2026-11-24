<?php

use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/health', HealthController::class);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/authors', [AuthorController::class, 'index']);
Route::get('/authors/{id}', [AuthorController::class, 'show']);
Route::get('/books/{bookId}/reviews', [ReviewController::class, 'index']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Orders & borrows
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/borrows', [BorrowController::class, 'index']);
    Route::post('/borrows', [BorrowController::class, 'store']);
    Route::post('/borrows/{id}/return', [BorrowController::class, 'returnBook']);

    // Reviews
    Route::post('/books/{bookId}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Admin only
    Route::middleware('admin')->group(function () {
        Route::get('/admin/stats', AdminStatsController::class);
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        Route::post('/authors', [AuthorController::class, 'store']);
        Route::put('/authors/{id}', [AuthorController::class, 'update']);
        Route::delete('/authors/{id}', [AuthorController::class, 'destroy']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });
});
