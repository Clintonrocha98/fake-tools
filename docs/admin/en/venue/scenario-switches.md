---
title: Scenario Switches
icon: heroicon-o-signal-slash
order: 5
---

# Scenario Switches

Three global switches, applied **before any endpoint** — even before signature verification — so they exercise the consuming application's `BinanceErrorBoundary` exactly as an outage would. They persist in the database, so they survive a restart; remember to turn them back off when you are done.

- **Modo outage** — every endpoint answers HTTP 5xx.
- **Modo rate limit** — every endpoint answers `429` with a `Retry-After` header.
- **Modo relógio torto** — every endpoint refuses with `-1021` (timestamp outside the recvWindow).

When more than one switch is on at the same time, outage wins over rate limit, which wins over clock skew.
