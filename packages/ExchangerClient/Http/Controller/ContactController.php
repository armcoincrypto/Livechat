<?php
namespace iEXPackages\ExchangerClient\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\LinksReviewGroup;
use App\Models\SocialReview;
use iEXPackages\ExchangerClient\Http\Resources\Contact\ContactGroupResources;
use iEXPackages\ExchangerClient\Http\Resources\Contact\LinksReviewGroupResources;
use iEXPackages\ExchangerClient\Http\Resources\Contact\SocialReviewResources;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    /**
     * Получаем контакты
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $contacts = ContactGroup::query()
            ->where('status', 1)
            ->with(['contacts' => function ($q) {
                $q->select('id', 'id_group', 'sorting', 'name', 'value', 'url', 'block_size', 'is_home', 'status', 'icon')
                    ->where('status', 1)
                    ->orderBy('sorting')
                    ->orderBy('id');
            }])
            ->select('id', 'name', 'sorting', 'status')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        $socialLinks = SocialReview::select('id', 'name', 'type', 'status', 'link', 'sorting')->orderBy('sorting')->get();

        $links_reviews = LinksReviewGroup::with(['links_review_large', 'links_review_url' => function ($q) {
            $q->orderby('sorting');
        }])->select('id', 'name')->orderBy('sorting')->get();

        return response()->json([
            'description' => [
                'contact' => iEXContentLanguage('description_contact'),
                'review' => iEXContentLanguage('description_review'),
            ],

            'contactGroups' => new ContactGroupResources($contacts),
            'linkGroups' => new LinksReviewGroupResources($links_reviews),
            'socialLinks' => new SocialReviewResources($socialLinks),
        ]);
    }
}
