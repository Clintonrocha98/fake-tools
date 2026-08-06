<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Tests\Support;

/**
 * Monta BR Codes EMV de teste — o insumo do preview e do pagamento.
 *
 * O CRC daqui é calculado por TABELA, enquanto o de produção
 * ({@see \He4rt\FakeStarkbank\Brcode\Support\Crc16}) é bit a bit: se as duas
 * implementações concordarem, a variante CCITT-FALSE está certa. Selar o
 * payload com a própria classe que o decodificador usa provaria só que ele
 * concorda consigo mesmo.
 *
 * O comprimento do TLV é contado em BYTES, como no emissor real — caminhar por
 * caractere UTF-8 desalinharia o offset no primeiro acento.
 */
trait BuildsBrcodes
{
    /**
     * A chave de funding cross-fake, a mesma que o `DictEntrySeeder` registra.
     * Constante de trait só é acessível de dentro dela (PHP 8.2+) — quem
     * escreve teste passa a chave por parâmetro ou usa o literal.
     */
    private const string FUNDING_PIX_KEY = 'funding@fake-binance.dev';

    /**
     * Um BR Code ESTÁTICO: o campo 54 carrega o valor, e o pagamento precisa
     * bater com ele.
     */
    protected function staticBrcode(
        string $pixKey = self::FUNDING_PIX_KEY,
        string $amount = '250.00',
        string $name = 'Fake Binance',
        string $city = 'Sao Paulo',
    ): string {
        return $this->sealCrc(
            $this->tlv('00', '01')
            .$this->tlv('26', $this->tlv('00', 'br.gov.bcb.pix').$this->tlv('01', $pixKey))
            .$this->tlv('52', '0000')
            .$this->tlv('53', '986')
            .$this->tlv('54', $amount)
            .$this->tlv('58', 'BR')
            .$this->tlv('59', $name)
            .$this->tlv('60', $city)
            .$this->tlv('62', $this->tlv('05', '***'))
        );
    }

    /**
     * Um BR Code DINÂMICO: sem campo 54, quem paga escolhe o valor — é o que o
     * preview anuncia com `allowChange: true`.
     */
    protected function dynamicBrcode(string $pixKey = self::FUNDING_PIX_KEY, string $name = 'Fake Binance'): string
    {
        return $this->sealCrc(
            $this->tlv('00', '01')
            .$this->tlv('26', $this->tlv('00', 'br.gov.bcb.pix').$this->tlv('01', $pixKey))
            .$this->tlv('53', '986')
            .$this->tlv('58', 'BR')
            .$this->tlv('59', $name)
            .$this->tlv('60', 'Sao Paulo')
        );
    }

    /**
     * Um payload bem formado, com CRC válido, mas sem o arranjo PIX — um QR de
     * outro arranjo, que não dá para pagar por PIX.
     */
    protected function brcodeWithoutPixKey(): string
    {
        return $this->sealCrc(
            $this->tlv('00', '01')
            .$this->tlv('26', $this->tlv('00', 'com.outro.arranjo').$this->tlv('01', 'seja-la-o-que-for'))
            .$this->tlv('54', '250.00')
            .$this->tlv('58', 'BR')
        );
    }

    /**
     * Troca um byte do valor mantendo o comprimento: o TLV continua caminhável
     * e só o CRC denuncia a adulteração — que é exatamente o ataque que o
     * dígito verificador existe para pegar.
     */
    protected function tamperedBrcode(string $brcode): string
    {
        return str_replace('250.00', '950.00', $brcode);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $fields  pares tag/valor, na ordem
     */
    protected function brcodeFromFields(array $fields): string
    {
        $payload = '';

        foreach ($fields as [$tag, $value]) {
            $payload .= $this->tlv($tag, $value);
        }

        return $this->sealCrc($payload);
    }

    /**
     * Um TLV solto, para montar campos compostos (26, 62) nos testes de borda.
     */
    protected function tlvField(string $tag, string $value): string
    {
        return $this->tlv($tag, $value);
    }

    private function tlv(string $tag, string $value): string
    {
        return $tag.mb_str_pad((string) mb_strlen($value, '8bit'), 2, '0', STR_PAD_LEFT).$value;
    }

    private function sealCrc(string $payload): string
    {
        $checked = $payload.'6304';

        return $checked.$this->crc16($checked);
    }

    /**
     * CRC16-CCITT-FALSE por tabela — a mesma variante do encoder de produção,
     * por outro caminho.
     */
    private function crc16(string $payload): string
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
