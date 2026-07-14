@extends('layouts.app')

@section('title', 'Planlar')

@section('content')
    <div class="text-center max-w-2xl mx-auto">
        <h1 class="text-3xl font-extrabold text-ink-900">Size uygun planı seçin</h1>
        <p class="mt-2 text-ink-500">Ücretsiz başlayın, işiniz büyüdükçe Pro'ya geçin. İstediğiniz zaman iptal edin.</p>
    </div>

    @unless($configured)
        <div class="mt-6 max-w-2xl mx-auto rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
            Ödeme sağlayıcısı (iyzico) henüz yapılandırılmadı. Anahtarlar <code>.env</code>'e girildiğinde Pro aboneliği aktifleşecek.
        </div>
    @endunless

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
        @foreach($plans as $code => $plan)
            @php $isCurrent = $currentPlan === $code; $isPro = $code === 'pro'; @endphp
            <div class="rounded-2xl border bg-white p-6 shadow-sm {{ $isPro ? 'border-2' : 'border-slate-200' }}"
                 style="{{ $isPro ? 'border-color: var(--brand)' : '' }}">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-ink-900">{{ $plan['name'] }}</h2>
                    @if($isCurrent)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Mevcut plan</span>
                    @elseif($isPro)
                        <span class="rounded-full px-3 py-1 text-xs font-semibold text-white" style="background: var(--brand)">Önerilen</span>
                    @endif
                </div>

                <div class="mt-4">
                    <span class="text-4xl font-extrabold text-ink-900">{{ number_format($plan['price'], 0, ',', '.') }} ₺</span>
                    <span class="text-ink-500">/ ay</span>
                </div>

                <ul class="mt-5 space-y-2 text-sm text-ink-700">
                    @foreach($plan['highlights'] as $line)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5" style="color: var(--brand)">✓</span> {{ $line }}
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6">
                    @if($isCurrent)
                        <button disabled class="w-full rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-ink-400">
                            Bu plandasınız
                        </button>
                    @else
                        <form method="POST" action="{{ route('billing.subscribe', $code) }}">
                            @csrf
                            <button class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white"
                                    style="background: {{ $isPro ? 'var(--brand)' : '#0f172a' }}">
                                {{ $isPro ? 'Pro\'ya yükselt' : 'Ücretsiz plana geç' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
