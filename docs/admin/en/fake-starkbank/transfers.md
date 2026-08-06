---
title: Transfers
icon: heroicon-o-arrow-up-on-square
order: 2
---

# Transfers

The cash-out leg: the outbound PIX the consuming application dispatched. Read-only — a transfer is born from `POST /v2/transfer`.

The happy path needs no click: `created` → `processing` → `success`, by age, on the read.

## Columns worth knowing

- **Destino armado** — the state an armed scenario wrote at creation; applied on the next read and then cleared.
- **Motivo da recusa** — the text carried in the webhook log when the outcome is a refusal.
- **Retida** — the transfer climbs to `processing` and stops there.

## Actions

- **Forçar status** — the only path to `failed` and `returned`, which no clock produces. Writes the status and emits the matching event, and clears any pending armed destination.
- **Armar próximo** (header) — arms the outcome of the next transfer.
