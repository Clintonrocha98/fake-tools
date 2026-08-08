<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Support;

use He4rt\FakeBinance\Withdraw\Enums\TxIdFormat;

/**
 * Gera o `txId` sintético de um movimento on-chain do fake — o withdraw ao
 * alcançar {@see \He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus::Completed} e
 * a chegada de cripto anunciada. O formato é sempre o da REDE
 * ({@see TxIdFormat}), nunca um formato único para todas: ver ADR-0005.
 */
final readonly class SyntheticTxId
{
    /**
     * Alfabeto base58 do Bitcoin — sem `0`, `O`, `I` e `l`, os quatro
     * caracteres que se confundem entre si numa tela.
     */
    private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Comprimento típico de uma assinatura Solana (64 bytes) em base58.
     */
    private const int SOLANA_SIGNATURE_LENGTH = 88;

    /**
     * Um hash de transação tem 32 bytes — 64 caracteres em hex — tanto na
     * Ethereum quanto na TRON.
     */
    private const int HASH_BYTE_LENGTH = 32;

    public static function forNetwork(string $network): string
    {
        return match (TxIdFormat::forNetwork($network)) {
            TxIdFormat::EvmHex => '0x'.self::hex(),
            TxIdFormat::Hex => self::hex(),
            TxIdFormat::Base58 => self::base58(),
        };
    }

    private static function hex(): string
    {
        return bin2hex(random_bytes(self::HASH_BYTE_LENGTH));
    }

    private static function base58(): string
    {
        $alphabetLastIndex = mb_strlen(self::BASE58_ALPHABET) - 1;
        $signature = '';

        for ($index = 0; $index < self::SOLANA_SIGNATURE_LENGTH; $index++) {
            $signature .= self::BASE58_ALPHABET[random_int(0, $alphabetLastIndex)];
        }

        return $signature;
    }
}
