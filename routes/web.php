<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\VideoGenerationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
    Route::post('chat/{conversation}/messages/{message}/regenerate', [ChatController::class, 'regenerate'])->name('chat.regenerate');
    Route::get('chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::patch('chat/{conversation}', [ChatController::class, 'update'])->name('chat.update');
    Route::get('videos', [VideoGenerationController::class, 'index'])->name('videos.index');
    Route::post('videos', [VideoGenerationController::class, 'store'])->name('videos.store');
    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
