---
title: Webhooks
icon: heroicon-o-bell-alert
order: 5
---

# Webhooks

Every webhook this fake built, signed and tried to deliver. Read this screen before opening a log: a `401` in the **HTTP** column is almost always a signature that does not match the public PEM the consuming application reads.

Delivery happens **after** the response that produced the event — POSTing inside the same request would deadlock a single-threaded `php artisan serve` on the other side.

## Actions

- **Reenviar** — resends the stored bytes, with the same signature and the same `event.id`. That repetition is what exercises the consuming application's idempotency; it must recognise the event instead of reconciling twice.
- **Emitir corrompida** — emits the same subscription/event pair signed with a key generated on the spot and thrown away. The expected answer on the other side is `401` with nothing stored.
- **Liberar represada** — only shows on an emission the `HoldNext` scenario held back. Delivers the held bytes as they are, keeping the `event.id` of the moment the event happened.

An emission held on purpose is deliberately skipped by `fake-starkbank:flush-webhooks`: the flush recovers what the network swallowed, not what an operator chose to hold.
