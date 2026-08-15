<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Others\APILogsResources;
use App\Http\Resources\Admin\Others\APIResources;
use App\Models\ApiLog;
use App\Models\BestchangeParserError;
use App\Models\User;
use iEXPackages\ExchangerApi\Models\PersonalAccessToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class APIController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $search = trim((string) $request->input('search', ''));

        $query = User::query()
            ->whereHas('tokens')
            ->withCount('tokens')
            ->with([
                'tokens' => function ($q) {
                    // Загружаем последние 5 токенов по id (самый новый будет первым)
                    $q->orderByDesc('id')->limit(5);
                },
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'items' => new APIResources($users),
        ]);
    }

    /**
     * Обработать и добавить код валюты
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function update(int $id, Request $request)
    {
        $user = User::findOrFail($id);
        $user->tokens->find((int)$request->id_key)->update([
            'abilities' => $request->abilities ?? []
        ]);

        return response()->json($request->all());
    }

    /**
     * Удаление токена
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        PersonalAccessToken::find($id)->delete();

        return response()->json([
            'status' => 0,
            'message' => 'Токен удален'
        ]);
    }

    /**
     * Лог API
     */
    public function logs(Request $request): JsonResponse
    {
        // Очищаем историю курсов / логов
        if ($request->boolean('clear_log')) {
            if (config('iexexchanger.is_reading_mode')) {
                return response()->json([
                    'status'  => 1,
                    'message' => 'В demo версии данная функция недоступна',
                ]);
            }

            ApiLog::query()->delete();

            return response()->json([
                'status'  => 0,
                'message' => 'Лог успешно очищен',
            ]);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = ApiLog::query()->orderByDesc('id');

        // Фильтр по token_id (логи по конкретному API-ключу)
        if ($request->filled('token_id')) {
            $query->where('token_id', $request->integer('token_id'));
        }

        // Фильтр по статусу (например: 2xx, 4xx, 5xx)
        if ($request->filled('status_group')) {
            $statusGroup = (string) $request->input('status_group');
            if ($statusGroup === '2xx') {
                $query->whereBetween('status_code', [200, 299]);
            } elseif ($statusGroup === '4xx') {
                $query->whereBetween('status_code', [400, 499]);
            } elseif ($statusGroup === '5xx') {
                $query->whereBetween('status_code', [500, 599]);
            }
        }

        // Фильтр по периоду (дата/время создания лога)
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'items' => new APILogsResources($logs),
        ]);
    }
}
