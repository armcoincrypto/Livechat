<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetSupportMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $defaultLimit = (int) config('support_chat.poll.default_limit', 50);

        $this->merge([
            'after_id' => $this->input('after_id', 0),
            'limit' => $this->input('limit', $defaultLimit),
        ]);
    }

    public function rules(): array
    {
        $maxLimit = (int) config('support_chat.poll.max_limit', 100);

        return [
            'after_id' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }
}
