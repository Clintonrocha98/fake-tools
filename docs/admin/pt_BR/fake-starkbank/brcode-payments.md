---
title: Pagamentos de BR Code
icon: heroicon-o-banknotes
order: 3
---

# Pagamentos de BR Code

A perna de funding da venue: os BR Codes de terceiro que este fake pagou. Só leitura — um pagamento nasce do `POST /v2/brcode-payment`, depois dos guards do provedor (taxId do recebedor e valor conferidos contra os bytes do próprio código).

O happy path não precisa de clique: `created` → `processing` → `success`, por idade, na leitura.

## Ações

- **Forçar status** — é o único caminho até `failed`, que nenhum relógio produz.
- **Armar próximo** (cabeçalho) — arma o desfecho do próximo pagamento. Um pagamento recusado pelos guards do funding nunca existiu e **não** consome o cenário armado.
