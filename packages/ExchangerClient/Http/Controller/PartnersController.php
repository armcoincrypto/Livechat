<?php
namespace iEXPackages\ExchangerClient\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\ExportRatesFile;
use App\Models\RewardProgram;
use Carbon\Carbon;
use iEXPackages\ReferralSystem\Models\ReferralProgram;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    public function index()
    {
        // Реферальная программа
        $referrals = ReferralProgram::get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'referral',
                'attributes' => [
                    'label' => $item->title,
                    'value' => $item->percent
                ],
            ];
        });

        return response()->json([
            'referrals' => $referrals,
            'exports' => $this->rates(),
            'text' => [
                'referral' => iEXContentLanguage('referral_system_text'),
                'exports' => iEXContentLanguage('monitoring_text'),
            ]
        ]);
    }

    public function rates()
    {
        $files = ExportRatesFile::where([
            ['is_view', '=', 0],
            ['status', '=', 1]
        ])->get();

        $array = [];
        foreach ($files as $file) {
            $array[] = [
                'url' =>  config('app.frontend_url') . '/exports/' . $file->filename . '.xml',
                'format' => 'xml',
                'description' => $file->description ?? null
            ];
        }

        return $array;
    }
}
