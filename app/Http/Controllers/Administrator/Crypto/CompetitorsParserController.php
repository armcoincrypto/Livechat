<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ParserRates\CompetitorParserResources;
use App\Models\CompetitorLink;
use App\Models\CompetitorRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompetitorsParserController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $parsers = CompetitorRates::filter($request->all());

        if(!$request->has('sorting_order')) {
            $parsers = $parsers->orderBy('id', 'desc');
        }

        $parsers = $parsers->paginate(iEXSetting('admin_competitor_parser_pagination', 20));
        $admin_hidden_columns = explode(',', iEXSetting('admin_competitor_parser_hidden_columns'));
        $allowedColumns = ['name', 'id_competitor', 'course', 'type', 'direction_exchange', 'created_at', 'last_updated', 'status'];


        $groups = CompetitorLink::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });

        return response()->json([
            'items' => new CompetitorParserResources($parsers),
            'groups' => $groups,
            'selected_columns' => collect($admin_hidden_columns)->map(function ($item) {
                return $item;
            })->reject(fn($item) => !in_array($item, $allowedColumns))->values(),
            'per_page' => (int)iEXSetting('admin_competitor_parser_pagination', 20),
        ]);
    }

    /**
     * Форма добавления курсов конкурентов
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $links = CompetitorLink::where('status', '=', 1)->get();

        return view('admin.crypto.competitors.create', compact('links'));
    }

    /**
     * Обработка и добавление курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                $item = CompetitorRates::find((int)$request->id_parser);
                $item->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name_from' => ['required'],
            'name_to' => ['required'],
            'id_competitor' => ['required', 'exists:competitor_links,id'],
            'type' => ['required'],
            'number_format' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $fields = $this->normalizedCompetitorPairFields(
            (string) $request->name_from,
            (string) $request->name_to
        );

        $rate = CompetitorRates::create([
            'name' => $fields['name'],
            'exchange_in' => $fields['exchange_in'],
            'exchange_out' => $fields['exchange_out'],
            'id_competitor' => $request->has('id_competitor') ? $request->get('id_competitor') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'type' => $request->has('type') ? $request->get('type') : 0,
            'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
        ]);

        $rate->update([
            'code' => '[competitors_'.\Str::lower(\Str::studly($rate->competitor_link->name)).'_'.\Str::lower($rate->exchange_in.'-'.$rate->exchange_out).']',
        ]);

        return response()->json([
            'status' => 0,
            'message' => $rate->name .' '.__('успешно добавлен')
        ]);
    }

    /**
     * Форма редактирования курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = CompetitorRates::findOrFail($id);

        $groups = CompetitorLink::orderBy('sorting')->get()->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'link' => $item->link
            ];
        });

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'status' => (bool)$item->status,
                'name_from' => $item->exchange_in,
                'name_to' => $item->exchange_out,
                'id_competitor' => $item->id_competitor,
                'number_format' => $item->number_format,
                'value' => $item->value,
                'summa' => $item->summa,
                'type' => $item->type
            ],
            'groups' => $groups,
        ]);
    }

    /**
     * Обработка и обновление курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name_from' => ['required'],
            'name_to' => ['required'],
            'id_competitor' => ['required', 'exists:competitor_links,id'],
            'type' => ['required'],
            'number_format' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $fields = $this->normalizedCompetitorPairFields(
            (string) $request->name_from,
            (string) $request->name_to
        );

        $rates = CompetitorRates::findOrFail($id);
        $rates->update([
            'name' => $fields['name'],
            'exchange_in' => $fields['exchange_in'],
            'exchange_out' => $fields['exchange_out'],
            'id_competitor' => $request->has('id_competitor') ? $request->get('id_competitor') : 0,
            'status' => $request->has('status') ? $request->get('status') : 0,
            'type' => $request->has('type') ? $request->get('type') : 0,
            'number_format' => $request->has('number_format') ? $request->get('number_format') : 0,
        ]);

        $rates->update([
            'code' => '[competitors_'.\Str::lower(\Str::studly($rates->competitor_link->name)).'_'.\Str::lower($rates->exchange_in.'-'.$rates->exchange_out).']',
        ]);

        return response()->json([
            'status' => 0,
            'message' => $rates->name .' '.__('успешно обновлен')
        ]);
    }

    /**
     * Удаление курсов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $rate = CompetitorRates::findOrFail($id);
        $oldItem = $rate;
        if ($rate->direction_exchange->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Выбранный курс удалить невозможно, К нему привязаны направления'
            ]);
        }
        $rate->delete();

        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->name} успешно удален"
        ]);
    }

    /**
     * Приводит коды пары к тому же виду, что и ключи XML/компилятора: TRIM + UPPER и имя "FROM - TO".
     *
     * @return array{name: string, exchange_in: string, exchange_out: string}
     */
    private function normalizedCompetitorPairFields(string $nameFrom, string $nameTo): array
    {
        $exchangeIn = \Str::upper(trim($nameFrom));
        $exchangeOut = \Str::upper(trim($nameTo));

        return [
            'exchange_in' => $exchangeIn,
            'exchange_out' => $exchangeOut,
            'name' => sprintf('%s - %s', $exchangeIn, $exchangeOut),
        ];
    }
}
