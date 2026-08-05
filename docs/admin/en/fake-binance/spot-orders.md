---
title: Spot Orders
icon: heroicon-o-arrows-right-left
order: 2
---

# Spot Orders

A spot order (`POST /api/v3/order`) is a single MARKET fill against the fixed USDCBRL price — no lazy advance, no scheduler: the happy path always fills (`FILLED`) synchronously.

## Columns

- **Status** — the enum status persisted at execution time.
- **Wire desconhecido** — an arbitrary wire vocabulary set via **Emitir vocabulário desconhecido**, echoed verbatim on the wire instead of the real status.

## Actions

- **Recusar (REJECTED)** — zeroes every fill field and moves the order to `REJECTED`, as if the venue never executed it.
- **Preencher parcial + EXPIRED** — halves the original fill and moves the order to `EXPIRED`, reproducing a MARKET order that could not fully match and is never resent.
- **Emitir vocabulário desconhecido** — sets an arbitrary status string, proving the consumer's fail-closed handling.
