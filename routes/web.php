<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->name('budget.index');
    Route::get('/api/budget', [BudgetController::class, 'fetch'])->name('budget.fetch');
    Route::post('/api/budget', [BudgetController::class, 'store'])->name('budget.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
