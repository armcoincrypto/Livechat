<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Mail\TestMailRequest;
use App\Models\Currency;
use App\Models\TaskStatus;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Контроллер управления системными настройками админки.
 *
 * Архитектура:
 * - Схема секций (какие поля выводить, типы и т.д.) лежит в JSON-файлах:
 *     storage/app/iexexchanger/settings/{route}.json
 *
 * - Значения:
 *     - обычные (default) настройки хранятся в DynamicConfig через iEXSetting();
 *     - языковые (locales) — через iEXContentLanguage().
 *
 * - Формат JSON файла:
 *     {
 *       "locales": [
 *         "sitename",
 *         "sitename_desc",
 *         "welcome_title",
 *         "welcome_description"
 *       ],
 *       "default": {
 *         "env_app_locale": {
 *           "type": "string"
 *         },
 *         "is_language_detection": {
 *           "type": "bool"
 *         },
 *         "app_multilanguage_locale": {
 *           "type": "string",
 *           "split": ","
 *         }
 *       },
 *       "default_update": { ... } // (опционально, секция interface-image)
 *     }
 *
 * - Ответ index():
 *     {
 *       "locales": {
 *         "sitename": {"ru": "...", "en": "..."},
 *         ...
 *       },
 *       "default": {
 *         "env_app_locale": "ru",
 *         "is_language_detection": true,
 *         ...
 *       },
 *       "options": { ... } // списки для селектов и т.п.
 *     }
 */
class SettingController extends Controller
{
    /**
     * Загрузка настроек для секции.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws FileNotFoundException
     */
    public function index(Request $request): JsonResponse
    {
        $mid = $request->mid;

        if (empty($mid)) {
            return response()->json([
                'status'  => 1,
                'message' => 'Не передан идентификатор секции (mid).',
            ], 422);
        }

        $route = Str::lower($mid);

        $config = $this->loadSectionSchema($route);
        if (!is_array($config)) {
            // loadSectionSchema уже вернёт JsonResponse в случае ошибки,
            // но на всякий случай ловим здесь.
            return response()->json([
                'status'  => 1,
                'message' => 'Ошибка загрузки схемы секции.',
            ], 500);
        }
        $scopeForRoute = $this->resolveScopeFromConfig($config);
        $schemaProfile = $this->resolveSchemaProfileFromConfig($config, $route);

        $response = [
            'locales' => [],
            'default' => [],
            'options' => [],
        ];

        // ===== 1. Языковые настройки (locales) через iEXContentLanguage(raw: true) =====
        $locales = $config['locales'] ?? [];
        if (is_array($locales)) {
            foreach ($locales as $key => $field) {
                // поддерживаем оба варианта:
                // - ["sitename", "welcome_title"]
                // - {"sitename": {...meta...}}
                $fieldName = is_string($key) ? $key : $field;
                $response['locales'][$fieldName] = iEXContentLanguage($fieldName, raw: true);
            }
        }

        // ===== 2. Обычные настройки (default) через iEXSetting =====
        $defaults = $config['default'] ?? [];
        if (is_array($defaults)) {
            foreach ($defaults as $key => $meta) {
                $type = $meta['type'] ?? 'string';

                // базовое значение из DynamicConfig
                $value = iEXSetting(
                    $key,
                    default: null,
                    locale: null,
                    scope: $scopeForRoute,
                    schemaProfile: $schemaProfile
                );
                // строгая типизация (можно заменить на DynamicConfig::getAs при желании)
                $value = $this->castValue($value, $type);

                // split → строку в массив
                if (isset($meta['split'])) {
                    $value = trim((string) $value);
                    $value = $value === '' ? [] : explode($meta['split'], $value);
                }

                // array_map → применить функцию ко всем элементам массива
                if (isset($meta['array_map']) && is_callable($meta['array_map'])) {
                    $value = array_map($meta['array_map'], (array) $value) ?? [];
                }

                // is_zero=false → убрать нулевые значения
                if (isset($meta['is_zero']) && $meta['is_zero'] === false) {
                    $value = array_filter((array) $value, fn ($v) => (int) $v !== 0);
                }

                // get_default_config → если пусто, подставить значение из config()
                if ((empty($value) || $value === []) && isset($meta['get_default_config'])) {
                    $value = config($meta['get_default_config']);
                }

                $response['default'][$key] = $value;
            }
        }

        // ===== 3. Дополнительные опции (справочники, select-списки) =====
        $response['options'] = $this->buildOptionsForRoute($route);

        return response()->json($response);
    }

