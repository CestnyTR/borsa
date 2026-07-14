@extends('layouts.app')

@section('title', 'Denetim Kaydı')

@section('content')
    <h1 class="text-2xl font-extrabold text-ink-900">Denetim Kaydı (Audit Log)</h1>
    <p class="mt-1 text-sm text-ink-500">Yönetici aksiyonları: mağaza, marj ve abonelik değişiklikleri.</p>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-ink-500">
                        <th class="py-2 px-4">Zaman</th>
                        <th class="py-2 px-4">Kullanıcı</th>
                        <th class="py-2 px-4">Aksiyon</th>
                        <th class="py-2 px-4">Konu</th>
                        <th class="py-2 px-4">Detay</th>
                        <th class="py-2 px-4">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-t border-slate-100">
                            <td class="py-2 px-4 whitespace-nowrap text-ink-600">{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="py-2 px-4">{{ $log->user?->email ?? '—' }}</td>
                            <td class="py-2 px-4"><span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ $log->action }}</span></td>
                            <td class="py-2 px-4 text-ink-600">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                            <td class="py-2 px-4 text-ink-500 font-mono text-xs">{{ $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : '' }}</td>
                            <td class="py-2 px-4 text-ink-400">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-ink-400">Henüz kayıt yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
