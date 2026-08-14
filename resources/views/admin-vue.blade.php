<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex" />
    <meta name="robots" content="noarchive"/>
    <title>Панель управления</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">


{{--    <link rel="stylesheet" href="/public/output.css">--}}
    @vite(['resources/assets/scss/styles.scss'])
</head>
<body>

<div id="app"></div>

<script>
    var reverbConfig = {
        VITE_REVERB_APP_KEY: '{{ getenv('VITE_REVERB_APP_KEY') }}',
        VITE_REVERB_HOST: '{{ getenv('VITE_REVERB_HOST') }}',
        VITE_REVERB_PORT: '{{ getenv('VITE_REVERB_PORT') }}',
        VITE_REVERB_SCHEME: '{{ getenv('VITE_REVERB_SCHEME') }}'
    };

    var adminConfig = @json($frontAPI);
</script>

@vite(['resources/vuejs/main.ts'])

{{-- KYC Operations Center entry (Blade ops UI; Vue SPA source is not rebuilt here). --}}
<script>
(function () {
  var href = @json(url(config('iexexchanger.admin_folder') . '/kyc-ops'));
  function inject() {
    if (document.getElementById('exswaping-kyc-ops-link')) return;
    var a = document.createElement('a');
    a.id = 'exswaping-kyc-ops-link';
    a.href = href;
    a.textContent = 'KYC Ops';
    a.title = 'KYC Operations Center';
    a.setAttribute('style',
      'position:fixed;right:16px;bottom:16px;z-index:99999;padding:10px 14px;' +
      'border-radius:8px;background:#2563eb;color:#fff;font:600 13px/1.2 system-ui,sans-serif;' +
      'text-decoration:none;box-shadow:0 4px 14px rgba(0,0,0,.2)');
    document.body.appendChild(a);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
</script>

{{-- Pricing ownership panel: hydrate from fees/profit API (Vue source tree not present on this host). --}}
<script src="/static/admin-pricing-ownership-panel.js?v=20260814" defer></script>

</body>
</html>