    /**
     * Работа с доп. опциями для разных разделов (reload_ip, test_send_mail).
     *
     * @param Request $request
     * @return array|void
     */
    public function store(Request $request)
    {
        if (!isset($request->action)) {
            return;
        }

        if ($request->action === 'reload_ip_address') {
            Artisan::call('proxyfilter:reload');

            return [
                'status'  => 0,
                'message' => 'IP-адреса обновлены',
            ];
        }

        if ($request->action === 'test_send_mail') {
            try {
                Mail::to(iEXSetting('project_email_support'))
                    ->send(new TestMailRequest());

                return [
                    'status'  => 0,
                    'message' => 'Сообщение успешно отправлено',
                ];
            } catch (\Exception $exception) {
                return [
                    'status'  => 1,
                    'message' => $exception->getMessage(),
                ];
            }
        }
    }

    /**
     * Обновление настроек секции.
     *
     * Ожидаемый формат запроса:
     *  - mid: string
     *  - default: { key: value, ... }
     *  - locales: { field: { "ru": "...", "en": "...", ... }, ... }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $mid = $request->mid;

        if (empty($mid)) {
            return response()->json([
                'status'  => 1,
                'message' => 'Не передан идентификатор секции (mid).',
            ], 422);
        }

        $route  = Str::lower($mid);
        $config = $this->loadSectionSchema($route);

        if (!is_array($config)) {
            return response()->json([
                'status'  => 1,
                'message' => 'Ошибка загрузки схемы секции.',
            ], 500);
        }
        $scopeForRoute = $this->resolveScopeFromConfig($config);
        $schemaProfile = $this->resolveSchemaProfileFromConfig($config, $route);

        try {
            // ===== 1. Спец. секция interface-image (работа с файлами) =====
            if ($route === 'interface-image') {
                $default = $this->updateInterface($request);

                if ($default instanceof JsonResponse) {
                    return $default;
                }

                // Обработка default_update, если она описана в JSON
                if (isset($config['default_update']) && is_array($config['default_update'])) {
                    foreach ($config['default_update'] as $key => $items) {
                        if (isset($request->{$key}) && is_array($request->{$key})) {
                            $default[$key] = implode(',', $request->{$key});
                        } else {
                            if (is_numeric($request->{$key})) {
                                $default[$key] = (int) ($request->{$key} ?? 0);
                            } else {
                                $default[$key] = $request->{$key} ?? null;
                            }
                        }
                    }
                }

                if (!empty($default)) {
                    iEXSetting($default);
                }
            }

            // ===== 2. Обычные default-настройки =====
            if ($request->has('default') && is_array($request->default)) {
                $defaultMeta = $config['default'] ?? [];

                $defaultToSave = [];
                foreach ($defaultMeta as $key => $meta) {
                    if (!array_key_exists($key, $request->default)) {
                        $defaultToSave[$key] = null;
                        continue;
                    }

                    $incoming = $request->default[$key];

                    if (is_array($incoming)) {
                        $defaultToSave[$key] = implode(',', array_values(array_filter(
                            $incoming,
                            fn ($v) => $v !== null
                        )));
                    } else {
                        $defaultToSave[$key] = $incoming;
                    }
                }

                foreach ($defaultToSave as $key => $value) {
                    if (is_array($value)) {
                        $defaultToSave[$key] = array_filter($value, fn ($v) => $v !== null);
                    }
                }


                if (!empty($defaultToSave)) {
                    iEXSetting($defaultToSave, scope: $scopeForRoute, schemaProfile: $schemaProfile);
                }
            }

            // ===== 3. Языковые настройки (locales) =====
            if ($request->has('locales') && is_array($request->locales)) {
                $localesFields = $config['locales'] ?? [];

                $localesToSave = [];
                foreach ($localesFields as $key => $field) {
                    $fieldName = is_string($key) ? $key : $field;
                    $localesToSave[$fieldName] = $request->locales[$fieldName] ?? null;
                }

                $localesToSave = array_filter($localesToSave, fn ($v) => $v !== null);

                if (!empty($localesToSave)) {
                    iEXContentLanguage($localesToSave);
                }
            }

            return response()->json([
                'status'  => 0,
                'message' => __('Настройки успешно сохранены'),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Ошибка при сохранении настроек', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status'  => 1,
                'message' => 'Произошла ошибка, обратитесь к администратору.',
            ], 500);
        }
    }

    /**
     * Загрузка схемы секции из JSON-файла.
     *
     * @param string $route
     * @return array|null
     */
    private function loadSectionSchema(string $route): ?array
    {
        $configPath = storage_path('app/iexexchanger/settings/'.$route.'.json');

        if (!File::exists($configPath)) {
            Log::warning('Схема секции настроек не найдена', ['route' => $route, 'path' => $configPath]);
            return null;
        }

        $raw = File::get($configPath);
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Log::error('Ошибка парсинга JSON схемы секции', [
                'route'   => $route,
                'path'    => $configPath,
                'error'   => json_last_error_msg(),
            ]);
            return null;
        }

