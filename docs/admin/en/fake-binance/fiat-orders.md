---
title: Fiat Orders
icon: heroicon-o-banknotes
order: 1
---

# Fiat Orders

A fiat order (`POST /sapi/v1/fiat/deposit`) is created by the consuming application to announce a PIX deposit. By default it credits the ledger on its own once it is older than `fake-binance-fiat.advance_seconds` — no action needed for the happy path.

## Columns

- **Order no** — the venue's order identifier.
- **Status (lazy)** — the underlying status the age-based advance computes.
- **Override** — `forced_status`, when set, wins over the lazy status without ever being written back to it. Clearing the override resumes the lazy advance from where `status` was.
- **Wire desconhecido** — an arbitrary wire vocabulary set via **Emitir vocabulário desconhecido**, echoed verbatim.
- **Congelada** — whether the lazy advance is paused for this order.
- **Atraso brcode** — how many reads of `get-order-detail` must happen before the brcode appears; the description shows how many reads have happened so far.

## Actions

- **Creditar agora** — skips the clock: credits the ledger immediately (idempotent — a re-click never credits twice).
- **Falhar com status** — forces one of `ORDER_FAILED`, `ORDER_EXPIRED`, `ORDER_CANCELLED`, `ORDER_NEED_ADDITIONAL_ACTION`.
- **Emitir vocabulário desconhecido** — sets an arbitrary wire status string, proving the consumer's fail-closed handling.
- **Atrasar brcode** — sets how many reads must happen before the brcode is returned; leave empty to remove the delay.
- **Congelar/Descongelar** — pauses or resumes the lazy advance.
