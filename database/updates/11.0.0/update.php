<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Spatie\Permission\Models\Permission;

return function () {
    encryptProxyPasswords();
};

function encryptProxyPasswords(): int
{
    $updated = 0;
    $nulled = 0;
    $skipped = 0;

    DB::table('proxies')
        ->whereNotNull('password')
        ->where('password', '!=', '')
        ->orderBy('id')
        ->chunkById(200, function ($rows) use (&$updated, &$nulled, &$skipped) {
            foreach ($rows as $row) {
                $raw = (string) $row->password;

                // если похоже на encrypted payload — проверим, что он реально decrypt'ится текущим APP_KEY
                if (str_starts_with($raw, '{"iv":') && str_contains($raw, '"mac"')) {
                    try {
                        Crypt::decryptString($raw);
                        $skipped++;
                        continue; // уже ок
                    } catch (DecryptException) {
                        // битый/чужой payload — обнуляем, чтобы система не падала
                        DB::table('proxies')->where('id', $row->id)->update(['password' => null]);
                        $nulled++;
                        continue;
                    }
                }

                // plaintext -> encrypt
                DB::table('proxies')->where('id', $row->id)->update([
                    'password' => Crypt::encryptString($raw),
                ]);

                $updated++;
            }
        });

    return $updated;
}
