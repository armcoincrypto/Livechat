<?php

namespace App\Exports;

use App\Models\Task;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrderUserExport implements FromCollection, WithBatchInserts, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    protected $id_user;

    protected $is_limit = false;

    public function __construct($id_user, $is_limit = false)
    {

        $this->id_user = $id_user;
        $this->is_limit = $is_limit;
    }

    public function collection()
    {
        $response = Task::with('direction_exchange')->select(
            ['id', 'id_direction_exchange', 'give_price', 'receiving_price', 'email', 'ip', 'from_shot', 'to_shot', 'course_display']
        )->where([
            ['id_user', '=', $this->id_user],
            ['status', '=', 4],
        ]);

        return $this->is_limit ? $response->limit((int) iEXSetting('orderarchive_count_order'))->get() : $response->get();
    }

    /**
     * Отправляем заголовки колонок
     */
    public function headings(): array
    {
        return [
            'Направление',
            'Отдаю',
            'Получаю',
            'Курс обмена',
            'Счет отдаю',
            'Счет получаю',
            'Email',
            'IP Адрес',
        ];
    }

    /**
     * @param  mixed  $row
     */
    public function map($row): array
    {
        return [
            [
                direction_name($row->direction_exchange),
                $row->give_price,
                $row->receiving_price,
                $row->course_display,
                $row->from_shot,
                $row->to_shot,
                $row->email,
                $row->ip,
            ],
        ];
    }

    public function batchSize(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 2;
    }
}
