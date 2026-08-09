<?php

namespace iEXPackages\Order;

use App\Models\DirectionExchange;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Order
{
    use Bindings\ManagerArray,
        Bindings\ManagerDetails,
        Bindings\ManagerOrder;

    /**
     * ID Пользователя
     */
    public int $authId = 0;

    /**
     * Информация о пользователе
     */
    public ?User $authInfo = null;

    /**
     * Id направления
     */
    protected DirectionExchange $directionId;

    /**
     * Дополнительные параметры
     */
    protected Collection $options;

    /**
     * Request
     */
    protected Request $request;

    /**
     * Информация о заявке
     *
     * @var Task
     */
    protected $order_data;

    /**
     * Validator входных параметров
     *
     * @var \Illuminate\Contracts\Validation\Validator|\Illuminate\Contracts\Validation\Factory
     */
    protected $validator;


    /**
     * Обработчик
     *
     * @return Order
     *
     * @throws \Exception
     */
    public function request(Request $request): static
    {
        // Resolve into a local first — typed non-nullable $directionId cannot accept null
        // from ->first(), which previously caused an uncaught TypeError → HTTP 500.
        $direction = DirectionExchange::where([
            ['status', 1],
            ['id_currency1', intval($request->get('income_payment_system'))],
            ['id_currency2', intval($request->get('outcome_payment_system'))],
        ])->first();

        if ($direction === null) {
            Log::warning('Order direction not found', [
                'income_payment_system' => intval($request->get('income_payment_system')),
                'outcome_payment_system' => intval($request->get('outcome_payment_system')),
            ]);
            throw new \Exception(__('Направление не найдено'));
        }

        $this->directionId = $direction;
        $this->options = collect($request->all());
        $this->validator = \validator($request->all());

        return $this;
    }
}
