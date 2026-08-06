---
title: Invoices
icon: heroicon-o-qr-code
order: 1
---

# Invoices

A perna de cash-in: as cobranças PIX que a aplicação consumidora emitiu. A lista é só leitura — uma cobrança nasce do `POST /v2/invoice`, nunca daqui.

O happy path não precisa de clique: uma invoice criada há um minuto vira `paid` na próxima leitura.

## Colunas que importam

- **Destino armado** — o estado que um cenário armado gravou na criação. É aplicado na leitura seguinte e depois zerado, então a invoice volta a envelhecer normalmente.
- **Atraso extra (s)** — segundos somados ao relógio do avanço lazy pelo desfecho *Atrasar o pagamento*.
- **Congelada** — enquanto ligada, nenhuma leitura move o status.

## Ações

- **Forçar status** — grava qualquer status do vocabulário ignorando os dois relógios, e emite o evento correspondente. É como se chega a `credited` e `reversed`, que nenhum relógio produz.
- **Congelar / Descongelar** — estaciona a invoice onde ela está. Descongelar depois do prazo faz a leitura seguinte saltar para o estado que os relógios já alcançaram.
- **Armar próximo** (cabeçalho) — os mesmos desfechos da página de cenários armados, oferecidos onde você já está olhando.
