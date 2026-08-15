<?php

namespace App\Http\Controllers\Administrator\Plugins;

use App\Http\Resources\Admin\Others\CitiesResources;
use App\Models\CitiesModel;
use App\Models\GeoCountryList;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class CitiesController extends Controller
{
    /**
     * Список городов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Проверка необходимости загрузки дополнительных данных
        if ($request->has('isLoadingData')) {
            // Получаем список стран в формате id => name
            $countries = GeoCountryList::query()
                ->pluck('value', 'id')
                ->map(fn($value, $id) => ['id' => $id, 'value' => $value])
                ->values()
                ->toArray();

            // Загружаем список городов из файла JSON с проверкой наличия файла
            $citiesPath = storage_path('app/cities_with_countries.json');
            if (!file_exists($citiesPath)) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Файл со списком городов не найден.'
                ], 404);
            }

            $citiesData = json_decode(file_get_contents($citiesPath), true);

            return response()->json([
                'countries' => $countries,
                'cities' => $citiesData
            ]);
        }

        // Получение списка городов с пагинацией и фильтрами
        $cities = CitiesModel::with('direction_exchange_cities')
            ->filter($request->all())
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'items' => new CitiesResources($cities)
        ]);
    }

    /**
     * Добавляем город
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                CitiesModel::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => 'required',
            'designation_xml' => 'required',
            'country_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Проверка уникальности designation_xml
        $designationXml = $request->get('designation_xml');
        if (CitiesModel::where('designation_xml', $designationXml)->exists()) {
            return response()->json([
                'status' => 1,
                'message' => 'Значение designation_xml уже существует.'
            ]);
        }

        // Проверка уникальности name (по текущей локали)
        $locale = config('iexexchanger.default_locale');
        $nameValue = Arr::get($request->name, $locale);
        if (
            CitiesModel::where("name->{$locale}", $nameValue)->exists()
        ) {
            return response()->json([
                'status' => 1,
                'message' => 'Значение name уже существует.'
            ]);
        }

        $options = [
            'name' => $request->name
        ];
        $options['designation_xml'] = $request->has('designation_xml') ? $request->get('designation_xml') : '';
        $options['status'] = $request->has('status') ? (int)$request->get('status') : 0;
        $options['country_id'] = $request->country_id ?? 0;

        $response = CitiesModel::create($options);

        return response()->json([
            'status' => 0,
            'message' => $response->name . ' успешно добавлен'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = CitiesModel::findOrFail($id);

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'locales' => $item->getTranslations(),
                'name' => $item->name,
                'status' => (bool)$item->status,
                'country_id' => (int)$item->country_id,
                'designation_xml' => $item->designation_xml
            ]
        ]);
    }

    /**
     * Обновляем городв
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $city = CitiesModel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name.' . config('iexexchanger.default_locale') => 'required',
            'designation_xml' => 'required',
            'country_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Проверка уникальности designation_xml, исключая текущий город
        $designationXml = $request->get('designation_xml');
        if (
            CitiesModel::where('designation_xml', $designationXml)
                ->where('id', '!=', $id)
                ->exists()
        ) {
            return response()->json([
                'status' => 1,
                'message' => 'Значение designation_xml уже существует.'
            ]);
        }

        // Проверка уникальности name (по текущей локали), исключая текущий город
        $locale = config('iexexchanger.default_locale');
        $nameValue = Arr::get($request->name, $locale);
        if (
            CitiesModel::where("name->{$locale}", $nameValue)
                ->where('id', '!=', $id)
                ->exists()
        ) {
            return response()->json([
                'status' => 1,
                'message' => 'Значение name уже существует.'
            ]);
        }

        $options = [
            'name' => $request->name
        ];
        $options['designation_xml'] = $request->has('designation_xml') ? $request->get('designation_xml') : '';
        $options['status'] = $request->has('status') ? (int)$request->get('status') : 0;
        $options['country_id'] = $request->country_id ?? 0;

        $city->update($options);

        return response()->json([
            'status' => 0,
            'message' => $city->name . ' успешно обновлен'
        ]);
    }

    /**
     * Удаляем город
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $item = CitiesModel::findOrFail($id);
        $oldItem = $item;

        if (isset($item->direction_exchange_cities) and $item->direction_exchange_cities->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => sprintf(__('Вы не можете удалить город %s, найдены привязанные направления'), $item->name)
            ]);
        }

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name . __('успешно удален')
        ]);
    }
}
