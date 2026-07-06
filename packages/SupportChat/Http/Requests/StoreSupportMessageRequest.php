<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Requests;

use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMessage = (int) config('support_chat.message_max_length', 8000);

        return [
            'message' => ['required', 'string', 'max:'.$maxMessage],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(SupportChatServiceInterface::class);
            $maxLength = (int) config('support_chat.message_max_length', 8000);
            $rawMessage = (string) $this->input('message', '');

            if ($service->sanitizeMessageBody($rawMessage, $maxLength) === '') {
                $validator->errors()->add(
                    'message',
                    'The message cannot be empty.'
                );
            }
        });
    }
}
