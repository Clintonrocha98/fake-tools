---
title: Switches de cenário (PIX)
icon: heroicon-o-signal-slash
order: 6
---

# Switches de cenário (PIX)

Dois switches globais, aplicados **antes de qualquer endpoint `/v2/*`** — antes até da verificação de assinatura —, para que a aplicação consumidora veja uma indisponibilidade em vez de uma falha de autenticação. Persistem em banco e sobrevivem a restart; lembre de desligar quando terminar.

- **Modo outage** — toda rota responde `503` com `{"errors": [{"code": "internalServerError", …}]}`.
- **Modo rate limit** — toda rota responde `429` com `{"errors": [{"code": "tooManyRequests", …}]}` e o header `Retry-After`.

Com os dois ligados, o outage vence o rate limit.

Estes switches alcançam **só** este fake. A seção Fake Binance tem switchboard próprio, em tabela própria: armar um outage aqui não derruba o fluxo da venue que já funciona.
