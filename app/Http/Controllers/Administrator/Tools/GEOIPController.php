<?php

namespace App\Http\Controllers\Administrator\Tools;

use App\Http\Controllers\Controller;
use App\Models\GeoCountryList;
use Illuminate\Http\Request;

class GEOIPController extends Controller
{
    /**
     * Список стран
     */
    public function countries(Request $request)
    {
        $countries = GeoCountryList::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'attributes' => [
                    'locales' => $item->getTranslations(),
                    'code' => $item->code,
                    'value' => $item->value
                ]
            ];
        });

        return response()->json([
            'data' => $countries,
            'total' => count($countries),
        ]);
    }

    public function countriesPut(int $id, Request $request)
    {
            $update = GeoCountryList::find($id);
            $update->update([
                'value' => $request->value
            ]);

        return response()->json([
            'status' => 0,
            'message' => 'Данные обновлены'
        ]);
    }
}
