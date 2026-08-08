<?php

declare(strict_types=1);

namespace He4rt\Control\Http;

use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Routing\Router;

/**
 * Cada recurso do plano de controle é resolvido pela chave que o dev tem em
 * mãos, não pela PK: a fiat order pelo `order_no` que a wire devolve, a spot
 * order pelo `order_id` numérico. `findOrFail`/`firstOrFail` deixa o
 * `ModelNotFoundException` virar 404 no handler — sem um `if` de existência em
 * cada um dos controllers.
 */
final readonly class RegistersControlRouteBindings
{
    public static function register(Router $router): void
    {
        $router->bind('invoice', static fn (string $id): Invoice => Invoice::query()->findOrFail($id));
        $router->bind('transfer', static fn (string $id): Transfer => Transfer::query()->findOrFail($id));
        $router->bind('brcodePayment', static fn (string $id): BrcodePayment => BrcodePayment::query()->findOrFail($id));
        $router->bind('emission', static fn (string $id): WebhookEmission => WebhookEmission::query()->findOrFail($id));

        // A wire da Binance identifica a ordem fiat pelo `orderNo`, e é ele que
        // o consumidor tem na mão quando algo dá errado.
        $router->bind('fiatOrder', static fn (string $orderNo): FiatOrder => FiatOrder::query()->where('order_no', $orderNo)->firstOrFail());
        $router->bind('spotOrder', static fn (string $orderId): SpotOrder => SpotOrder::query()->where('order_id', $orderId)->firstOrFail());
        $router->bind('withdrawal', static fn (string $id): Withdrawal => Withdrawal::query()->findOrFail($id));
        $router->bind('cryptoDeposit', static fn (string $id): CryptoDeposit => CryptoDeposit::query()->findOrFail($id));
    }
}
