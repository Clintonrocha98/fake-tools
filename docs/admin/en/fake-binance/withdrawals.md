---
title: Withdrawals
icon: heroicon-o-arrow-up-on-square
order: 3
---

# Withdrawals

A withdrawal (`POST /sapi/v1/capital/withdraw/apply`) advances lazily by age: 2 (Awaiting Approval) → 4 (Processing) → 6 (Completed), read by `GET /sapi/v1/capital/withdraw/history` — no action needed for the happy path.

## Columns

- **Status** — the real, persisted status (0-6).
- **Código desconhecido** — `raw_status_override`, when set, wins over `status` on the wire without touching the real column.
- **Congelado** — whether the lazy advance is paused for this withdrawal.

## Actions

- **Completar agora** — skips the clock: moves straight to Completed (6) with a synthetic tx id in the format of the withdrawal's own network (base58 on Solana, `0x` + 64 hex on Ethereum, bare hex elsewhere), so the hash can be pasted into that chain's explorer.
- **Falhar com status** — forces one of Cancelled (1), Rejected (3), Failure (5), with a required `info` reason.
- **Emitir vocabulário desconhecido** — sets an arbitrary status code outside 0-6, proving the consumer's fail-closed handling (it throws on an unmapped code).
- **Congelar/Descongelar** — pauses or resumes the lazy advance.
