<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Support\StarkbankLog;

/**
 * Cenário: congela/descongela uma invoice. Enquanto congelada, o avanço lazy
 * nunca calcula um novo status — nem o pagamento simulado nem o vencimento —,
 * então o estado fica fixo durante um teste manual. Descongelar retoma o
 * avanço de onde o status estava, e uma invoice descongelada depois do prazo
 * salta na leitura seguinte para o estado que os relógios já alcançaram.
 */
final readonly class SetInvoiceFrozen
{
    public function handle(Invoice $invoice, bool $frozen): Invoice
    {
        $invoice->update(['frozen' => $frozen]);

        StarkbankLog::info('fake-starkbank.invoice: congelamento de cenário alterado — enquanto congelada, nenhuma leitura move o status', [
            'invoice_id' => $invoice->id,
            'frozen' => $frozen,
            'status' => $invoice->status->value,
        ]);

        return $invoice->refresh();
    }
}
