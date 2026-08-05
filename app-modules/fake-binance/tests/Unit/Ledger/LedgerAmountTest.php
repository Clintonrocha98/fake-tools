<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;

it('trims trailing zeros from a padded decimal', function (): void {
    expect(LedgerAmount::wire('100000.000000000000000000'))->toBe('100000');
});

it('collapses an all-zero decimal to a bare zero', function (): void {
    expect(LedgerAmount::wire('0.000000000000000000'))->toBe('0');
});

it('preserves significant fractional digits', function (): void {
    expect(LedgerAmount::wire('123.450000000000000000'))->toBe('123.45');
});

it('leaves an integer-looking string untouched', function (): void {
    expect(LedgerAmount::wire('0'))->toBe('0');
});
