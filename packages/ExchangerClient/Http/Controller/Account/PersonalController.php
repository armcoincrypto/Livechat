<?php
namespace iEXPackages\ExchangerClient\Http\Controller\Account;

use App\Http\Controllers\AbstractController;
use App\Models\CodeCurrency;
use iEXPackages\ExchangerClient\Http\Resources\Account\UserResource;
use iEXPackages\ExchangerClient\Http\Resources\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Session;

class PersonalController extends AbstractController
{
    /**
     * Информация о пользователе
     *
     * @param Request $request
     * @return UserResource
     */
    public function index(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Обновляем API Key
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateApiKey(Request $request): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
            ], 422);
        }

        $user = $request->user();

        if ($user->is_enabled_restapi == 1) {
            // $user->tokens()->delete();
            $user->update([
                'restapi_key' => $user->createToken('user-api-'.$user->id)->plainTextToken,
            ]);

            return response()->json([
                'status' => 0,
                'token' => $user->restapi_key
            ]);
        } else {
            return response()->json([
                'status' => 1,
                'token' => 'error api'
            ], 403);
        }
    }

    /**
     * Обновление личных данных пользователя
     */
    public function update(Request $request): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => __('В данный момент изменения запрещены.'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|regex:/[A-Za-zА-Яа-яЁё -]$/u',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = $request->user();
        $user->update([
            'name' => security_xss($request->input('name')),
            'phone' => $request->filled('phone') ? security_xss($request->input('phone')) : null,
            'telegram' => $request->filled('telegram') ? security_xss($request->input('telegram')) : null,
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Данные успешно изменены'),
            'user' => new SessionResource($user),
        ]);
    }

    /**
     * Обновляем пароль
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => 'error',
            ], 422);
        }

        $user = $request->user();
        $placeholder = !empty($user->oauth_password_is_placeholder);

        $validate = Validator::make($request->all(), [
            // OAuth-only accounts never learned the random placeholder hash.
            'current_password' => $placeholder ? 'nullable' : 'required',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => __('Пароли не совпадают'),
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validate->errors()->first(),
            ], 422);
        }

        if (!$placeholder && ! Hash::check($request->get('current_password'), $user->password)) {
            return response()->json([
                'status' => 1,
                'message' => __('Неверный старый пароль'),
            ], 422);
        }

        $user->password = bcrypt($request->get('password'));
        if ($placeholder) {
            $user->oauth_password_is_placeholder = false;
        }
        $user->save();

        // Keep the current device signed in. AuthenticateSession compares the
        // session password_hash_* to the DB hash; without this refresh the next
        // account click logs the user out and strands them on login?returnTo=.
        if ($request->hasSession()) {
            $request->session()->put(
                'password_hash_'.Auth::getDefaultDriver(),
                $user->getAuthPassword()
            );
        }

        return response()->json([
            'status' => 0,
            'message' => __('Данные успешно изменены')
        ]);
    }

    /**
     * Внутренний баланс
     *
     * @return JsonResponse
     */
    public function internalBalance(): JsonResponse
    {
        if ((int) iEXSetting('enable_internal_account', 0) === 1) {
            $codes = CodeCurrency::with('internal_account')->get();

            return response()->json($codes->map(fn($code) => [
                'balance' => $code->internal_account->balance ?? 0,
                'name' => $code->name,
            ]));
        }

        return response()->json([]);
    }
}
