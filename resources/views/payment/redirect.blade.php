<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Переадресация') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md flex flex-col items-center animate-fade-in">
    <svg class="w-14 h-14 mb-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
    </svg>
    <h2 class="text-xl font-semibold mb-2 text-center">{{ __('Ожидание перенаправления на платёжную систему') }}...</h2>
    <p class="text-gray-500 text-center mb-6">
        {{ __('Пожалуйста, подождите. Вы будете автоматически перенаправлены через пару секунд.') }}
    </p>

    {!! $html !!}

    <button onclick="document.getElementById('autosubmit').submit()" class="mt-2 text-blue-600 hover:underline text-sm">
        {{ __('Если перенаправление не произошло, нажмите сюда') }}
    </button>
</div>
<script>
    setTimeout(function() {
        document.getElementById('autosubmit').submit();
    }, 1200);
</script>
</body>
</html>
