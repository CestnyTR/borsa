<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Girişi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <header class="bg-gradient-to-r from-yellow-500 to-yellow-600 shadow-lg">
            <div class="container mx-auto px-4 py-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-white">🔐 Admin Girişi</h1>
                    <p class="text-yellow-100 mt-2">Yönetim paneline erişmek için giriş yapın.</p>
                </div>
                <a href="/" class="text-yellow-100 hover:text-white text-sm underline">Dashboard</a>
            </div>
        </header>

        <div class="container mx-auto px-4 py-10 flex justify-center">
            <div class="w-full max-w-md">
                @if(config('app.env') !== 'production')
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold">Demo Giriş Bilgileri</p>
                            <span class="text-xs bg-blue-100 px-2 py-1 rounded">Sadece demo</span>
                        </div>
                        <p class="text-sm mt-2">
                            E-posta: <span class="font-mono font-semibold">test@example.com</span><br>
                            Şifre: <span class="font-mono font-semibold">password</span>
                        </p>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow-md p-6">

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">E-posta</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="ör: test@example.com"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Şifre</label>
                    <input
                        type="password"
                        name="password"
                        required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="••••••••"
                    />
                </div>

                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4">
                        <span>Beni hatırla</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
                    Giriş Yap
                </button>
            </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
