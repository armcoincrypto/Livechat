<?php

namespace App\Console\Commands\Update;

use App\Models\CompetitorRates;
use App\Models\ContactGroup;
use App\Models\Currency;
use App\Models\DirectionExchange;
use App\Models\FileParserRates;
use App\Models\FilterCurrency;
use App\Models\Gateway;
use App\Models\GatewayMerchant;
use App\Models\GatewayPayment;
use App\Models\GeoCountryList;
use App\Models\GroupParserExchange;
use App\Models\ParserExchange;
use App\Models\PendingOrderStatus;
use App\Models\PermissionsGroup;
use App\Models\RewardProgram;
use App\Models\TaskRejectionStatus;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\UserBalance;
use App\Models\WithdrawalRequest;
use iEXPackages\Update\Facades\UpdateClientFacade;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use iEXPackages\ReferralSystem\Models\ReferralLog;
use iEXPackages\ReferralSystem\ReferralSystemFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdatingOldDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:old-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $autoDelType = DB::getSchemaBuilder()->getColumnType('direction_exchange', 'auto_del_order_status');
        if($autoDelType != 'json') {
            DB::table('direction_exchange')->update(['auto_del_order_status' => null]);
        }


        DB::table('info_statistics')->whereDate('created_at', '<', '2024-08-23')->delete();


        // Удаляем все папки связанные с мерчантами и таблицы в базе
        if(!Schema::hasColumn('gateways_merchants', 'alias'))
        {
            $gateways_merchants = GatewayMerchant::all();
            foreach ($gateways_merchants as $item) {
                $item->currencies()->sync([]);
                $item->direction_exchange()->sync([]);
                $item->delete();
            }

            $gateways_payments = GatewayPayment::all();
            foreach ($gateways_payments as $item) {
                $item->currencies()->sync([]);
                $item->delete();
            }
        }

        if(!Schema::hasColumn('aml_services', 'filename')) {
            DB::table('aml_services')->delete();
        }
        $this->comment('* Обновление данных в базе MYSQL');
        $this->call('migrate', [
            '--force' => true,
        ]);

        $this->call('db:seed', [
            '--force' => true,
        ]);
        $this->comment('-------------------------------');
        $this->comment('* Обновление данных');

        $this->call('monitoring:daily');

        $verification_card = \App\Models\VerificationCard::whereNull('email')->get();
        foreach ($verification_card as $item) {
            if (! isset($item->user)) {
                if (isset($item->tasks)) {
                    $item->update(['email' => $item->tasks->email]);
                }

                continue;
            }
            $item->update(['email' => $item->user->email]);
        }

        // Обновление кошельков
        $wallets = WithdrawalRequest::where('status', '=', 1)->groupBy('score')->get();
        foreach ($wallets as $wallet) {
            \App\Models\WithdrawalWallets::updateOrCreate([
                'wallet' => $wallet->score,
                'id_currency' => $wallet->id_currency,
            ], [
                'id_user' => $wallet->id_user,
                'wallet' => $wallet->score,
                'id_currency' => $wallet->id_currency,
            ]);
        }

        // Обновление баланса
        $balances = UserBalance::select('id', 'id_user', 'balance')->where('balance', '>', 0)->get();

        foreach ($balances as $balance) {
            $referrals_total_profit = ReferralLog::with(['user_admin', 'user_admin.user_balance'])
                ->select('id_user')
                ->where('referral_log.id_user', $balance->id_user)
                ->selectRaw('count(*) as count_num')
                ->selectRaw('sum(`bonus_number`) as total_amount')
                ->first();

            $total_sum = WithdrawalRequest::where('id_user', $balance->id_user)->where('status', '=', 1)->sum('base_referral');

            $balance->update([
                'referral_total_profit' => $referrals_total_profit['total_amount'],
                'referral_total_withdrawal' => $total_sum,
            ]);
        }

        // Проверка папок
        $required_fields = ['advantage', 'contact', 'links', 'news', 'svg', 'uploads', 'payment_systems', 'orders'];
        foreach ($required_fields as $required_field) {
            if (! \File::isDirectory(public_path('/storage/'.$required_field))) {
                File::makeDirectory(public_path('/storage/'.$required_field), 0777, true);
            }
        }

        if (! \File::isDirectory(storage_path('/app/iexexchanger/images'))) {
            File::makeDirectory(storage_path('/app/iexexchanger/images'), 0777, true);
        }


        DB::table('migrations')->where('migration', '2024_03_23_070532_create_pulse_tables')->delete();


        // Перевести параметры в мультиязычность
        $default_params = DB::table('filter_currency')->get()->keyBy('id')->toArray();
        $filter_currency = FilterCurrency::all();
        foreach ($filter_currency as $item) {
            if (empty($item->name)) {
                $item->update([
                    'name' => ['ru' => $default_params[$item->id]->name], ['en' => $default_params[$item->id]->name],
                ]);
            }
        }


        TaskStatus::updateOrCreate([
            'id' => '12',
        ], [
            'id' => '12',
            'color' => '#fffae0',
            'class' => 'st-warning',
            'is_export' => 0,
            'allow_delete' => 0
        ]);

        TaskStatus::updateOrCreate([
            'id' => '13',
        ], [
            'id' => '13',
            'color' => '#fffae0',
            'class' => 'st-merchant-waiting',
            'is_export' => 0,
            'allow_delete' => 0
        ]);

        TaskStatus::updateOrCreate([
            'id' => '14',
        ], [
            'id' => '14',
            'color' => '#a70000',
            'class' => 'st-error-light',
            'is_export' => 0,
            'allow_delete' => 0
        ]);


        iEXSetting([
            'session_lifetime' => config('session.lifetime'),
            'env_session_cookie' => config('session.cookie'),
            'env_app_url' => config('app.url'),
            'env_app_timezone' => config('app.timezone'),
            'env_app_locale' => config('app.locale'),
            'env_cache_driver' => config('cache.default'),
            'env_mail_driver' => config('mail.default')
        ]);


        // Обновление статусов
        $get_task_statuses = collect(json_decode(file_get_contents(storage_path('/task_status.json')), true))->keyBy('id')->toArray();
        foreach (TaskStatus::all() as $status) {
            if (empty($status->name) and isset($get_task_statuses[$status->id])) {
                $status->name = $get_task_statuses[$status->id]['name'];
                $status->save();
            }
        }

        // Обновление причин
        $task_pending_status = collect(json_decode(file_get_contents(storage_path('/task_pending_status.json')), true))->keyBy('id')->toArray();
        foreach (PendingOrderStatus::all() as $status) {
            if (empty($status->name) and isset($task_pending_status[$status->id])) {
                $status->name = $task_pending_status[$status->id]['name'];
                $status->save();
            }
        }

        // Обновление причин
        $task_reject_status = collect(json_decode(file_get_contents(storage_path('/task_reject_status.json')), true))->keyBy('id')->toArray();
        foreach (TaskRejectionStatus::all() as $status) {
            if (empty($status->name) and isset($task_reject_status[$status->id])) {
                $status->name = $task_reject_status[$status->id]['name'];
                $status->save();
            }
        }

        $rewardProgram = RewardProgram::orderBy('id')->first();
        $usersReward = User::where('id_reward_program', '=', 0)->count();
        if (isset($rewardProgram) and isset($rewardProgram->id) and $usersReward > 0) {
            DB::table('users')->where('id_reward_program', '=', 0)->update(['id_reward_program' => 1]);
        }

        if (empty(iEXSetting('type_working_mode')) or is_null(iEXSetting('type_working_mode'))) {
            iEXSetting(['type_working_mode' => 2]);
        }

        if (\Str::length(iEXSetting('admin_reserves_column_hidden_columns')) == 0) {
            iEXSetting(['admin_reserves_column_hidden_columns' => 'currency,summa,last_updated'])->save();
        }

        if (\Str::length(iEXSetting('admin_currencies_column_hidden_columns')) == 0) {
            iEXSetting(['admin_currencies_column_hidden_columns' => 'icon,pc,code,xml,reserve,receiving,sending'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_competitor_parser_hidden_columns')) == 0 or iEXSetting('admin_competitor_parser_hidden_columns') == 0) {
            iEXSetting(['admin_competitor_parser_hidden_columns' => 'name,id_competitor,course,type,created_at,last_updated,status'])->save();
        }

        if (\Str::length(iEXSetting('admin_mass_direction_editor_hidden_columns')) == 0) {
            iEXSetting(['admin_mass_direction_editor_hidden_columns' => 'direction_exchange,text,status'])->save();
        }


        if (\Str::length(iEXSetting('admin_requisites_info_hidden_columns')) == 0) {
            iEXSetting(['admin_requisites_info_hidden_columns' => 'name,value,attached_requisites,created_at,updated_at,status'])->save();
        }

        if (\Str::length(iEXSetting('admin_codes_hidden_columns')) == 0) {
            iEXSetting(['admin_codes_hidden_columns' => 'name,symbol,currencies,exchange_rate'])->save();
        }

        if (\Str::length(iEXSetting('admin_directions_hidden_columns')) == 0) {
            iEXSetting(['admin_directions_hidden_columns' => 'direction,exchangeRate,profit,otherFeeIn,otherFeeOut,status'])->save();
        }

        if (\Str::length(iEXSetting('admin_user_hidden_columns')) == 0) {
            iEXSetting(['admin_user_hidden_columns' => 'name,email,count_exchange,balance,ip_address_browser,created_at'])->save();
        }

        if (\Str::length(iEXSetting('admin_merchant_hidden_columns')) == 0) {
            iEXSetting(['admin_merchant_hidden_columns' => 'name,alias,settings,security,pegged_currencies,count_order,status'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_parser_formula_hidden_columns')) == 0) {
            iEXSetting(['admin_parser_formula_hidden_columns' => 'title,formula,course,pegged_currencies,created_at,updated_at,status'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_requisites_hidden_columns')) == 0) {
            iEXSetting(['admin_requisites_hidden_columns' => 'currency,account_number,views,exchange_today,exchange_month,status'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_payment_systems_hidden_columns')) == 0) {
            iEXSetting(['admin_payment_systems_hidden_columns' => 'logo,name,updated_at'])->save();
        }

        if (\Str::length(iEXSetting('admin_crypto_parser_hidden_columns')) == 0) {
            iEXSetting(['admin_crypto_parser_hidden_columns' => 'name,course,type,attached_direction,last_updated,status'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_reserves_hidden_columns')) == 0) {
            iEXSetting(['admin_reserves_hidden_columns' => 'currency,amount,created_at,last_updated,events,group'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_autopayment_hidden_columns')) == 0) {
            iEXSetting(['admin_autopayment_hidden_columns' => 'name,alias,attached_currencies,total_orders,total_to_usd,created_at,last_updated,status'])->save();
        }

        if (\Str::length(iEXSetting('admin_bestchange_parser_hidden_columns')) == 0) {
            iEXSetting(['admin_bestchange_parser_hidden_columns' => 'direction,course,information,position,created_at,last_updated,status'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_telegram_notification_hidden_columns')) == 0) {
            iEXSetting(['admin_telegram_notification_hidden_columns' => 'name,channel,status,last_updated'])->save();
        }

        // Обновление настроек страницы
        if (\Str::length(iEXSetting('admin_bonuses_discount_hidden_columns')) == 0) {
            iEXSetting(['admin_bonuses_discount_hidden_columns' => 'amount,percent,created_at,last_updated'])->save();
        }

        if (\Str::length(iEXSetting('admin_verifications_card_hidden_columns')) == 0) {
            iEXSetting(['admin_verifications_card_hidden_columns' => 'currency,ip_address,account_number,photo,user,created_at,status'])->save();
        }

        if (\Str::length(iEXSetting('admin_order_statuses_log_hidden_columns')) == 0) {
            iEXSetting(['admin_order_statuses_log_hidden_columns' => 'number,user,old_status,new_status,give_price,receiving_price,exchange_rate'])->save();
        }

        if (\Str::length(iEXSetting('admin_file_parser_hidden_columns')) == 0) {
            iEXSetting(['admin_file_parser_hidden_columns' => 'name,id_group,course,attached_direction,created_at,last_updated,status'])->save();
        }

        if (empty((int) iEXSetting('max_number_format_reserve'))) {
            iEXSetting(['max_number_format_reserve' => 10])->save();
        }

        if ((int) iEXSetting('id_referral_code_currency') == 0) {
            iEXSetting(['id_referral_code_currency' => getenv('PAYOUT_CURRENCY_ID')])->save();
        }

        DB::table('direction_exchange')->whereNull('oth_comm_percent')->update(['oth_comm_percent' => 0]);
        DB::table('direction_exchange')->whereNull('oth_comm_currency')->update(['oth_comm_currency' => 0]);

        Schema::dropIfExists('course_update_time_logs');
        Schema::dropIfExists('reserve_log_profit');
        Schema::dropIfExists('currencies_log');
        Schema::dropIfExists('reserve_log');
        Schema::dropIfExists('event_reserve');
        Schema::dropIfExists('log_check_pay');
        Schema::dropIfExists('requisites_attach_logs');
        Schema::dropIfExists('direction_exchange_group');
        Schema::dropIfExists('parser_exchange_error_rates');
        Schema::dropIfExists('parser_exchange_log');
        Schema::dropIfExists('parser_exchange_http_logs');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('fund');
        Schema::dropIfExists('reserves_alerts');
        Schema::dropIfExists('job_settings');
        Schema::dropIfExists('backup_codes');
        Schema::dropIfExists('selected_courses');
        Schema::dropIfExists('amlbot_histories');
        Schema::dropIfExists('getblockbot_histories');
        Schema::dropIfExists('aml_analysis_logs');
        Schema::dropIfExists('logs_email');
        Schema::dropIfExists('requisites_blacklist');
        Schema::dropIfExists('main_event_logs');
        Schema::dropIfExists('users_history_profiles');
        Schema::dropIfExists('tasks_managers_styles');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('template_type_events');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('log_drain');
        Schema::dropIfExists('reserve_request');
        Schema::dropIfExists('reward');
        Schema::dropIfExists('reward_log');
        Schema::dropIfExists('settings_reward');
        Schema::dropIfExists('cashback_error_log');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('docs_category');
        Schema::dropIfExists('docs_category_type');
        Schema::dropIfExists('docs_items');
        Schema::dropIfExists('archive_reports');
        Schema::dropIfExists('file_storages');
        Schema::dropIfExists('bestchange_rates_log');
        Schema::dropIfExists('bestchange_rates');
        Schema::dropIfExists('bestchange_currency_codes');
        Schema::dropIfExists('bestchange_cities');
        Schema::dropIfExists('bestchange_currencies');
        Schema::dropIfExists('bestchange_data_log');
        Schema::dropIfExists('whitelist_order');
        Schema::dropIfExists('debtors');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('yandex_currencies');
        Schema::dropIfExists('task_private_hash');
        Schema::dropIfExists('transit_requisites');
        Schema::dropIfExists('logs_autopayment_events');
        Schema::dropIfExists('wallets_addresses');;
        Schema::dropIfExists('aml_services_logs');
        Schema::dropIfExists('directions_has_networks');
        Schema::dropIfExists('currencies_networks');
        Schema::dropIfExists('bestchange_bl_histories');
        Schema::dropIfExists('affiliate_settings');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('backgrounds');
        Schema::dropIfExists('collaboration_pr');
        Schema::dropIfExists('cron');
        Schema::dropIfExists('cron_category');
        Schema::dropIfExists('iex_script_config');
        Schema::dropIfExists('your_exchange');

        Schema::dropIfExists('fine_employees');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('event_employees');
        Schema::dropIfExists('admin_desktops');
        Schema::dropIfExists('admin_desktop_gadgets');
        Schema::dropIfExists('unpaid_items');
        Schema::dropIfExists('admin_filter_header');
        Schema::dropIfExists('admin_filter_header');

        $this->gateways();
        $this->rules();
        $this->mailTemplates();

        // Создаем пользователь "Guest"
        $is_guest = User::where('is_guest', 1)->count();
        if ($is_guest == 0) {
            $user = User::create([
                'is_guest' => 1,
                'name' => 'Guest',
                'email' => 'guest@test.com',
                'password' => Hash::make(Str::random()),
                'ip_address' => '127.0.0.1', // Local IP
                'last_login' => \Illuminate\Support\Carbon::now(),
                'last_activity' => Carbon::now(),
            ]);

            //Регистрация реферала
            ReferralSystemFacade::register($user, null);
        }

        $permissions_groups_decode = json_decode(
            file_get_contents(
                storage_path('permission_group.json')
            ), true);

        foreach ($permissions_groups_decode as $value)
        {
            PermissionsGroup::updateOrCreate([
                'id' => $value['id'],
                'name' => $value['name'],
            ], [
                'id' => $value['id'],
                'created_at' => $value['created_at'],
                'updated_at' => $value['updated_at'],
                'name' => $value['name'],
            ]);
        }

        Permission::where('name', '=', 'requisites_blacklist')->delete();
        Permission::where('name', '=', 'course_designer')->delete();
        Permission::where('name', '=', 'reserve_request')->delete();
        Permission::where('name', '=', 'admin_access_cloudflare')->delete();
        Permission::where('name', '=', 'order_whitelist')->delete();
        Permission::where('name', '=', 'order_debtors')->delete();
        Permission::where('name', '=', 'your_course')->delete();
        Permission::where('name', '=', 'admin_limit_editing_directions')->delete();
        Permission::where('name', '=', 'admin_handler_any_order')->delete();
        Permission::where('name', '=', 'admin_affiliate_program')->update([
            'name' => 'admin_bonuses_program',
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_api',
        ], [
            'name' => 'admin_api',
            'title' => 'Разрешить доступ к API в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, конторолировать функции API',
            'id_permissions_group' => 3,
        ]);

        // Новые роли
        Permission::updateOrCreate([
            'name' => 'admin_unlimited_order_creation',
        ], [
            'name' => 'admin_unlimited_order_creation',
            'title' => 'Разрешить снятие лимитов для заявок',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, создать заявки обходя установленные ограничения',
            'id_permissions_group' => 3,
        ]);

        // Новые роли
        Permission::updateOrCreate([
            'name' => 'admin_laravel_links',
        ], [
            'name' => 'admin_laravel_links',
            'title' => 'Разрешить доступ к мониторингу данных',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить возможность просматривать мониторинг важных данных.',
            'id_permissions_group' => 3,
        ]);

        // Новые роли
        Permission::updateOrCreate([
            'name' => 'admin_account_change_password',
        ], [
            'name' => 'admin_account_change_password',
            'title' => 'Разрешить сбрасывать пароли пользователям',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить возможность устанавливать новые пароли для пользователей.',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_access_google_authentication',
        ], [
            'name' => 'admin_access_google_authentication',
            'title' => 'Разрешить управление Google Authentication',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ключами безопасности Google Authentication.',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_account_logs',
        ], [
            'name' => 'admin_account_logs',
            'title' => 'Разрешить доступ к логам авторизаций',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, просматривать и очищать логи авторизаций.',
            'id_permissions_group' => 3,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_requisites_log',
        ], [
            'name' => 'admin_requisites_log',
            'title' => 'Разрешить управление логами реквизитов',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, просматривать и очищать логи платежных реквизитов.',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_widgets',
        ], [
            'name' => 'admin_widgets',
            'title' => 'Разрешить управление виджетами для рабочего стола',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять виджетами и рабочим столом.',
            'id_permissions_group' => 4,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_other_favorites',
        ], [
            'name' => 'admin_other_favorites',
            'title' => 'Разрешить управление избранными страницами',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, могут управлять избранными страницами.',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_verification_account',
        ], [
            'name' => 'admin_verification_account',
            'title' => 'Разрешить управление верификациями личности в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять верификациями личности в админпанели.',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_causes_limit',
        ], [
            'name' => 'admin_orders_causes_limit',
            'title' => 'Разрешить настройку причин и лимитов для заявок',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, создавать или редактирование причин и лимитов для заявок',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_control_status',
        ], [
            'name' => 'admin_orders_control_status',
            'title' => 'Разрешить управление статусами для заявок',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять статусами для заявок',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_logs',
        ], [
            'name' => 'admin_orders_logs',
            'title' => 'Разрешить управление логами заявок',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить просматривать и очищать логи заявок',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_parser_bestchange',
        ], [
            'name' => 'admin_parser_bestchange',
            'title' => 'Разрешить управление BestChange парсером в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять BestChange парсером в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_parser_competitors',
        ], [
            'name' => 'admin_parser_competitors',
            'title' => 'Разрешить управление парсером конкурентов в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять парсером конкурентов в админпанели',
            'id_permissions_group' => 3,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_parser_formula',
        ], [
            'name' => 'admin_parser_formula',
            'title' => 'Разрешить управление формулами курсов в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять формулами курсов в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_parser_file',
        ], [
            'name' => 'admin_parser_file',
            'title' => 'Разрешить управление файлами курсов в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять файлами курсов в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_plugin_promo_code',
        ], [
            'name' => 'admin_plugin_promo_code',
            'title' => 'Разрешить управление промо-кодами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять промо-кодами в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_plugin_contests',
        ], [
            'name' => 'admin_plugin_contests',
            'title' => 'Разрешить управление конкурсами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять конкурсами в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_plugin_card_info',
        ], [
            'name' => 'admin_plugin_card_info',
            'title' => 'Разрешить управление информациями о картах в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, настроить плагин которая собирает информацию о картах в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_plugin_cities',
        ], [
            'name' => 'admin_plugin_cities',
            'title' => 'Разрешить управление городами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять городами в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_plugin_proxy',
        ], [
            'name' => 'admin_plugin_proxy',
            'title' => 'Разрешить управление proxy менеджером в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять proxy менеджером в админпанели',
            'id_permissions_group' => 2,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_plugin_aml',
        ], [
            'name' => 'admin_plugin_aml',
            'title' => 'Разрешить управление AML-сервисами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять AML-сервисами в админпанели',
            'id_permissions_group' => 2,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_export_data',
        ], [
            'name' => 'admin_export_data',
            'title' => 'Разрешить управление экспортными данными в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять экспортными данными в админпанели',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_other_blockchain_explorer',
        ], [
            'name' => 'admin_other_blockchain_explorer',
            'title' => 'Разрешить управление blockchain explorer в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять blockchain explorer в админпанели',
            'id_permissions_group' => 4,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_banners',
        ], [
            'name' => 'admin_banners',
            'title' => 'Разрешить управление баннерами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять баннерами в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_geoip',
        ], [
            'name' => 'admin_geoip',
            'title' => 'Разрешить управление GEOIP в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять GEOIP в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_notification',
        ], [
            'name' => 'admin_notification',
            'title' => 'Разрешить управление уведомлениями в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять уведомлениями в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_advantage',
        ], [
            'name' => 'admin_advantage',
            'title' => 'Разрешить управление преимуществами в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять преимуществами в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_links_reviews',
        ], [
            'name' => 'admin_links_reviews',
            'title' => 'Разрешить управление ссылками на отзывы в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ссылками на отзывы в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_links_footer',
        ], [
            'name' => 'admin_links_footer',
            'title' => 'Разрешить управление ссылками для footer в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять ссылками для footer в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_menu',
        ], [
            'name' => 'admin_menu',
            'title' => 'Разрешить управление навигационным меню в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять навигационным меню в админпанели',
            'id_permissions_group' => 2,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_statistics_tools',
        ], [
            'name' => 'admin_statistics_tools',
            'title' => 'Разрешить управление статистикой для главной страницы в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управлять статистикой для главной страницы в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_other_online_chat',
        ], [
            'name' => 'admin_other_online_chat',
            'title' => 'Разрешить управление онлайн чатом для заявок в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, управление онлайн чатом для заявок админпанели',
            'id_permissions_group' => 5,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_autopayment',
        ], [
            'name' => 'admin_autopayment',
            'title' => 'Разрешить доступ к выплатам',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к автовыплатам в админпанели',
            'id_permissions_group' => 3,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_merchant_api_logs',
        ], [
            'name' => 'admin_merchant_api_logs',
            'title' => 'Разрешить доступ к логам мерчантов и api',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к логам мерчантов и api в админпанели',
            'id_permissions_group' => 3,
        ]);



        Permission::updateOrCreate([
            'name' => 'admin_order_archive_orders',
        ], [
            'name' => 'admin_order_archive_orders',
            'title' => 'Разрешить доступ к настройке архивации заявок',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить доступ к настройке архивации заявок в админпанели',
            'id_permissions_group' => 3,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_orders_restore',
        ], [
            'name' => 'admin_orders_restore',
            'title' => 'Разрешить восстанавливать заявки',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить восстанавливать заявки',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_reject',
        ], [
            'name' => 'admin_orders_reject',
            'title' => 'Разрешить отклонять заявки',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить отклонять заявки',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_client_black_list',
        ], [
            'name' => 'admin_orders_client_black_list',
            'title' => 'Разрешить вносить клиентов в черный список',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить в заявках вносить клиентов в черный список',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_attach_photo',
        ], [
            'name' => 'admin_orders_attach_photo',
            'title' => 'Разрешить прикреплять фото',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить прикреплять фотографии к заявкам',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_control_comment',
        ], [
            'name' => 'admin_orders_control_comment',
            'title' => 'Разрешить управление комментариями',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать управление комментариями в заявке',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_id_editor',
        ], [
            'name' => 'admin_orders_id_editor',
            'title' => 'Разрешить редактировать заявку',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать редактирование заявки',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_id_recount',
        ], [
            'name' => 'admin_orders_id_recount',
            'title' => 'Разрешить пересчитать заявку',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать пересчитывать заявку',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_change_operator',
        ], [
            'name' => 'admin_orders_change_operator',
            'title' => 'Разрешить передавать заявки между менеджерами',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать передать заявку другим менеджерам',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_execute',
        ], [
            'name' => 'admin_orders_execute',
            'title' => 'Разрешить обработку заявки',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать обрабатывать заявку',
            'id_permissions_group' => 5,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_orders_auto_successful',
        ], [
            'name' => 'admin_orders_auto_successful',
            'title' => 'Разрешить вывести кнопку автовыплаты',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешать отображение кнопки автовыплаты заявки',
            'id_permissions_group' => 5,
        ]);

        // 9.0.7

        Permission::updateOrCreate([
            'name' => 'admin_session_logs',
        ], [
            'name' => 'admin_session_logs',
            'title' => 'Разрешить доступ к сессиям пользователей в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, получить доступ к сессиям пользователей в админпанели',
            'id_permissions_group' => 4,
        ]);

        Permission::updateOrCreate([
            'name' => 'admin_session_destroy',
        ], [
            'name' => 'admin_session_destroy',
            'title' => 'Разрешить удалять сессии пользователей в админпанели',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, удалять сессии пользователей в админпанели',
            'id_permissions_group' => 4,
        ]);


        Permission::updateOrCreate([
            'name' => 'admin_telegram_notification',
        ], [
            'name' => 'admin_telegram_notification',
            'title' => 'Разрешить управление Telegram уведомлениями',
            'text' => 'Данная опция позволит пользователям, имеющим доступ в админпанель, разрешить полный контроль над telegram уведомлениями в админпанели',
            'id_permissions_group' => 4,
        ]);


        //  Меняем роли
        foreach (Role::whereNull('title')->get() as $item) {

            if($item->name == 'Разработчик') {
                $item->update([
                    'name' => 'super admin',
                    'title' => 'Главные администраторы'
                ]);
            }elseif($item->name == 'Администратор') {
                $item->update([
                    'name' => 'administrator',
                    'title' => 'Администраторы'
                ]);
            }elseif($item->name == 'Модератор') {
                $item->update([
                    'name' => 'moderator',
                    'title' => 'Модераторы'
                ]);
            }elseif($item->name == 'Служба поддержки') {
                $item->update([
                    'name' => 'support',
                    'title' => 'Служба поддержки'
                ]);
            }elseif($item->name == 'Менеджер') {
                $item->update([
                    'name' => 'manager',
                    'title' => 'Менеджеры'
                ]);
            }

        }


        // Группа
        if (ContactGroup::count() == 0) {
            $contact = ContactGroup::create([
                'name' => 'Техническая поддержка',
                'sorting' => 0,
            ]);

            DB::table('contacts')->update(['id_group' => $contact->id]);
        }

        // Обновить тех. название
        $currencies_name = Currency::has('payment')->has('code_currency')->whereNull('tech_name')->get();
        foreach ($currencies_name as $item) {
            $item->update(['tech_name' => $item->payment->name.' '.$item->code_currency->name]);
        }


        try {
            $this->addEnvironmentVariables();
        }catch (\Exception $exception) {

        }

        $competitors_exchanges = CompetitorRates::whereNull('code')->get();
        foreach ($competitors_exchanges as $rate) {
            if (isset($rate->competitor_link) and ! empty($rate->competitor_link->alias)) {
                $rate->update([
                    'code' => '[competitors_'.\Str::lower(\Str::studly($rate->competitor_link->name)).'_'.\Str::lower($rate->exchange_in.'-'.$rate->exchange_out).']',
                ]);
            }
        }

        $file_parser_exchanges = FileParserRates::whereNull('code')->get();
        foreach ($file_parser_exchanges as $rate) {
            if (isset($rate->file_parser_group) and ! empty($rate->file_parser_group->alias)) {
                $rate->update([
                    'code' => '[fileparser_'.\Str::lower(Str::studly($rate->file_parser_group->name)).'_'.\Str::lower($rate->exchange_in.'-'.$rate->exchange_out).']',
                ]);
            }
        }

        $parser_exchanges = ParserExchange::whereNull('code')->get();
        foreach ($parser_exchanges as $parser_exchange) {
            if (isset($parser_exchange->group_parse_exchange) and ! empty($parser_exchange->group_parse_exchange->alias)) {
                $parser_exchange->update([
                    'code' => '['.\Str::lower($parser_exchange->group_parse_exchange->alias).'_'.\Str::lower($parser_exchange->code_in.'-'.$parser_exchange->code_out).']',
                ]);
            }
        }


        // Очистка кэша
        $this->call('view:clear');
        $this->line('Начинаем обновление iEXExchanger...');
        try {
            UpdateClientFacade::checkUpdates();
        } catch (\Exception|ServerException|ClientException $exception) {
            //
        }

        $this->info('iEXExchanger обновление (актуальная версия '.config('iexexchanger.version.current').') ⚡');

    }

    protected function rules()
    {
        foreach (json_decode(file_get_contents(storage_path('geo_country_list.json')), true) as $value) {
            GeoCountryList::updateOrCreate([
                'code' => $value['code'],
            ], [
                'code' => $value['code'],
                'value' => $value['value'],
            ]);
        }

        $imports = collect(json_decode(file_get_contents(storage_path('/geo_country_list.json')), true))->pluck('value', 'code');

        foreach (GeoCountryList::all() as $item) {
            if (empty($item->value) and isset($imports[$item->code])) {
                $item->setTranslation('value', 'ru', $imports[$item->code]);
                $item->save();
            }
        }
    }

    protected function gateways()
    {
        $this->comment('* Добавление/Обновление мерчантов или выплат');

        // Удаляем папки с ключами
        if(File::isDirectory(storage_path('/gateways/merchant'))) {
            File::deleteDirectory(storage_path('/gateways/merchant'));
        }

        if(File::isDirectory(storage_path('/gateways/pay'))) {
            File::deleteDirectory(storage_path('/gateways/pay'));
        }

//        foreach (json_decode(file_get_contents(storage_path('gateways.json')), true) as $value) {
//            Gateway::updateOrCreate([
//                'alias' => $value['alias'],
//                'name' => $value['name'],
//                'class_name' => $value['class_name'],
//            ], [
//                'alias' => $value['alias'],
//                'name' => $value['name'],
//                'class_name' => $value['class_name'],
//                'is_merchant' => $value['is_merchant'],
//                'is_pay' => $value['is_pay'],
//                'is_rpc' => $value['is_rpc'],
//                'is_check_pay' => $value['is_check_pay'],
//                'version' => $value['version'],
//                'security_options' => $value['security_options'],
//                'status' => $value['status'],
//            ]);
//        }
    }

    protected function mailTemplates()
    {
    }

    protected function addEnvironmentVariables()
    {
        $envFile = app()->environmentFile();
        $contents = File::get($envFile);
        if(empty(config('iexexchanger.app_secret_password'))) {
            $appSecretPassword = Str::lower(Str::random(20));

            $variables = Arr::where([
                "IEX_APP_SECRET_PASSWORD" => "IEX_APP_SECRET_PASSWORD={$appSecretPassword}"
            ], function ($value, $key) use ($contents) {
                return ! Str::contains($contents, PHP_EOL.$key);
            });


            $variables = trim(implode(PHP_EOL, $variables));

            if ($variables === '') {
                return;
            }

            File::append(
                $envFile,
                Str::endsWith($contents, PHP_EOL) ? PHP_EOL.$variables.PHP_EOL : PHP_EOL.PHP_EOL.$variables.PHP_EOL,
            );
        }
    }
}
