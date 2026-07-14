<?php

namespace App\Services;

use App\Models\User;
use Iyzipay\Model\Customer;
use Iyzipay\Model\Subscription\RetrieveSubscriptionCheckoutForm;
use Iyzipay\Model\Subscription\SubscriptionCreateCheckoutForm;
use Iyzipay\Options;
use Iyzipay\Request\Subscription\RetrieveSubscriptionCreateCheckoutFormRequest;
use Iyzipay\Request\Subscription\SubscriptionCreateCheckoutFormRequest;
use RuntimeException;

/**
 * iyzico Abonelik (subscription) entegrasyonu. Anahtarlar tanımlı değilse
 * isConfigured() false döner ve çağrılar açıklayıcı hata fırlatır — böylece
 * sistem anahtarsız da (yapılandırılmamış modda) ayakta kalır.
 */
class IyzicoService
{
    public function isConfigured(): bool
    {
        return filled(config('services.iyzico.api_key'))
            && filled(config('services.iyzico.secret_key'));
    }

    /**
     * Abonelik ödeme formunu başlatır; iyzico'nun döndürdüğü token ve
     * checkoutFormContent (sayfaya gömülen HTML/JS) döner.
     *
     * @return array{token: string, content: string}
     */
    public function startSubscriptionCheckout(User $user, string $pricingPlanRef, string $callbackUrl): array
    {
        $this->assertConfigured();

        if (blank($pricingPlanRef)) {
            throw new RuntimeException('Bu plan için iyzico pricing plan referansı tanımlı değil (IYZICO_PRO_PLAN_REF).');
        }

        [$name, $surname] = $this->splitName($user->name);

        $customer = new Customer();
        $customer->setName($name);
        $customer->setSurname($surname);
        $customer->setEmail($user->email);
        // Gerçek akışta bu alanlar kullanıcıdan alınır; sandbox için yer tutucu.
        $customer->setIdentityNumber('11111111111');
        $customer->setGsmNumber('+905000000000');
        $customer->setBillingContactName($user->name ?? $name);
        $customer->setBillingCity('Istanbul');
        $customer->setBillingCountry('Turkey');
        $customer->setBillingAddress('-');
        $customer->setShippingContactName($user->name ?? $name);
        $customer->setShippingCity('Istanbul');
        $customer->setShippingCountry('Turkey');
        $customer->setShippingAddress('-');

        $request = new SubscriptionCreateCheckoutFormRequest();
        $request->setLocale('tr');
        $request->setConversationId((string) $user->id);
        $request->setPricingPlanReferenceCode($pricingPlanRef);
        $request->setSubscriptionInitialStatus('ACTIVE');
        $request->setCallbackUrl($callbackUrl);
        $request->setCustomer($customer);

        $form = SubscriptionCreateCheckoutForm::create($request, $this->options());

        if ($form->getStatus() !== 'success') {
            throw new RuntimeException('iyzico: ' . ($form->getErrorMessage() ?: 'ödeme formu başlatılamadı.'));
        }

        return [
            'token' => (string) $form->getToken(),
            'content' => (string) $form->getCheckoutFormContent(),
        ];
    }

    /**
     * Callback sonrası ödeme/abonelik sonucunu doğrular.
     *
     * @return array{success: bool, subscription_ref: ?string}
     */
    public function retrieveCheckout(string $token): array
    {
        $this->assertConfigured();

        $request = new RetrieveSubscriptionCreateCheckoutFormRequest();
        $request->setLocale('tr');
        $request->setCheckoutFormToken($token);

        $result = RetrieveSubscriptionCheckoutForm::retrieve($request, $this->options());

        $ref = method_exists($result, 'getReferenceCode') ? $result->getReferenceCode() : null;

        return [
            'success' => $result->getStatus() === 'success',
            'subscription_ref' => $ref !== null ? (string) $ref : null,
        ];
    }

    /**
     * Webhook imzasını doğrular (HMAC-SHA256, secret key ile).
     * iyzico ham gövdeyi imzalar; base64 imzayı sabit-zamanlı karşılaştırırız.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if (!$this->isConfigured() || blank($signature)) {
            return false;
        }

        $expected = base64_encode(
            hash_hmac('sha256', $rawBody, (string) config('services.iyzico.secret_key'), true)
        );

        return hash_equals($expected, (string) $signature);
    }

    private function options(): Options
    {
        $options = new Options();
        $options->setApiKey((string) config('services.iyzico.api_key'));
        $options->setSecretKey((string) config('services.iyzico.secret_key'));
        $options->setBaseUrl((string) config('services.iyzico.base_url'));

        return $options;
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('iyzico yapılandırılmamış (IYZICO_API_KEY / IYZICO_SECRET_KEY .env dosyasına eklenmeli).');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(?string $full): array
    {
        $full = trim((string) $full);
        if ($full === '') {
            return ['Müşteri', '-'];
        }

        $parts = preg_split('/\s+/', $full) ?: [$full];
        $surname = count($parts) > 1 ? (string) array_pop($parts) : '-';

        return [implode(' ', $parts), $surname];
    }
}
