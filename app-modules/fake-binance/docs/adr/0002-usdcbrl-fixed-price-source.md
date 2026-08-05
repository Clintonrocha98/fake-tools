# ADR-0002: preço fixo por config como fonte do bookTicker USDCBRL

## Status

Aceito.

## Contexto

`GET /api/v3/ticker/bookTicker` precisa de uma fonte de preço para `USDCBRL`. A
Binance real deriva bid/ask de um order book vivo; o mapa de endpoints do fake
deixava essa fonte em aberto ("Not yet specified").

## Decisão

O fake usa um preço médio (`mid`) fixo, configurável por env
(`FAKE_BINANCE_USDCBRL_PRICE`), com um spread também fixo por env
(`FAKE_BINANCE_USDCBRL_SPREAD`) em torno do mid: `bid = mid - spread/2`,
`ask = mid + spread/2`. Uma ordem BUY preenche no ask (o comprador paga o
ask), uma SELL preenche no bid (o vendedor recebe o bid) — mesma convenção de
qualquer order book.

## Alternativas consideradas

- Simular um livro de ofertas com profundidade e variação ao longo do tempo:
  rejeitada — sobre-modela um fake cujo consumidor só lê `bidPrice`/`askPrice`
  no topo do livro.

## Consequências

Preço e spread previsíveis facilitam asserts determinísticos nos testes e no
painel; não reproduz volatilidade real de mercado, o que é aceitável porque o
fake nunca precisou disso para o fluxo de conversão.
