<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates the ledger account when crediting an asset for the first time', function (): void {
    $account = (new CreditLedgerAccount)('BRL', '100000');

    expect($account->asset)->toBe('BRL')
        ->and($account->free)->toBe('100000.000000000000000000')
        ->and($account->locked)->toBe('0.000000000000000000');

    expect(LedgerAccount::query()->where('asset', 'BRL')->count())->toBe(1);
});

it('adds to the existing free balance on a second credit', function (): void {
    $credit = new CreditLedgerAccount;

    $credit('USDC', '100');
    $account = $credit('USDC', '50.5');

    expect($account->free)->toBe('150.500000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->count())->toBe(1);
});

it('never touches the locked balance', function (): void {
    $account = (new CreditLedgerAccount)('BRL', '10');

    expect($account->locked)->toBe('0.000000000000000000');
});

it('treats an asset code as case-insensitive: crediting usdc and USDC lands on one account each', function (): void {
    $credit = new CreditLedgerAccount;

    $credit('usdc', '100');
    $account = $credit('USDC', '50');

    expect($account->asset)->toBe('USDC')
        ->and($account->free)->toBe('150.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->count())->toBe(1);
});

it('recovers when two concurrent creates race for the same new asset', function (): void {
    // Simula a corrida: logo após o lockForUpdate()->first() desta chamada não achar
    // nada, "outra transação" insere a linha do mesmo asset antes do create() desta
    // — o savepoint em torno do create() isola a violação de unicidade sem abortar a
    // transação externa, e a releitura com lock pega a linha da vencedora.
    $raced = false;

    DB::listen(function ($query) use (&$raced): void {
        $isFirstLookup = !$raced
            && str_starts_with(mb_strtolower((string) $query->sql), 'select')
            && str_contains((string) $query->sql, 'venue_ledger_accounts');

        if ($isFirstLookup) {
            $raced = true;

            DB::table('venue_ledger_accounts')->insert([
                'id' => Str::uuid()->toString(),
                'asset' => 'BRL',
                'free' => '0',
                'locked' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $account = (new CreditLedgerAccount)('BRL', '100');

    expect($account->free)->toBe('100.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'BRL')->count())->toBe(1);
});
