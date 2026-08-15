<?php

namespace App\Http\Controllers\Administrator\Content;

use App\Http\Controllers\Controller;
use App\Models\Advantage;
use App\Settings\AdvantageConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdvantageController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected array $allowFilteredPage = [
        'is_advantage_style',
        'advantage_row_height',
        'advantage_col',
        'advantage_gutter_size'
    ];


    /**
     * Список преимуществ
     */
    public function index(
        AdvantageConfig $settings,
        Request $request
    )
    {
        if($request->has('showPage') == 'settings') {
            return response()->json([
                'is_advantage_style'    => (int) $settings->isStyleEnabled(),
                'advantage_row_height' => $settings->rowHeight() ?? '2:1',
                'advantage_col'        => $settings->columns(),
                'advantage_gutter_size'=> $settings->gutterSize() ?? '10px',
            ]);
        }

        $advantages = Advantage::orderBy('sorting')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->title,
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'updated_at' => $item->updated_at->translatedFormat('d M Y H:i'),
                    'updated_at_human' => $item->updated_at->diffForHumans(),
                ]
            ];
        });

        return response()->json([
            'data' => $advantages,
            'total' => count($advantages)
        ]);
    }

    public function store(
        Request $request,
        AdvantageConfig $settings
    ) {

        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                // Обновление конфига
                $update = [];
                foreach ($this->allowFilteredPage as $item) {
                    if ($request->has($item) && is_array($request->get($item))) {
                        $update[$item] = implode(',', $request->get($item));
                    } else {
                        $update[$item] = $request->has($item) ? $request->get($item) : null;
                    }
                }
                $settings->update($update);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

            if ($request->hasFile('icon')) {
                $logo_icon = $request->file('icon');
                $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

                if (! \File::isDirectory(public_path('storage/advantage/'))) {
                    \File::makeDirectory(public_path('storage/advantage/'), 0777, true, true);
                }

                $destinationPath = public_path('/storage/advantage');
                $logo_icon->move($destinationPath, $filename);
            }

            $options = [
                'link' => ($request->has('link') ? $request->get('link') : null),
                'is_target' => (int)($request->has('is_target') ? $request->get('is_target') : 0),
                'status' => (int)($request->has('status') ? $request->get('status') : 0),
                'icon' => $filename ?? '',
                'id_user' => auth()->id(),
              'rowspan' => min(5, (int)($request->has('rowspan') ? $request->get('rowspan') : 0)),
                'colspan' => min(5, (int)($request->has('colspan') ? $request->get('colspan') : 0)),
                'title' => $request->title,
                'content' => $request->text
            ];

            Advantage::create($options);

            return response()->json([
                'status' => 0,
                'message' => 'Новое преимущество успешно добавлено'
            ]);

    }

    /**
     * Изменить преимущество
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $item = Advantage::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'title' => $item->title,
                'text' => $item->content,
                'link' => $item->link,
                'icon' => $item->icon,
                'icon_path' => '/storage/advantage/'. $item->icon,
                'status' => (bool)$item->status,
                'is_target' => (bool)$item->is_target,
                'rowspan' => (int)$item->rowspan,
                'colspan' => (int)$item->colspan,
            ]
        ]);
    }

    /**
     * Обновление преимущества
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title.'.config('iexexchanger.default_locale') => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $advantage = Advantage::findOrFail($id);
        $filename = $advantage->icon;


        if ($request->hasFile('icon')) {
            // Удаляем актуальное фото
            iex_file_delete(public_path('storage/advantage/'.$filename));

            $logo_icon = $request->file('icon');
            $filename = sprintf('%s.%s', Str::random(10), $logo_icon->getClientOriginalExtension());

            $destinationPath = public_path('/storage/advantage');
            $logo_icon->move($destinationPath, $filename);
        }

        $options = [
            'link' => ($request->has('link') ? $request->get('link') : null),
            'is_target' => (int)($request->has('is_target') ? $request->get('is_target') : 0),
            'status' => (int)($request->has('status') ? $request->get('status') : 0),
            'icon' => $filename ?? '',
            'id_user' => auth()->id(),
            'rowspan' => min(5, (int)($request->has('rowspan') ? $request->get('rowspan') : 0)),
            'colspan' => min(5, (int)($request->has('colspan') ? $request->get('colspan') : 0)),
            'title' => $request->title,
            'content' => $request->text
        ];


        $advantage->update($options);

        return response()->json([
            'status' => 0,
            'message' => $advantage->title . ' успешно обновлен'
        ]);
    }

    /**
     * Удалить преимущество
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $item = Advantage::findOrFail($id);
        $oldItem = $item;
        iex_file_delete(public_path('storage/advantage/'.$item->icon));
        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->title . ' успешно удален'
        ]);
    }
}
