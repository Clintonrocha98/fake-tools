<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\DTOs;

use JsonSerializable;

/**
 * Uma página de `GET /v2/transfer` — os itens e o `cursor` da próxima página.
 * `cursor: null` é o sinal de fim; `starkbank:poll-extrato` não pagina sozinho,
 * o `after` chega como opção de CLI.
 */
final readonly class TransferListPage implements JsonSerializable
{
    /**
     * @param  list<TransferView>  $transfers
     */
    public function __construct(
        public array $transfers,
        public ?string $cursor,
    ) {}

    /**
     * @return array{transfers: list<TransferView>, cursor: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'transfers' => $this->transfers,
            'cursor' => $this->cursor,
        ];
    }
}
