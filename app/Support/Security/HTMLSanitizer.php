<?php

declare(strict_types=1);

namespace App\Support\Security;

use DOMDocument;
use DOMElement;

final class HTMLSanitizer
{
    /**
     * Разрешённые HTML-теги.
     */
    private static array $allowedTags = [
        'h1','h2','h3','h4','h5','h6',
        'p','br','span',
        'b','strong','i','em','u',
        'ul','ol','li',
        'img','a',
    ];

    /**
     * Разрешённые атрибуты.
     */
    private static array $allowedAttributes = [
        'src','href','title','alt',
        'target','rel','class','style',
    ];

    /**
     * Разрешённые схемы ссылок.
     */
    private static array $allowedSchemes = [
        'http','https','mailto','tel','tg'
    ];

    /**
     * Главная функция очистки HTML.
     */
    public static function clean(string|null $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        self::sanitizeNode($doc->documentElement);

        $clean = $doc->saveHTML();

        libxml_clear_errors();
        return $clean ?: '';
    }

    /**
     * Рекурсивная фильтрация нодов.
     */
    private static function sanitizeNode(\DOMNode $node): void
    {
        if ($node instanceof DOMElement) {

            // Удаляем запрещённые теги, но оставляем текст внутри
            if (!in_array($node->tagName, self::$allowedTags, true)) {
                self::unwrap($node);
                return;
            }

            // Чистим атрибуты
            if ($node->hasAttributes()) {
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $name  = strtolower($attr->name);
                    $value = $attr->value;

                    // Удаляем события: onclick, onload и т.п.
                    if (str_starts_with($name, 'on')) {
                        $node->removeAttribute($name);
                        continue;
                    }

                    // Проверка схемы URL
                    if (in_array($name, ['href','src'], true) && !self::validUrl($value)) {
                        $node->removeAttribute($name);
                        continue;
                    }

                    // Атрибут не разрешён — удалить
                    if (!in_array($name, self::$allowedAttributes, true)) {
                        $node->removeAttribute($name);
                    }
                }
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::sanitizeNode($child);
        }
    }

    /**
     * Убираем тег, но оставляем текст внутри.
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent) return;

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    /**
     * Проверка ссылки — запрещаем javascript:, data:, vbscript:
     */
    private static function validUrl(string $value): bool
    {
        $value = trim($value);

        // Относительные пути разрешены
        if (!str_contains($value, ':')) {
            return true;
        }

        $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
        return in_array($scheme, self::$allowedSchemes, true);
    }
}
