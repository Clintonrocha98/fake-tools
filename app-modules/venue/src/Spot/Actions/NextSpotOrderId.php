<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\Models\SpotOrder;

/**
 * O próximo `orderId` inteiro sequencial. Ferramenta de dev single-consumer, sem
 * concorrência real a proteger: um MAX+1 basta, e não vale uma sequence dedicada
 * numa coluna que não é a PK.
 */
final readonly class NextSpotOrderId
{
    public function handle(): int
    {
        return (int) (SpotOrder::query()->max('order_id') ?? 1_000_000) + 1;
    }
}
