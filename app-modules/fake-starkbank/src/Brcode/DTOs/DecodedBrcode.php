<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

/**
 * O que sobra de um BR Code EMV depois de decodificado: a chave PIX do arranjo
 * (campo 26, sub 01), o valor do campo 54 e o nome do recebedor do campo 59.
 *
 * `amountCentavos` é `null` quando o campo 54 está AUSENTE — o BR Code é
 * dinâmico e quem paga escolhe o valor. Zero seria uma resposta diferente
 * ("cobrança de zero real"), e é essa distinção que decide o `allowChange` do
 * preview e se o `amount` do pagamento precisa bater com o do código.
 */
final readonly class DecodedBrcode
{
    public function __construct(
        public string $pixKey,
        public ?int $amountCentavos,
        public string $receiverName,
        public string $city,
    ) {}

    /**
     * Um BR Code sem valor embutido é o dinâmico: o campo 54 ausente é o que
     * autoriza o pagador a escolher o valor.
     */
    public function allowsAmountChange(): bool
    {
        return $this->amountCentavos === null;
    }
}
