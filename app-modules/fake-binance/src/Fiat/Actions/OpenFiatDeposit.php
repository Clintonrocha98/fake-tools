<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Exceptions\FiatDepositRefusedException;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Fiat\Support\FiatOrderNumber;
use He4rt\FakeBinance\Fiat\Support\PaymentMethodMatcher;
use He4rt\FakeBinance\Support\BinanceLog;

/**
 * POST /sapi/v1/fiat/deposit — abre a ordem em {@see FiatOrderStatus::Processing}
 * com o brcode já anexado (imediato por default; "brcode só após N leituras" é
 * cenário do painel). Toda recusa aqui é síncrona e "sob comando": cada guarda
 * lê um switch de `config('fake-binance-fiat')`, nunca uma decisão aleatória.
 */
final readonly class OpenFiatDeposit
{
    public function __construct(
        private BuildStaticBrcode $brcode = new BuildStaticBrcode,
    ) {}

    /**
     * `$amount` chega cru da wire (body JSON) — validado como decimal por
     * {@see \He4rt\FakeBinance\Fiat\Http\Requests\CreateFiatDepositRequest}, mas
     * nunca `numeric-string` estaticamente neste limite de entrada.
     */
    public function handle(string $currency, string $paymentMethod, string $amount): FiatOrder
    {
        $this->guardServiceEnabled();
        $this->guardSupportedCurrencyAndMethod($currency, $paymentMethod);
        $this->guardDepositLimit($amount);

        $orderNo = FiatOrderNumber::generate(
            static fn (string $candidate): bool => FiatOrder::query()->where('order_no', $candidate)->exists(),
        );

        $order = FiatOrder::query()->create([
            'order_no' => $orderNo,
            'currency' => mb_strtoupper($currency),
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'status' => FiatOrderStatus::Processing,
            'brcode' => $this->brcode->handle($amount),
        ]);

        BinanceLog::info('fake-binance.fiat: depósito aberto com BR Code EMV estático — o valor da ordem viaja no campo 54, então o preview do StarkBank devolve o mesmo montante que o consumidor pediu', [
            'order_no' => $orderNo,
            'currency' => $order->currency,
            'amount' => $amount,
            'pix_key' => config()->string('fake-binance-fiat.pix_key', 'funding@fake-binance.dev'),
        ]);

        return $order;
    }

    private function guardServiceEnabled(): void
    {
        if (!config()->boolean('fake-binance-fiat.deposit_enabled', default: true)) {
            throw FiatDepositRefusedException::serviceNotEnabled();
        }
    }

    private function guardSupportedCurrencyAndMethod(string $currency, string $paymentMethod): void
    {
        $supportedCurrency = config()->string('fake-binance-fiat.supported_currency', 'BRL');
        $supportedMethod = config()->string('fake-binance-fiat.supported_payment_method', 'Pix');

        if (mb_strtoupper($currency) !== mb_strtoupper($supportedCurrency)
            || !PaymentMethodMatcher::matches($paymentMethod, $supportedMethod)) {
            throw FiatDepositRefusedException::unsupportedCurrencyOrMethod($currency, $paymentMethod);
        }
    }

    private function guardDepositLimit(string $amount): void
    {
        /** @var string|null $limit */
        $limit = config('fake-binance-fiat.deposit_limit');

        // Um `deposit_limit` mal configurado (não numérico) nunca deve derrubar o
        // endpoint — fail-open para "sem limite", igual a `null`.
        if (!is_numeric($limit) || !is_numeric($amount)) {
            return;
        }

        if (bccomp($amount, $limit, 18) > 0) {
            throw FiatDepositRefusedException::depositLimitExceeded($amount, $limit);
        }
    }
}
