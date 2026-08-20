<?php

declare(strict_types=1);

namespace App\Services\Orders\ManualCompletion;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use iEXPackages\Transaction\Facades\TransactionFacade;
use Throwable;

/**
 * Shared manual completion entry for Admin UI and Telegram operator actions.
 * Always goes through ManualCompletionGuard → Transaction::success → ManagersSuccess.
 */
final class ManualOrderCompletionService
{
    /**
     * @param  array{settlement_reference?: string, otherFieldsForSuccess?: array, message?: string|null}  $evidence
     * @return array{ok: bool, code: string, message: string, dry_run?: bool, already_completed?: bool}
     */
    public function complete(int $taskId, Authenticatable $operator, array $evidence, bool $dryRun = false): array
    {
        $previous = Auth::user();
        Auth::setUser($operator);

        try {
            if ($dryRun) {
                $task = \App\Models\Task::query()->whereKey($taskId)->first();
                if ($task === null) {
                    throw ManualCompletionException::invalidStatus(0);
                }

                $guard = app(ManualCompletionGuard::class);
                $decision = $guard->authorizeLockedTask(
                    $task,
                    ManualCompletionGuard::SOURCE_MANUAL,
                    array_merge($evidence, ['completion_source' => ManualCompletionGuard::SOURCE_MANUAL]),
                    $operator
                );

                if ($decision['already_completed'] === true) {
                    return [
                        'ok' => true,
                        'code' => 'already_completed',
                        'message' => 'Заявка уже выполнена',
                        'already_completed' => true,
                        'dry_run' => true,
                    ];
                }

                Log::info('telegram_operator_dry_run_complete', [
                    'task_id' => $taskId,
                    'operator_id' => (int) $operator->getAuthIdentifier(),
                    'from_status' => $decision['from_status'],
                    'settlement_reference_len' => isset($evidence['settlement_reference'])
                        ? mb_strlen((string) $evidence['settlement_reference'])
                        : 0,
                ]);

                return [
                    'ok' => true,
                    'code' => 'dry_run_ok',
                    'message' => 'DRY-RUN: completion would succeed (no financial mutation)',
                    'dry_run' => true,
                ];
            }

            $otherFields = $evidence['otherFieldsForSuccess'] ?? [];
            $transaction = TransactionFacade::find($taskId, [
                'preview' => false,
                'commission' => 0,
                'type' => 'default',
                'actions' => 'success',
                'message' => $evidence['message'] ?? null,
                'otherFieldsForSuccess' => $otherFields,
                'settlement_reference' => $evidence['settlement_reference'] ?? null,
                'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
            ]);

            if ($transaction->hasAction() !== 'success') {
                return [
                    'ok' => false,
                    'code' => 'invalid_action',
                    'message' => 'Completion action not configured',
                ];
            }

            $transaction->success([
                'skip_auto_payment' => true,
                'completion_source' => ManualCompletionGuard::SOURCE_MANUAL,
                'settlement_reference' => $evidence['settlement_reference'] ?? null,
                'otherFieldsForSuccess' => $otherFields,
                'message' => $evidence['message'] ?? null,
            ]);

            return [
                'ok' => true,
                'code' => 'completed',
                'message' => 'Заявка выполнена',
            ];
        } catch (ManualCompletionException $e) {
            if ($e->errorCode === 'already_completed') {
                return [
                    'ok' => true,
                    'code' => 'already_completed',
                    'message' => 'Заявка уже выполнена',
                    'already_completed' => true,
                ];
            }
            throw $e;
        } catch (Throwable $e) {
            Log::error('manual_order_completion_failed', [
                'task_id' => $taskId,
                'operator_id' => (int) $operator->getAuthIdentifier(),
                'exception' => $e::class,
            ]);
            throw $e;
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
