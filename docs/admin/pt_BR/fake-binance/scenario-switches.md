---
title: Scenario Switches
icon: heroicon-o-signal-slash
order: 5
---

# Scenario Switches

Três switches globais, aplicados **antes de qualquer endpoint** — antes até da verificação de assinatura — para exercitar o `BinanceErrorBoundary` da aplicação consumidora exatamente como uma indisponibilidade real faria. Persistem em banco, então sobrevivem a um restart; lembre-se de desligá-los de volta ao terminar.

- **Modo outage** — todo endpoint responde HTTP 5xx.
- **Modo rate limit** — todo endpoint responde `429` com um header `Retry-After`.
- **Modo relógio torto** — todo endpoint recusa com `-1021` (timestamp fora da janela).

Quando mais de um switch está ligado ao mesmo tempo, outage vence rate limit, que vence relógio torto.
