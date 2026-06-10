<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\VideoGenerationController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
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
    Route::get('credits', [CreditsController::class, 'index'])->name('credits.index');
    Route::post('credits/purchase', [CreditsController::class, 'store'])->name('credits.purchase');
    Route::get('credits/history', [CreditsController::class, 'index'])->name('credits.history');
    Route::get('usage', [UsageController::class, 'index'])->name('usage.index');
    Route::post('usage/credits/purchase', [UsageController::class, 'purchaseCredits'])->name('usage.credits.purchase');
    Route::post('usage/credits/recharge', [UsageController::class, 'rechargeCredits'])->name('usage.credits.recharge');

    Route::prefix('admin')->middleware(EnsureAdmin::class)->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('payments', [AdminController::class, 'payments'])->name('payments.index');
        Route::post('payments/{purchase}/approve', [AdminController::class, 'approvePayment'])->name('payments.approve');
        Route::post('payments/{purchase}/reject', [AdminController::class, 'rejectPayment'])->name('payments.reject');
        Route::get('users', [AdminController::class, 'users'])->name('users.index');
        Route::post('users/{user}/adjust', [AdminController::class, 'adjustUser'])->name('users.adjust');
        Route::patch('users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::get('credits/settings', [AdminController::class, 'settings'])->name('credits.settings');
        Route::patch('credits/settings', [AdminController::class, 'updateSettings'])->name('credits.settings.update');
        Route::post('credits/recharge', [AdminController::class, 'recharge'])->name('credits.recharge');
    });
});

Route::inertia('terms', 'legal/terms')->name('terms');
Route::inertia('privacy', 'legal/privacy')->name('privacy');
Route::inertia('refund-policy', 'legal/refund-policy')->name('refund-policy');
Route::inertia('acceptable-use', 'legal/acceptable-use')->name('acceptable-use');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
