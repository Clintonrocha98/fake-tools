<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\Exceptions\BrcodePaymentNotFoundException;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Support\StarkbankLog;

/**
 * `GET /v2/brcode-payment/{id}` — a releitura autoritativa ("webhook = trigger,
 * GET = truth"). É dela que `ConfirmBrcodePaymentSettlement` monta o Settlement
 * Fact, e é ela que faz o tempo passar: o avanço lazy roda ANTES de responder,
 * então o estado lido aqui é o mesmo que a varredura do extrato veria no mesmo
 * instante.
 */
final readonly class GetBrcodePayment
{
    public function __construct(private AdvanceBrcodePaymentStatus $advance) {}

    public function handle(string $id): BrcodePayment
    {
        $payment = BrcodePayment::query()->whereKey($id)->first();

        if (!$payment instanceof BrcodePayment) {
            StarkbankLog::warning('fake-starkbank.brcode: releitura de id que este fake nunca criou — 404 em vez de payment vazio, que o consumidor leria como resposta malformada', [
                'payment_id' => $id,
            ]);

            throw BrcodePaymentNotFoundException::forId($id);
        }

        return $this->advance->handle($payment);
    }
}
