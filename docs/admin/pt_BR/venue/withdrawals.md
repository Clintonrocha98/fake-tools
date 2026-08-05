---
title: Withdrawals
icon: heroicon-o-arrow-up-on-square
order: 3
---

# Withdrawals

Um withdraw (`POST /sapi/v1/capital/withdraw/apply`) avança lazy por idade: 2 (Awaiting Approval) → 4 (Processing) → 6 (Completed), lido por `GET /sapi/v1/capital/withdraw/history` — nenhuma ação é necessária para o happy path.

## Colunas

- **Status** — o status real, persistido (0-6).
- **Código desconhecido** — `raw_status_override`, quando setado, vence `status` na wire sem tocar na coluna real.
- **Congelado** — se o avanço lazy está pausado para este withdraw.

## Ações

- **Completar agora** — pula o relógio: move direto para Completed (6) com um tx id sintético.
- **Falhar com status** — força um de Cancelled (1), Rejected (3), Failure (5), com um motivo (`info`) obrigatório.
- **Emitir vocabulário desconhecido** — define um código de status arbitrário fora de 0-6, provando o fail-closed do consumidor (ele lança exceção num código não mapeado).
- **Congelar/Descongelar** — pausa ou retoma o avanço lazy.
