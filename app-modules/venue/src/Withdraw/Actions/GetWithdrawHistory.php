<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\DTOs\WithdrawHistoryRow;
use He4rt\Venue\Withdraw\Models\Withdrawal;

/**
 * GET /sapi/v1/capital/withdraw/history: filtra por `coin` + `withdrawOrderId` (o
 * mesmo par que `treasury:reconcile-offramp-withdraws` usa para encontrar o
 * withdraw) e avança o status LAZY de cada linha lida antes de serializar
 * ({@see AdvanceWithdrawStatus}) — a leitura é o único gatilho do avanço.
 */
final readonly class GetWithdrawHistory
{
    public function __construct(
        private AdvanceWithdrawStatus $advance = new AdvanceWithdrawStatus,
    ) {}

    /**
     * @return list<WithdrawHistoryRow>
     */
    public function handle(?string $coin, ?string $withdrawOrderId): array
    {
        $query = Withdrawal::query();

        if ($coin !== null) {
            $query->where('coin', mb_strtoupper($coin));
        }

        if ($withdrawOrderId !== null) {
            $query->where('withdraw_order_id', $withdrawOrderId);
        }

        $rows = $query->latest('applied_at')
            ->get()
            ->map(fn (Withdrawal $withdrawal): Withdrawal => $this->advance->handle($withdrawal))
            ->map(fn (Withdrawal $withdrawal): WithdrawHistoryRow => WithdrawHistoryRow::fromModel($withdrawal))
            ->all();

        return array_values($rows);
    }
}
