<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Borsa')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="bg-gray-100">
<div class="min-h-screen">
    @include('partials.nav')

    <main class="container mx-auto px-4 py-6">
        @include('partials.flash')
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
