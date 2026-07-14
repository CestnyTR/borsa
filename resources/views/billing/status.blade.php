@extends('layouts.app')

@section('title', 'Aboneliğim')

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-extrabold text-ink-900">Aboneliğim</h1>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm text-ink-500">Mevcut plan</div>
                    <div class="text-2xl font-bold text-ink-900">{{ $plan['name'] ?? '—' }}</div>
                </div>
                @php $active = $subscription && $subscription->isActive(); @endphp
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-ink-500' }}">
                    {{ $subscription?->status ?? 'ücretsiz' }}
                </span>
            </div>

            @if($subscription && $subscription->current_period_ends_at)
                <div class="mt-4 text-sm text-ink-600">
                    Dönem bitişi:
                    <span class="font-medium">{{ $subscription->current_period_ends_at->format('d.m.Y') }}</span>
                </div>
            @endif

            <ul class="mt-5 space-y-2 text-sm text-ink-700">
                @foreach(($plan['highlights'] ?? []) as $line)
                    <li class="flex items-start gap-2"><span style="color: var(--brand)">✓</span> {{ $line }}</li>
                @endforeach
            </ul>

            <div class="mt-6 flex items-center gap-3">
                <a href="{{ route('billing.pricing') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background: var(--brand)">
                    Planları gör / yükselt
                </a>
                @if($subscription && $subscription->plan_code !== 'free' && $subscription->status === 'active')
                    <form method="POST" action="{{ route('billing.cancel') }}"
                          onsubmit="return confirm('Aboneliği iptal etmek istediğinize emin misiniz?')">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-ink-600 hover:bg-slate-50">
                            Aboneliği iptal et
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
