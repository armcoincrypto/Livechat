<?php

declare(strict_types=1);

use iEXPackages\SupportChat\Http\Controllers\SupportTelegramWebhookController;
use iEXPackages\SupportChat\Http\Middleware\VerifySupportTelegramWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', VerifySupportTelegramWebhookSecret::class])
    ->post('/callbacks/v1/support-chat/telegram', [SupportTelegramWebhookController::class, 'handle'])
    ->name('support_chat.telegram.webhook');
