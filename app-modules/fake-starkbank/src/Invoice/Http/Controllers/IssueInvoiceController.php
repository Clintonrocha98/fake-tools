<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Http\Controllers;

use He4rt\FakeStarkbank\Invoice\Actions\IssueInvoice;
use He4rt\FakeStarkbank\Invoice\DTOs\IssuedInvoiceView;
use He4rt\FakeStarkbank\Invoice\DTOs\IssueInvoiceData;
use He4rt\FakeStarkbank\Invoice\Http\Requests\IssueInvoiceRequest;
use Illuminate\Http\JsonResponse;

/**
 * `POST /v2/invoice` — a assinatura é verificada pelo middleware
 * `fake-starkbank.signed` na definição da rota, nunca aqui.
 *
 * A resposta ecoa o shape COMPLETO da emissão ({@see IssuedInvoiceView}), mais
 * gordo que o da releitura, e mantém a ordem do array recebido: o consumidor lê
 * `invoices.0` posicionalmente.
 */
final readonly class IssueInvoiceController
{
    public function __construct(private IssueInvoice $issueInvoice) {}

    public function __invoke(IssueInvoiceRequest $request): JsonResponse
    {
        /** @var array<int, array<array-key, mixed>> $items */
        $items = $request->validated('invoices');

        $invoices = [];

        foreach ($items as $item) {
            $invoices[] = IssuedInvoiceView::fromModel(
                $this->issueInvoice->handle(IssueInvoiceData::fromWire($item)),
            );
        }

        return response()->json([
            'invoices' => $invoices,
            'message' => 'Invoice successfully created',
        ]);
    }
}
