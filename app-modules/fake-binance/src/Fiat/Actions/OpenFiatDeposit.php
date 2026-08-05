<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Exceptions\FiatDepositRefusedException;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;

/**
 * POST /sapi/v1/fiat/deposit — abre a ordem em {@see FiatOrderStatus::Processing}
 * com o brcode já anexado (imediato por default; "brcode só após N leituras" é
 * cenário do painel). Toda recusa aqui é síncrona e "sob comando": cada guarda
 * lê um switch de `config('fake-binance-fiat')`, nunca uma decisão aleatória.
 */
final readonly class OpenFiatDeposit
{
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

        $orderNo = $this->generateOrderNo();

        return FiatOrder::query()->create([
            'order_no' => $orderNo,
            'currency' => mb_strtoupper($currency),
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'status' => FiatOrderStatus::Processing,
            'brcode' => $this->generateBrcode($orderNo),
        ]);
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
            || mb_strtolower($paymentMethod) !== mb_strtolower($supportedMethod)) {
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

    private function generateOrderNo(): string
    {
        do {
            $candidate = sprintf('%013d%03d', now()->getTimestampMs() % 10_000_000_000_000, random_int(0, 999));
        } while (FiatOrder::query()->where('order_no', $candidate)->exists());

        return $candidate;
    }

    private function generateBrcode(string $orderNo): string
    {
        return sprintf('000201BR.GOV.BCB.PIX-FAKE-%s-EMV', $orderNo);
    }
}
