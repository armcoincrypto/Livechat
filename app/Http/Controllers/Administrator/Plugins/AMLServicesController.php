<?php

namespace App\Http\Controllers\Administrator\Plugins;

use App\Facades\Vault;
use App\Http\Resources\Admin\Tools\AmlServicesResources;
use App\Models\AMLService;
use iEXPackages\AMLPlugin\Helpers\AMLConfigLoader;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AMLServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $items = AMLService::filter($request->all())->orderByDesc('id')->paginate(20);

        // Получаем список все сервисов
        $services = getAvailableAmlDrivers();

        return response()->json([
            'services' => collect($services)->map(function ($value) {
                return [
                    'id' => $value['alias'],
                    'value' => $value['name']
                ];
            })->values(),
            'items' => new AmlServicesResources($items)
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function store(Request $request)
    {
        if($request->has('updateField'))
        {
            if($request->get('updateField') == 'status')
            {
                AMLService::find((int)$request->id)->update([
                    'status' => (int)$request->status
                ]);

                return response()->json([
                    'status' => 0
                ]);
            }
        }

        // Проверка входных параметров
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'alias' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        // Получаем список всех платежных шлюзов для мерчанта
        $amlData = collect(getAvailableAmlDrivers())
            ->first(fn($fn) => $fn['alias'] == $request->get('alias'));


        // Добавление нового фьд
        $item = AMLService::create([
            'name' => $request->has('name') ? $request->get('name') : null,
            'status' => $request->has('status') ? (int)$request->get('status') : 0,
            'alias' => $request->get('alias'),
        ]);

        $unique_filename = sprintf('aml%s-%s-%s',
            $item->id, $amlData['alias'], Str::lower(Str::random(30)),
        );

        $item->update(['filename' => $unique_filename]);

        $configAml = AMLConfigLoader::load($amlData['alias']);
        $onlyMainFields = collect($configAml['inputs']['fields'])->pluck('key', 'key')->values()->toArray();

        // Записываем данные в файл
        Vault::encryptToFile($unique_filename, $onlyMainFields, 'aml');

        return response()->json([
            'status' => 0,
            'message' => "AML сервис {$item->name} успешно добавлен"
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id)
    {
        $item = AMLService::find($id);

        try {
            $data = Vault::decryptFromFile($item->filename, 'aml');

        } catch (\Throwable) {
            return response()->json([
                'status' => 1,
                'message' => __('AML сервис :aml_name поврежден, удалите и создайте новый', ['aml_name' => $item->aml_name])
            ]);
        }

        $configAml = AMLConfigLoader::load($item->alias);

        $connectionFields = [];
        if(isset($configAml['inputs']['fields'])) {
            foreach($configAml['inputs']['fields'] as $field)
            {
                $inputFieldPlaceholder = '';
                $inputFieldValue = $data[$field['key']] ?? '';

                if(isset($field['is_hidden']) and $field['is_hidden'] == 1) {
                    $inputFieldPlaceholder = (!empty($inputFieldValue) ? '** Параметр заполнен **' : '');
                    $inputFieldValue = '';
                }

                $optionsValue = [];
                if(isset($field['options']) and !empty($field['options'])) {
                    $optionsValue = collect($field['options'])->map(function($value, $key) {
                        return [
                            'id' => $key,
                            'value' => $value
                        ];
                    })->values();
                }

                $connectionFields[] = [
                    'key' => $field['key'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'placeholder' => $inputFieldPlaceholder,
                    'value' => $inputFieldValue,
                    'options' => $optionsValue
                ];
            }
        }

        $riskFields = [];
        if(isset($configAml['inputs']['risk_fields'])) {
            foreach($configAml['inputs']['risk_fields'] as $field)
            {
                $riskFields[] = [
                    'key' => $field['key'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'value' => $item->ext_options[$field['key']] ?? []
                ];
            }
        }

        return response()->json([
            'id' => $item->id,
            'attributes' => [
                'name' => $item->name,
                'alias' => $item->alias,
                'status' => (bool)$item->status,
            ],
            'connectionFields' => $connectionFields,
            'optionsFields' => $riskFields
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     * @throws \Throwable
     */
    public function update(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        // Проверка входных параметров
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $item = AMLService::find($id);
        $configAml = AMLConfigLoader::load($item->alias);

        $hiddenKeys = collect($configAml['inputs']['fields'])
            ->filter(fn($field) => ($field['is_hidden'] ?? 0) == 1)
            ->pluck('key')
            ->toArray();

        Vault::updateFile($item->filename, $request->connectionFields ?? [], 'aml', $hiddenKeys);

        $options = [
            'name' => $request->get('name'),
            'status' => ($request->has('status') ? $request->get('status') : 0),
        ];

        // Обновление настроек в базе
        $item->update($options);

        if(isset($amlFacade['inputs']['risk_fields'])) {
            $item->update([
                'ext_options' => $request->optionFields
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => "{$item->name} успешно обновлен"
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $item = AMLService::find($id);
        $oldItem = $item;

        Vault::deleteFile($oldItem->filename, 'aml');

        $item->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->name .' успешно удален'
        ]);
    }
}
