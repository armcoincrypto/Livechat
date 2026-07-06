<?php

declare(strict_types=1);

use iEXPackages\SupportChat\Http\Controllers\Public\ConversationController;
use iEXPackages\SupportChat\Http\Controllers\Public\MessageController;
use iEXPackages\SupportChat\Http\Controllers\Public\SupportAttachmentController;
use iEXPackages\SupportChat\Http\Middleware\AuthenticateSupportConversation;
use iEXPackages\SupportChat\Http\Middleware\EnsureSupportAttachmentsEnabled;
use iEXPackages\SupportChat\Http\Middleware\EnsureSupportChatEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([
    EnsureSupportChatEnabled::class,
    'bindings',
])->prefix('client-api')->group(function (): void {
    Route::prefix('v1/support')->group(function (): void {
        Route::post('/conversations', [ConversationController::class, 'store'])
            ->middleware('throttle:support-create');

        Route::post('/conversations/{uuid}/messages', [MessageController::class, 'store'])
            ->middleware([
                'throttle:support-send',
                AuthenticateSupportConversation::class,
            ]);

        Route::get('/conversations/{uuid}/messages', [MessageController::class, 'index'])
            ->middleware([
                'throttle:support-poll',
                AuthenticateSupportConversation::class,
            ]);

        Route::post('/conversations/{uuid}/attachments', [SupportAttachmentController::class, 'store'])
            ->middleware([
                'throttle:support-send',
                AuthenticateSupportConversation::class,
                EnsureSupportAttachmentsEnabled::class,
            ]);

        Route::get('/conversations/{uuid}/attachments/{attachment}', [SupportAttachmentController::class, 'show'])
            ->whereNumber('attachment')
            ->middleware([
                'throttle:support-poll',
                AuthenticateSupportConversation::class,
                EnsureSupportAttachmentsEnabled::class,
            ]);
    });
});
