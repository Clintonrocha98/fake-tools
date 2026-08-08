<?php

declare(strict_types=1);

use He4rt\FakeBinance\Withdraw\Support\WithdrawWireId;

it('strips the hyphens of a UUID into the 32 hex chars the venue answers', function (): void {
    expect(WithdrawWireId::for('019fd5a8-d279-72f7-b8ff-0bbd97779bc9'))
        ->toBe('019fd5a8d27972f7b8ff0bbd97779bc9')
        ->toMatch('/^[0-9a-f]{32}$/');
});

it('is a pure reformat: the hex carries the same value the PK holds', function (): void {
    $uuid = '019fd5a8-d279-72f7-b8ff-0bbd97779bc9';

    expect(WithdrawWireId::for($uuid))->toBe(str_replace('-', '', $uuid));
});
