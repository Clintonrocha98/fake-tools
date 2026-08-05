<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\Models\Withdrawal;

/**
 * Cenário do painel: congela/descongela um withdraw — enquanto `frozen`, o
 * avanço lazy ({@see AdvanceWithdrawStatus}) nunca calcula um novo status pela
 * idade desde `applied_at`.
 */
final readonly class SetWithdrawFrozen
{
    public function handle(Withdrawal $withdrawal, bool $frozen): Withdrawal
    {
        $withdrawal->update(['frozen' => $frozen]);

        return $withdrawal->refresh();
    }
}
