<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cabinet\DangerZoneController;
use App\Http\Controllers\Cabinet\MyNewsController;
use App\Http\Controllers\Cabinet\SecurityController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SocialLinkController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------
// Public
// ------------------------------------------------------------------

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/cookie', fn () => view('cookie'))->name('cookie');
Route::get('/privacy', fn () => view('privacy'))->name('privacy');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/gallery/{album:slug}', [GalleryController::class, 'showAlbum'])->name('gallery.album');
Route::get('/gallery/{album:slug}/{photo}', [GalleryController::class, 'showPhoto'])
    ->whereNumber('photo')
    ->name('gallery.photo');

// ------------------------------------------------------------------
// Players (auth)
// ------------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::get('/players', [PlayerController::class, 'dashboard'])->name('players.dashboard');
    Route::get('/players/directory', [PlayerController::class, 'directory'])->name('players.directory');
    Route::get('/players/{user:name}', [PlayerController::class, 'show'])->name('players.show');
});

// ------------------------------------------------------------------
// Cabinet (auth; write actions require verified email)
// ------------------------------------------------------------------

Route::middleware('auth')->prefix('cabinet')->name('cabinet.')->group(function () {
    // Reading — any authenticated user (frontend-spec §3.2).
    Route::get('/', [CabinetController::class, 'show'])->name('show');
    Route::get('/{tab}', [CabinetController::class, 'show'])
        ->whereIn('tab', CabinetController::TABS)
        ->name('tab');

    // Writing — requires a verified email (frontend-spec §3.1).
    Route::middleware('verified')->group(function () {
        Route::post('/profile', [CabinetController::class, 'updateProfile'])->name('profile.update');
        Route::post('/avatar', [CabinetController::class, 'uploadAvatar'])
            ->middleware('throttle:10,60')
            ->name('avatar.upload');
        Route::delete('/avatar', [CabinetController::class, 'deleteAvatar'])->name('avatar.delete');

        Route::post('/social', [SocialLinkController::class, 'store'])->name('social.store');
        Route::patch('/social/{link}', [SocialLinkController::class, 'update'])->name('social.update');
        Route::post('/social/{link}/toggle', [SocialLinkController::class, 'toggle'])->name('social.toggle');
        Route::delete('/social/{link}', [SocialLinkController::class, 'destroy'])->name('social.destroy');

        Route::post('/security/password', [SecurityController::class, 'updatePassword'])
            ->name('security.password');
        Route::post('/security/email', [SecurityController::class, 'updateEmail'])
            ->name('security.email');

        Route::post('/danger/request', [DangerZoneController::class, 'request'])
            ->middleware('throttle:3,60')
            ->name('danger.request');

        // My news (player-scope)
        Route::post('/news', [MyNewsController::class, 'store'])->name('news.store');
        Route::patch('/news/{news}', [MyNewsController::class, 'update'])->name('news.update');
        Route::post('/news/{news}/publish', [MyNewsController::class, 'publish'])->name('news.publish');
        Route::delete('/news/{news}', [MyNewsController::class, 'destroy'])->name('news.destroy');
        Route::post('/news/{news}/cover', [MyNewsController::class, 'uploadCover'])->name('news.cover');
        Route::delete('/news/{news}/cover', [MyNewsController::class, 'deleteCover'])->name('news.cover.delete');
    });
});

// ------------------------------------------------------------------
// Stubs
// ------------------------------------------------------------------

Route::get('/contacts', fn () => view('stubs.coming-soon'))->name('contacts');

// ------------------------------------------------------------------
// Auth
// ------------------------------------------------------------------

Route::middleware(['guest', 'registration.open'])->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');

    Route::get('/password/reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:3,60')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1440')->name('verification.resend');
});

Route::middleware('auth')->group(function () {
    Route::post('/news/{news:slug}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:20,60')->name('comments.store');
    Route::patch('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/report', [CommentController::class, 'report'])
        ->middleware('throttle:10,60')->name('comments.report');
});

Route::post('/api/nickname/check', [RegisterController::class, 'checkNickname'])
    ->middleware('guest')->name('api.nickname.check');

Route::get('/{page:slug}', [PageController::class, 'show'])->name('page.show');
