<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FALaravel\Google2FA;

class GoogleAuthenticatorController
{
    /**
     * Форма регистрации Google Authenticator и активация
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(int $id)
    {
        $user = User::find($id);

        if(empty($user->google2fa_secret)) {
            $google2fa = app('pragmarx.google2fa');
            $generateKey = $google2fa->generateSecretKey();

            $QR_Image = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $generateKey
            );
        }

        return response()->json([
            'id' => $user->id,
            'attributes' => [
                'is_google2fa_secret' => (int)!empty($user->google2fa_secret),
                'secret' => ($generateKey ?? null),
                'qr_image' => $QR_Image ?? null,
            ]
        ]);
    }

    /**
     * Установка Google Authenticator
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws IncompatibleWithGoogleAuthenticatorException
     * @throws InvalidCharactersException
     * @throws SecretKeyTooShortException
     */
    public function update(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $password = $request->google_code;
        $secret_key = $request->secret_key;
        $window = 8;

        $google2fa = app(Google2FA::class);
        $valid = $google2fa->verifyKey($secret_key, $password, $window);

        if($valid == 1) {
            $user = User::find($id);
            $user->update([
                'google2fa_secret' => $secret_key
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Google Authenticator активирован'
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Ключ Google Authenticator неверный'
        ]);
    }

    /**
     * Сбрасываем ключ
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(int $id): \Illuminate\Http\JsonResponse
    {
        $user = User::find($id);
        if(!empty($user->google2fa_secret)) {
            $user->update([
                'google2fa_secret' => ''
            ]);
        }


        return response()->json([
            'status' => 0,
            'message' => 'Google Authenticator сброшен'
        ]);
    }
}
