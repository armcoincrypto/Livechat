<?php

namespace App\Support\DynamicConfig\Encoders;

class EncryptedJsonEncoder
{
    public static function encode(mixed $value): string
    {
        return encrypt(json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public static function decode(string $payload): mixed
    {
        return json_decode(decrypt($payload), true);
    }
}
