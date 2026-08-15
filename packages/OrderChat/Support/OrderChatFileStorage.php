<?php

declare(strict_types=1);

namespace iEXPackages\OrderChat\Support;

use Illuminate\Http\UploadedFile;

final class OrderChatFileStorage
{
    /**
     * Сохранить файл чата в публичную папку.
     *
     * ВАЖНО:
     * - Сейчас у тебя путь: public/images/order_chat
     * - Возвращаем ТОЛЬКО имя файла (как в текущей реализации).
     */
    public function storeOrderChatFile(UploadedFile $file): string
    {
        $directory = public_path('images/order_chat');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = now()->format('YmdHis') . '_' . uniqid('', true) . '.' . $file->getClientOriginalExtension();

        $file->move($directory, $fileName);

        return $fileName;
    }
}
