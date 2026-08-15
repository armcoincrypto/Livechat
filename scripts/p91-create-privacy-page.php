<?php

declare(strict_types=1);

/**
 * P9.1 — Create factual Privacy Policy CMS page (RU + EN).
 *
 * Usage: php8.4 scripts/p91-create-privacy-page.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);
$backupDir = __DIR__ . '/../storage/app/p91-backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$ru = <<<'HTML'
<h2>Политика конфиденциальности Exswaping</h2>
<p>Настоящая политика описывает, как сервис Exswaping на сайте exswaping.com обрабатывает данные пользователей при использовании онлайн-обмена криптовалют.</p>
<h2>Какие данные могут обрабатываться</h2>
<ul>
<li>Контактные данные, указанные в заявке (Telegram, e-mail, номер телефона, реквизиты получателя).</li>
<li>Технические данные (IP-адрес, cookies, параметры браузера) для работы сайта и безопасности.</li>
<li>Данные, необходимые для AML/KYC-проверок по правилам сервиса и выбранного направления обмена.</li>
</ul>
<h2>Для чего используются данные</h2>
<ul>
<li>Обработка заявок на обмен и связь с поддержкой.</li>
<li>Соблюдение требований AML/KYC и предотвращение мошенничества.</li>
<li>Улучшение работы сайта и качества сервиса.</li>
</ul>
<h2>Передача третьим лицам</h2>
<p>Exswaping не продаёт персональные данные. Передача возможна только в случаях, предусмотренных законом, правилами AML/KYC или необходимостью выполнения заявки (например, платёжным партнёрам по выбранному направлению).</p>
<h2>Хранение и безопасность</h2>
<p>Данные хранятся в объёме и срок, необходимые для оказания услуги и соблюдения требований законодательства. Применяются организационные и технические меры защиты.</p>
<h2>Права пользователя</h2>
<p>Вы можете запросить уточнение или удаление данных, если это не противоречит закону и правилам обработки заявок. Обращайтесь в поддержку с номером активной заявки.</p>
<h2>Связанные документы</h2>
<ul>
<li><a href="/ru/pages/amlkyc">Политика AML и KYC</a></li>
<li><a href="/ru/pages/service">Пользовательское соглашение</a></li>
<li><a href="/ru/contacts">Контакты и поддержка</a></li>
</ul>
<p>Поддержка: <a href="https://t.me/exswaping" rel="noopener noreferrer">Telegram @exswaping</a>, <a href="https://wa.me/+17477580645" rel="noopener noreferrer">WhatsApp</a>.</p>
HTML;

$en = <<<'HTML'
<h2>Exswaping Privacy Policy</h2>
<p>This policy describes how the Exswaping service at exswaping.com handles user data when you use the online cryptocurrency exchange.</p>
<h2>Data we may process</h2>
<ul>
<li>Contact details submitted with an order (Telegram, email, phone, payout details).</li>
<li>Technical data (IP address, cookies, browser parameters) required for site operation and security.</li>
<li>Information required for AML/KYC checks under service rules and the selected exchange route.</li>
</ul>
<h2>How data is used</h2>
<ul>
<li>Processing exchange orders and support communication.</li>
<li>AML/KYC compliance and fraud prevention.</li>
<li>Improving site performance and service quality.</li>
</ul>
<h2>Third-party sharing</h2>
<p>Exswaping does not sell personal data. Sharing occurs only where required by law, AML/KYC rules, or to complete an order (for example, payment partners for the selected route).</p>
<h2>Retention and security</h2>
<p>Data is retained only as long as needed to provide the service and meet legal requirements. Organizational and technical safeguards are applied.</p>
<h2>Your rights</h2>
<p>You may request clarification or deletion of data where this does not conflict with law or active order processing. Contact support with your order ID.</p>
<h2>Related documents</h2>
<ul>
<li><a href="/en/pages/amlkyc">AML/KYC policy</a></li>
<li><a href="/en/pages/service">Terms of service</a></li>
<li><a href="/en/pages/contacts">Contacts and support</a></li>
</ul>
<p>Support: <a href="https://t.me/exswaping" rel="noopener noreferrer">Telegram @exswaping</a>, <a href="https://wa.me/+17477580645" rel="noopener noreferrer">WhatsApp</a>.</p>
HTML;

$existing = DB::table('pages')->where('page_slug', 'privacy')->first();
if ($existing) {
    echo "Privacy page already exists (page_id={$existing->page_id})\n";
    exit(0);
}

$payload = [
    'group_id' => null,
    'sort_order' => 0,
    'is_active' => 1,
    'page_title' => json_encode([
        'ru' => 'Политика конфиденциальности',
        'en' => 'Privacy Policy',
    ], JSON_UNESCAPED_UNICODE),
    'page_headline' => json_encode([
        'ru' => 'Политика конфиденциальности',
        'en' => 'Privacy Policy',
    ], JSON_UNESCAPED_UNICODE),
    'page_content' => json_encode(['ru' => $ru, 'en' => $en], JSON_UNESCAPED_UNICODE),
    'page_slug' => 'privacy',
    'user_id' => 1,
    'created_at' => now(),
    'updated_at' => now(),
];

file_put_contents(
    $backupDir . '/privacy-page-create.json',
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

if ($dryRun) {
    echo "[dry-run] would create pages.privacy\n";
    exit(0);
}

$id = DB::table('pages')->insertGetId($payload);
echo "Created privacy page_id={$id}\n";
