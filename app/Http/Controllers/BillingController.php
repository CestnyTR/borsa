<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Services\IyzicoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function __construct(private readonly IyzicoService $iyzico)
    {
    }

    /**
     * Fiyatlandırma / plan seçimi.
     */
    public function pricing(Request $request)
    {
        return view('billing.pricing', [
            'plans' => (array) config('plans.plans', []),
            'currentPlan' => $request->user()->planCode(),
            'configured' => $this->iyzico->isConfigured(),
        ]);
    }

    /**
     * Abonelik durumu.
     */
    public function status(Request $request)
    {
        return view('billing.status', [
            'user' => $request->user(),
            'plan' => $request->user()->plan(),
            'subscription' => $request->user()->subscription,
        ]);
    }

    /**
     * Seçilen plana geçiş başlatır.
     */
    public function subscribe(Request $request, string $plan)
    {
        $plans = (array) config('plans.plans', []);
        if (!isset($plans[$plan])) {
            abort(404);
        }

        // Ücretsiz plan: doğrudan geçir (ödeme yok).
        if ($plan === 'free') {
            $this->activate($request->user()->id, 'free', null, null);
            return redirect()->route('billing.status')->with('success', 'Ücretsiz plana geçildi.');
        }

        $pricingPlanRef = (string) ($plans[$plan]['iyzico_plan_ref'] ?? '');

        try {
            $checkout = $this->iyzico->startSubscriptionCheckout(
                user: $request->user(),
                pricingPlanRef: $pricingPlanRef,
                callbackUrl: route('billing.callback', ['plan' => $plan]),
            );
        } catch (\Throwable $e) {
            return redirect()->route('billing.pricing')->with('error', $e->getMessage());
        }

        // iyzico ödeme formunu sayfaya göm.
        return view('billing.checkout', [
            'content' => $checkout['content'],
            'plan' => $plans[$plan],
        ]);
    }

    /**
     * iyzico callback: ödeme sonucunu doğrula ve aboneliği aktive et.
     */
    public function callback(Request $request, string $plan)
    {
        $token = (string) $request->input('token', '');

        try {
            $result = $this->iyzico->retrieveCheckout($token);
        } catch (\Throwable $e) {
            Log::warning('iyzico callback failed', ['message' => $e->getMessage()]);
            return redirect()->route('billing.pricing')->with('error', 'Ödeme doğrulanamadı: ' . $e->getMessage());
        }

        if (!$result['success']) {
            return redirect()->route('billing.pricing')->with('error', 'Ödeme tamamlanmadı.');
        }

        $this->activate(
            userId: $request->user()->id,
            planCode: $plan,
            iyzicoRef: $result['subscription_ref'],
            endsAt: CarbonImmutable::now()->addMonth(),
        );
        AuditLog::record('subscription.activated', null, ['plan' => $plan]);

        return redirect()->route('billing.status')->with('success', 'Pro plan aktif! Teşekkürler.');
    }

    /**
     * Aboneliği iptal eder (dönem sonuna kadar geçerli kalır).
     */
    public function cancel(Request $request)
    {
        $sub = $request->user()->subscription;
        if ($sub && $sub->plan_code !== 'free') {
            $sub->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
            AuditLog::record('subscription.canceled', null, ['plan' => $sub->plan_code]);
        }

        return redirect()->route('billing.status')->with('success', 'Abonelik iptal edildi.');
    }

    /**
     * iyzico webhook: abonelik yenileme/iptal/ödeme bildirimlerini işler.
     * İmza doğrulanır; ilgili aboneliğin durumu güncellenir.
     */
    public function webhook(Request $request)
    {
        $raw = $request->getContent();
        $signature = $request->header('X-IYZ-SIGNATURE-V3') ?? $request->header('X-IYZ-SIGNATURE');

        if (!$this->iyzico->verifyWebhookSignature($raw, $signature)) {
            Log::warning('iyzico webhook: imza doğrulanamadı', ['ip' => $request->ip()]);
            abort(403);
        }

        $payload = (array) $request->json()->all();
        $ref = $payload['subscriptionReferenceCode'] ?? $payload['referenceCode'] ?? null;
        $event = strtolower((string) ($payload['iyziEventType'] ?? $payload['subscriptionStatus'] ?? $payload['status'] ?? ''));

        if ($ref) {
            $sub = Subscription::query()->where('iyzico_ref', $ref)->first();
            if ($sub) {
                $this->applyWebhookEvent($sub, $event);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function applyWebhookEvent(Subscription $sub, string $event): void
    {
        if (str_contains($event, 'cancel') || str_contains($event, 'expire') || str_contains($event, 'terminate') || str_contains($event, 'unsubscribe')) {
            $sub->update(['status' => 'canceled', 'canceled_at' => now()]);
            return;
        }

        if (str_contains($event, 'fail') || str_contains($event, 'unpaid') || str_contains($event, 'past')) {
            $sub->update(['status' => 'past_due']);
            return;
        }

        // active / renewed / success → yenile ve dönemi uzat.
        $sub->update([
            'status' => 'active',
            'canceled_at' => null,
            'current_period_ends_at' => CarbonImmutable::now()->addMonth(),
        ]);
    }

    private function activate(int $userId, string $planCode, ?string $iyzicoRef, ?CarbonImmutable $endsAt): void
    {
        Subscription::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'plan_code' => $planCode,
                'status' => 'active',
                'iyzico_ref' => $iyzicoRef,
                'current_period_ends_at' => $endsAt,
                'canceled_at' => null,
            ]
        );
    }
}
