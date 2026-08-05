---
title: Ledger Accounts
icon: heroicon-o-wallet
order: 4
---

# Ledger Accounts

The ledger holds the fake account's per-asset `free`/`locked` balances, credited and debited by the fiat, spot and withdraw legs.

## Actions

- **Novo saldo** (header action) — sets `free`/`locked` for a new asset, creating the account.
- **Editar saldo** (row action) — overwrites `free`/`locked` for an existing asset. Unlike the internal credit/debit actions (which apply a relative delta), this **replaces** the balance outright — use it to seed a scenario, not to simulate a transaction.
