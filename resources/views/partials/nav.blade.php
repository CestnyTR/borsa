<nav class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-gray-900 font-semibold">Dashboard</a>

            @auth
                @can('admin')
                    <a href="{{ route('admin.stores.index') }}" class="text-gray-600 hover:text-gray-900">Mağazalar</a>
                    <a href="{{ route('admin.companies.index') }}" class="text-gray-600 hover:text-gray-900">Şirketler (legacy)</a>
                @endcan
            @endauth
        </div>

        <div class="flex items-center gap-4">
            @auth
                <span class="text-sm text-gray-600">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline">Çıkış</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">Giriş</a>
            @endauth
        </div>
    </div>
</nav>

