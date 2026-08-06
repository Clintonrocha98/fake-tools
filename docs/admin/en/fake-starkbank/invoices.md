---
title: Invoices
icon: heroicon-o-qr-code
order: 1
---

# Invoices

The cash-in leg: the PIX charges the consuming application issued. The list is read-only — a charge is born from `POST /v2/invoice`, never from here.

The happy path needs no click: an invoice created a minute ago turns `paid` on the next read.

## Columns worth knowing

- **Destino armado** — the state an armed scenario wrote at creation. It is applied on the next read and then cleared, so the invoice goes back to ageing normally.
- **Atraso extra (s)** — seconds added to the lazy-advance clock by the *Atrasar o pagamento* outcome.
- **Congelada** — while on, no read moves the status.

## Actions

- **Forçar status** — writes any status of the vocabulary, ignoring both clocks, and emits the matching event. It is how you reach `credited` and `reversed`, which no clock produces.
- **Congelar / Descongelar** — parks the invoice where it is. Unfreezing after the deadline makes the next read jump to whatever the clocks already reached.
- **Armar próximo** (header) — the same outcomes as the Armed Scenarios page, offered where you are already looking.
