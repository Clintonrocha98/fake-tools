<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\DTOs\DecodedBrcode;
use He4rt\FakeStarkbank\Brcode\DTOs\PayBrcodeData;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Exceptions\BrcodePaymentRefusedException;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Actions\ResolveDictKey;
use He4rt\FakeStarkbank\Dict\Exceptions\DictKeyNotFoundException;
use He4rt\FakeStarkbank\Support\NumericId;
use Illuminate\Support\Facades\Log;

/**
 * `POST /v2/brcode-payment` — paga o BR Code de terceiro que fecha o funding da
 * conversão, em {@see BrcodePaymentStatus::Created}.
 *
 * O código é RE-DECODIFICADO aqui, e não só no preview: o consumidor pode ter
 * previsto um código e pago outro, e é o provedor que garante que o `taxId` e o
 * `amount` do corpo descrevem o mesmo pagamento que os bytes do EMV. Sem essa
 * conferência o campo `taxId` do body seria decorativo, e a recusa que o
 * consumidor trata (`invalidTaxId`) nunca aconteceria em dev.
 *
 * Sem idempotência: o endpoint não aceita `externalId` e `tags` não é chave de
 * deduplicação no provedor. Dois POST iguais são dois pagamentos — quem impede
 * o segundo é o guard de estado da Conversion, do lado de lá.
 */
final readonly class PayBrcode
{
    public function __construct(
        private DecodeBrcode $decode,
        private ResolveDictKey $resolveDictKey,
    ) {}

    public function handle(PayBrcodeData $data): BrcodePayment
    {
        if ($data->hasExternalId) {
            Log::warning('fake-starkbank.brcode: pagamento recusado por trazer externalId — o provedor real recusa este parâmetro nesta perna, e aceitá-lo aqui deixaria o consumidor confiar numa chave de idempotência que não existe do outro lado', [
                'tags' => $data->tags,
            ]);

            throw BrcodePaymentRefusedException::unknownExternalId();
        }

        $decoded = $this->decode->handle($data->brcode);

        $this->assertDescription($data, $decoded);
        $this->assertReceiver($data, $decoded);
        $this->assertAmount($data, $decoded);

        $payment = BrcodePayment::query()->create([
            'id' => NumericId::generate(),
            'brcode' => $data->brcode,
            'tax_id' => $data->taxId,
            'amount' => $data->amount,
            'status' => BrcodePaymentStatus::Created,
            'description' => $data->description === '' ? null : $data->description,
            'tags' => $data->tags,
        ]);

        Log::info('fake-starkbank.brcode: funding despachado — o taxId e o valor do corpo foram conferidos contra os bytes do próprio código, que é o que o provedor faz antes de mover dinheiro', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'pix_key' => $decoded->pixKey,
            'correlation_id' => $payment->tags->correlationId(),
        ]);

        return $payment;
    }

    /**
     * O BR Code dinâmico (sem campo 54) exige descrição no provedor real —
     * fato observado ao vivo pelo consumidor, que por isso sempre a manda. No
     * estático a ausência não é recusa.
     */
    private function assertDescription(PayBrcodeData $data, DecodedBrcode $decoded): void
    {
        if ($data->description !== '' || !$decoded->allowsAmountChange()) {
            return;
        }

        Log::warning('fake-starkbank.brcode: pagamento de BR Code dinâmico recusado sem description — é a recusa que o consumidor já apanhou do provedor, e reproduzi-la é o que mantém o campo obrigatório do lado de lá', [
            'pix_key' => $decoded->pixKey,
        ]);

        throw BrcodePaymentRefusedException::missingDescription();
    }

    /**
     * O `taxId` do corpo é o do RECEBEDOR, como o consumidor o leu do preview.
     * A comparação é por dígitos: `20018183000180` e `20.018.183/0001-80` são o
     * mesmo CNPJ, e recusar por pontuação seria inventar uma recusa que o
     * provedor não faz.
     *
     * Chave fora do registro não recusa: o fake não tem contra o que conferir,
     * e é o preview (com `taxId` vazio) que já mandou o consumidor não pagar.
     */
    private function assertReceiver(PayBrcodeData $data, DecodedBrcode $decoded): void
    {
        try {
            $entry = $this->resolveDictKey->handle($decoded->pixKey);
        } catch (DictKeyNotFoundException) {
            Log::warning('fake-starkbank.brcode: recebedor não conferido — a chave do código não está no registro DICT, e o fake não tem contra o que comparar o taxId do corpo; o fail-closed desse caminho é do consumidor, no preview', [
                'pix_key' => $decoded->pixKey,
                'tax_id' => $data->taxId,
            ]);

            return;
        }

        if ($this->digits($data->taxId) === $this->digits($entry->tax_id)) {
            return;
        }

        Log::warning('fake-starkbank.brcode: pagamento recusado por recebedor divergente — o taxId do corpo não é o titular da chave embutida no código, e pagar assim mandaria dinheiro para quem ninguém verificou', [
            'pix_key' => $decoded->pixKey,
            'tax_id_do_corpo' => $data->taxId,
            'tax_id_do_registro' => $entry->tax_id,
        ]);

        throw BrcodePaymentRefusedException::taxIdMismatch($data->taxId, $entry->tax_id);
    }

    /**
     * Num BR Code ESTÁTICO o valor está no código e não é negociável. No
     * dinâmico (campo 54 ausente) qualquer valor é legítimo — é justamente o
     * que `allowChange: true` anuncia no preview.
     */
    private function assertAmount(PayBrcodeData $data, DecodedBrcode $decoded): void
    {
        if ($decoded->amountCentavos === null || $decoded->amountCentavos === $data->amount) {
            return;
        }

        Log::warning('fake-starkbank.brcode: pagamento recusado por valor divergente do código — o campo 54 é o valor que o recebedor cobrou, e pagar outro produziria um funding que a conciliação do consumidor nunca casaria', [
            'amount_do_corpo' => $data->amount,
            'amount_do_brcode' => $decoded->amountCentavos,
        ]);

        throw BrcodePaymentRefusedException::amountMismatch($data->amount, $decoded->amountCentavos);
    }

    private function digits(string $taxId): string
    {
        return (string) preg_replace('/\D/', '', $taxId);
    }
}
