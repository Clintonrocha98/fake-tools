---
title: Cenários armados (PIX)
icon: heroicon-o-bolt-slash
order: 7
---

# Cenários armados (PIX)

Aqui você combina o que vai acontecer com o **próximo** pedido — ou com o próximo evento — de uma perna da malha PIX. Ligue o switch do desfecho, feche a página: o próximo disparo da aplicação consumidora sai daquele jeito, e o fake volta sozinho ao happy path.

## Regras

- **Vale uma vez.** O primeiro pedido da perna consome o cenário. Para dois seguidos, arme duas vezes.
- **Um desfecho por perna.** Ligar um desliga o anterior — dois desfechos para o mesmo pedido se contradizem. Pernas diferentes ficam armadas ao mesmo tempo.
- **Os switches globais vencem.** Com o modo outage ligado, o pedido não chega na perna e o cenário armado continua de pé para depois.
- **Editar o parâmetro re-arma.** Mudar o motivo ou os segundos extras com o switch já ligado grava o valor novo na hora e avisa. Digitar com todos os switches desligados não arma nada.

## Duas naturezas de perna

- **Assíncrona — Invoice, Transfer, BrcodePayment.** O cenário é consumido no `POST` e o registro **nasce destinado**: a resposta da criação continua sendo a normal, e a leitura seguinte entrega o desfecho. É o mesmo avanço lazy que o fake já usa para tudo.
- **Por evento — Webhook.** O cenário é consumido no instante da **emissão**, seja qual for a perna que a produziu. Armar aqui vale para o próximo evento, não para a próxima invoice.

## Invoice

- **Nascer vencida / expirada / cancelada** — a cobrança nasce destinada a `overdue`, `expired` ou `canceled`; a próxima releitura já a entrega assim.
- **Congelar o avanço** — a cobrança nasce congelada: nenhuma leitura move o status até um operador descongelar na tela de Invoices.
- **Atrasar o pagamento** — a cobrança paga normalmente, só que os segundos informados somam ao relógio do avanço lazy. Vazio usa 300.

## Transfer e BrcodePayment

- **Nascer destinada a failed** — é o único caminho até `failed` sem clique de operador. O motivo digitado viaja no log do webhook do desfecho; vazio usa o motivo padrão da perna.
- **Segurar em processing** — o registro sobe até `processing` e para ali, por mais que o relógio ande.

## Webhook

- **Entregar o próximo evento duas vezes** — o mesmo `event.id` chega duas vezes, exercitando a idempotência da aplicação consumidora.
- **Assinar o próximo evento com outra chave** — envelope bem formado cuja assinatura não fecha com o PEM público do outro lado; a resposta esperada lá é `401` e nada persistido.
- **Represar o próximo evento** — a emissão é gravada e nunca POSTada. Ela espera na tela de Webhooks até você usar **Liberar represada**; o comando de flush a ignora de propósito.
