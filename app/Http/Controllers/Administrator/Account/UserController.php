<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Exports\OrderUserExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Users\UsersResource;
use App\Models\User;
use App\Models\UserBalanceLog;
use App\Models\SettingsLimitProfile;
use iEXPackages\ReferralSystem\Models\ReferralLink;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use iEXPackages\ReferralSystem\Models\ReferralRelationship;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Список пользователей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->has('verify_email')) {
            $user = User::find((int)$request->id);

            if (!$user) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Пользователь не найден'),
                ]);
            }

            if (is_null($user->email_verified_at)) {
                $user->sendEmailVerificationNotification();

                return response()->json([
                    'status' => 0,
                    'message' => __('Сообщение успешно отправлено на почту'),
                ]);
            }

            return response()->json([
                'status' => 1,
                'message' => __('E-mail уже был подтвержден ранее'),
            ]);
        }


        $users = User::query()
            ->where(function ($q) {
                $q->where('is_guest', '!=', 1)->orWhereNull('is_guest');
            })
            ->with([
                'user_balance',
                'roles:id,name,title',
                'fromReferral.referralLink.user:id,name,email',
            ])
            ->withCount([
                'exchangeTotals as exchanges_count',
            ])
            ->withSum('exchangeTotals as exchanges_sum_usd', 'exchange_usd');

        $users->filter($request->all());
        $users->orderByDesc('id');

        $paginationCount = (int) iEXSetting('admin_user_pagination', 20);

        $items = $users->paginate($paginationCount);

        $selectedColumns = array_filter(
            explode(',', iEXSetting('admin_user_hidden_columns', ''))
        );

        return response()->json([
            'items' => new UsersResource($items),
            'selected_columns' => $selectedColumns,
            'per_page' => $paginationCount,
            'roles' => Role::select(['name', 'title'])->get(),
            'referralPrograms' => ReferralProgram::select(['id', 'name'])->get(),
        ]);

    }

    /**
     * Обработка настроек
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'message' => __('Ошибка')
        ]);
    }

    /**
     * Форма редактирования пользователей
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request, int $id)
    {
        $item = User::findOrFail($id);

        $item->loadCount([
            'exchangeTotals as exchanges_count',
        ]);

        $item->loadSum(
            'exchangeTotals as exchanges_sum_usd',
            'exchange_usd'
        );

        $roles = Role::select('title', 'id')->get();
        $programs = ReferralProgram::select('name', 'id')->get();
        $link = ReferralLink::where('user_id', $id)->first();

        if ($request->has('verified') and is_null($item->email_verified_at))
        {
            $item->email_verified_at = Carbon::now();
            $item->save();

            return response()->json([
                'status' => 0,
                'message' => __(':name успешно верифицирован', ['name' => $item->email])
            ]);
        }


        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $id)
            ->orderBy('last_activity', 'desc')
            ->get();

        $sessions = $sessions->map(
            function ($session) use ($request) {
                $agent = tap(new Agent, fn($agent) => $agent->setUserAgent($session->user_agent));

                return [
                    'agent'           => [
                        'platform' => $agent->platform(),
                        'browser'  => $agent->browser(),
                    ],
                    'user_agent'      =>    $session->user_agent,
                    'ip'              => $session->ip_address,
                    'isCurrentDevice' => $session->id === $request->session()->getId(),
                    'lastActive'      => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            }
        )->toArray();

        $limitProfiles = SettingsLimitProfile::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (SettingsLimitProfile $profile) {
                return [
                    'id'          => (int) $profile->id,
                    'value'       => $profile->name,
                    'slug'        => $profile->slug,
                    'description' => $profile->description,
                    'is_default'  => (bool) $profile->is_default,
                ];
            })
            ->values();

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'email' => $item->email,
                'ip_address' => $item->ip_address,
                'is_banned' => $item->isBanned(),
                'avatar' => Str::upper(Str::substr($item->name, 0, 1)),
                'order_num' => (int) ($item->exchanges_count ?? 0),
                'order_total_exchanges' => (float) ($item->exchanges_sum_usd ?? 0),
                'balance' => iex_number_format((isset($item->user_balance) ? $item->user_balance->balance : 0), 2, true),
                'balance_code' => \Config::get('partners-bonus.name'),
                'roles' => $item->roles->select('id')->pluck('id') ?? [],
                'role_expired_at' => $item->role_expired_at,
                'allowed_ip_addresses' => $item->allowed_ip_addresses,
                'phone' => $item->phone,
                'deactivation' => $item->deactivation,
                'email_verified_at' => !is_null($item->email_verified_at),
                'is_verify_account' => (bool)$item->is_verify_account,
                'personal_discount' => $item->personal_discount,
                'personal_ref_discount' => $item->personal_ref_discount,
                'max_ref_discount' => $item->max_ref_discount,
                'referral_program_id' => (int)($link?->referral_program_id ?? 0),
                'partner_method' => (int)$item->partner_method,
                'is_unique_user' => $item->is_unique_user == 0 ? 'default' : 'individual',
                'is_order' => $item->is_order == 0 ? 'active': 'unactive',
                'is_pay_referral' => $item->is_pay_referral == 0 ? 'yes' : 'no',
                'is_enabled_restapi' => $item->is_enabled_restapi == 1 ? 'yes' : 'no',
                'referral_code' =>  $item->referralLink?->code,
                'from_referral' => isset($item->fromReferral, $item->fromReferral->referralLink) ? [
                    'id' => $item->fromReferral->referralLink->user->id,
                    'name' => $item->fromReferral->referralLink->user->name
                ] : [],
                'limit_profile_id' => $item->limit_profile_id ?? null,
                'user_agent' => $item->user_agent
            ],
            'roles' => $roles,
            'referral_programs' => $programs,
            'sessions' => $sessions,
            'limit_profiles' => $limitProfiles,
        ]);
    }

    /**
     * Обновление данных пользователя
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $manager = auth()->user();

        // Действия по подтверждению почты пользователя
        if ($request->action === 'verifiedEmail') {
            $user->update(['email_verified_at' => now()]);
            return response()->json([
                'status' => 0,
                'message' => __(':name успешно верифицирован', ['name' => $user->email])
            ]);
        }

        if ($request->actionName === 'verify_email') {
            if (is_null($user->email_verified_at)) {
                $user->sendEmailVerificationNotification();
                return response()->json([
                    'status' => 0,
                    'message' => __('Сообщение успешно отправлено на почту')
                ]);
            }
            return response()->json([
                'status' => 1,
                'message' => __('E-mail уже подтверждён ранее')
            ]);
        }

        // Обновление реферального хеша
        if ($request->edit_referral_hash === 'enable') {
            $validator = Validator::make($request->all(), [
                'referral_name' => 'required|string|max:255|unique:referral_links,code|regex:/^[a-zA-Z0-9]+$/u'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 1, 'message' => $validator->messages()->first()]);
            }

            $user->update(['username' => $request->referral_name]);
            ReferralLink::updateOrCreate(['user_id' => $user->id], ['code' => $request->referral_name]);

            return response()->json(['status' => 0, 'message' => '✅ Хэш успешно обновлен']);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:120',
            'email' => 'required|email|unique:users,email,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $data = $request->only([
            'name', 'email', 'phone', 'deactivation', 'is_unique_user', 'is_notify_email',
            'is_password_reset', 'is_pay_referral', 'is_order', 'is_enable_order_paginate',
            'is_verify_account', 'is_hidden_ip_address', 'personal_discount',
            'role_expired_at', 'is_active_role', 'limit_profile_id'
        ]);

        if ($manager->hasRole('super admin')) {
            $data['allowed_ip_addresses'] = $request->allowed_ip_addresses ?: null;
        }

        if ($manager->can('admin_account_change_password') && $request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($manager->can('admin_bonuses_program') && $request->filled('referral_program_id')) {
            $referralLink = ReferralLink::where('user_id', $id)->first();
            if ($referralLink) {
                $referralLink->update([
                    'referral_program_id' => $request->referral_program_id,
                ]);
            } else {
                if ((int)$request->referral_program_id === 0) {
                    $program = ReferralProgram::query()
                        ->orderBy('percent', 'asc')
                        ->select('id', 'name', 'percent')
                        ->first();
                    if ($program) {
                        $request->merge(['referral_program_id' => $program->id]);
                    }
                }

                ReferralSystemFacade::registerUserToReferralProgram($user, (int) $request->referral_program_id);
            }
            $user->partner_method = $request->has('partner_method') ? $request->get('partner_method') : 0;
            $user->max_ref_discount = $request->has('max_ref_discount') ? $request->get('max_ref_discount') : 0;
            $user->personal_ref_discount = $request->has('personal_ref_discount') ? $request->get('personal_ref_discount') : 0;
            $user->is_follow_referral = ($request->has('is_follow_referral') ? $request->get('is_follow_referral') : false);
        }

        // Включение API доступа
        if ($manager->can('admin_api')) {
            $user->update(['is_enabled_restapi' => $request->boolean('is_enabled_restapi')]);
        }

        // Работа с ролями пользователя
        if ($manager->can('admin_roles')) {
            $roles = $request->get('roles', []);
            if ($user->id === $manager->id && empty($roles) && $user->roles()->count() === 1) {
                return response()->json(['status' => 1, 'message' => '⚠️ Вы не можете отвязать последнюю роль от себя']);
            }
            $user->roles()->sync($roles);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Данные успешно обновлены'
        ]);
    }

    /**
     * Управление партнёром (реферером) пользователя: отвязать или переключить на другого.
     */
    public function changePartner(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'mode' => 'required|string|in:detach,change',
            'partner_user_id' => 'required_if:mode,change|integer|different:id|exists:users,id',
        ], [
            'mode.required' => 'Не указан режим изменения партнёра.',
            'mode.in' => 'Неверный режим. Допустимые значения: detach, change.',
            'partner_user_id.required_if' => 'Укажите пользователя-партнёра, на которого нужно переключить.',
            'partner_user_id.exists' => 'Выбранный партнёр не найден.',
            'partner_user_id.different' => 'Партнёр не может совпадать с самим пользователем.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ]);
        }

        $mode = $request->get('mode');

        // Текущая связь пользователя с реферальной ссылкой
        $relationship = ReferralRelationship::where('user_id', $user->id)->first();

        if ($mode === 'detach') {
            if (! $relationship) {
                return response()->json([
                    'status' => 1,
                    'message' => 'У пользователя уже нет привязанного партнёра.',
                ]);
            }

            $relationship->delete();

            return response()->json([
                'status' => 0,
                'message' => 'Партнёр успешно отвязан от пользователя.',
            ]);
        }

        // mode === 'change' — переключение на другого партнёра
        $partnerUserId = (int) $request->get('partner_user_id');

        // Находим реферальную ссылку нового партнёра
        $referralLink = ReferralLink::where('user_id', $partnerUserId)->first();

        if (! $referralLink) {
            return response()->json([
                'status' => 1,
                'message' => 'У выбранного пользователя нет реферальной ссылки.',
            ]);
        }

        \DB::transaction(function () use ($user, $relationship, $referralLink) {
            if ($relationship) {
                $relationship->update([
                    'referral_link_id' => $referralLink->id,
                ]);
            } else {
                ReferralRelationship::create([
                    'user_id' => $user->id,
                    'referral_link_id' => $referralLink->id,
                ]);
            }

            // Обновляем историю логов, если она есть, чтобы аналитика не ломалась
            ReferralLog::where('id_user', $user->id)
                ->update(['id_referral_link' => $referralLink->id]);
        });

        return response()->json([
            'status' => 0,
            'message' => 'Партнёр пользователя успешно обновлён.',
        ]);
    }

    /**
     * Заблокировать клиента
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ban(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'banned_at' => 'required|date_format:"d.m.Y, H:i:s"|after:now',
            'comment' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $user = User::findOrFail($request->id);

        if ($user->isBanned()) {
            return response()->json([
                'status' => 1,
                'message' => 'Пользователь уже заблокирован'
            ]);
        }

        $user->ban([
            'expired_at' => $request->expired_at,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Пользователь успешно заблокирован до ' . Carbon::parse($request->expired_at)->format('d.m.Y H:i')
        ]);
    }

    /**
     * Управление балансом
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function balances(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'balance' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => '' . $validator->messages()->first()
            ]);
        }

        $user = User::with('user_balance')->find($request->id);

        $newBalance = (float) $request->balance;
        $currentBalance = $user->user_balance?->balance ?? 0;

        if (!$user->user_balance) {
            $user->user_balance()->create(['balance' => $newBalance]);
            $logText = "Установлен начальный баланс: {$newBalance}";
            $type = 1;
        } elseif ($newBalance !== $currentBalance) {
            $type = $newBalance > $currentBalance ? 1 : 0;
            $action = $type ? 'увеличен' : 'уменьшен';
            $logText = "Реферальный бонус {$action} с {$currentBalance} до {$newBalance}";

            $user->user_balance->update(['balance' => $newBalance]);
        } else {
            return response()->json([
                'status' => 1,
                'message' => 'Баланс не изменился'
            ]);
        }

        UserBalanceLog::create([
            'id_manager' => $request->user()->id,
            'id_user' => $user->id,
            'route_type' => 1,
            'type' => $type,
            'text' => $logText,
            'from_balance' => $currentBalance,
            'to_balance' => $newBalance,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Баланс успешно обновлён',
            'balance' => $newBalance
        ]);
    }

    /**
     * Разблокировать клиента
     *
     * @return JsonResponse
     */
    public function revokeUser(Request $request)
    {
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'Данная функция недоступна в демо версии'
            ]);
        }

        $user = User::find($request->id);

        if (!$user) {
            return response()->json([
                'status' => 1,
                'message' => 'Пользователь не найден'
            ]);
        }

        if (!$user->isBanned()) {
            return response()->json([
                'status' => 1,
                'message' => 'Пользователь уже разблокирован'
            ]);
        }

        $user->unban();

        return response()->json([
            'status' => 0,
            'message' => 'Пользователь успешно разблокирован'
        ]);
    }

    /**
     * @deprecated
     * Рефералы пользователя
     *
     * @return Factory|RedirectResponse|\Illuminate\View\View
     */
    public function referral($id, Request $request)
    {
        if ($request->getMethod() == 'POST') {
            foreach ($request->redirects as $key => $value) {
                if ($value > 0) {
                    $r_referral = ReferralLink::where('user_id', $value)->first();
                    ReferralRelationship::find($key)->update([
                        'referral_link_id' => $r_referral->id,
                    ]);

                    ReferralLog::where('id_user', $id)->update([
                        'id_referral_link' => $r_referral->id,
                    ]);
                }
            }

            return redirect()->back();
        }

        $referral_link = ReferralLink::where('user_id', $request->id)->first();
        $items = ReferralRelationship::select(['user_id', 'id'])->whereHas('user')
            ->where('referral_link_id', $referral_link->id)->orderBy('id', 'desc')
            ->paginate(20);

        // Список избранных рефералов
        $follow_referrals = User::where('is_follow_referral', '=', 1)->get();

        return view('admin.account.users.referral', [
            'items' => $items,
            'id_user' => $request->id,
            'follow_referrals' => $follow_referrals,
        ]);
    }

    /**
     * @deprecated
     * История блокировок
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function bannedHistories($id)
    {
        $user = User::findOrFail($id);
        $histories = $user->bans()->paginate(20);

        return view('admin.account.users.banned_histories', compact('user', 'histories'));
    }

    /**
     * Загружаем заявки клиентов
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @throws \Exception
     */
    public function download_order(int $id)
    {
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'Данная функция недоступна в демо версии'
            ]);
        }

        $first = Carbon::now()->format('Y_m_d').'_'.random_int(0, 999999);

        return Excel::download(new OrderUserExport($id), $first.'.xls', excel_type('XLS'))->send();
    }

    /**
     * @deprecated
     * Активные сессия
    */
    public function activitySessions(Request $request, int $id)
    {
        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $id)
            ->orderBy('last_activity', 'desc')
            ->get();

        $user = User::find($id);


        $sessions = $sessions->map(
            function ($session) use ($request) {
                $agent = tap(new Agent, fn($agent) => $agent->setUserAgent($session->user_agent));

                return [
                    'agent'           => [
                        'platform' => $agent->platform(),
                        'browser'  => $agent->browser(),
                    ],
                    'user_agent'      =>    $session->user_agent,
                    'ip'              => $session->ip_address,
                    'isCurrentDevice' => $session->id === $request->session()->getId(),
                    'lastActive'      => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            }
        )->toArray();


        return view('admin.account.users.activity_sessions', compact('sessions', 'user'));
    }

    /**
     * Удаляем все сессии
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteActivitySessions(int $id, Request $request): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'status' => 1,
                'message' => '❌ Пользователь не найден'
            ]);
        }

        // Удаляем все сессии кроме текущей (если совпадают ID пользователя)
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $id)
            ->when($id === $request->user()->id, function ($query) use ($request) {
                $query->where('id', '!=', $request->session()->getId());
            })
            ->delete();

        // Получаем оставшиеся сессии после удаления
        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($request) {
                $agent = tap(new Agent, fn($agent) => $agent->setUserAgent($session->user_agent));

                return [
                    'agent' => [
                        'platform' => $agent->platform(),
                        'browser' => $agent->browser(),
                    ],
                    'user_agent' => $session->user_agent,
                    'ip' => $session->ip_address,
                    'isCurrentDevice' => $session->id === $request->session()->getId(),
                    'lastActive' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            })
            ->toArray();

        return response()->json([
            'status' => 0,
            'message' => 'Сессии успешно закрыты',
            'sessions' => $sessions
        ]);
    }
}
