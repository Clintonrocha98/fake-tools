<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Tests\Support;

use InvalidArgumentException;

/**
 * Decodificador TLV de teste para o BR Code EMV que
 * {@see \He4rt\FakeBinance\Fiat\Actions\BuildStaticBrcode} emite.
 *
 * Existe para que o teste não confira o encoder contra ele mesmo: caminha o
 * payload por offset lendo tag/comprimento/valor, e recalcula o CRC16 por uma
 * implementação orientada a tabela — nada aqui é reaproveitado de
 * {@see \He4rt\FakeBinance\Fiat\Support\Crc16}, que é bit a bit. Se as duas
 * concordarem, a variante do CRC está certa.
 *
 * As tags saem em lista de pares, nunca como chave de array: `'26'` viraria a
 * chave inteira `26` e o teste perderia de vista o formato de dois dígitos que
 * o EMV exige.
 */
final readonly class EmvDecoder
{
    /**
     * @return list<array{tag: string, value: string}> na ordem em que aparecem
     */
    public static function fields(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = mb_strlen($payload, '8bit');

        while ($offset < $length) {
            throw_if($offset + 4 > $length, InvalidArgumentException::class, sprintf('Truncated TLV header at offset %d.', $offset));

            $tag = mb_substr($payload, $offset, 2, '8bit');
            $declared = mb_substr($payload, $offset + 2, 2, '8bit');

            throw_if(preg_match('/^\d{2}$/', $declared) !== 1, InvalidArgumentException::class, sprintf("Non-numeric TLV length '%s' at offset %d.", $declared, $offset));

            $valueLength = (int) $declared;

            throw_if($offset + 4 + $valueLength > $length, InvalidArgumentException::class, sprintf('TLV value for tag %s runs past the payload end.', $tag));

            $fields[] = ['tag' => $tag, 'value' => mb_substr($payload, $offset + 4, $valueLength, '8bit')];
            $offset += 4 + $valueLength;
        }

        return $fields;
    }

    /**
     * As tags presentes, na ordem — o EMV é posicional, então a ordem é contrato.
     *
     * @return list<string>
     */
    public static function tags(string $payload): array
    {
        return array_map(static fn (array $field): string => $field['tag'], self::fields($payload));
    }

    /**
     * O valor de uma tag; para um campo composto (26, 62) basta reaplicar sobre
     * o valor devolvido.
     */
    public static function value(string $payload, string $tag): string
    {
        foreach (self::fields($payload) as $field) {
            if ($field['tag'] === $tag) {
                return $field['value'];
            }
        }

        throw new InvalidArgumentException(sprintf('Tag %s is absent from the payload.', $tag));
    }

    /**
     * O CRC declarado no campo 63 bate com o recalculado sobre tudo que vem
     * antes dele, incluindo o cabeçalho `6304`.
     */
    public static function crcIsValid(string $payload): bool
    {
        $position = mb_strrpos($payload, '6304', 0, '8bit');

        if ($position === false || $position + 8 !== mb_strlen($payload, '8bit')) {
            return false;
        }

        $declared = mb_substr($payload, $position + 4, 4, '8bit');

        return mb_strtoupper($declared) === self::crc16(mb_substr($payload, 0, $position + 4, '8bit'));
    }

    /**
     * CRC16-CCITT-FALSE por tabela — a mesma variante do encoder, por outro caminho.
     */
    public static function crc16(string $payload): string
    {
        /** @var array<int, int>|null $table */
        static $table = null;

        if ($table === null) {
            $table = [];

            for ($byte = 0; $byte < 256; $byte++) {
                $value = $byte << 8;

                for ($bit = 0; $bit < 8; $bit++) {
                    $value = ($value & 0x80_00) !== 0 ? (($value << 1) ^ 0x10_21) & 0xFF_FF : ($value << 1) & 0xFF_FF;
                }

                $table[$byte] = $value;
            }
        }

        $crc = 0xFF_FF;

        foreach (mb_str_split($payload, 1, '8bit') as $character) {
            $crc = (($crc << 8) & 0xFF_FF) ^ $table[(($crc >> 8) ^ ord($character)) & 0xFF];
        }

        return mb_strtoupper(mb_str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
