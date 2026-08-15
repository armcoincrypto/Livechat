<?php

namespace App\Http\Controllers\Administrator\Marketing\Contests;

use App\Http\Resources\Admin\Tools\ContestsResources;
use App\Models\CodeCurrency;
use App\Models\ContestModel;
use App\Models\ContestsUser;
use App\Models\LinksReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContestsController extends Controller
{
    /**
     * Дополнительные фильтры
     *
     * @var array
     */
    protected array $allowFilteredPage = [
        'is_view_contests_home',
        'is_view_contests_account',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        if($request->has('showPage') and $request->showPage == 'settings')
        {
            return response()->json([
                'is_view_contests_home' => (bool)iEXSetting('is_view_contests_home'),
                'is_view_contests_account' => (bool)iEXSetting('is_view_contests_account'),
            ]);
        }

        $contests = ContestModel::with(['contests_waiting_user', 'contests_has_user'])->orderBy('status')->paginate(20);


        return response()->json([
            'items' => new ContestsResources($contests)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Если включена возможность обновления данных
        if($request->is_update == 1 and $request->has('showPage'))
        {
            // Обновление колонок
            if($request->showPage == 'settings')
            {
                // Обновление конфига
                $array = [];
                foreach ($this->allowFilteredPage as $item) {
                    if (isset($request->{$item}) and is_array($request->{$item})) {
                        $array[$item] = implode(',', $request->{$item});
                    } else {
                        $array[$item] = ($request->{$item} ?? '');
                    }
                }
                iEXSetting($array);
            }

            return response()->json([
                'status' => 0,
                'message' => __('Настройки успешно сохранены')
            ]);
        }


        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $contest = ContestModel::create([
            'id_manager' => $request->user()->id,
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Конкурс успешно добавлен',
            'itemId' => $contest->id
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return JsonResponse
     */
    public function edit(int $id)
    {
        $codes = CodeCurrency::active()->get();
        $item = ContestModel::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'status' => (bool)$item->status,
                'bank' => $item->bank,
                'percent' => $item->percent,
                'code_name' => $item->code_name,
                'code_sign' => $item->code_sign,
                'is_manual_bank' => (bool)$item->is_manual_bank,
                'id_code_currency' => $item->id_code_currency,
                'icon_url_home' => $item->icon_url_home,
                'title' => $item->title,
                'subtitle' => $item->subtitle,
                'title_color' => $item->title_color,
                'button_name' => $item->button_name,
                'subtitle_color' => $item->subtitle_color,
                'icon_url_home_full' => '/storage/contests/'. $item->icon_url_home,
                'icon_url_account' => $item->icon_url_account,
                'icon_url_account_full' => '/storage/contests/'. $item->icon_url_account,
                'contests_has_user' => $item->contests_has_user ?? [],
                'duration' => Carbon::parse($item->duration)->format('Y-m-d H:i')
            ],


            'codes' => $codes->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name
                ];
            })->toArray()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'id_code_currency' => 'required',
            'title.'.config('iexexchanger.default_locale') => 'required',
            'subtitle.'.config('iexexchanger.default_locale') => 'required',
            'duration' => ['required', 'date']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
            $contests = ContestModel::findOrFail($id);

            $destinationPath = public_path('/storage/contests');

            $filename_home = $contests->icon_url_home;
            $filename_account = $contests->icon_url_account;
            if ($request->hasFile('icon_url_home')) {
                $icon_url_home = $request->file('icon_url_home');
                $filename_home = sprintf('%s.%s', Str::random(10), $icon_url_home->getClientOriginalExtension());
                if (! \File::isDirectory(public_path('storage/contests/'))) {
                    \File::makeDirectory(public_path('storage/contests/'), 0777, true, true);
                }
                $icon_url_home->move($destinationPath, $filename_home);
            }

            if ($request->hasFile('icon_url_account')) {
                $icon_url_account = $request->file('icon_url_account');
                $filename_account = sprintf('%s.%s', Str::random(10), $icon_url_account->getClientOriginalExtension());
                if (! \File::isDirectory(public_path('storage/contests/'))) {
                    \File::makeDirectory(public_path('storage/contests/'), 0777, true, true);
                }
                $icon_url_account->move($destinationPath, $filename_account);
            }

            $options = [
                'name' => $request->name,
                'status' => $request->has('status') ? (int)$request->get('status') : 0,
                'duration' => Carbon::parse($request->duration)->timezone('EUROPE/MOSCOW')->format('c'),
                'is_manual_bank' => $request->has('is_manual_bank') ? (int)$request->get('is_manual_bank') : 0,
                'bank' => ($request->has('bank') ? (float)$request->get('bank') : 0),
                'bank_base' => ($request->has('bank') ? (float)$request->get('bank') : 0),
                'id_code_currency' => ($request->has('id_code_currency') ? (int)$request->get('id_code_currency') : 0),
                'code_name' => ($request->has('code_name') ? $request->get('code_name') : null),
                'code_sign' => ($request->has('code_sign') ? $request->get('code_sign') : null),
                'percent' => ($request->has('percent') ? (float)$request->get('percent') : 0),

                'title_color' => ($request->has('title_color') ? $request->get('title_color') : null),
                'subtitle_color' => ($request->has('subtitle_color') ? $request->get('subtitle_color') : null),
                'icon_url_home' => $filename_home,
                'icon_url_account' => $filename_account,
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'button_name' => $request->button_name,
                'info_title' => $request->info_title,
                'info_text' => $request->info_text
            ];

            $contests->update($options);

            return response()->json([
                'status' => 0,
                'message' => $contests->name.' успешно обновлен'
            ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return JsonResponse
     */
    public function destroy(int $id)
    {
        ContestModel::findOrFail($id)->update([
            'status' => 2,
        ]);
        \Cache::forget('actual-lottery-module');

        return response()->json([
            'status' => 0,
            'message' => 'Конкурс закрыт'
        ]);
    }

    public function users(Request $request)
    {

        $item = ContestModel::find($request->id);
        $users = ContestsUser::filter($request->all())
            ->where('id_contest', $item->id)->cursor()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'created_at' => $item->created_at->translatedFormat('d M Y H:i'),
                    'created_at_human' => $item->created_at->diffForHumans(),
                    'is_winner' => $item->contests_user_winner->count(),
                    'name' => $item->name,
                    'email' => $item->email,
                    'link' => $item->link,
                    'bonus' => $item->bonus,
                    'status' => (int)$item->status,
                ]
            ];
        });

        $link_reviews = LinksReview::all();

        return response()->json([
            'users' => [
                'data' => $users,
                'total' => count($users),
            ]
        ]);
    }

    public function usersUpdate(int $id, Request $request)
    {
        $item = ContestModel::find($id);

        if ($request->type == 'status') {

            $user = ContestsUser::find($request->id_user);
            $user->update([
                'status' => $request->value ?? $user->status,
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно обновлены'
            ]);
        }

        if ($request->type == 'bonus') {

            $user = ContestsUser::find($request->id_user);
            $user->update([
                'bonus' => $request->value ?? $user->bonus,
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно обновлены'
            ]);
        }

        // Выбираем победителя
        if ($request->type == 'select-winner') {
            // Количество победителей
            $count_winner = $request->value ?? 0;

            if($count_winner == 0) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Победители не определены'
                ]);
            }

            // Получаем победителей
            $winner_ids = ContestsUser::where([
                ['id_contest', $id],
                ['status', 1],
            ])->inRandomOrder()->limit($count_winner)->pluck('id', 'id')->toArray();
            $item->contests_has_user()->sync($winner_ids);

            return response()->json([
                'status' => 0,
                'message' => 'Данные успешно обновлены'
            ]);
        }
    }
}
