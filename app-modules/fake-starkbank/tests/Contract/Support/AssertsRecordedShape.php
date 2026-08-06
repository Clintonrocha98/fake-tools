<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Tests\Contract\Support;

use JsonException;

/**
 * Compara uma resposta real do fake contra um fixture de referência (payload
 * gravado do consumidor) por FORMA — nunca por igualdade literal de valor,
 * exceto onde o fixture usa {@see exactValue()}. Toda key do fixture precisa
 * existir na resposta real, com o mesmo tipo PHP; a resposta real pode carregar
 * keys extras (o StarkBank real também carrega), mas nunca pode faltar uma.
 *
 * Um valor `null` no fixture marca um campo genuinamente nullable (`pictureUrl`
 * na página 2, `cursor` na última página) e aceita qualquer tipo na resposta
 * real, incluindo null. Ids, timestamps e cardinalidade nunca entram na
 * comparação: um fixture de duas páginas é dois SCHEMAS, não uma contagem de
 * itens.
 *
 * Gêmeo deliberado do helper do fake-binance (ADR-0001: nenhum módulo
 * compartilhado entre os fakes) — sem a máquina de key discriminadora, que só o
 * `filterType`/`asset` da Binance exige; as listas do StarkBank são lidas
 * posicionalmente pelo consumidor.
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

            // A resposta real pode ter itens além do fixture (subconjunto,
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
     * comparação exata — nunca só o tipo. Ex.: o `code` de um envelope de erro,
     * que `StarkbankGateway::providerError()` reporta verbatim ao operador.
     */
    protected function exactValue(mixed $value): ExactRecordedValue
    {
        return new ExactRecordedValue($value);
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
