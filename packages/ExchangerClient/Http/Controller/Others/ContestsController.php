<?php

namespace iEXPackages\ExchangerClient\Http\Controller\Others;

use App\Http\Controllers\Controller;
use App\Models\ContestConditionModel;
use App\Models\ContestFaqModel;
use App\Models\ContestModel;
use App\Models\ContestsUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ContestsController extends Controller
{
    public function index(): JsonResponse
    {
        // Условие
        $condition = ContestConditionModel::orderBy('sorting')->get()->map(function ($user, $index) {
            $index++;
            return [
                'sorting' => $index,
                'description' => $user->description,
            ];
        })->toArray();

        $recent_winners = ContestsUser::where('bonus', '>', 0)->orderBy('created_at', 'desc')->limit(10)->get()->map(function ($user) {
            return [
                'name' => $user->name,
                'bonus' => iex_number_format($user->bonus, 0, true),
                'code_sign' => $user->code_sign_bonus,
                'created_at' => Carbon::parse($user->created_at)->translatedFormat('d M Y, H:i'),
                'link' => [
                    'name' => isset($user->link_review) ? $user->link_review?->name : null,
                    'url' => isset($user->link_review) ? $user->link_review?->url : null,
                ],
            ];
        })->toArray();

        // Actual
        $actual = ContestModel::where('status', '=', 1)->first();
        $participants = [];
        $leadtime = 0;
        if (! empty($actual)) {

            // Время обработки заявки
            $start_time = Carbon::now();
            $duration = Carbon::parse($actual->duration);
            if ($duration->gt($start_time)) {
                $leadtime = $start_time->diffInSeconds($duration);
            }

            $participants_array = ContestsUser::where([
                ['id_contest', '=', $actual->id],
                ['status', '=', 1],
            ])->get();

            foreach ($participants_array as $item) {
                $participants[] = [
                    'name' => $item->name,
                    'created_at' => Carbon::parse($item->created_at)->translatedFormat('d M Y, H:i'),
                    'link' => [
                        'name' => $item->link_review?->name,
                        'url' => $item->link_review?->url,
                    ],
                ];
            }

        }

        return response()->json([
            'conditions' => $condition,
            'recent_winners' => [
                'count' => count($recent_winners),
                'list' => $recent_winners,
            ],

            'actual' => [
                'bank' => ! empty($actual) ? iex_number_format($actual->bank, 2, true) : 0,
                'code_data' => [
                    'sign' => ! empty($actual) ? $actual->code_sign : null,
                    'pos' => ! empty($actual) ? $actual->code_position : null,
                ],
                'title' => $actual->info_title ?? '',
                'description' => $actual->info_text ?? '',
                'participants' => $participants
            ],
            'leadtime' => $leadtime,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json([
                'status' => 1,
                'message' => 'Чтобы принять участие в розыгрыше, вы должны быть авторизованы на сайте',
            ], 422);
        }

        $find = ContestModel::where('status', '=', 1)->first();
        $contest_user = ContestsUser::where([
            ['id_contest', '=', $find->id],
            ['id_user', '=', $request->user()->id],
        ])->exists();

        if (empty($find)) {
            return response()->json([
                'status' => 1,
                'message' => 'Мы не можем принять вашу заявку, обратитесь к администратору',
            ], 422);
        }

        if ($contest_user) {
            return response()->json([
                'status' => 1,
                'message' => 'Вы уже ранее подавали заявку на участие в розыгрыше',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'max:50'],
            'name' => ['required', 'max:40'],
            'link' => ['required', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        } else {

            ContestsUser::create([
                'id_user' => $request->user()->id,
                'status' => 0,
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'link' => $request->get('link'),
                'id_contest' => $find->id,
                'code_sign_bonus' => $find->code_sign,
                'code_name_bonus' => $find->code_name,
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Заявка на розыгрыш подано',
            ]);

        }

    }

    /**
     * Вопросы и Ответы
     *
     * @return JsonResponse
     */
    public function faq()
    {
        $faq = ContestFaqModel::where('status', 1)->orderBy('sorting')->get();

        return response()->json($faq->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'title' => $item->title,
                    'description' => $item->description,
                ]
            ];
        }));
    }

}
