---
title: Spot Orders
icon: heroicon-o-arrows-right-left
order: 2
---

# Spot Orders

Uma ordem spot (`POST /api/v3/order`) é um único fill MARKET ao preço fixo USDCBRL — sem avanço lazy, sem scheduler: o happy path sempre preenche (`FILLED`) de forma síncrona.

## Colunas

- **Status** — o status do enum persistido no momento da execução.
- **Wire desconhecido** — um vocabulário de wire arbitrário definido via **Emitir vocabulário desconhecido**, ecoado na wire no lugar do status real.

## Ações

- **Recusar (REJECTED)** — zera todos os campos de fill e move a ordem para `REJECTED`, como se a venue nunca a tivesse executado.
- **Preencher parcial + EXPIRED** — reduz o fill original à metade e move a ordem para `EXPIRED`, reproduzindo uma MARKET que não conseguiu casar o total e não é reenviada.
- **Emitir vocabulário desconhecido** — define um status arbitrário, provando o fail-closed do consumidor.
