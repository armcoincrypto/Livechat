<?php

declare(strict_types=1);

namespace Tests\Unit\TelegramOperator;

use App\Models\Task;
use App\Models\TaskField;
use App\Notifications\TelegramNewOrder;
use App\Services\TelegramOperator\TelegramOrderMessagePresenter;
use App\Services\TelegramOperator\TelegramOrderOperatorWorkflowService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class TelegramOrderMessagePresenterTest extends TestCase
{
    public function test_fiat_payout_fields_and_rates_and_keyboard(): void
    {
        $task = $this->baseTask();
        $task->course_display = '1 USDT = 90.0747 RUB';
        $task->give_price = '441';
        $task->receiving_price = '39722.9427';
        $task->setRelation('tasks_fields_currency_in', collect([
            $this->field('Ваш Телеграмм', '@victor_mo_official', 'income_outcome_income_vas_telegramm_whatsapp_1', 'in'),
        ]));
        $task->setRelation('tasks_fields_currency_out', collect([
            $this->field('Card number', '410011506091695', 'outcome_nomer_karty', 'out'),
            $this->field('Recipient', 'Виктор Кузнецов', 'sender_fullname', 'out'),
            $this->field('Phone', '+79179971077', 'outcome_nomer_telefona', 'out'),
        ]));
        $task->setRelation('direction_exchange', $this->direction('Tether TRC20', 'USDT', 'USDTTRC20', 'ЮMoney', 'RUB', 'YAMRUB', '1 USDT = 90.1000 RUB'));

        $presenter = new TelegramOrderMessagePresenter();
        $text = $presenter->renderText($presenter->present($task));

        $this->assertStringContainsString('📋 Заявка №: 1787225842559', $text);
        $this->assertStringContainsString('Отдает клиент:', $text);
        $this->assertStringContainsString('ПС: Tether TRC20 USDT', $text);
        $this->assertStringContainsString('Сумма: 441 USDT', $text);
        $this->assertStringContainsString('Telegram: @victor_mo_official', $text);
        $this->assertStringContainsString('Переводит сервис:', $text);
        $this->assertStringContainsString('ПС: ЮMoney RUB', $text);
        $this->assertStringContainsString('Сумма: 39722.9427 RUB', $text);
        $this->assertStringContainsString('Курс обмена: 1 USDT = 90.0747 RUB', $text);
        $this->assertStringContainsString('Актуальный: 1 USDT = 90.1000 RUB', $text);
        $this->assertStringContainsString('Card number: 410011506091695', $text);
        $this->assertStringContainsString('Recipient: Виктор Кузнецов', $text);
        $this->assertStringContainsString('Phone: +79179971077', $text);
        $this->assertStringContainsString('Ожидается оплата', $text);
        $this->assertStringNotContainsString('webhook', strtolower($text));
        $this->assertStringNotContainsString('token', strtolower($text));

        Config::set('telegram_operator.actions_enabled', true);
        $labels = [];
        foreach (TelegramOrderOperatorWorkflowService::notificationKeyboard((int) $task->id) as $row) {
            foreach ($row as $btn) {
                $labels[] = $btn['text'] ?? '';
            }
        }
        $this->assertTrue((bool) array_filter($labels, fn ($t) => str_contains((string) $t, 'Принять')));
        $this->assertTrue((bool) array_filter($labels, fn ($t) => str_contains((string) $t, 'Выполнить')));
        $this->assertTrue((bool) array_filter($labels, fn ($t) => str_contains(mb_strtolower((string) $t), 'админ')));
    }

    public function test_crypto_receive_includes_wallet_and_omits_blank_optionals(): void
    {
        $task = $this->baseTask();
        $task->public_id = '1787385423237';
        $task->give_price = '42.00000000';
        $task->receiving_price = '1726.00489634';
        $task->course_display = '1 DASH = 41.09535467 USDT';
        $task->from_shot = '';
        $task->setRelation('payment_requisites', (object) ['account_number' => 'XhgePhzRE6a3pSPyKnuCUEBBMBA9o8nqNg']);
        $task->setRelation('tasks_fields_currency_in', collect([
            $this->field('Ваш Телеграмм', '@artemiy_nur', 'income_outcome_income_vas_telegramm_whatsapp_1', 'in'),
        ]));
        $task->setRelation('tasks_fields_currency_out', collect([
            $this->field('Адрес для депозита', '0xa03c3699E40F0b7893a1Fb80Ac6B570f0E3b75', 'outcome_deposit_adress', 'out'),
        ]));
        $task->setRelation('direction_exchange', $this->direction('Dash', 'DASH', 'DASH', 'Tether BEP20', 'USDT', 'USDTBEP20', '1 DASH = 40.76327959 USDT'));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );

        $this->assertStringContainsString('Адрес для депозита: XhgePhzRE6a3pSPyKnuCUEBBMBA9o8nqNg', $text);
        $this->assertStringContainsString('Кошелек: 0xa03c3699E40F0b7893a1Fb80Ac6B570f0E3b75', $text);
        $this->assertStringContainsString('Курс обмена: 1 DASH = 41.09535467 USDT', $text);
        $this->assertStringContainsString('Актуальный: 1 DASH = 40.76327959 USDT', $text);
        $this->assertStringContainsString('Tether BEP20 USDT', $text);
        $this->assertStringNotContainsString('Phone:', $text);
        $this->assertStringNotContainsString('Card number:', $text);
    }

    public function test_missing_optional_fields_do_not_leave_blank_labels(): void
    {
        $task = $this->baseTask();
        $task->setRelation('tasks_fields_currency_in', collect());
        $task->setRelation('tasks_fields_currency_out', collect());
        $task->setRelation('payment_requisites', null);
        $task->setRelation('direction_exchange', $this->direction('Dash', 'DASH', 'DASH', 'Tether BEP20', 'USDT', 'USDTBEP20', '1 DASH = 41.09535467 USDT'));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );

        $this->assertStringNotContainsString('Telegram:', $text);
        $this->assertStringNotContainsString('Card number:', $text);
        $this->assertStringNotContainsString('Phone:', $text);
        $this->assertStringNotContainsString(' — :', $text);
        $this->assertStringContainsString('📋 Заявка №:', $text);
    }

    public function test_secret_fields_are_omitted(): void
    {
        $task = $this->baseTask();
        $task->setRelation('tasks_fields_currency_out', collect([
            $this->field('API token', 'abc', 'api_token', 'out'),
            $this->field('Card number', '4100', 'outcome_nomer_karty', 'out'),
        ]));
        $task->setRelation('direction_exchange', $this->direction('Tether TRC20', 'USDT', 'USDTTRC20', 'ЮMoney', 'RUB', 'YAMRUB', null));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );
        $this->assertStringNotContainsString('abc', $text);
        $this->assertStringContainsString('Card number: 4100', $text);
    }

    public function test_usdt_to_xmr_payout_wallet_and_inverse_rate(): void
    {
        $task = $this->baseTask();
        $task->public_id = '1786612898135';
        $task->give_price = '400';
        $task->receiving_price = '0.98684385';
        $task->course_display = '405.33261688 USDT = 1 XMR';
        $task->setRelation('tasks_fields_currency_in', collect([
            $this->field('Ваш Телеграмм', 'Spec.dima01@proton.me', 'income_outcome_income_vas_telegramm_whatsapp_1', 'in'),
        ]));
        $task->setRelation('tasks_fields_currency_out', collect([
            $this->field('Адрес для депозита', '4DSQMNzzq46N1z2pZWAVdeA6JvUL9TCB2bnBiA3ZzoqEdYJnMydt5akCa3vtmapeDsbVKGPFdNkzqTcJS8M8oyK7WGjAEoLzf56Tu78MdS', 'outcome_deposit_adress', 'out'),
        ]));
        $task->setRelation('payment_requisites', null);
        $task->setRelation('direction_exchange', $this->direction('Tether TRC20', 'USDT', 'USDTTRC20', 'Monero', 'XMR', 'XMR', '405.33261688 USDT = 1 XMR'));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );

        $this->assertStringContainsString('ПС: Tether TRC20 USDT', $text);
        $this->assertStringContainsString('Сумма: 400 USDT', $text);
        $this->assertStringContainsString('Telegram: Spec.dima01@proton.me', $text);
        $this->assertStringContainsString('ПС: Monero XMR', $text);
        $this->assertStringContainsString('Сумма: 0.98684385 XMR', $text);
        $this->assertStringContainsString('Курс обмена: 405.33261688 USDT = 1 XMR', $text);
        $this->assertStringContainsString('Кошелек: 4DSQMNzzq46N1z2pZWAVdeA6JvUL9TCB2bnBiA3ZzoqEdYJnMydt5akCa3vtmapeDsbVKGPFdNkzqTcJS8M8oyK7WGjAEoLzf56Tu78MdS', $text);
        $this->assertStringNotContainsString('Актуальный:', $text);
    }

    public function test_usdt_to_kaspi_card_recipient_phone(): void
    {
        $task = $this->baseTask();
        $task->public_id = '1786554546236';
        $task->give_price = '620';
        $task->receiving_price = '288388.48';
        $task->course_display = '1 USDT = 465.14 KZT';
        $task->setRelation('tasks_fields_currency_in', collect([
            $this->field('Ваш Телеграмм', '@kikoeer8', 'income_outcome_income_vas_telegramm_whatsapp_1', 'in'),
        ]));
        $task->setRelation('tasks_fields_currency_out', collect([
            $this->field('Номер карты', '4400430051522917', 'outcome_nomer_karty', 'out'),
            $this->field('ФИО получателя', 'MAXIM KHAKIMOV', 'sender_fullname', 'out'),
            $this->field('Номер телефона', '87780633922', 'outcome_nomer_telefona', 'out'),
        ]));
        $task->setRelation('direction_exchange', $this->direction('Tether TRC20', 'USDT', 'USDTTRC20', 'KASPI', 'KZT', 'KASPIKZT', '1 USDT = 465.14 KZT'));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );

        $this->assertStringContainsString('Telegram: @kikoeer8', $text);
        $this->assertStringContainsString('ПС: KASPI KZT', $text);
        $this->assertStringContainsString('Курс обмена: 1 USDT = 465.14 KZT', $text);
        $this->assertStringContainsString('Номер карты: 4400430051522917', $text);
        $this->assertStringContainsString('ФИО получателя: MAXIM KHAKIMOV', $text);
        $this->assertStringContainsString('Номер телефона: 87780633922', $text);
        $this->assertStringContainsString('E-mail: a@b.c', $text);
        $this->assertStringNotContainsString('Актуальный:', $text);
    }

    public function test_current_rate_omitted_when_direction_rate_missing(): void
    {
        $task = $this->baseTask();
        $task->course_display = '1 USDT = 90 RUB';
        $task->setRelation('direction_exchange', $this->direction('Tether TRC20', 'USDT', 'USDTTRC20', 'ЮMoney', 'RUB', 'YAMRUB', null));

        $text = (new TelegramOrderMessagePresenter())->renderText(
            (new TelegramOrderMessagePresenter())->present($task)
        );
        $this->assertStringContainsString('Курс обмена: 1 USDT = 90 RUB', $text);
        $this->assertStringNotContainsString('Актуальный:', $text);
    }

    public function test_telegram_new_order_uses_presenter_and_fail_open_fallback(): void
    {
        $src = (string) file_get_contents(base_path('app/Notifications/TelegramNewOrder.php'));
        $this->assertStringContainsString('TelegramOrderMessagePresenter', $src);
        $this->assertStringContainsString('telegram_order_message_render_failed', $src);
        $diag = (string) file_get_contents(base_path('app/Console/Commands/TelegramOperatorDiagCommand.php'));
        $this->assertStringContainsString('TelegramBotTokenResolver', $diag);
        $this->assertStringContainsString('ACTIVE_OPERATOR_BOT', $diag);
    }

    private function baseTask(): Task
    {
        $task = new Task();
        $task->id = 48040;
        $task->public_id = '1787225842559';
        $task->status = 2;
        $task->give_price = '400';
        $task->receiving_price = '36000';
        $task->course_display = '1 USDT = 90 RUB';
        $task->exists = true;
        $task->setRelation('user', (object) ['id' => 12, 'email' => 'a@b.c', 'name' => 'User']);
        $task->setRelation('task_status', (object) ['name' => ['ru' => 'Ожидается оплата']]);
        $task->setRelation('tasks_fields_currency_in', collect());
        $task->setRelation('tasks_fields_currency_out', collect());
        $task->created_at = now();

        return $task;
    }

    private function field(string $name, string $value, string $key, string $type): TaskField
    {
        $f = new TaskField();
        $f->field_name = $name;
        $f->field_value = $value;
        $f->field_key = $key;
        $f->type_field = $type;
        $f->alias = 'currency';

        return $f;
    }

    private function direction(
        string $payIn,
        string $codeIn,
        string $xmlIn,
        string $payOut,
        string $codeOut,
        string $xmlOut,
        ?string $liveRate,
    ): object {
        $c1 = (object) [
            'payment' => (object) ['name' => $payIn],
            'code_currency' => (object) ['name' => $codeIn],
            'designation_xml' => $xmlIn,
        ];
        $c2 = (object) [
            'payment' => (object) ['name' => $payOut],
            'code_currency' => (object) ['name' => $codeOut],
            'designation_xml' => $xmlOut,
        ];

        return (object) [
            'currency1' => $c1,
            'currency2' => $c2,
            'exchange_rate' => $liveRate,
        ];
    }
}
