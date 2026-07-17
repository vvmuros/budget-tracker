<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['sr', 'en'], true), 404);
    Cookie::queue(Cookie::make('lang', $locale, 60 * 24 * 365, null, null, null, false));

    return redirect()->back();
})->name('lang.switch');

Route::view('/privacy', 'legal.privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'showVerifyNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')->name('verification.send');
    Route::post('/account/delete', [AuthController::class, 'deleteAccount'])
        ->middleware('throttle:6,1')->name('account.delete');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});

// 'verified' dropped for now — Resend has no verified domain yet, so
// verification emails can't reach anyone but the account owner. Add
// 'verified' back to this group once a domain is set up on Resend.
Route::middleware(['auth'])->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->name('budget.index');
    Route::get('/api/budget', [BudgetController::class, 'fetch'])->name('budget.fetch');
    Route::get('/api/budget/yearly', [BudgetController::class, 'yearly'])->name('budget.yearly');
    Route::post('/api/budget', [BudgetController::class, 'store'])->name('budget.store');
    Route::post('/api/budget/chat', [BudgetController::class, 'chat'])->middleware(['throttle:15,1', 'gemini.quota'])->name('budget.chat');
    Route::post('/api/budget/voice', [BudgetController::class, 'voice'])->middleware(['throttle:15,1', 'gemini.quota'])->name('budget.voice');
    Route::post('/api/budget/analyze', [BudgetController::class, 'analyze'])->middleware(['throttle:6,1', 'gemini.quota'])->name('budget.analyze');
    Route::post('/api/budget/receipt', [BudgetController::class, 'receipt'])->middleware(['throttle:6,1', 'gemini.quota'])->name('budget.receipt');
    Route::post('/api/budget/categories', [BudgetController::class, 'updateCategory'])->middleware('throttle:20,1')->name('budget.categories');
    Route::get('/api/budget/report/pdf', [ReportController::class, 'monthlyPdf'])->middleware('throttle:10,1')->name('report.monthlyPdf');

    Route::get('/api/exchange-rate/latest', [ExchangeRateController::class, 'latest'])->name('exchangeRate.latest');
    Route::get('/api/exchange-rate/history', [ExchangeRateController::class, 'history'])->name('exchangeRate.history');

    Route::get('/api/push/public-key', [PushController::class, 'publicKey'])->name('push.publicKey');
    Route::post('/api/push/subscribe', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/api/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/api/push/test', [PushController::class, 'sendTest'])->middleware('throttle:6,1')->name('push.test');
});
