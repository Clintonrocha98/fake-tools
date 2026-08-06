<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\DTOs\DecodedBrcode;
use He4rt\FakeStarkbank\Brcode\Exceptions\MalformedBrcodeException;
use He4rt\FakeStarkbank\Brcode\Support\Crc16;
use Illuminate\Support\Facades\Log;

/**
 * Decodifica o BR Code EMV de verdade — TLV byte a byte, CRC16 conferido, chave
 * PIX extraída do arranjo `br.gov.bcb.pix`.
 *
 * É esta classe que dá valor aos dois guards do `SendConversionFunding` do
 * consumidor: servir `taxId` e `amount` a partir de config faria o guard de
 * destino e o de valor passarem sempre, quaisquer que fossem os bytes do
 * código. Aqui o código manda, e um payload adulterado no meio do caminho é
 * recusado pelo CRC antes de virar preview.
 *
 * O caminho por BYTES (`'8bit'`) não é preciosismo: o comprimento de um TLV é
 * contado em bytes, e caminhar por caractere UTF-8 desalinha o offset já no
 * primeiro acento que aparecer no nome do recebedor.
 */
final readonly class DecodeBrcode
{
    /**
     * Globally Unique Identifier do arranjo PIX dentro do Merchant Account
     * Information — fixado pelo Banco Central, comparado sem caixa porque o
     * EMV admite as duas grafias.
     */
    private const string PIX_GUI = 'br.gov.bcb.pix';

    /**
     * A faixa de tags reservada ao Merchant Account Information. O arranjo PIX
     * costuma ocupar a 26, mas o payload é livre para colocá-lo em qualquer uma
     * delas — procurar só na 26 recusaria um código legítimo.
     */
    private const int MAI_FIRST_TAG = 26;

    private const int MAI_LAST_TAG = 51;

    public function handle(string $brcode): DecodedBrcode
    {
        $payload = mb_trim($brcode);

        if ($payload === '') {
            $this->refuse(MalformedBrcodeException::empty(), $brcode);
        }

        $fields = $this->fields($payload, $brcode);

        $this->assertFormatIndicator($fields, $brcode);
        $this->assertChecksum($payload, $fields, $brcode);

        $pixKey = $this->pixKey($fields, $brcode);

        return new DecodedBrcode(
            pixKey: $pixKey,
            amountCentavos: $this->amountCentavos($fields, $brcode),
            receiverName: $this->value($fields, '59') ?? '',
            city: $this->value($fields, '60') ?? '',
        );
    }

    /**
     * @param  list<array{tag: string, value: string}>  $fields
     */
    private function assertFormatIndicator(array $fields, string $brcode): void
    {
        $indicator = $this->value($fields, '00');

        if ($indicator !== '01') {
            $this->refuse(MalformedBrcodeException::unknownFormat($indicator ?? ''), $brcode);
        }
    }

    /**
     * O CRC fecha sobre TUDO que vem antes dos quatro dígitos, inclusive o
     * cabeçalho `6304` — é essa a pegadinha da especificação, e uma conta que
     * pare antes dele fecha com um valor plausível e errado.
     *
     * @param  list<array{tag: string, value: string}>  $fields
     */
    private function assertChecksum(string $payload, array $fields, string $brcode): void
    {
        $last = $fields[count($fields) - 1] ?? null;

        if ($last === null || $last['tag'] !== '63') {
            $this->refuse(MalformedBrcodeException::notTlv('the CRC field 63 must be the last one'), $brcode);
        }

        $length = mb_strlen($payload, '8bit');
        $recalculated = Crc16::ccittFalse(mb_substr($payload, 0, $length - 4, '8bit'));

        if (mb_strtoupper($last['value']) !== $recalculated) {
            $this->refuse(MalformedBrcodeException::checksumMismatch(), $brcode);
        }
    }

    /**
     * @param  list<array{tag: string, value: string}>  $fields
     */
    private function pixKey(array $fields, string $brcode): string
    {
        foreach ($fields as $field) {
            $tag = (int) $field['tag'];

            if ($tag >= self::MAI_FIRST_TAG && $tag <= self::MAI_LAST_TAG) {
                $pixKey = $this->pixKeyFrom($field['value'], $brcode);

                if ($pixKey !== null) {
                    return $pixKey;
                }
            }
        }

        $this->refuse(MalformedBrcodeException::withoutPixKey(), $brcode);
    }

    /**
     * A chave dentro de um Merchant Account Information — `null` quando aquele
     * campo é de outro arranjo, e não um erro: o payload pode carregar vários.
     */
    private function pixKeyFrom(string $merchantAccountInformation, string $brcode): ?string
    {
        $subFields = $this->fields($merchantAccountInformation, $brcode);
        $gui = $this->value($subFields, '00');

        if ($gui === null || mb_strtolower($gui) !== self::PIX_GUI) {
            return null;
        }

        $pixKey = $this->value($subFields, '01');

        return $pixKey === '' ? null : $pixKey;
    }

    /**
     * O campo 54 é decimal com ponto; a conversão para centavos é por
     * aritmética de string — um cast para float perderia justamente o centavo
     * que o guard de valor do consumidor confere.
     *
     * @param  list<array{tag: string, value: string}>  $fields
     */
    private function amountCentavos(array $fields, string $brcode): ?int
    {
        $raw = $this->value($fields, '54');

        if ($raw === null) {
            // Campo ausente é o BR Code dinâmico: quem paga escolhe o valor.
            return null;
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $raw, $partes) !== 1) {
            $this->refuse(MalformedBrcodeException::unreadableAmount($raw), $brcode);
        }

        // Aritmética de inteiros sobre as duas metades da decimal: um
        // `(float) $raw * 100` perderia justamente o centavo que o guard de
        // valor do consumidor confere.
        return (int) $partes[1] * 100 + (int) mb_str_pad($partes[2] ?? '', 2, '0');
    }

    /**
     * Caminha o payload lendo tag (2), comprimento (2) e valor, na ordem em que
     * aparecem. Reaplicável sobre o valor de um campo composto (26, 62).
     *
     * @return list<array{tag: string, value: string}>
     */
    private function fields(string $payload, string $brcode): array
    {
        $fields = [];
        $offset = 0;
        $length = mb_strlen($payload, '8bit');

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                $this->refuse(MalformedBrcodeException::notTlv(sprintf('truncated TLV header at offset %d', $offset)), $brcode);
            }

            $tag = mb_substr($payload, $offset, 2, '8bit');
            $declared = mb_substr($payload, $offset + 2, 2, '8bit');

            if (preg_match('/^\d{2}$/', $tag) !== 1 || preg_match('/^\d{2}$/', $declared) !== 1) {
                $this->refuse(MalformedBrcodeException::notTlv(sprintf('non-numeric TLV header at offset %d', $offset)), $brcode);
            }

            $valueLength = (int) $declared;

            if ($offset + 4 + $valueLength > $length) {
                $this->refuse(MalformedBrcodeException::notTlv(sprintf('TLV value for tag %s runs past the payload end', $tag)), $brcode);
            }

            $fields[] = ['tag' => $tag, 'value' => mb_substr($payload, $offset + 4, $valueLength, '8bit')];
            $offset += 4 + $valueLength;
        }

        return $fields;
    }

    /**
     * @param  list<array{tag: string, value: string}>  $fields
     */
    private function value(array $fields, string $tag): ?string
    {
        foreach ($fields as $field) {
            if ($field['tag'] === $tag) {
                return $field['value'];
            }
        }

        return null;
    }

    private function refuse(MalformedBrcodeException $recusa, string $brcode): never
    {
        Log::warning('fake-starkbank.brcode: BR Code recusado na decodificação — o preview serve o que o código carrega, e aceitar um payload que não fecha faria o guard de destino do consumidor conferir um dado inventado', [
            'motivo' => $recusa->getMessage(),
            // O código inteiro, e não um trecho: é ele o insumo para reproduzir
            // a recusa fora do fluxo.
            'brcode' => $brcode,
        ]);

        throw $recusa;
    }
}
