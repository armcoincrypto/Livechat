<?php

namespace App\Exports;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Класс экспорта заявок в Excel-файл с возможностью выбора полей.
 */
class OrdersExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    /**
     * @var string|null Начальная дата создания
     */
    protected ?string $createdFrom;

    /**
     * @var string|null Конечная дата создания
     */
    protected ?string $createdTo;

    /**
     * @var string|null Начальная дата обновления
     */
    protected ?string $updatedFrom;

    /**
     * @var string|null Конечная дата обновления
     */
    protected ?string $updatedTo;

    /**
     * @var array Массив ID статусов для фильтрации
     */
    protected array $statuses;

    /**
     * @var array Массив выбранных полей для экспорта
     */
    protected array $fields;

    /**
     * Конструктор класса.
     *
     * @param string|null $createdFrom
     * @param string|null $createdTo
     * @param string|null $updatedFrom
     * @param string|null $updatedTo
     * @param array $statuses
     * @param array $fields
     */
    public function __construct(
        ?string $createdFrom,
        ?string $createdTo,
        ?string $updatedFrom,
        ?string $updatedTo,
        array $statuses = [],
        array $fields = []
    ) {
        $this->createdFrom = $createdFrom;
        $this->createdTo = $createdTo;
        $this->updatedFrom = $updatedFrom;
        $this->updatedTo = $updatedTo;
        $this->statuses = $statuses;
        $this->fields = $fields;
    }

    /**
     * Возвращает коллекцию заявок с применением фильтров и предварительной загрузкой отношений.
     *
     * @return Collection
     */
    public function query()
    {
        return Task::query()
            ->with([
                'direction_exchange.currency1.payment',
                'direction_exchange.currency1.code_currency',
                'direction_exchange.currency2.payment',
                'direction_exchange.currency2.code_currency',
                'task_status',
                'task_info',
                'completedByUser',
            ])
            ->when($this->createdFrom, fn($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->createdFrom)))
            ->when($this->createdTo, fn($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->createdTo)))
            ->when($this->updatedFrom, fn($q) => $q->whereDate('updated_at', '>=', Carbon::parse($this->updatedFrom)))
            ->when($this->updatedTo, fn($q) => $q->whereDate('updated_at', '<=', Carbon::parse($this->updatedTo)))
            ->when(!empty($this->statuses), fn($q) => $q->whereIn('status', $this->statuses));
    }

    /**
     * Возвращает заголовки для таблицы Excel в зависимости от выбранных полей.
     *
     * @return array
     */
    public function headings(): array
    {
        $allHeadings = $this->availableFields();

        return array_values(array_intersect_key($allHeadings, array_flip($this->fields)));
    }

    /**
     * Формирует строки данных для экспорта в зависимости от выбранных полей.
     *
     * @param Task $task
     * @return array
     */
    public function map($task): array
    {
        $direction = $task->direction_exchange;

        $data = [
            'id' => $task->id,
            'public_id' => $task->public_id,
            'user_id' => $task->id_user,
            'email' => $task->email,
            'ip' => $task->ip,
            'give_price' => $task->give_price,
            'receiving_price' => $task->receiving_price,
            'status' => $task->task_status?->name ?? '–',
            'created_at' => $task->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $task->updated_at->format('Y-m-d H:i:s'),
            'tech_name' => $direction?->tech_name ?? '–',
            'give_currency_name' => $direction?->currency1?->payment?->name
                ?? $direction?->currency1?->code_currency?->name
                    ?? '–',
            'give_currency_code' => $direction?->currency1?->code ?? '–',
            'receive_currency_name' => $direction?->currency2?->payment?->name
                ?? $direction?->currency2?->code_currency?->name
                    ?? '–',
            'receive_currency_code' => $direction?->currency2?->code ?? '–',
            'give_price_default' => $task->give_price_default,
            'give_price_with_comm' => $task->give_price_with_comm,
            'give_price_with_comm_pay' => $task->give_price_with_comm_pay,
            'receiving_price_default' => $task->receiving_price_default,
            'receiving_price_with_comm' => $task->receiving_price_with_comm,
            'receiving_price_with_comm_pay' => $task->receiving_price_with_comm_pay,
            'city_name' => $task->task_info?->city_name ?? '–',
            'country_name' => $task->task_info?->country_name ?? '–',
            'completed_by_id' => $task->id_who_completed,
            'completed_by_email' => $task->completedByUser?->email ?? '–',
        ];

        return array_values(array_intersect_key($data, array_flip($this->fields)));
    }


    /**
     * Список доступных полей для экспорта.
     *
     * @return array
     */
    protected function availableFields(): array
    {
        return [
            'id' => 'ID заявки',
            'public_id' => 'Публичный ID заявки',
            'user_id' => 'Пользователь (ID)',
            'email' => 'E-mail',
            'ip' => 'IP адрес',
            'give_price' => 'Сумма отдаю',
            'receiving_price' => 'Сумма получаю',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата обновления',
            'tech_name' => 'Направление обмена',
            'give_currency_name' => 'Отдаю (валюта)',
            'give_currency_code' => 'Отдаю (код валюты)',
            'receive_currency_name' => 'Получаю (валюта)',
            'receive_currency_code' => 'Получаю (код валюты)',
            'give_price_default' => 'Отдаю (базовая цена)',
            'give_price_with_comm' => 'Отдаю (с доп. комиссией)',
            'give_price_with_comm_pay' => 'Отдаю (с комиссией доп. и ПС)',
            'receiving_price_default' => 'Получаю (базовая цена)',
            'receiving_price_with_comm' => 'Получаю (с доп. комиссией)',
            'receiving_price_with_comm_pay' => 'Получаю (с комиссией доп. и ПС)',
            'city_name' => 'Город',
            'country_name' => 'Страна',
            'completed_by_id' => 'ID менеджера, завершившего заявку',
            'completed_by_email' => 'E-mail менеджера, завершившего заявку',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
