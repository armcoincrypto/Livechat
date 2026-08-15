<?php

declare(strict_types=1);

namespace iEXPackages\TagProcessors\Processors;

use iEXPackages\TagProcessors\Contracts\DescribableTagProcessorInterface;
use iEXPackages\TagProcessors\Support\TagDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Системные теги: [site_name], [default_email_from], [server_name] и т.п.
 */
class SystemTagProcessor extends AbstractTagProcessor implements DescribableTagProcessorInterface
{
    /**
     * Описание всех тегов этого процессора.
     *
     * @return TagDefinition[]
     */
    public function definitions(): array
    {
        return [
            new TagDefinition(
                key: '[site_name]',
                name: 'Название сайта',
                description: 'Выводит название проекта из конфигурации (config("app.name")).',
                example: config('app.name'),
                group: 'system',
                resolver: fn() => config('app.name'),
            ),

            new TagDefinition(
                key: '[default_email_from]',
                name: 'Email отправителя по умолчанию',
                description: 'Стандартный адрес отправителя писем (config("mail.from.address")).',
                example: config('mail.from.address'),
                group: 'system',
                resolver: fn() => config('mail.from.address'),
            ),

            new TagDefinition(
                key: '[server_name]',
                name: 'Имя сервера',
                description: 'Текущее имя сервера (SERVER_NAME) из HTTP-запроса.',
                example: 'example.com',
                group: 'system',
                resolver: function ($data, ?Request $request) {
                    $req = $request ?? request();

                    return (string) $req->server('SERVER_NAME', '');
                },
            ),

            new TagDefinition(
                key: '[locale]',
                name: 'Текущая локаль',
                description: 'Текущий код языка интерфейса.',
                example: 'ru',
                group: 'system',
                resolver: fn() => app()->getLocale(),
            ),

            new TagDefinition(
                key: '[now]',
                name: 'Текущее дата и время',
                description: 'Текущая дата и время в часовом поясе приложения.',
                example: Carbon::now()->toDateTimeString(),
                group: 'system',
                resolver: fn() => Carbon::now()->toDateTimeString(),
            ),

            new TagDefinition(
                key: '[today_date]',
                name: 'Текущая дата',
                description: 'Дата на момент формирования шаблона.',
                example: Carbon::now()->toDateString(),
                group: 'system',
                resolver: fn() => Carbon::now()->toDateString(),
            ),

            // сюда легко добавлять новые системные теги
        ];
    }

    /**
     * Реализация абстрактного метода.
     *
     * Собираем map [tag => resolver] из TagDefinition.
     */
    protected function tagHandlers(): array
    {
        $handlers = [];

        foreach ($this->definitions() as $definition) {
            if ($definition->resolver instanceof \Closure) {
                $handlers[$definition->key] = $definition->resolver;
            }
        }

        return $handlers;
    }
}
