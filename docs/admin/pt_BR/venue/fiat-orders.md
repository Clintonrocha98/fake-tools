---
title: Fiat Orders
icon: heroicon-o-banknotes
order: 1
---

# Fiat Orders

Uma fiat order (`POST /sapi/v1/fiat/deposit`) é criada pela aplicação consumidora para anunciar um depósito PIX. Por padrão ela credita o ledger sozinha assim que fica mais velha que `venue-fiat.advance_seconds` — nenhuma ação é necessária para o happy path.

## Colunas

- **Order no** — o identificador da ordem na venue.
- **Status (lazy)** — o status real que o avanço por idade calcula.
- **Override** — `forced_status`, quando setado, vence o status lazy sem nunca ser gravado nele. Limpar o override retoma o avanço lazy de onde `status` estava.
- **Wire desconhecido** — um vocabulário de wire arbitrário definido via **Emitir vocabulário desconhecido**, ecoado verbatim.
- **Congelada** — se o avanço lazy está pausado para esta ordem.
- **Atraso brcode** — quantas leituras de `get-order-detail` precisam acontecer antes do brcode aparecer; a descrição mostra quantas leituras já aconteceram.

## Ações

- **Creditar agora** — pula o relógio: credita o ledger imediatamente (idempotente — clicar de novo nunca credita duas vezes).
- **Falhar com status** — força um de `ORDER_FAILED`, `ORDER_EXPIRED`, `ORDER_CANCELLED`, `ORDER_NEED_ADDITIONAL_ACTION`.
- **Emitir vocabulário desconhecido** — define um status de wire arbitrário, provando o fail-closed do consumidor.
- **Atrasar brcode** — define quantas leituras precisam acontecer antes do brcode ser devolvido; deixe vazio para remover o atraso.
- **Congelar/Descongelar** — pausa ou retoma o avanço lazy.
