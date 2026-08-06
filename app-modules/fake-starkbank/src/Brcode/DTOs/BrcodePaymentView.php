<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Support\WireTags;
use He4rt\FakeStarkbank\Support\WireTimestamp;
use JsonSerializable;

/**
 * O shape de um pagamento de BR Code na wire, em camelCase. Um só para os três
 * usos — o eco de `POST /v2/brcode-payment`, a releitura de
 * `GET /v2/brcode-payment/{id}` e cada item da listagem —, porque os três
 * fixtures do consumidor têm exatamente as mesmas keys, como na transfer. A
 * assimetria gordo/magro é da invoice.
 *
 * A `description` não sai aqui: nenhum dos três fixtures a carrega, e devolvê-la
 * transformaria um parâmetro de ida num campo que algum consumidor futuro
 * passaria a ler.
 */
final readonly class BrcodePaymentView implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $brcode,
        public string $taxId,
        public int $amount,
        public string $status,
        public WireTags $tags,
        public string $created,
        public string $updated,
    ) {}

    public static function fromModel(BrcodePayment $payment): self
    {
        return new self(
            id: $payment->id,
            brcode: $payment->brcode,
            taxId: $payment->tax_id,
            amount: $payment->amount,
            status: $payment->status->value,
            tags: $payment->tags,
            created: WireTimestamp::format($payment->created_at),
            updated: WireTimestamp::format($payment->updated_at),
        );
    }

    /**
     * @return array{id: string, brcode: string, taxId: string, amount: int, status: string, tags: list<string>, created: string, updated: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'brcode' => $this->brcode,
            'taxId' => $this->taxId,
            'amount' => $this->amount,
            'status' => $this->status,
            'tags' => $this->tags->toArray(),
            'created' => $this->created,
            'updated' => $this->updated,
        ];
    }
}
