<?php

namespace iEXPackages\TagProcessors\Processors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConditionalTagsProcessor extends AbstractTagProcessor
{
    /**
     * Массив условных тегов и их обработчиков.
     *
     * @return array<string, callable>
     */
    protected function tagHandlers(): array
    {
        return [
            'is_admin'        => fn($data, $request) => Auth::user()?->is_admin ?? false,
            'is_user_banned' => fn($data) => (bool)($data['is_banned'] ?? false),
            'is_auth'        => fn() => Auth::check(),
            'is_guest'       => fn() => !Auth::check(),
            // добавляйте здесь новые условия по необходимости
        ];
    }

    /**
     * Обработка условных блоков в тексте.
     *
     * @param string $text
     * @param array|object $data
     * @param Request|null $request
     *
     * @return string
     */
    public function processConditionals(string $text, mixed $data = [], ?Request $request = null): string
    {
        foreach ($this->tagHandlers() as $condition => $callback) {
            $result = (bool) $callback($data, $request);

            $pattern = "/\[if:{$condition}\](.*?)\[\/if:{$condition}\]/s";

            $text = preg_replace_callback($pattern, function ($matches) use ($result) {
                if (!$result) {
                    return '';
                }

                // Удаление всех HTML-тегов из текста
                return strip_tags($matches[1]);
            }, $text);
        }

        return $text;
    }
}
