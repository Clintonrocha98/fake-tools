<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Actions;

use He4rt\FakeBinance\Deposit\DTOs\DepositHistoryRow;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use Illuminate\Support\Facades\Date;

/**
 * GET /sapi/v1/capital/deposit/hisrec: honra os filtros que o consumidor
 * declara em `GetDepositHistoryRequest` (`coin`, `network`, `status`) mais os
 * da doc (`txId`, `startTime`, `endTime`, `limit`, `offset`), e avança o
 * status LAZY de cada linha lida antes de serializar
 * ({@see AdvanceCryptoDepositStatus}) — a leitura é o único gatilho do avanço
 * e do crédito no ledger.
 *
 * O filtro de `status` é aplicado DEPOIS do avanço lazy, sobre o status que a
 * wire reporta — um depósito que amadureceu para Credited nesta leitura já
 * responde a `status=6`.
 */
final readonly class GetCryptoDepositHistory
{
    public function __construct(
        private AdvanceCryptoDepositStatus $advance = new AdvanceCryptoDepositStatus,
    ) {}

    /**
     * @return list<DepositHistoryRow>
     */
    public function handle(
        ?string $coin = null,
        ?string $network = null,
        ?int $status = null,
        ?string $txId = null,
        ?int $startTime = null,
        ?int $endTime = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $query = CryptoDeposit::query();

        if ($coin !== null) {
            $query->where('coin', mb_strtoupper($coin));
        }

        if ($network !== null) {
            $query->where('network', mb_strtoupper($network));
        }

        if ($txId !== null) {
            $query->where('tx_id', $txId);
        }

        if ($startTime !== null) {
            $query->where('announced_at', '>=', Date::createFromTimestampMs($startTime));
        }

        if ($endTime !== null) {
            $query->where('announced_at', '<=', Date::createFromTimestampMs($endTime));
        }

        $rows = $query->latest('announced_at')
            ->get()
            ->map(fn (CryptoDeposit $deposit): CryptoDeposit => $this->advance->handle($deposit))
            ->map(fn (CryptoDeposit $deposit): DepositHistoryRow => DepositHistoryRow::fromModel($deposit))
            ->when($status !== null, fn ($rows) => $rows->filter(
                fn (DepositHistoryRow $row): bool => $row->status === $status,
            ))
            ->slice($offset ?? 0, $limit)
            ->all();

        return array_values($rows);
    }
}
