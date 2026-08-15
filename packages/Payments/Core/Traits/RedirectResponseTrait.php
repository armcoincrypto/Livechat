<?php

declare(strict_types=1);

namespace iEXPackages\Payments\Core\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use RuntimeException;

trait RedirectResponseTrait
{
    public function redirect(): void
    {
        $this->getRedirectResponse()->send();
    }

    public function getRedirectResponse(): RedirectResponse|HttpResponse
    {
        $this->assertRedirectable();

        $method = strtoupper((string) $this->getRedirectMethod());
        $url    = (string) $this->getRedirectUrl();

        if ($method === 'GET') {
            return redirect()->away($url);
        }

        // POST редирект через auto-submit форму (безопасно экранируем)
        $fieldsHtml = '';

        foreach ($this->getRedirectData() as $key => $value) {
            $fieldsHtml .= sprintf(
                "<input type=\"hidden\" name=\"%s\" value=\"%s\">\n",
                htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            );
        }

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="no-referrer">
  <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit();">
  <form action="{$this->escapeHtmlAttr($url)}" method="post">
    <noscript>
      <p>Для продолжения нажмите кнопку ниже.</p>
    </noscript>
    {$fieldsHtml}
    <button type="submit">Continue</button>
  </form>
</body>
</html>
HTML;



        return new HttpResponse($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Проверка корректности редиректа.
     */
    protected function assertRedirectable(): void
    {
        if (!method_exists($this, 'isRedirect') || !$this->isRedirect()) {
            throw new RuntimeException('Этот ответ не поддерживает редирект.');
        }

        $url = $this->getRedirectUrl();

        if (!is_string($url) || trim($url) === '') {
            throw new RuntimeException('redirectUrl не может быть пустым.');
        }

        $method = strtoupper((string) $this->getRedirectMethod());

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new RuntimeException('Недопустимый метод редиректа: ' . $method);
        }

        $data = $this->getRedirectData();

        if (!is_array($data)) {
            throw new RuntimeException('redirectData должен быть массивом.');
        }
    }

    protected function escapeHtmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
