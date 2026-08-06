---
title: BR Code payments
icon: heroicon-o-banknotes
order: 3
---

# BR Code payments

The venue-funding leg: the third-party BR Codes this fake paid. Read-only — a payment is born from `POST /v2/brcode-payment`, after the provider guards (receiver tax id and amount checked against the bytes of the code itself).

The happy path needs no click: `created` → `processing` → `success`, by age, on the read.

## Actions

- **Forçar status** — the only path to `failed`, which no clock produces.
- **Armar próximo** (header) — arms the outcome of the next payment. A payment refused by the funding guards never existed and does **not** consume the armed scenario.
