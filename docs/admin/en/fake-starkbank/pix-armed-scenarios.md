---
title: Armed Scenarios (PIX)
icon: heroicon-o-bolt-slash
order: 7
---

# Armed Scenarios (PIX)

Here you decide what happens to the **next** request — or the next event — of a PIX leg. Flip the outcome's switch, close the page: the next thing the consuming application triggers comes out that way, and the fake returns to the happy path on its own.

## Rules

- **Good for one shot.** The leg's first request consumes the scenario. For two in a row, arm it twice.
- **One outcome per leg.** Turning one on turns the previous one off — two outcomes for the same request would contradict each other. Different legs can stay armed at the same time.
- **The global switches win.** With outage mode on, the request never reaches the leg, and the armed scenario stays standing for later.
- **Editing a parameter re-arms.** Changing the reason or the extra seconds with the switch already on stores the new value right away and notifies you. Typing with every switch off arms nothing.

## Two natures of leg

- **Asynchronous — Invoice, Transfer, BR Code payment.** The scenario is consumed at the `POST` and the record is **born destined**: the response to the creation is still the normal one, and the next read delivers the outcome. This is the same lazy advance the fake already uses for everything else.
- **Per event — Webhook.** The scenario is consumed at the moment of the **emission**, whatever leg produced it. Arming here is good for the next event, not for the next invoice.

## Invoice

- **Nascer vencida / expirada / cancelada** — the invoice is born destined to `overdue`, `expired` or `canceled`; the next read already delivers it that way.
- **Congelar o avanço** — the invoice is born frozen: no read moves its status until an operator unfreezes it on the Invoices screen.
- **Atrasar o pagamento** — the invoice pays normally, only the given seconds are added to the lazy-advance clock. Empty uses 300.

## Transfer and BR Code payment

- **Nascer destinada a failed** — the only path to `failed` without an operator click. The reason you type travels in the webhook log of the outcome; empty uses the leg's default reason.
- **Segurar em processing** — the record climbs to `processing` and stops there, however far the clock runs.

## Webhook

- **Entregar o próximo evento duas vezes** — the same `event.id` arrives twice, exercising the consuming application's idempotency.
- **Assinar o próximo evento com outra chave** — a well-formed envelope whose signature does not match the public PEM on the other side; the expected answer there is `401` with nothing stored.
- **Represar o próximo evento** — the emission is stored and never POSTed. It waits on the Webhooks screen until you use **Liberar represada**; the flush command deliberately ignores it.
