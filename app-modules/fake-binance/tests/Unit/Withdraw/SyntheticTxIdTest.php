<?php

declare(strict_types=1);

use He4rt\FakeBinance\Withdraw\Enums\TxIdFormat;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;

it('maps each network the fake serves to the format of its chain', function (string $network, TxIdFormat $format): void {
    expect(TxIdFormat::forNetwork($network))->toBe($format);
})->with([
    'Ethereum is EVM hex' => ['ETH', TxIdFormat::EvmHex],
    'Solana is base58' => ['SOL', TxIdFormat::Base58],
    'TRON is bare hex' => ['TRX', TxIdFormat::Hex],
    'an unknown chain never pretends to be EVM' => ['BSC', TxIdFormat::Hex],
]);

it('reads the network case-insensitively, the same normalization the apply does', function (): void {
    expect(TxIdFormat::forNetwork('sol'))->toBe(TxIdFormat::Base58);
});

it('generates an 0x-prefixed 64-hex hash on Ethereum', function (): void {
    expect(SyntheticTxId::forNetwork('ETH'))->toMatch('/^0x[0-9a-f]{64}$/');
});

it('generates a base58 signature on Solana — no 0x prefix, no hex-only alphabet', function (): void {
    $txId = SyntheticTxId::forNetwork('SOL');

    expect($txId)->toMatch('/^[1-9A-HJ-NP-Za-km-z]{88}$/')
        ->and($txId)->not->toStartWith('0x');
});

it('generates a bare 64-hex hash on TRON', function (): void {
    expect(SyntheticTxId::forNetwork('TRX'))->toMatch('/^[0-9a-f]{64}$/');
});

it('never repeats a tx id across calls', function (): void {
    expect(SyntheticTxId::forNetwork('ETH'))->not->toBe(SyntheticTxId::forNetwork('ETH'));
});
