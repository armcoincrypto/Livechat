<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Requests;

use iEXPackages\SupportChat\Contracts\SupportChatServiceInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMessage = (int) config('support_chat.message_max_length', 8000);

        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:'.$maxMessage],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'page_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'A valid email address is required.',
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
