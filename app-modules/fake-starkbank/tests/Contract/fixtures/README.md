# Fixtures do contrato

Payloads de referência copiados do consumidor (`brd-digital`, módulo
`integration-starkbank`) — a origem de cada arquivo abaixo. Um teste em
`../*ContractTest.php` bate no endpoint real do fake e compara a resposta contra o
arquivo por FORMA (mesmas keys, mesmo tipo por key), nunca por igualdade literal —
ver `AssertsRecordedShape` em `../Support/`.

O wire do StarkBank é **camelCase** (`allowedTaxIds`, `organizationId`,
`pictureUrl`); resumos derivados dos SDKs mostram snake_case e estão errados. Os
fixtures vencem a doc.

| Fixture | Origem no consumidor |
|---|---|
| `workspace/workspace_list_page1.json` | `tests/Fixtures/workspace_list_page1.json` (verbatim) |
| `workspace/workspace_list_page2.json` | `tests/Fixtures/workspace_list_page2.json` (verbatim) |
| `errors/error_envelope.json` | `Rails/Pix/StarkbankGateway::providerError()` — lê `errors.0.code` + `errors.0.message` |
| `webhook/webhook_invoice_paid.json` | `tests/Fixtures/webhook_invoice_paid.json` (verbatim) |

O fixture de webhook é o único que descreve o sentido **fake → consumidor**: é o
envelope que sai no `POST /webhooks/starkbank`, não uma resposta do fake. Repare na
assimetria da key da entity dentro do log — subscription `brcode-payment`, key
`payment` —, que `WebhookEvent::fromWebhookBody()` do consumidor lê posicionalmente.

As duas páginas de workspace são dois **schemas** comparados isoladamente
(`pictureUrl` string + `cursor` string na página 1; ambos `null` na página 2), nunca
uma contagem de itens: o fake serve o workspace de config numa página única e já
encerra em `cursor: null`.

## Como rodar só o contrato

```bash
php artisan test --group=contract
# ou
composer test:contract
```

## Quando o consumidor muda

Quando um PR do consumidor tocar `integration-starkbank` — request novo, campo novo,
status novo — a mudança correspondente aqui (fixture + endpoint, se necessário) sai
no mesmo dia. Sem automação por ora.
