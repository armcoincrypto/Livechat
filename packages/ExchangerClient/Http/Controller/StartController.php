<?php
namespace iEXPackages\ExchangerClient\Http\Controller;

use iEXPackages\ExchangerClient\Facades\StartServiceFacade;
use Illuminate\Http\JsonResponse;

class StartController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'initialData' => StartServiceFacade::build(),
        ]);
    }
}
