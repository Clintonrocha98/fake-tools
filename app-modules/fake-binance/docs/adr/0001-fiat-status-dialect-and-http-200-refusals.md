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
   `fake-binance-fiat.status_dialect`. Os dois caminhos do enum consumidor
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
- `BinanceErrorBoundary::translate()` do consumidor lê sempre `code`/`msg`
  (vocabulário spot/wallet), mesmo numa chamada fiat — o `(int) $body['code']`
  sobrevive nos dois envelopes (numeric-string vira int igual), mas
  `$body['msg']` só existe no dialeto spot/wallet. Um erro de
  assinatura/autenticação na perna fiat perde o TEXTO da mensagem (`message`,
  não `msg`) — a exceção lançada continua sendo a certa, porque a decisão é só
  pelo código, nunca pelo texto, mas o operador lê um `reason` vazio. Rastreado
  em `brd-digital/brd-digital#292`; o fake mantém os dois envelopes com
  fidelidade à Binance real (o boundary do consumidor é quem escolheu o
  vocabulário fixo) até a correção lá — a suíte de contrato prova essa
  compatibilidade PARCIAL explicitamente, em vez de assumir que os dois textos
  sempre chegam.
- O cruzamento exaustivo `FiatOrderStatus::cases()` × `FiatStatusDialect::cases()`
  contra o vocabulário de `BinanceFiatOrderStatus` (suíte de contrato,
  `FiatContractTest`) prova que só o dialeto Live tem um gap real: o
  consumidor não modela `order_refund_failed` nem
  `order_partial_credit_stopped`, então os dois caem no `tryFrom()` como
  `null` e o consumidor fail-closa para `Pending` — um estado TERMINAL de
  falha nunca sai de "pendente". Rastreado em
  `brd-digital/brd-digital#291`; o fake mantém os dois valores (é o que a
  Binance real foi observada respondendo) até o consumidor os aceitar — a
  suíte de contrato afirma esse gap explicitamente em vez de escondê-lo.
