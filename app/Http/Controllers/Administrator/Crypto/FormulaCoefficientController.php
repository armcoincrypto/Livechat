<?php

namespace App\Http\Controllers\Administrator\Crypto;

use App\Http\Controllers\Controller;
use App\Models\ParserFormulaCoefficient;
use iEXPackages\Courses\Rates\Compilers\CompilerFormulaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FormulaCoefficientController extends Controller
{
    /**
     * Главная страница
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $compilerFormulaService = app(CompilerFormulaService::class);
        $parsers = ParserFormulaCoefficient::orderByDesc('id')->get()->map(function($item) use ($compilerFormulaService) {
            $template = $item->template;
            $formulaResult = null;
            if ($item->type_index == 1 && !empty($template)) {
                $formulaResult = $compilerFormulaService->getFormulaResult($template);
            }
            return [
                'id' => $item->id,
                'attributes' => [
                    'name' => $item->name,
                    'summa' => $item->summa,
                    'alias' => $item->alias,
                    'comment' => $item->comment,
                    'type_index' => $item->type_index,
                    'template' => $template,
                    'formula_result' => $formulaResult,
                ]
            ];
        });

        return response()->json([
            'data' => $parsers
        ]);
    }

    /**
     * Обработка и добавление курсов конкурентов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => ['required'],
            'type_index' => ['required', 'numeric'],
        ];

        if((int)$request->type_index === 0) {
            $rules['summa'] = ['required', 'numeric'];
        } else {
            $rules['template'] = ['required'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }
        $custom_name = '[index_'.Str::lower(Str::slug($request->get('name'))).']';


        $options = [
            'name' => $request->has('name') ? $request->get('name') : null,
            'alias' => $custom_name,
            'comment' => $request->has('comment') ? $request->get('comment') : null,
            'type_index' => $request->has('type_index') ? $request->get('type_index') : null,
        ];

        if($request->type_index == 0) {
            $options['summa'] = $request->has('summa') ? $request->get('summa') : null;
        } else {
            $options['template'] = $request->has('template') ? $request->get('template') : null;
        }

        $rate = ParserFormulaCoefficient::create($options);

        return response()->json([
            'status' => 0,
            'message' => "{$rate->name} успешно добавлен"
        ]);
    }

    /**
     * Обработка и обновление
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $rates = ParserFormulaCoefficient::findOrFail($id);

        $rules = [];
        if((int)$rates->type_index === 0) {
            $rules['summa'] = ['required', 'numeric'];
        } else {
            $rules['template'] = ['required'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails())
        {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $options = [
            'comment' => $request->has('comment') ? $request->get('comment') : null
        ];

        if($rates->type_index == 0) {
            $options['summa'] = $request->has('summa') ? $request->get('summa') : null;
        } else {
            $options['template'] = $request->has('template') ? $request->get('template') : null;
        }

        $rates->update($options);

        return response()->json([
            'status' => 0,
            'message' => "{$rates->name} успешно обновлен"
        ]);
    }

    /**
     * Удаление курсов
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id)
    {
        $rate = ParserFormulaCoefficient::findOrFail($id);
        $oldItem = $rate;
        $rate->delete();

        return response()->json([
            'status' => 0,
            'message' => "{$oldItem->name} успешно удален"
        ]);
    }
}
