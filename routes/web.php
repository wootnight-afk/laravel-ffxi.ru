<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------
// Публичные
// ------------------------------------------------------------------

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/cookie', fn () => view('cookie'))->name('cookie');
Route::get('/privacy', fn () => view('privacy'))->name('privacy');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

// ------------------------------------------------------------------
// Заглушки для будущих этапов (5–7)
// ------------------------------------------------------------------

Route::get('/gallery', fn () => view('stubs.coming-soon'))->name('gallery.index');
Route::get('/players', fn () => view('stubs.coming-soon'))->name('players.dashboard');
Route::get('/contacts', fn () => view('stubs.coming-soon'))->name('contacts');
Route::get('/cabinet/profile', fn () => view('stubs.coming-soon'))->name('cabinet.profile');

// ------------------------------------------------------------------
// Auth: регистрация (guest + registration.open)
// ------------------------------------------------------------------

Route::middleware(['guest', 'registration.open'])->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

// ------------------------------------------------------------------
// Auth: логин, пароль (guest, без registration.open)
// ------------------------------------------------------------------

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');

    Route::get('/password/reset', [PasswordResetController::class, 'showRequestForm'])
        ->name('password.request');
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:3,60')
        ->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->name('password.update');
});

// ------------------------------------------------------------------
// Logout (auth)
// ------------------------------------------------------------------

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ------------------------------------------------------------------
// Email verification (auth)
// ------------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1440')
        ->name('verification.resend');
});

// ------------------------------------------------------------------
// API
// ------------------------------------------------------------------

Route::post('/api/nickname/check', [RegisterController::class, 'checkNickname'])
    ->middleware('guest')
    ->name('api.nickname.check');

// ------------------------------------------------------------------
// Catch-all для статических страниц (должен быть последним)
// ------------------------------------------------------------------

Route::get('/{page:slug}', [PageController::class, 'show'])->name('page.show');
