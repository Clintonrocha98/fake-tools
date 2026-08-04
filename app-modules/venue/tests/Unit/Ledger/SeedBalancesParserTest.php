<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Support\SeedBalancesParser;

it('parses the documented "ASSET:AMOUNT,ASSET:AMOUNT" format', function (): void {
    expect(SeedBalancesParser::parse('BRL:100000,USDC:0'))->toBe([
        'BRL' => '100000',
        'USDC' => '0',
    ]);
});

it('trims whitespace around pairs and around each side of the colon', function (): void {
    expect(SeedBalancesParser::parse(' BRL : 100000 , USDC : 0 '))->toBe([
        'BRL' => '100000',
        'USDC' => '0',
    ]);
});

it('returns the asset code as-is, without touching its case', function (): void {
    // Normalizar o case é responsabilidade de CreditLedgerAccount, não do parser.
    expect(SeedBalancesParser::parse('brl:100000'))->toBe(['brl' => '100000']);
});

it('returns an empty array for a null value', function (): void {
    expect(SeedBalancesParser::parse(raw: null))->toBeEmpty();
});

it('returns an empty array for a blank string', function (): void {
    expect(SeedBalancesParser::parse('  '))->toBeEmpty();
});

it('silently skips a pair missing the colon', function (): void {
    expect(SeedBalancesParser::parse('BRL:100000,GARBAGE'))->toBe(['BRL' => '100000']);
});

it('silently skips a pair with a non-numeric amount', function (): void {
    expect(SeedBalancesParser::parse('BRL:100000,USDC:not-a-number'))->toBe(['BRL' => '100000']);
});

it('silently skips a pair with an empty asset', function (): void {
    expect(SeedBalancesParser::parse('BRL:100000,:50'))->toBe(['BRL' => '100000']);
});
