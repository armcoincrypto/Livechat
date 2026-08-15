<?php

declare(strict_types=1);

namespace iEXPackages\SupportChat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMb = max(
            (int) config('support_chat.attachments.max_image_mb', 5),
            (int) config('support_chat.attachments.max_video_mb', 25),
            (int) config('support_chat.attachments.max_pdf_mb', 10),
        );
        $maxKb = max(1, $maxMb * 1024);

        return [
            'file' => ['required', 'file', 'max:'.$maxKb],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
