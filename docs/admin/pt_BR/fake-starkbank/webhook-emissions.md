---
title: Webhooks
icon: heroicon-o-bell-alert
order: 5
---

# Webhooks

Toda emissão que este fake montou, assinou e tentou entregar. Olhe esta tela antes de abrir log: um `401` na coluna **HTTP** é quase sempre assinatura que não bate com o PEM público que a aplicação consumidora lê.

A entrega acontece **depois** da resposta que produziu o evento — POSTar dentro do mesmo request travaria um `php artisan serve` single-thread do outro lado.

## Ações

- **Reenviar** — reenvia os bytes gravados, com a mesma assinatura e o mesmo `event.id`. É essa repetição que exercita a idempotência da aplicação consumidora, que precisa reconhecer o evento em vez de conciliar duas vezes.
- **Emitir corrompida** — emite o mesmo par subscription/evento assinado com uma chave gerada na hora e jogada fora. A resposta esperada do outro lado é `401` e nada persistido.
- **Liberar represada** — só aparece numa emissão que o cenário `HoldNext` segurou. Entrega os bytes represados como estão, preservando o `event.id` do instante em que o evento aconteceu.

Uma emissão represada de propósito fica de fora do `fake-starkbank:flush-webhooks`: o flush recupera o que a rede engoliu, não o que um operador escolheu segurar.
