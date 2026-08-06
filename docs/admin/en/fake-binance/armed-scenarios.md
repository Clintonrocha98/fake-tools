---
title: Armed Scenarios
icon: heroicon-o-bolt-slash
order: 6
---

# Armed Scenarios

Here you decide what happens to the **next** order of a leg. Flip the outcome's switch, close the page: the next order the consuming application places comes back that way, and the fake returns to the happy path on its own.

This is different from the actions on the other screens, which rewrite a record **already created** — here the deviation is born in the order's own response, exactly as the real venue does it.

## Rules

- **Good for one shot.** The leg's first order consumes the scenario. For two in a row, arm it twice.
- **One outcome per leg.** Turning one on turns the previous one off — two outcomes for the same order would contradict each other. Different legs can stay armed at the same time.
- **The global switches win.** With outage mode on, the order never reaches the leg, and the armed scenario stays standing for later.
- **The ledger follows the outcome.** A partial fill credits only the fraction; a refusal moves nothing.
- **Editing a parameter re-arms.** Changing the fraction, code or status with the switch already on stores the new value right away and notifies you. Typing with every switch off arms nothing.

## Spot conversion

- **Preencher parcial e expirar o resto** — executes only the given fraction and responds `EXPIRED`. The fraction runs from 0 to 1; empty uses half.
- **Recusar com código** — responds with the `/api/v3` error envelope and creates no order at all. The list only offers codes this leg can emit.
- **Responder REJECTED** — HTTP 200 with `REJECTED` and a zeroed fill.
- **Emitir vocabulário desconhecido** — executes normally, but the wire responds with whatever status you type. Empty uses `SOME_FUTURE_STATE`.
