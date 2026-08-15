<?php

namespace iEXPackages\ExchangerClient\Http\Resources\Orders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'data';

    /**
     * Название статусов в тест формате
     */
    protected array $statusText = [
        2 => 'pending',
        9 => 'merchant',
        3 => 'process',
        7 => 'pay',
        4 => 'success',
        8 => 'frozen',
    ];

    public function toArray(Request $request): array
    {
        $public_id = $this->public_id;
        $is_paid = in_array($this->status, [4, 7]);

        $response = [
            'id' => iEXSetting('client_id_type_for_order') == 1 ? $public_id : $this->id,
            'order_id' => (string) $this->id,
            'type' => 'order_status',
            'attributes' => [
                'public_id' => (string) $this->public_id,
                'created_at' => $this->created_at,
                'is_paid' => $is_paid,
                'is_handler' => $this->status == 3,
                'status' => $this->statusText[$this->status] ?? 'cancel',
                'is_request_payment_type' => $this->is_request_payment_type,
            ],
        ];

        if ($this->status == 2 and ! empty($this->requisites_receive)) {
            $response['attributes']['custom_payment_field_value'] = $this->requisites_receive;

            // Получаем доп. поля реквизитов по запросу
            if(isset($this->task_requisite_attached) and !empty($this->task_requisite_attached))
            {
                if(isset($this->task_requisite_attached->ext_params['description']) and !empty($this->task_requisite_attached->ext_params['description'])) {
                    $response['attributes']['payment_field']['attach_description'] = $this->task_requisite_attached->ext_params['description'];
                }

                if(isset($this->task_requisite_attached->ext_params['fields'])) {
                    $response['attributes']['payment_field']['attach_fields'] = $this->task_requisite_attached->ext_params['fields'];
                }
            }
        }

        return $response;
    }
}
