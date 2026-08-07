<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\DTOs\WithdrawHistoryRow;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

/**
 * GET /sapi/v1/capital/withdraw/history: honra os filtros que o consumidor
 * declara em `GetWithdrawHistoryRequest` (`coin`, `withdrawOrderId`, `status`,
 * `startTime`, `endTime`, `limit`) mais o `offset` da doc, e avança o status
 * LAZY de cada linha lida antes de serializar ({@see AdvanceWithdrawStatus}) —
 * a leitura é o único gatilho do avanço.
 *
 * O filtro de `status` é aplicado DEPOIS do avanço lazy, sobre o status que a
 * wire reporta (inclusive `raw_status_override`): um withdraw que amadureceu
 * para Completed nesta leitura já responde a `status=6` — filtrar no SQL
 * congelaria a linha no estado anterior ao avanço.
 */
final readonly class GetWithdrawHistory
{
    public function __construct(
        private AdvanceWithdrawStatus $advance = new AdvanceWithdrawStatus,
    ) {}

    /**
     * @return list<WithdrawHistoryRow>
     */
    public function handle(
        ?string $coin,
        ?string $withdrawOrderId,
        ?int $status = null,
        ?int $startTime = null,
        ?int $endTime = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $query = Withdrawal::query();

        if ($coin !== null) {
            $query->where('coin', mb_strtoupper($coin));
        }

        if ($withdrawOrderId !== null) {
            $query->where('withdraw_order_id', $withdrawOrderId);
        }

        if ($startTime !== null) {
            $query->where('applied_at', '>=', Date::createFromTimestampMs($startTime));
        }

        if ($endTime !== null) {
            $query->where('applied_at', '<=', Date::createFromTimestampMs($endTime));
        }

        $rows = $query->latest('applied_at')
            ->get()
            ->map(fn (Withdrawal $withdrawal): Withdrawal => $this->advance->handle($withdrawal))
            ->map(fn (Withdrawal $withdrawal): WithdrawHistoryRow => WithdrawHistoryRow::fromModel($withdrawal))
            ->when($status !== null, fn ($rows) => $rows->filter(
                fn (WithdrawHistoryRow $row): bool => $row->status === $status,
            ))
            ->slice($offset ?? 0, $limit)
            ->all();

        BinanceLog::info('fake-binance.withdraw: histórico servido com o avanço lazy aplicado antes do filtro — é o que faz um withdraw concluído nesta leitura já aparecer no status pedido', [
            'coin' => $coin,
            'withdraw_order_id' => $withdrawOrderId,
            'status' => $status,
            'servidas' => count($rows),
        ]);

        return array_values($rows);
    }
}
