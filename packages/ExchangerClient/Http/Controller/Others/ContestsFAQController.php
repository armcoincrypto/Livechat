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

class ContestsFAQController extends Controller
{
    /**
     * Вопросы и Ответы
     *
     * @return JsonResponse
     */
    public function index()
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
