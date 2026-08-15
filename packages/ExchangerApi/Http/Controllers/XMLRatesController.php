<?php

namespace iEXPackages\ExchangerApi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class XMLRatesController extends Controller
{
    /**
     * Получаем список курсов
     */
    public function index(): Response
    {
        $name = (string) iEXSetting('grates_filename');
        $name = str_replace(['..', '\\'], '', $name);
        $name = trim($name, '/');
        if ($name === '') {
            abort(404);
        }

        // scheme:files / ExportRatesFile пишет в public/static/exports/{filename}.xml
        $exportPath = public_path('static/exports/' . $name . '.xml');
        $legacyPath = public_path($name . '.xml');

        if (File::exists($exportPath)) {
            $path = $exportPath;
        } elseif (File::exists($legacyPath)) {
            $path = $legacyPath;
        } else {
            abort(404);
        }

        $xmlFile = File::get($path);

        return new Response($xmlFile, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
