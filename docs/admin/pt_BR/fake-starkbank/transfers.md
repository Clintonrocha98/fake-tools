---
title: Transfers
icon: heroicon-o-arrow-up-on-square
order: 2
---

# Transfers

A perna de cash-out: os PIX de saída que a aplicação consumidora despachou. Só leitura — uma transfer nasce do `POST /v2/transfer`.

O happy path não precisa de clique: `created` → `processing` → `success`, por idade, na leitura.

## Colunas que importam

- **Destino armado** — o estado que um cenário armado gravou na criação; aplicado na leitura seguinte e depois zerado.
- **Motivo da recusa** — o texto que viaja no log do webhook quando o desfecho é uma recusa.
- **Retida** — a transfer sobe até `processing` e para ali.

## Ações

- **Forçar status** — é o único caminho até `failed` e `returned`, que nenhum relógio produz. Grava o status, emite o evento correspondente e descarta qualquer destino armado ainda pendente.
- **Armar próximo** (cabeçalho) — arma o desfecho da próxima transfer.
