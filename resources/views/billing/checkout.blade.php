@extends('layouts.app')

@section('title', 'Ödeme')

@section('content')
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-extrabold text-ink-900">{{ $plan['name'] }} — Ödeme</h1>
        <p class="mt-1 text-sm text-ink-500">Ödeme iyzico güvencesiyle alınır.</p>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
            {{-- iyzico ödeme formu (checkoutFormContent) buraya gömülür --}}
            <div id="iyzipay-checkout-form" class="responsive">
                {!! $content !!}
            </div>
        </div>
    </div>
@endsection
