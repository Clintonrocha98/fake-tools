# ADR-0001: Dialeto de status fiat e recusas síncronas em HTTP 200

## Status

Aceita.

## Contexto

O `GetFiatOrderDetail` precisa responder o vocabulário de status que a Binance
fala em `/sapi/v1/fiat/get-order-detail`. A doc pública documenta um dialeto
(`Processing`, `Successful`, …); a produção, observada ao vivo em 02/ago/2026,
respondeu outro (`ORDER_PROCESSING`, `ORDER_SUCCESS`, …). O monolito consumidor
(`BinanceFiatOrderStatus`) normaliza e aceita os dois.

Separadamente, toda recusa síncrona de negócio em `POST /sapi/v1/fiat/deposit`
(serviço desabilitado, moeda/método não suportado, limite excedido) precisa de
um HTTP status — e a fixture real observada responde essas recusas com
HTTP 200 e um `code` de erro no corpo, não com 4xx.

## Decisão

1. `FiatOrderStatus::toWire()` serializa em dois dialetos
   ({@see FiatStatusDialect}) — `Live` (SCREAMING_SNAKE observado ao vivo) é o
   default do fake; `Classic` (doc pública) é selecionável via
   `venue-fiat.status_dialect`. Os dois caminhos do enum consumidor
   (`BinanceFiatOrderStatus`) precisam ficar exercitáveis contra o fake.
2. Toda recusa de negócio da família Fiat responde HTTP 200 — nunca um HTTP de
   erro — com o `code` de erro no envelope (`BinanceErrorCode::httpStatus()`
   fixa 200 para as recusas de negócio fiat). Só a verificação de assinatura
   (família compartilhada com spot/wallet) usa 400/401.

## Consequências

- Um consumidor que trata HTTP 200 como sucesso, sem checar `code`, quebra
  contra o fake exatamente como quebraria contra a Binance real — esse é o
  ponto: o fake não esconde essa armadilha.
- Adicionar um novo caso a `FiatOrderStatus` obriga a decidir seu wire nos dois
  dialetos, sem `default` — {@see FiatOrderStatus::toWire()} já falha em tempo
  de compilação se um caso ficar sem os dois mapeamentos.
