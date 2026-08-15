<?php
declare(strict_types=1);

namespace iEXPackages\SmartMailer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Абстрактный класс писем SmartMailer с поддержкой мультиязычности и расширяемыми хуками.
 *
 * Предназначен для отправки писем через Laravel Mail с автоматическим переключением языка
 * пользователя, возможностью добавления общей логики до и после отправки, и подробным логированием.
 *
 * @package iEXPackages\SmartMailer
 */
abstract class SmartMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Шаблон письма по умолчанию, загружается из конфигурации.
     *
     * @var string|null
     */
    protected ?string $defaultView = null;

    /**
     * Общие данные, доступные во всех шаблонах писем.
     *
     * @var array
     */
    protected array $defaultData = [];

    /**
     * Флаг, предотвращающий повторную сборку письма.
     *
     * @var bool
     */
    protected bool $built = false;

    /**
     * Предыдущий язык приложения, используется для восстановления после отправки письма.
     *
     * @var string|null
     */
    protected ?string $previousLocale = null;

    /**
     * Конструктор письма.
     *
     * @param string|null $locale Язык отправки письма (опционально).
     */
    public function __construct(?string $locale = null)
    {
        $this->loadConfig();
        $this->locale = $locale;
    }

    /**
     * Загружает базовые настройки из конфигурации smart-mailer.php.
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $this->defaultView = config('smart-mailer.default_template', 'emails.default');
        $this->defaultData = config('smart-mailer.default_data', []);
    }

    /**
     * Формирует полный путь к шаблону на основе активной темы.
     *
     * @param string $template Имя шаблона письма.
     *
     * @return string Полный путь к шаблону письма.
     */
    protected function themeTemplate(string $template): string
    {
        $theme = config('smart-mailer.theme', 'default');

        return "emails.{$theme}.{$template}";
    }

    /**
     * Метод-хук, выполняющийся перед отправкой письма.
     *
     * Может быть переопределён в дочерних классах.
     *
     * @return void
     */
    protected function beforeSend(): void
    {
        // Реализация в дочерних классах
    }

    /**
     * Метод-хук, выполняющийся после отправки письма.
     *
     * Может быть переопределён в дочерних классах.
     *
     * @return void
     */
    protected function afterSend(): void
    {
        // Реализация в дочерних классах
    }

    /**
     * Абстрактный метод сборки письма, должен быть реализован в дочернем классе.
     *
     * @return void
     */
    abstract protected function compose(): void;

    /**
     * Выполняет полную сборку письма с учетом выбранного языка.
     *
     * Автоматически переключает язык приложения, вызывает хуки beforeSend и afterSend,
     * и обеспечивает корректное восстановление языка после отправки.
     *
     * @throws \LogicException При повторном вызове метода.
     * @throws Throwable При ошибках сборки или отправки письма.
     *
     * @return $this
     */
    final public function build()
    {
        if ($this->built) {
            throw new \LogicException('Метод build() был вызван повторно.');
        }

        $this->previousLocale = App::getLocale();

        try {
            $this->beforeSend();

            if ($this->locale) {
                App::setLocale($this->locale);
            }

            if ($this->defaultView) {
                $this->markdown($this->defaultView, $this->defaultData);
            }

            $this->compose();
            $this->log('Отправлено письмо.');
            $this->afterSend();
        } catch (Throwable $e) {
            $this->log('Ошибка отправки письма.', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            App::setLocale($this->previousLocale);
        }

        $this->built = true;

        return $this;
    }

    /**
     * Устанавливает тему письма.
     *
     * @param string $subject Тема письма.
     *
     * @return static
     */
    protected function setSubject(string $subject): static
    {
        $this->subject($subject);

        return $this;
    }

    /**
     * Добавляет данные к общему массиву данных шаблона письма.
     *
     * @param array $data Данные для шаблона.
     *
     * @return static
     */
    protected function withDefaultData(array $data): static
    {
        $this->defaultData = array_merge($this->defaultData, $data);

        return $this;
    }

    /**
     * Логирует процесс отправки писем с детализированной информацией.
     *
     * @param string $message Сообщение для логирования.
     * @param array $context Дополнительный контекст для лога.
     *
     * @return void
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info("[SmartMailer] {$message}", array_merge($context, [
            'mailable' => static::class,
            'subject' => $this->subject ?? '(без темы)',
            'locale' => $this->locale ?? App::getLocale(),
            'to' => collect($this->to)->pluck('address')->toArray(),
        ]));
    }
}
