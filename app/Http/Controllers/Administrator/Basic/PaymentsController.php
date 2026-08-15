<?php

namespace App\Http\Controllers\Administrator\Basic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Basic\PaymentSystemsResources;
use App\Models\Currency;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PaymentsController extends Controller
{
    /**
     * Список платежных систем
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        // Базовый запрос с фильтрацией и подсчетом связанных валют
        $paymentsQuery = Payment::withCount('currencies')
            ->filter($request->all())
            ->where('is_delete', 0);

        // Применяем сортировку по умолчанию, если не указана другая
        $sortField = $request->input('sorting_order', 'id');
        $sortOrder = $request->input('sorting_type', 'desc');
        $paymentsQuery->orderBy($sortField, $sortOrder);


        // Выполняем пагинацию
        $payments = $paymentsQuery->paginate(30);

        return response()->json([
            'items' => new PaymentSystemsResources($payments)
        ]);
    }


    /**
     * Обработка и добавление новой платежной системы
     *
     * @throws \Exception
     */
    public function store(Request $request): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode'))
        {
            return response()->json([
                'status' => 1,
                'message' => 'Данная функция недоступна в демо версии'
            ]);
        }

        // Валидация входящих данных
        $validator = Validator::make($request->all(), [
            'logo' => 'nullable|file|mimes:jpeg,png,jpg,svg,webp|max:5000',
            'name.' . config('iexexchanger.default_locale') => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Создаем новое имя для логотипа
        $filename = '';

        if ($request->hasFile('logo'))
        {
            $logo_icon = $request->file('logo');
            $extension = strtolower($logo_icon->getClientOriginalExtension());
            $filename = Str::random(10) . ($extension === 'svg' ? '.svg' : '.webp');
            $destinationPath = public_path('/storage/payment_systems');


            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            if ($extension === 'svg') {
                // Сохраняем SVG без изменений
                $logo_icon->move($destinationPath, $filename);
            } else {
                // Обрабатываем растровое изображение (jpeg, png, webp)
                $image = Image::read($logo_icon)
                    ->scale(width: 200)  // ширина до 300px, высота авто
                    ->encodeByExtension('webp', quality: 85);

                // Сохраняем изображение в указанной папке
                $image->save($destinationPath . '/' . $filename);
            }
        }

        // Создание записи о платёжной системе
        $payment = Payment::create([
            'logo' => $filename,
            'name' => $request->name,
        ]);

        return response()->json([
            'status' => 0,
            'message' => __('Платежная система «:name» успешно добавлена.', ['name' => $payment->name])
        ]);
    }

    /**
     * Получить данные платежной системы
     */
    public function edit(int $id): JsonResponse
    {
        $item = Payment::findOrFail($id);

        // Безопасное формирование пути к логотипу
        $logoPath = $item->logo
            ? asset(trim(config('image.folders.payment_systems'), '/\\') . '/' . $item->logo)
            : null;

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'logo' => $item->logo,
                'logo_path' => $logoPath,
                'locales' => $item->getTranslations(),
            ],
        ]);
    }


    /**
     * Обработка и обновление данных платежной системы
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        // Проверка входных параметров
        $validator = Validator::make($request->all(), [
            'logo' => 'file|mimes:jpeg,png,jpg,svg,webp|max:5000',
            'name.'.config('iexexchanger.default_locale') => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }


        $filename = $payment->logo;
        if ($request->hasFile('logo')) {
            try {
                // Удаляем старый файл, если он существует
                $oldFilePath = public_path('/' . trim(config('image.folders.payment_systems'), '/\\') . '/' . $filename);
                if ($filename && file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }

                $logo_icon = $request->file('logo');
                $extension = strtolower($logo_icon->getClientOriginalExtension());

                // Генерируем новое имя файла
                $filename = Str::random(10) . ($extension === 'svg' ? '.svg' : '.webp');
                $destinationPath = public_path('/' . trim(config('image.folders.payment_systems'), '/\\'));

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                if ($extension === 'svg') {
                    // Сохраняем SVG без обработки
                    $logo_icon->move($destinationPath, $filename);
                } else {
                    // Обработка изображения (jpeg, png, webp)
                    $image = Image::read($logo_icon)
                        ->scale(width: 200) // ширина до 300px, высота пропорционально
                        ->encodeByExtension('webp', quality: 85);

                    // Сохраняем обработанное изображение
                    $image->save($destinationPath . '/' . $filename);
                }

            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 1,
                    'message' => __('Ошибка загрузки файла: :message', ['message' => $e->getMessage()])
                ]);
            }
        }

        $payment->update([
            'logo' => $filename,
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 0,
            'message' => 'Платежная система '.$payment->name.' успешно обновлена'
        ]);
    }

    /**
     * Визуальное удаление платежной системы
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(int $id): JsonResponse
    {
        if (config('iexexchanger.is_reading_mode')) {
            return response()->json([
                'status' => 1,
                'message' => __('Данная функция недоступна в демо-версии.')
            ]);
        }

        $payment = Payment::withCount('currencies')->findOrFail($id);

        if ($payment->currencies_count > 0) {
            return response()->json([
                'status' => 1,
                'message' => __('Платежную систему «:name» удалить нельзя, так как к ней привязаны валюты.', ['name' => $payment->name])
            ]);
        }

        try {
            // Удаляем логотип, если он есть
            if ($payment->logo) {
                $filePath = public_path(trim(config('image.folders.payment_systems'), '/\\') . '/' . $payment->logo);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // Удаляем запись из базы данных
            $payment->delete();

            return response()->json([
                'status' => 0,
                'message' => __('Платежная система «:name» успешно удалена.', ['name' => $payment->name])
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 1,
                'message' => __('Ошибка при удалении платежной системы: :message', ['message' => $e->getMessage()])
            ]);
        }
    }

    public function delete_url(int $id)
    {
        $this->destroy($id);
    }
}
