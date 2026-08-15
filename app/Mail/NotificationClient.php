<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/***
 * Уведомление для клиента об успешном заверщении заявки
 *
 * @class NotificationClient
*/

class NotificationClient extends Mailable
{
    use Queueable, SerializesModels;

    /***
     * Массив где будет собрана вся необходимая информация
     *
     * @return array
    */
    protected $array = [];

    protected $input = null;

    /**
     * Create a new message instance.
     */
    public function __construct(array $array, $input)
    {
        $this->input = $input;
        $this->array = $array;
        $this->subject = 'По Вашей заявке создан номер чека!';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.notification_client', [
            'input' => $this->input,
            'item' => $this->array,
        ]);
    }
}
