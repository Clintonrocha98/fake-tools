<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\Models\SpotOrder;

/**
 * O próximo `orderId` inteiro sequencial — ferramenta de dev single-consumer,
 * sem concorrência real a proteger, por isso um MAX+1 simples basta (não
 * precisa do rigor de auto-increment de uma coluna não-PK, frágil no SQLite).
 */
final readonly class NextSpotOrderId
{
    public function __invoke(): int
    {
        return (int) (SpotOrder::query()->max('order_id') ?? 1_000_000) + 1;
    }
}
