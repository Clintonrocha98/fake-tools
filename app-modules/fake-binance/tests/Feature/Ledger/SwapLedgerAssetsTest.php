<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Ledger\Actions\SwapLedgerAssets;
use He4rt\FakeBinance\Ledger\DTOs\LedgerFill;
use He4rt\FakeBinance\Ledger\Enums\Side;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;

function swapAction(): SwapLedgerAssets
{
    return new SwapLedgerAssets(new CreditLedgerAccount, new DebitLedgerAccount);
}

it('on a BUY, debits the quote total from `from` and credits the base total to `to` for a single fill', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    swapAction()->handle('BRL', 'USDC', [
        new LedgerFill(qty: '1000', price: '5.10'),
    ], Side::Buy);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($brl->free)->toBe('94900.000000000000000000')
        ->and($usdc->free)->toBe('1000.000000000000000000');
});

it('credits the quote asset and debits the base asset on a SELL-side swap', function (): void {
    // USDC->BRL é sempre SELL no monolito (BRL é quote asset): `from`=USDC (base,
    // gasta = qty) e `to`=BRL (quote, recebida = qty*price) — o inverso do BUY acima.
    (new CreditLedgerAccount)->handle('USDC', '1000');

    swapAction()->handle('USDC', 'BRL', [
        new LedgerFill(qty: '1000', price: '5.10'),
    ], Side::Sell);

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();
    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($usdc->free)->toBe('0.000000000000000000')
        ->and($brl->free)->toBe('5100.000000000000000000');
});

it('sums multiple fills across price levels into one atomic move', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    swapAction()->handle('BRL', 'USDC', [
        new LedgerFill(qty: '500', price: '5.10'),
        new LedgerFill(qty: '500', price: '5.12'),
    ], Side::Buy);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    // 500*5.10 + 500*5.12 = 2550 + 2560 = 5110
    expect($brl->free)->toBe('94890.000000000000000000')
        ->and($usdc->free)->toBe('1000.000000000000000000');
});

it('deducts commission from the received (`to`) asset when commissionAsset matches it', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    swapAction()->handle('BRL', 'USDC', [
        new LedgerFill(qty: '1000', price: '5.10', commission: '1', commissionAsset: 'USDC'),
    ], Side::Buy);

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($usdc->free)->toBe('999.000000000000000000');
});

it('adds commission to the spent (`from`) asset when commissionAsset matches it', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    swapAction()->handle('BRL', 'USDC', [
        new LedgerFill(qty: '1000', price: '5.10', commission: '5', commissionAsset: 'BRL'),
    ], Side::Buy);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('94895.000000000000000000');
});

it('rejects the whole swap when the `from` balance cannot cover the total spent, crediting nothing to `to`', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100');

    try {
        swapAction()->handle('BRL', 'USDC', [
            new LedgerFill(qty: '1000', price: '5.10'),
        ], Side::Buy);
    } catch (InsufficientLedgerBalanceException) {
        // esperado
    }

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('100.000000000000000000')
        ->and(LedgerAccount::query()->where('asset', 'USDC')->exists())->toBeFalse();
});

it('keeps both ledgers coherent across deposit, swap and withdraw in sequence', function (): void {
    // Espelha o cenário do ticket: depósito credita BRL, ordem MARKET troca BRL->USDC,
    // withdraw debita USDC — cada operação deve ver o saldo deixado pela anterior.
    (new CreditLedgerAccount)->handle('BRL', '100000');

    swapAction()->handle('BRL', 'USDC', [
        new LedgerFill(qty: '1000', price: '5.10'),
    ], Side::Buy);

    (new DebitLedgerAccount)->handle('USDC', '400');

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($brl->free)->toBe('94900.000000000000000000')
        ->and($usdc->free)->toBe('600.000000000000000000');
});
