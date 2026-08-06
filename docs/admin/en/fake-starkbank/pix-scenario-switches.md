---
title: Scenario Switches (PIX)
icon: heroicon-o-signal-slash
order: 6
---

# Scenario Switches (PIX)

Two global switches, applied **before any `/v2/*` endpoint** — even before signature verification — so the consuming application sees an outage instead of an auth failure. They persist in the database, so they survive a restart; remember to turn them back off when you are done.

- **Modo outage** — every route answers `503` with `{"errors": [{"code": "internalServerError", …}]}`.
- **Modo rate limit** — every route answers `429` with `{"errors": [{"code": "tooManyRequests", …}]}` and a `Retry-After` header.

When both are on, outage wins over rate limit.

These switches reach **only** this fake. The Fake Binance section has its own switchboard, in its own table: arming an outage here cannot take down the venue flow that already works.
