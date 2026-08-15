<?php

namespace App\Http\Controllers\Administrator\Verifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Verifications\VerificationCardsResources;
use App\Models\Currency;
use App\Models\VerificationCard;
use iEXPackages\SmartMailer\Facades\SmartMailer;
use iEXPackages\SmartMailer\SmartMailerConditionFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerificationCardController extends Controller
{
    /**
     * Дополнительные фильтры
     */
    protected array $allowFiltered = [
        'num_count_failed_verification',
        'enabled_auto_verification_card',
        'other_percent_user_verification',
    ];

    protected array $allowLocaleOptions = [
        'description_verification_card',
        'error_verification_card',
    ];

    /**
     * Список карт
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(
        Request $request,
    ){

        if($request->has('showPage') == 'settings') {
            return response()->json([
                'enabled_auto_verification_card' => (int)iEXSetting('enabled_auto_verification_card'),
                'num_count_failed_verification' => (int)iEXSetting('num_count_failed_verification', 10),
                'description_verification_card' => iEXContentLanguage('description_verification_card', raw: true),
                'error_verification_card' => iEXContentLanguage('error_verification_card', raw: true),
            ]);
        }

        $cards = VerificationCard::filter($request->all())
            ->with('tasks', function ($q) {
                $q->select('id', 'public_id');
            })
            ->orderBy('id', 'desc')
            ->paginate((int)iEXSetting('admin_verifications_card_pagination', 20));
        $currencies = Currency::where('status', '=', 0)->get()->map(function ($item) {
            return [
                'value' => $item->tech_name,
                'id' => $item->id,
            ];
        })->values();

        $admin_hidden_columns = explode(',', iEXSetting('admin_verifications_card_hidden_columns'));
        $allowedColumns = ['currency', 'ip_address', 'account_number', 'photo', 'user', 'created_at', 'status', 'id_order'];

        return response()->json([
            'items' => new VerificationCardsResources($cards),
            'currencies' => $currencies,
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_verifications_card_pagination', 20),
        ]);
    }

    /**
     * Обновление данные
     *
     * @return JsonResponse
     */
    public function store(
        Request $request
    )
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                $options_locale = [];
                foreach ($this->allowLocaleOptions as $allowLocaleOption) {
                    $options_locale[$allowLocaleOption] = $request->{$allowLocaleOption};
                }

                $array = [];
                foreach ($this->allowFiltered as $item) {
                    $array[$item] = ($request->has($item) ? (int)$request->get($item) : 0);
                }

                //Для мультиязычности
                $locale_data = collect($options_locale)->only($this->allowLocaleOptions)->all();
                iEXContentLanguage($locale_data);
                iEXSetting($array);

            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }
    }

    public function update(int $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 0 pending, 1 approved, 2/3 rejected-family (client maps 3→2)
            'status' => ['required', 'numeric', 'in:0,1,2,3'],
        ]);

        // Перед добавлением новой валюты проверяем на ошибки
        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $verification = VerificationCard::find($id);
        if (!$verification) {
            return response()->json([
                'status' => 1,
                'message' => 'Not found',
            ], 404);
        }

        $previousStatus = (int) $verification->status;
        $newStatus = (int) $request->status;

        if ($previousStatus === $newStatus) {
            return response()->json([
                'status' => 0,
                'message' => $newStatus == 1 ? 'Счет успешно верифицирован' : 'Верификация счета отклонена',
            ]);
        }

        $verification->update([
            'status' => $newStatus,
            'id_manager' => auth()->id(),
            'text_message' => $request->message ?? '',
        ]);

        Log::info('verification_card_admin_review', [
            'verification_id' => $verification->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'admin_id' => auth()->id(),
            'has_message' => filled($request->message),
            'ip' => $request->ip(),
        ]);

        // Отправить уведомление в случае отказа верификации
        if ($newStatus == 3 and (int) iEXSetting('is_email_fail_verification_card') == 1) {

            SmartMailer::dispatch(
                sendable: 'verification_card_fail_job',
                model: $verification,
                delaySeconds: 10,
                queue: 'low'
            );
        }

        // Если найдена заявка и есть статус верификации успешный,
        // В этом случае убираем метку с заявки.
        if (isset($verification->tasks) and $newStatus == 1) {
            // Если включена возможность отправки сообщений на почту об
            // успешной верификации счета, уведомляем клиента
            if ((int) iEXSetting('is_email_success_verification_card') == 1) {
                SmartMailer::dispatch(
                    sendable: 'verification_card_success_job',
                    model: $verification,
                    delaySeconds: 10,
                    queue: 'low'
                );
            }

            // Убираем метки и заявки
            $verification->tasks->update(['is_from_verification_card' => 0]);

            // Отсылаем сообщение о создании заявки (после прохождения верификации успешной)
            if (SmartMailerConditionFactory::make('order_created', $verification->tasks)->shouldSend())
            {
                SmartMailer::dispatch(
                    sendable: 'order_created_job',
                    model: $verification->tasks,
                    delaySeconds: 5,
                    queue: 'high'
                );
            }
        }

        return response()->json([
            'status' => 0,
            'message' => $newStatus == 1 ? 'Счет успешно верифицирован' : 'Верификация счета отклонена'
        ]);
    }

    /**
     * Удаление счета
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        $verification = VerificationCard::find($id);
        if (!$verification) {
            return response()->json([
                'status' => 1,
                'message' => 'Not found',
            ], 404);
        }

        if (isset($verification->tasks) and $verification->tasks->status == 2) {
            $verification->tasks->update(['is_from_verification_card' => 1]);
        }

        $verificationId = $verification->id;
        $verification->delete();

        Log::info('verification_card_admin_delete', [
            'verification_id' => $verificationId,
            'admin_id' => auth()->id(),
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Счет успешно удален'
        ]);
    }
}
