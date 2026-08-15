<?php
namespace iEXPackages\ExchangerClient\Http\Controller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewsResponses;
use App\Mail\Admin\AdminNewReviewMail;
use App\Models\LinksReview;
use App\Models\Review;
use App\Models\Task;
use iEXPackages\ExchangerClient\Http\Resources\LinksReviewResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReviewsController extends Controller
{
    /**
     * Получаем список отзывов
     *
     * @return ReviewsResponses
     */
    public function index(): ReviewsResponses
    {
        //Поиск по запросу
        $items = Review::with(['tasks' => function ($q) {
            $q->select('id', 'public_id', 'id_direction_exchange', 'give_price', 'receiving_price');
        }, 'tasks.direction_exchange' => function ($q) {
            $q->select('id', 'id_currency1', 'id_currency2');
        }, 'tasks.direction_exchange.currency1' => function ($q) {
            $q->select('id', 'id_payment', 'id_code_currency', 'number_format');
        }, 'tasks.direction_exchange.currency2' => function ($q) {
            $q->select('id', 'id_payment', 'id_code_currency', 'number_format');
        }, 'tasks.direction_exchange.currency1.payment' => function ($q) {
            $q->select('id', 'name', 'logo');
        }, 'tasks.direction_exchange.currency2.payment' => function ($q) {
            $q->select('id', 'name', 'logo');
        }, 'tasks.direction_exchange.currency1.code_currency' => function ($q) {
            $q->select('id', 'name');
        }, 'tasks.direction_exchange.currency2.code_currency' => function ($q) {
            $q->select('id', 'name');
        }])->where('version', '=', 1)->where('status', '=', 1)->orderByDesc('id');

        return new ReviewsResponses($items->simplePaginate(10));
    }

    /**
     * Ссылки на отзывы, информация
     *
     * @return array
     */
    public function links(): array
    {
        $link_reviews = LinksReview::where('is_review', '=', 1)->get();

        return [
            'links' => new LinksReviewResources($link_reviews),
        ];
    }

    /**
     * Публикация нового отзыва
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function create(Request $request)
    {
        if (iEXSetting('is_enabled_reviews') == 1) {
            return response()->json([
                'status' => 1,
                'message' => 'Администратор отключил, публикацию отзывов',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'max:40'],
            'text' => ['required', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first(),
            ], 422);
        } else {

            $task = Task::where('public_id', (int) $request->get('order_id'))->first();

            // Получить название
            $review = Review::create([
                'version' => 1,
                'id_task' => $task->id,
                'id_direction_exchange' => $task->id_direction_exchange,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'id_user' => $task->id_user,
                'type' => ($request->has('type') ? $request->get('type') : 0),
                'name' => security_xss($request->get('name')),
                'text' => security_xss($request->get('text')),
                'rate_speed' => (int) $request->get('rate_speed'),
                'status' => 0, // Отправляем на проверку
            ]);

            if ((int) iEXSetting('is_admin_mail_new_review') == 1) {
                try {
                    foreach (get_admin_recipient_email() as $value) {
                        Mail::to($value)
                            ->send(new AdminNewReviewMail($review));
                    }
                } catch (\Exception $exception) {
                    \Log::error('AdminNewReviewMail-'.$exception->getMessage());
                }
            }

            return response()->json([
                'status' => 0,
                'message' => 'Отзыв отправлен на модерацию',
            ]);
        }
    }
}
