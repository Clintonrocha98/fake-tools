---
title: Ledger Accounts
icon: heroicon-o-wallet
order: 4
---

# Ledger Accounts

O ledger guarda os saldos `free`/`locked` por asset da conta fake, creditados e debitados pelas pernas fiat, spot e withdraw.

## Ações

- **Novo saldo** (ação de cabeçalho) — define `free`/`locked` para um asset novo, criando a conta.
- **Editar saldo** (ação de linha) — sobrescreve `free`/`locked` de um asset existente. Ao contrário das ações internas de crédito/débito (que aplicam um delta relativo), esta **substitui** o saldo — use-a para semear um cenário, não para simular uma transação.
