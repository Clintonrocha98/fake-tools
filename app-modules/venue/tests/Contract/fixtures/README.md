# Fixtures do contrato

Payloads de referência copiados do consumidor (`brd-digital`, módulo
`integration-binance`) — a origem de cada arquivo abaixo. Um teste em
`../*ContractTest.php` bate no endpoint real do fake e compara a resposta contra o
arquivo por FORMA (mesmas keys, mesmo tipo por key), nunca por igualdade literal —
ver `AssertsRecordedShape` em `../Support/`.

| Fixture | Origem no consumidor |
|---|---|
| `spot/book_ticker.json` | `Tests\Support\Fixtures::bookTicker()` |
| `spot/exchange_info.json` | `Tests\Support\Fixtures::exchangeInfo()` |
| `spot/account.json` | `Unit\ConversionGatewayTest` — `'lists every non-zero balance with free AND locked, dropping the dust-less zeros'` |
| `spot/place_order_response.json` | `Unit\ConversionGatewayTest` — `'converts BRL→USDC at market: precheck, order, mapped fill'` |
| `spot/get_order_response.json` | `Unit\ConversionGatewayTest` — `'re-reads the conversion status'` |
| `fiat/create_deposit_success.json` | `Unit\VenueFundingGatewayTest` — `'opens the fiat deposit order and returns the venue order id'` |
| `fiat/create_deposit_refused.json` | `Unit\VenueFundingGatewayTest` — `'fails loud when the envelope carries a refusal despite the HTTP 200'` |
| `fiat/get_order_detail_success.json` | `Unit\VenueFundingGatewayTest` — `'re-reads one order and translates the closed status vocabulary to the neutral buckets'` |
| `withdraw/apply_success.json` | `Unit\FxFlowTest` — `'walks the flow: quote → buy → transfer'` (o único MockClient literal do consumidor para `ApplyWithdrawRequest`) |
| `withdraw/history_row.json` | **Não há MockClient literal upstream ainda** — shape extraído direto de `Http/Responses/WithdrawHistoryResponse.php` (o PHPDoc `list<array{...}>` documenta cada key/tipo lido) |
| `errors/spot_wallet_error.json` | `Support/BinanceErrorBoundary::translate()` — lê `code` (int) + `msg` (string) |
| `errors/fiat_error.json` | `Http/Responses/FiatDepositResponse.php`/`FiatOrderDetailResponse.php` — lê `code` (string) + `message` |

`FiatContractTest`'s exhaustive vocabulary guard copia o conjunto de values de
`Funding/BinanceFiatOrderStatus.php` (não um MockClient — o enum é o próprio
contrato de vocabulário lido por `toFundingState()`).

Todos os arquivos referenciados vivem em
`brd-digital/app-modules/integration-binance/{src,tests}`.

## Como rodar só o contrato

```bash
php artisan test --group=contract
# ou
composer test:contract
```

## Quando o consumidor muda

O mecanismo deste diretório mitiga o risco nº 1 do esforço (issue #1): o fake
envelhecer sozinho enquanto `integration-binance` evolui. Quando um PR do
consumidor tocar esse módulo — request novo, campo novo, status novo — a mudança
correspondente aqui (fixture + endpoint, se necessário) sai **no mesmo dia**, e o
PR de lá linka o PR daqui. Sem automação por ora; se o drift acontecer na
prática, graduar para um job de CI que roda esta suíte direto contra os
fixtures de `integration-binance`.
