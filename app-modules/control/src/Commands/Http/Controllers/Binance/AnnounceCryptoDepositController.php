<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\AnnounceCryptoDepositRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Deposit\Actions\AnnounceCryptoDeposit;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/crypto-deposits` — a única perna cujo dinheiro não
 * nasce de um pedido do consumidor. Anunciar nunca move saldo: o crédito só
 * acontece quando o avanço lazy leva o depósito a Credited.
 *
 * Sem Resource no painel: a superfície humana deste gesto é o comando
 * `fake-binance:announce-deposit`, e é a mesma Action.
 */
final readonly class AnnounceCryptoDepositController
{
    public function __construct(private AnnounceCryptoDeposit $announce) {}

    public function __invoke(AnnounceCryptoDepositRequest $request): JsonResponse
    {
        $deposito = $this->announce->handle(
            $request->coin(),
            $request->network(),
            $request->amount(),
            $request->txId(),
        );

        return response()->json(['cryptoDeposit' => ResourceRows::cryptoDeposit($deposito)->toArray()], 201);
    }
}
