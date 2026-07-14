<nav class="bg-white border-b border-slate-200">
    <div class="mx-auto max-w-6xl px-4 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-extrabold text-ink-900">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white text-sm"
                      style="background: var(--brand, #C9A227)">₺</span>
                Fiyat Paneli
            </a>

            @auth
                @can('admin')
                    <div class="hidden sm:flex items-center gap-5 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-ink-700 hover:text-ink-900 font-medium">Panel</a>
                        <a href="{{ route('admin.stores.index') }}" class="text-ink-500 hover:text-ink-900">Mağazalar</a>
                        <a href="{{ route('admin.companies.index') }}" class="text-ink-500 hover:text-ink-900">Şirketler</a>
                    </div>
                @endcan
            @endauth
        </div>

        <div class="flex items-center gap-4">
            @auth
                <span class="hidden sm:inline text-sm text-ink-500">{{ auth()->user()->email }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-ink-500 hover:text-ink-900">Çıkış</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-sm text-ink-500 hover:text-ink-900">Giriş</a>
            @endauth
        </div>
    </div>
</nav>
