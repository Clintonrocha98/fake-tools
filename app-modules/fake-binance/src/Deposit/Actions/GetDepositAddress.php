<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Actions;

use He4rt\FakeBinance\Deposit\Exceptions\UnsupportedDepositNetworkException;
use RuntimeException;

/**
 * GET /sapi/v1/capital/deposit/address: endereço FIXO por rede, lido de
 * `fake-binance-deposit.addresses` (ADR-0003) — o mesmo endereço que
 * {@see AnnounceCryptoDeposit} carimba nas chegadas, para que o hisrec liste
 * depósitos exatamente no endereço que esta rota anunciou. Sem `network`, vale
 * a primeira rede do mapa — o "default da coin" da venue real.
 *
 * @phpstan-type AddressShape array{coin: string, address: string, tag: string, url: string}
 */
final readonly class GetDepositAddress
{
    /**
     * @return AddressShape
     */
    public function handle(string $coin, ?string $network = null): array
    {
        $coin = mb_strtoupper($coin);
        $address = $this->addressFor($network === null ? null : mb_strtoupper($network));

        return [
            'coin' => $coin,
            'address' => $address,
            'tag' => '',
            'url' => 'https://explorer.fake-binance.local/address/'.$address,
        ];
    }

    private function addressFor(?string $network): string
    {
        $addresses = config()->array('fake-binance-deposit.addresses');

        if ($network === null) {
            $first = reset($addresses);

            throw_unless(is_string($first) && $first !== '', RuntimeException::class, 'fake-binance-deposit.addresses must have at least one network.');

            return $first;
        }

        $address = $addresses[$network] ?? null;

        if (!is_string($address) || $address === '') {
            throw UnsupportedDepositNetworkException::forNetwork($network);
        }

        return $address;
    }
}