        return $data;
    }

    /**
     * Строгое приведение значения к типу (для default-ключей).
     *
     * @param mixed  $value
     * @param string $type
     * @return mixed
     */
    private function castValue(mixed $value, string $type): mixed
    {
        return match (strtolower($type)) {
            'int', 'integer'   => is_numeric($value) ? (int) $value : 0,
            'float', 'double'  => is_numeric($value) ? (float) $value : 0.0,
            'bool', 'boolean'  => (bool) $value,
            'array'            => is_array($value) ? $value : (array) $value,
            default            => $value === null ? '' : (string) $value,
        };
    }

    /**
     * Построение options для секций (справочники, списки).
     *
     * @param string $route
     * @return array<string,mixed>
     */
    private function buildOptionsForRoute(string $route): array
    {
        $options = [];

        // Главное — языки интерфейса
        if ($route === 'main') {
            $options['allLanguages'] = collect(config('app.all_locale'))->map(function ($name, $code) {
                return [
                    'id'    => $code,
                    'value' => $name,
                ];
            })->values();
        }

        // Безопасность — список прокси-сервисов
        if ($route === 'security') {
            $storage = File::allFiles(
                storage_path('/app/iexexchanger/proxies')
            );

            $allServices = Collection::make($storage)
                ->map(fn ($output) => explode('.', $output->getFilename())[0]);

            $options['proxyFiltered'] = $allServices->map(function ($value) {
                return [
                    'id'    => $value,
                    'value' => $value,
                ];
            })->values();
        }

        // Заявки / онлайн-чат — статусы заказов
        if ($route === 'orders' || $route === 'online-chat') {
            $statuses = TaskStatus::all();

            $options['orderStatuses'] = $statuses->map(function ($item) {
                return [
                    'id'    => $item->id,
                    'value' => $item->name,
                ];
            })->values();

            if ($route === 'orders') {
                $options['timeOrder'] = collect(config('iexexchanger.orders.active_job_timeout'))->map(function ($value) {
                    return [
                        'id'    => $value,
                        'value' => CarbonInterval::seconds($value)->cascade()->forHumans(),
                    ];
                })->values();
            }
        }

        // Пользователи / дизайн интерфейса — список валют
        if ($route === 'users' || $route === 'interface-design') {
            $currencies = Currency::active()->select('id', 'tech_name')->get();

            $options['allCurrencies'] = $currencies->map(function ($item) {
                return [
                    'id'    => $item->id,
                    'value' => $item->tech_name,
                ];
            })->values();
        }

        // Изображения интерфейса — пути к папкам
        if ($route === 'interface-image') {
            $options['logotype_folder'] = '/images/logotype/';
            $options['favicon_folder']  = '/images/favicons/';
            $options['icons_folder']    = '/images/icons/';
            $options['bg_folder']       = '/images/backgrounds/';
        }

        return $options;
    }

    /**
     * Обновление раздела «Интерфейс → Изображения».
     *
     * @param Request $request
     * @return array|JsonResponse
     */
    private function updateInterface(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logotype_mail'           => 'nullable|file|mimes:svg,png,webp,jpeg,jpg,gif|max:5120',
            'logotype_web'            => 'nullable|file|mimes:svg,png,webp,jpeg,jpg,gif|max:5120',
            'logotype_dark'           => 'nullable|file|mimes:svg,png,webp,jpeg,jpg,gif|max:5120',
            'favicon_icon'            => 'nullable|file|mimes:png|max:2048',
            'brand_icon'              => 'nullable|file|mimes:svg,png,webp|max:2048',
            'brand_icon_dark'         => 'nullable|file|mimes:svg,png,webp|max:2048',
            'exchange_fon_file'       => 'nullable|file|mimes:svg,png,webp,jpeg,jpg|max:5120',
            'exchange_fon_file_dark'  => 'nullable|file|mimes:svg,png,webp,jpeg,jpg|max:5120',
        ], [
            'logotype_mail.mimes' => 'Почтовый логотип должен быть файлом формата: svg, png, webp, jpeg, jpg, gif.',
            'logotype_mail.max'   => 'Почтовый логотип не должен превышать 5 МБ.',

            'logotype_web.mimes' => 'Основной логотип должен быть файлом формата: svg, png, webp, jpeg, jpg, gif.',
            'logotype_web.max'   => 'Основной логотип не должен превышать 5 МБ.',

            'logotype_dark.mimes' => 'Логотип для темной темы должен быть файлом формата: svg, png, webp, jpeg, jpg, gif.',
            'logotype_dark.max'   => 'Логотип для темной темы не должен превышать 5 МБ.',

            'favicon_icon.mimes' => 'Favicon должен быть файлом формата: png.',
            'favicon_icon.max'   => 'Favicon не должен превышать 2 МБ.',

            'brand_icon.mimes' => 'Иконка бренда должна быть файлом формата: svg, png, webp.',
            'brand_icon.max'   => 'Иконка бренда не должна превышать 2 МБ.',

            'brand_icon_dark.mimes' => 'Иконка бренда (темная тема) должна быть файлом формата: svg, png, webp.',
            'brand_icon_dark.max'   => 'Иконка бренда (темная тема) не должна превышать 2 МБ.',

            'exchange_fon_file.mimes' => 'Фоновое изображение должно быть файлом формата: svg, png, webp, jpeg, jpg.',
            'exchange_fon_file.max'   => 'Фоновое изображение не должно превышать 5 МБ.',

            'exchange_fon_file_dark.mimes' => 'Фоновое изображение (темная тема) должно быть файлом формата: svg, png, webp, jpeg, jpg.',
            'exchange_fon_file_dark.max'   => 'Фоновое изображение (темная тема) не должно превышать 5 МБ.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'errors' => $validator->errors(),
            ], 422);
        }

        $options = [];

        // Папки
        $paths = [
            'logotype' => public_path('images/logotype'),
            'favicon'  => public_path('images/favicons'),
            'icons'    => public_path('images/icons'),
            'bg'       => public_path('images/backgrounds'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }

        // Общая функция загрузки файла
        $uploadFile = function ($file, string $path, string $prefix, ?string $oldFileSetting = null): string {
            if ($oldFileSetting) {
                iex_file_delete($path . '/' . iEXSetting($oldFileSetting));
            }
            $filename = $prefix . '-' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($path, $filename);
            return $filename;
        };

        // Логотипы
        foreach (['logotype_mail', 'logotype_web', 'logotype_dark'] as $field) {
            if ($request->hasFile($field)) {
                $options[$field] = $uploadFile(
                    $request->file($field),
                    $paths['logotype'],
                    $field,
                    $field
                );
                $options['last_update_logo'] = time();
            }
        }

        // Favicon + все размеры
        if ($request->hasFile('favicon_icon')) {
            $faviconOriginal = Image::read($request->file('favicon_icon')->getRealPath());

            $faviconSizes = [
                'favicon.ico'            => [16, 16],
                'favicon-32x32.png'      => [32, 32],
                'favicon-96x96.png'      => [96, 96],
                'apple-icon-180x180.png' => [180, 180],
                'apple-icon-57x57.png'   => [57, 57],
                'apple-icon-60x60.png'   => [60, 60],
                'apple-icon-114x114.png' => [114, 114],
            ];

            foreach ($faviconSizes as $filename => [$width, $height]) {
                $faviconImage = clone $faviconOriginal;
                $faviconImage->scale($width, $height)->save($paths['favicon'] . '/' . $filename);
            }

            $options['favicon_icon']   = 'apple-icon-180x180.png';
            $options['last_update_icon'] = time();
        }

        // Остальные изображения и иконки
        $otherIcons = [
            'exchange_fon_file'      => ['prefix' => 'iex-bg',       'path' => $paths['bg'],    'use_image' => true],
            'exchange_fon_file_dark' => ['prefix' => 'iex-bg-dark',  'path' => $paths['bg'],    'use_image' => true],
            'brand_icon'             => ['prefix' => 'brand-icon',   'path' => $paths['icons'], 'use_image' => false],
            'brand_icon_dark'        => ['prefix' => 'brand-icon-dark','path' => $paths['icons'],'use_image' => false],
        ];

        foreach ($otherIcons as $field => $details) {
            if ($request->hasFile($field)) {
                $filename = $details['prefix'] . '-' . Str::random(15) . '.' . $request->file($field)->getClientOriginalExtension();

                iex_file_delete($details['path'] . '/' . iEXSetting($field));

                if ($details['use_image']) {
                    Image::read($request->file($field)->getRealPath())
                        ->scale(width: 1920)
                        ->save($details['path'] . '/' . $filename);
                    $options['last_update_bg'] = time();
                } else {
                    $request->file($field)->move($details['path'], $filename);
                }

                $options[$field] = $filename;
            }
        }

        if (empty(array_filter($options, fn ($v) => $v !== null && $v !== ''))) {
            return [];
        }

        return $options;
    }
    /**
     * Возвращает scope для секции настроек из JSON-схемы.
     *
     * Поддерживаем формат в JSON:
     *  "scope": { "type": "ai", "id": 1 }
     *
     * Если scope не указан — используем текущий (null), как было раньше.
     */
    private function resolveScopeFromConfig(array $config): ?\iEXPackages\DynamicConfig\ValueObjects\Scope
    {
        $scope = $config['scope'] ?? null;

        if (!is_array($scope)) {
            return null;
        }

        $type = (string) ($scope['type'] ?? 'global');
        $id = $scope['id'] ?? null;

        return \iEXPackages\DynamicConfig\ValueObjects\Scope::fromString(
            $type,
            $id !== null ? (int) $id : null
        );
    }

    /**
     * Возвращает schemaProfile для секции.
     *
     * Приоритет:
     * - "schemaProfile" из JSON (если задан)
     * - иначе route секции (например "ai")
     */
    private function resolveSchemaProfileFromConfig(array $config, string $route): ?string
    {
        $profile = $config['schemaProfile'] ?? null;

        if (is_string($profile) && trim($profile) !== '') {
            return trim($profile);
        }

        return $route;
    }
}
