<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Tests\Contract\Support;

use JsonException;

/**
 * Compara uma resposta real do fake contra um fixture de referência (payload gravado
 * do consumidor) por FORMA — nunca por igualdade literal de valor, exceto onde o
 * fixture usa {@see exactValue()}. Toda key do fixture precisa existir na resposta
 * real, com o mesmo tipo PHP; a resposta real pode carregar keys extras (a Binance
 * real também carrega), mas nunca pode faltar uma. Um valor `null` no fixture marca
 * um campo genuinely nullable (ex.: `txId` antes do withdraw completar, `data` numa
 * recusa fiat) e aceita qualquer tipo na resposta real, incluindo null — ids,
 * timestamps e outros valores voláteis nunca entram na comparação porque ela nunca
 * olha o VALOR, só a KEY e o tipo, salvo o literal que o consumidor decide por
 * `switch`/comparação exata (marcado com `exactValue()` no fixture-código).
 */
trait AssertsRecordedShape
{
    /**
     * Keys discriminadoras conhecidas: quando um item de lista as carrega, o
     * pareamento entre fixture e resposta real é por VALOR dessa key (o
     * consumidor busca por ela — {@see ExchangeInfoResponse::filterByType()},
     * `Account::balances` por `asset`), nunca por posição.
     *
     * @var list<string>
     */
    private const array LIST_DISCRIMINATOR_KEYS = ['filterType', 'asset'];

    /**
     * @param  array<array-key, mixed>  $expected
     * @param  array<array-key, mixed>  $actual
     */
    protected function assertMatchesRecordedShape(array $expected, array $actual, string $path = '$'): void
    {
        if (array_is_list($expected)) {
            expect($actual)->toBeList();

            $discriminator = $this->discriminatorKeyFor($expected);

            if ($discriminator !== null) {
                $this->assertMatchesRecordedListByDiscriminator($expected, $actual, $discriminator, $path);

                return;
            }

            // Sem key discriminadora: a lista só faz sentido comparada posição a
            // posição (ex.: um único par ordenado sem identidade própria). A
            // resposta real pode ter itens extras além do fixture (subconjunto,
            // nunca igualdade), mas nunca pode ter menos.
            foreach ($expected as $index => $template) {
                expect($actual)->toHaveKey($index);
                $this->assertMatchesRecordedValue($template, $actual[$index], sprintf('%s[%d]', $path, $index));
            }

            return;
        }

        foreach ($expected as $key => $value) {
            expect($actual)->toHaveKey($key);

            $this->assertMatchesRecordedValue($value, $actual[$key], sprintf('%s.%s', $path, $key));
        }
    }

    /**
     * Carrega um fixture JSON de `tests/Contract/fixtures/{$relativePath}`.
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    protected function loadContractFixture(string $relativePath): array
    {
        $path = dirname(__DIR__).'/fixtures/'.$relativePath;

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode(
            json: (string) file_get_contents($path),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }

    /**
     * Marca um valor do fixture como um literal que o consumidor decide por
     * `switch`/comparação exata — nunca só o tipo. Ex.: `code` de sucesso/recusa
     * fiat ({@see \Brd\IntegrationBinance\Http\Responses\FiatDepositResponse::successful()}
     * exige exatamente `'000000'`).
     */
    protected function exactValue(mixed $value): ExactRecordedValue
    {
        return new ExactRecordedValue($value);
    }

    /**
     * @param  array<array-key, mixed>  $expected
     */
    private function discriminatorKeyFor(array $expected): ?string
    {
        foreach (self::LIST_DISCRIMINATOR_KEYS as $key) {
            $allItemsCarryIt = array_all(
                $expected,
                static fn (mixed $item): bool => is_array($item) && array_key_exists($key, $item),
            );

            if ($allItemsCarryIt) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<array<array-key, mixed>>  $expected
     * @param  array<array-key, mixed>  $actual
     */
    private function assertMatchesRecordedListByDiscriminator(array $expected, array $actual, string $discriminator, string $path): void
    {
        $actualByDiscriminator = [];

        foreach ($actual as $item) {
            expect($item)->toBeArray()->toHaveKey($discriminator);
            $actualByDiscriminator[$item[$discriminator]] = $item;
        }

        foreach ($expected as $template) {
            $discriminatorValue = $template[$discriminator];

            expect($actualByDiscriminator)->toHaveKey($discriminatorValue);

            $this->assertMatchesRecordedShape(
                $template,
                $actualByDiscriminator[$discriminatorValue],
                sprintf('%s[%s=%s]', $path, $discriminator, $discriminatorValue),
            );
        }
    }

    private function assertMatchesRecordedValue(mixed $expected, mixed $actual, string $path): void
    {
        if ($expected instanceof ExactRecordedValue) {
            expect($actual)->toBe($expected->value, sprintf('Valor divergente em %s: esperado exatamente %s.', $path, json_encode($expected->value)));

            return;
        }

        if (is_array($expected)) {
            expect($actual)->toBeArray(sprintf('Esperava um array em %s.', $path));
            $this->assertMatchesRecordedShape($expected, $actual, $path);

            return;
        }

        if ($expected === null) {
            return;
        }

        $expectedType = get_debug_type($expected);
        $actualType = get_debug_type($actual);

        expect($actualType)->toBe($expectedType, sprintf('Tipo divergente em %s: esperado %s, recebido %s.', $path, $expectedType, $actualType));
    }
}
