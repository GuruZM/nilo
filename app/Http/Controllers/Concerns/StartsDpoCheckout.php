<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Payment;
use App\Services\CouponService;
use App\Services\Dpo\DpoClient;
use App\Services\Dpo\DpoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared DPO handoff: create the transaction token for a pending Payment and
 * send the browser to DPO's hosted payment page.
 */
trait StartsDpoCheckout
{
    protected function startDpoCheckout(Payment $payment, DpoClient $client, CouponService $coupons): RedirectResponse|Response
    {
        try {
            $result = $client->createToken(
                transaction: [
                    'amount' => $payment->chargedAmount(),
                    'currency' => $payment->chargedCurrency(),
                    'company_ref' => $payment->company_ref,
                    // DPO's callbacks must use APP_URL, not the browsing host — their WAF
                    // rejects any request whose body contains a localhost URL.
                    'redirect_url' => $this->dpoCallbackUrl('subscription.payment.dpo.return'),
                    'back_url' => $this->dpoCallbackUrl('subscription.payment.dpo.cancel'),
                    'ptl_hours' => (int) config('services.dpo.ptl_hours', 24),
                    'customer_first_name' => $this->firstName($payment->user?->name),
                    'customer_last_name' => $this->lastName($payment->user?->name),
                    'customer_email' => $payment->user?->email,
                    'customer_phone' => $this->dpoPhone($payment->phone_number),
                    // Nilo sells into one market, so the country is known up
                    // front and there is no reason to make the customer pick
                    // it out of a list on the gateway's page.
                    'customer_country' => config('services.dpo.customer_country'),
                    'default_payment' => config('services.dpo.default_payment'),
                    'default_payment_country' => config('services.dpo.default_payment_country'),
                ],
                service: [
                    'description' => "{$payment->plan->name} — {$payment->plan->billing_period} subscription",
                ],
            );
        } catch (DpoException $exception) {
            Log::error('DPO createToken failed', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
                'callback_base' => $this->dpoCallbackBase(),
                'hint' => $this->unreachableCallbackHost() !== null
                    ? 'DPO cannot reach this callback host, and its WAF answers with a CloudFront 403 instead of XML. Point DPO_CALLBACK_BASE at a publicly resolvable https host.'
                    : null,
            ]);

            $payment->update([
                'status' => 'rejected',
                'gateway_status' => 'failed',
                'gateway_response' => $exception->response,
            ]);

            $payment->subscription?->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // The charge never happened, so a single-use coupon must not be
            // burnt by a gateway outage.
            $coupons->release($payment);

            // A gateway that answered and said no is not a gateway that is
            // down, and telling the customer to "try again" when the answer
            // will not change just wastes their afternoon. The refusal itself
            // stays out of the message — it is written for the merchant, and
            // sends the reader to DPO's support desk rather than ours.
            return back()->with('error', $this->wasRefusedByGateway($exception)
                ? 'The payment provider turned down this transaction. Please pay another way, or contact us and we will sort it out.'
                : 'We could not reach the payment provider. Please try again, or pay another way.');
        }

        $payment->update([
            'dpo_transaction_token' => $result->transactionToken,
            'gateway_response' => $result->raw,
        ]);

        $payment->subscription?->update(['payment_reference' => $result->transactionRef]);

        // DPO's hosted payment page must be a real browser navigation, not an Inertia
        // XHR visit — Inertia::location() sends a 409 that the client turns into one.
        return Inertia::location($client->paymentUrl($result->transactionToken));
    }

    /**
     * DPO rejects the whole transaction unless customerPhone is 6–20 digits only,
     * so strip formatting and drop the optional field rather than fail the payment.
     */
    private function dpoPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 6 && strlen($digits) <= 20 ? $digits : null;
    }

    /**
     * Whether DPO answered and declined, as opposed to never answering at all.
     *
     * A decline carries a result code; a timeout, a DNS failure or a WAF block
     * carries only a raw body, because nothing well-formed ever came back.
     */
    private function wasRefusedByGateway(DpoException $exception): bool
    {
        return isset($exception->response['Result']);
    }

    private function dpoCallbackUrl(string $route): string
    {
        return $this->dpoCallbackBase().route($route, absolute: false);
    }

    private function dpoCallbackBase(): string
    {
        return rtrim(config('services.dpo.callback_base') ?: config('app.url'), '/');
    }

    /**
     * The callback host if DPO's WAF will refuse it, null otherwise.
     *
     * A body carrying a loopback URL is turned away as a CloudFront 403 — an
     * HTML page where the XML belongs, which surfaces as a generic "could not
     * reach the provider". Naming the host in the log turns that dead end into
     * a one-line fix.
     *
     * Only loopback is listed, because only loopback was observed to fail. A
     * development TLD such as .test passes: DPO never fetches the callback
     * itself, it redirects the customer's browser, which resolves the host
     * locally just as it would any other.
     */
    private function unreachableCallbackHost(): ?string
    {
        $host = (string) parse_url($this->dpoCallbackBase(), PHP_URL_HOST);

        $isLoopback = $host === ''
            || preg_match('/^(localhost|0\.0\.0\.0|\[?::1\]?)$/i', $host) === 1
            || preg_match('/^127\./', $host) === 1;

        return $isLoopback ? $host : null;
    }

    private function firstName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return trim(explode(' ', $name, 2)[0]);
    }

    private function lastName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $parts = explode(' ', $name, 2);

        return $parts[1] ?? null;
    }
}
