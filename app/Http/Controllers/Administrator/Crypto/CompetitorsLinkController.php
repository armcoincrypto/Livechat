<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Models\CompetitorLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompetitorsLinkController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $groups = CompetitorLink::orderBy('sorting')->get()->map(function($item) {

            $cacheKey = 'competitor_info_' . md5($item->link);
            $info = cache()->remember($cacheKey, 300, function () use ($item) {
                return [
                    'is_reachable' => self::checkLinkReachable($item->link),
                    'file_type' => self::detectFileType($item->link),
                    'file_size' => self::detectFileSize($item->link),
                ];
            });


            return [

                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'link' => $item->link,
                    'is_reachable' => $info['is_reachable'],
                    'file_type' => $info['file_type'],
                    'file_size' => $info['file_size'],
                    'count_links' => $item->rates->count()
                ]
            ];
        });

        return response()->json([
            'data' => $groups,
            'total' => count($groups)
        ]);
    }

    /**
     * Форма добавления ссылок конкуректов
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        return view('admin.crypto.competitors.links.create');
    }

    /**
     * Обработка и добавление ссылок конкуректов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'link' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        try {
            $check_file = json_encode(simplexml_load_file($request->link));
        } catch (\Exception $exception) {
            return response()->json([
                'status' => 1,
                'message' => 'Файл курсов недоступен для парсинга'
            ]);
        }

        $link = CompetitorLink::create([
            'name' => $request->has('name') ? $request->get('name') : null,
            'link' => $request->has('link') ? $request->get('link') : null,
            'status' => 1,
        ]);

        $links = CompetitorLink::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });


        return response()->json([
            'status' => 0,
            'message' => "{$link->name} успешно добавлен",
            'updated' => $links
        ]);
    }

    /**
     * Форма редактирования ссылок конкуректов
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(int $id)
    {
        $item = CompetitorLink::findOrFail($id);

        return view('admin.crypto.competitors.links.edit', compact('item'));
    }

    /**
     * Обработка и обновления ссылок конкуректов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $link = CompetitorLink::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'link' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        try {
            $check_file = json_encode(simplexml_load_file($request->link));
        } catch (\Exception $exception) {
            return response()->json([
                'status' => 1,
                'message' => 'Файл курсов недоступен для парсинга'
            ]);
        }


        $link->update([
            'name' => $request->has('name') ? $request->get('name') : null,
            'link' => $request->has('link') ? $request->get('link') : null,
            'status' => 0,
        ]);


        $links = CompetitorLink::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });


        return response()->json([
            'status' => 0,
            'message' => "{$link->name} успешно обновлен",
            'updated' => $links
        ]);
    }

    /**
     * Удаление ссылок
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $link = CompetitorLink::findOrFail($id);
        $oldItem = $link;
        if ($link->rates->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Выбранного конкурента удалить невозможно, К нему привязаны курсы'
            ]);
        }

        $link->delete();

        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->name} успешно удален"
        ]);
    }

    /**
     * Проверяет, доступен ли файл по ссылке.
     */
    private static function checkLinkReachable(?string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            $headers = @get_headers($url, 1);
            if (!$headers || !is_array($headers)) {
                return false;
            }

            $statusLine = is_array($headers[0]) ? implode(' ', $headers[0]) : $headers[0];
            return str_contains($statusLine, '200');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Определяет тип файла по URL или заголовкам.
     */
    private static function detectFileType(?string $url): ?string
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

        if ($extension) {
            return strtolower($extension);
        }

        try {
            $headers = @get_headers($url, 1);
            if (isset($headers['Content-Type'])) {
                $type = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
                return explode(';', $type)[0];
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
    /**
     * Определяет размер файла по URL (в байтах).
     */
    private static function detectFileSize(?string $url): ?int
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $headers = @get_headers($url, 1);
            if (isset($headers['Content-Length'])) {
                $size = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];
                return (int) $size;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
