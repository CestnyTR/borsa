<?php

namespace App\Console\Commands;

use App\MarketData\MarketQuoteService;
use App\Models\MarketQuote;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sistem sağlığını kontrol eder: canlı daemon heartbeat'i ve piyasa verisi
 * tazeliği. Sorun varsa error log'a yazar (log kanalı Slack/mail'e bağlanabilir)
 * ve son durumu cache'e koyar. Scheduler ile birkaç dakikada bir çalışır.
 */
class HealthMonitorCommand extends Command
{
    protected $signature = 'monitor:health {--max-age=10 : Piyasa verisi için maks. yaş (dk)}';

    protected $description = 'Canlı akış ve veri tazeliği sağlık kontrolü + uyarı.';

    public function handle(): int
    {
        $problems = [];

        // 1) Canlı akış daemon'ları (heartbeat).
        foreach ((array) config('market.streamed_sources', []) as $code) {
            if (!Cache::has(MarketQuoteService::streamHeartbeatKey($code))) {
                $problems[] = "Canlı akış daemon'u çalışmıyor: {$code} (harem:listen?).";
            }
        }

        // 2) Piyasa verisi tazeliği.
        $maxAge = (int) $this->option('max-age');
        $latest = MarketQuote::query()->max('fetched_at');
        if ($latest === null) {
            $problems[] = 'Hiç piyasa verisi yok.';
        } else {
            $ageMin = (int) round(CarbonImmutable::parse($latest)->diffInMinutes(now()));
            if ($ageMin > $maxAge) {
                $problems[] = "Piyasa verisi {$ageMin} dk güncellenmedi (limit {$maxAge} dk).";
            }
        }

        $status = $problems === [] ? 'ok' : 'alert';
        Cache::put('system:health:last', [
            'status' => $status,
            'problems' => $problems,
            'checked_at' => now()->toIso8601String(),
        ], 3600);

        if ($problems !== []) {
            Log::error('Sağlık uyarısı', ['problems' => $problems]);
            foreach ($problems as $p) {
                $this->error('[ALERT] ' . $p);
            }

            return self::FAILURE;
        }

        $this->info('[health] OK');

        return self::SUCCESS;
    }
}
