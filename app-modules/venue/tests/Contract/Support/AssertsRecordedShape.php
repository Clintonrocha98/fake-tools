<?php

declare(strict_types=1);

namespace He4rt\Venue\Tests\Contract\Support;

use JsonException;

/**
 * Compara uma resposta real do fake contra um fixture de referência (payload gravado
 * do consumidor) por FORMA — nunca por igualdade literal de valor. Toda key do
 * fixture precisa existir na resposta real, com o mesmo tipo PHP; a resposta real
 * pode carregar keys extras (a Binance real também carrega), mas nunca pode faltar
 * uma. Um valor `null` no fixture marca um campo genuinely nullable (ex.: `txId`
 * antes do withdraw completar, `data` numa recusa fiat) e aceita qualquer tipo na
 * resposta real, incluindo null — ids, timestamps e outros valores voláteis nunca
 * entram na comparação porque ela nunca olha o VALOR, só a KEY e o tipo.
 */
trait AssertsRecordedShape
{
    /**
     * @param  array<array-key, mixed>  $expected
     * @param  array<array-key, mixed>  $actual
     */
    protected function assertMatchesRecordedShape(array $expected, array $actual, string $path = '$'): void
    {
        if (array_is_list($expected)) {
            expect($actual)->toBeList();

            // Cada posição do fixture é seu PRÓPRIO template — uma lista heterogênea
            // (ex.: `filters` mistura LOT_SIZE e NOTIONAL, cada um com suas keys) só
            // faz sentido comparada posição a posição, nunca com o item 0 imposto a
            // todo o resto. A resposta real pode ter itens extras além do fixture
            // (subconjunto, nunca igualdade), mas nunca pode ter menos.
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

    private function assertMatchesRecordedValue(mixed $expected, mixed $actual, string $path): void
    {
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
