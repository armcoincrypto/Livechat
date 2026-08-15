<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Settings\BestChangeBlacklistConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


/**
 * Черный список BestChange
*/
class BlackListBestChangeController extends Controller
{

    protected array $allowOptions = [
        "categories",
        "method",
        "is_status",
        "api_id",
        "api_key",
        "columns",
        "type"
    ];

    /**
     *
     * @param BestChangeBlacklistConfig $blackListSettings
     */
    public function index(
        BestChangeBlacklistConfig $blackListSettings,
    ): JsonResponse {
        return response()->json($blackListSettings->toArray());
    }

    /**
     * @param BestChangeBlacklistConfig $settings
     */
    public function store(
        BestChangeBlacklistConfig $settings,
        Request $request
    ): JsonResponse {

       $settings->update($request->all());


        return response()->json([
            'status'  => 0,
            'message' => __('Настройки успешно сохранены'),
        ]);
    }
}
