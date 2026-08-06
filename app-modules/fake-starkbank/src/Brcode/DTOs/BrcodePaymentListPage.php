<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

use JsonSerializable;

/**
 * Uma página de `GET /v2/brcode-payment` — os itens e o `cursor` da próxima
 * página. `cursor: null` é o sinal de fim; a varredura do consumidor não
 * pagina sozinha, o `after` chega como opção de CLI.
 */
final readonly class BrcodePaymentListPage implements JsonSerializable
{
    /**
     * @param  list<BrcodePaymentView>  $payments
     */
    public function __construct(
        public array $payments,
        public ?string $cursor,
    ) {}

    /**
     * @return array{payments: list<BrcodePaymentView>, cursor: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'payments' => $this->payments,
            'cursor' => $this->cursor,
        ];
    }
}
