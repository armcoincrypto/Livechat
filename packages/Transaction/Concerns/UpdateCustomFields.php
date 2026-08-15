<?php
/**
 * Created by PhpStorm.
 * User: steei
 * Date: 05.07.2019
 * Time: 8:22
 */

namespace iEXPackages\Transaction\Concerns;

use App\Models\CurrencyFields;
use App\Models\TaskRequisiteAttached;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;

trait UpdateCustomFields
{
    /**
     * Универсальное обновление доп. полей (отдаю/получаю)
     */
    public function updateCustomFields(array $input = []): void
    {
        if (empty($input)) {
            $input = request()->all();
        }

        $currencyFields = CurrencyFields::pluck('key_id')->toArray();

        foreach ($currencyFields as $key) {
            if (isset($input[$key])) {
                $this->transaction->{$key} = security_xss($input[$key]);
            }
        }

        $this->transaction->save();
    }

    /**
     * Обновляем краткие данные (from_shot/to_shot)
     */
    public function updateEditData(string $typeEdit = 'income', string $shotValue = ''): void
    {
        $fields = [
            'income'  => 'from_shot',
            'outcome' => 'to_shot',
        ];

        if (isset($fields[$typeEdit])) {
            $this->transaction->update([
                $fields[$typeEdit] => $shotValue,
                'id_edit_data_manager' => auth()->id(),
            ]);
        }
    }

    /**
     * Смена оператора заявки
     */
    public function updateOperator(int $idOperator = 0): void
    {
        if ($idOperator > 0) {
            $this->setOperator($idOperator, true);
        }
    }

    /**
     * Добавление реквизитов выплаты и отправка уведомления
     */
    public function updateAddRequisitesPayment(string $account, array $options = [], Request $request = null): void
    {
        $request = $request ?? request();

        $account = trim((string) $account);
        $requisitesDescription = trim((string) ($options['requisites_description'] ?? ''));

        $attachedInputFields = collect($options['attach_input_fields'] ?? [])
            ->pluck('name')
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim((string) $v))
            ->values()
            ->all();

        // В твоей схеме id_manager NOT NULL и default 0
        $managerId = (int) (Auth::id() ?? 0);


        DB::transaction(function () use (
            $account,
            $requisitesDescription,
            $attachedInputFields,
            $managerId,
            $request
        ) {
            $this->transaction->update([
                'requisites_receive'      => $account,
                'requisites_description'  => $requisitesDescription,
                'is_request_payment_type' => $account !== '' ? 0 : 1,
            ]);

            TaskRequisiteAttached::create([
                'id_task'      => (int) $this->transaction->id,
                'id_manager'   => $managerId,
                'wallet_number'=> $account !== '' ? $account : null,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'ext_params'   => [
                    'fields'      => $attachedInputFields,
                    'description' => $requisitesDescription,
                ],
            ]);
        }, 3);

        try {
            if (SmartMailerConditionFactory::make('order_created', $this->transaction)->shouldSend()) {
                SmartMailer::dispatch(
                    sendable: 'order_created_job',
                    model: $this->transaction,
                    delaySeconds: 5,
                    queue: 'high'
                );
            }

            if ((int) iEXSetting('is_email_request_payment', 0) === 1) {
                SmartMailer::dispatch(
                    sendable: 'order_payment_details_issued',
                    model: $this->transaction,
                    email: (string) $this->transaction->email,
                    delaySeconds: 10,
                    queue: 'low'
                );
            }
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки уведомлений после прикрепления реквизитов выплаты', [
                'task_id'     => (int) $this->transaction->id,
                'manager_id'  => $managerId,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
