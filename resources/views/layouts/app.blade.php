<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fiyat Paneli')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            DEFAULT: 'var(--brand, #C9A227)',
                        },
                        ink: {
                            900: '#0f172a', 700: '#334155', 500: '#64748b', 400: '#94a3b8',
                        },
                    },
                    keyframes: {
                        pulseDot: { '0%,100%': { opacity: 1 }, '50%': { opacity: .35 } },
                        flash: { '0%': { backgroundColor: 'var(--flash, transparent)' }, '100%': { backgroundColor: 'transparent' } },
                    },
                    animation: {
                        pulseDot: 'pulseDot 1.4s ease-in-out infinite',
                        flashUp: 'flash .8s ease-out',
                    },
                },
            },
        }
    </script>
    <style>
        :root { --brand: {{ $brandColor ?? '#C9A227' }}; }
        body { -webkit-font-smoothing: antialiased; }
        .tnum { font-variant-numeric: tabular-nums; }
    </style>
    @stack('head')
</head>
<body class="bg-slate-50 text-ink-900 min-h-screen">
    @include('partials.nav')

    <main class="mx-auto max-w-6xl px-4 py-6">
        @include('partials.flash')
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
