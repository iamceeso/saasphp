<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\WebhookController;

Route::prefix('billing')->group(function () {
    Route::get('pricing', [PricingController::class, 'show'])->name('pricing.show');
});

Route::middleware(['auth', 'verified'])->prefix('billing')->group(function () {
    Route::get('checkout', [PricingController::class, 'checkout'])->name('checkout.show');
    Route::post('subscribe', [PricingController::class, 'subscribe'])->name('subscribe');
});

Route::post('webhooks/stripe', [WebhookController::class, 'handleStripeWebhook'])->name('webhooks.stripe');

Route::middleware(['auth', 'verified'])->prefix('subscriptions')->group(function () {
    Route::get('/', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
    Route::post('{subscription}/swap-plan', [SubscriptionController::class, 'swapPlan'])->name('subscriptions.swap-plan');
    Route::post('{subscription}/change-cycle', [SubscriptionController::class, 'changeBillingCycle'])->name('subscriptions.change-cycle');
    Route::post('{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
    Route::get('{subscription}/invoices', [SubscriptionController::class, 'invoices'])->name('subscriptions.invoices');
});
