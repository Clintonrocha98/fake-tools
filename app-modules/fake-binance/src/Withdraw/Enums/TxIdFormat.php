<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * O formato do hash de transação de cada chain. Um `txId` vira
 * `settlementTransactionHash` no recibo do consumidor e, cedo ou tarde, um link
 * de explorer numa tela: um hash com prefixo `0x` num explorer de Solana é um
 * link morto, e o painel que o montar vai parecer quebrado sem estar. Uma rede
 * que o fake não conhece cai em {@see self::Hex} — o formato neutro, que nunca
 * finge ser um hash EVM numa chain que não é EVM.
 */
enum TxIdFormat: string implements HasColor, HasDescription, HasLabel
{
    case EvmHex = 'evm_hex';
    case Hex = 'hex';
    case Base58 = 'base58';

    public static function forNetwork(string $network): self
    {
        return match (mb_strtoupper($network)) {
            'ETH' => self::EvmHex,
            'SOL' => self::Base58,
            default => self::Hex,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::EvmHex => 'Hex com prefixo 0x (EVM)',
            self::Hex => 'Hex sem prefixo',
            self::Base58 => 'Base58 (assinatura Solana)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EvmHex => 'info',
            self::Hex => 'gray',
            self::Base58 => 'primary',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EvmHex => '`0x` + 64 hex minúsculos — Ethereum e as chains EVM',
            self::Hex => '64 hex minúsculos, sem prefixo — TRON e o default de uma rede desconhecida',
            self::Base58 => '88 caracteres base58, sem prefixo — a assinatura de 64 bytes da Solana',
        };
    }
}
