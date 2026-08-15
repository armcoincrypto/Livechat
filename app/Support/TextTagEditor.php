<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @deprecated
*/
class TextTagEditor
{
    /**
     * Модуль Request
     *
     * @var mixed
     */
    protected $request;

    /**
     * Опции для обработки тегов
     */
    protected array $options;

    /**
     * Конструктор
     */
    public function __construct(mixed $request = null)
    {
        $this->request = $request ?? null;
    }

    /**
     * Опции для обработки текста
     *
     * @param $driver
     * @param string $text
     * @param array|object $item
     * @return TextTagEditor
     */
    public function setOptions($driver, string $text, array|object $item = []): static
    {
        if ($driver == 'order') {
            $search = [
                '[order_id]',
                '[created_at]',
                '[updated_at]',
                '[ip_address]',
                '[email]',
                '[income_amount]',
                '[outcome_amount]',
                '[income_currency]',
                '[outcome_currency]',
                '[course]',
                '[unique_code]',
                '[check_url]',
                '[app_name]',
                '[public_id]',
            ];
            $change_by = [
                $item['id'],
                $item['created_at'],
                $item['updated_at'],
                $item['ip'],
                $item['email'],
                $item['give_price'],
                $item['receiving_price'],
                $item->direction_exchange->currency1->payment->name.' '.$item->direction_exchange->currency1->code_currency->name,
                $item->direction_exchange->currency2->payment->name.' '.$item->direction_exchange->currency2->code_currency->name,
                $item['course_display'],
                $item['unique_security_code'],
                url('/order/'.$item->public_id),
                iEXContentLanguage('sitename'),
                $item->public_id,
            ];
        } else {
            $search = [
                '[site_name]',
                '[default_email_from]',
                '[server_name]',
            ];

            $change_by = [
                '[site_name]',
                '[default_email_from]',
                '[server_name]',
            ];
        }

        $this->options['text'] = str_replace($search, $change_by, $text);

        return $this;
    }

    /**
     * Получаем текст обработанный
     */
    public function getText(): ?string
    {
        return $this->options['text'] ?? null;
    }
}
