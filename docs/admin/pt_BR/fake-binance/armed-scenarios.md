---
title: Cenários armados
icon: heroicon-o-bolt-slash
order: 6
---

# Cenários armados

Aqui você combina o que vai acontecer com o **próximo** pedido de uma perna. Ligue o switch do desfecho, feche a página: o próximo pedido que a aplicação consumidora fizer sai daquele jeito, e o fake volta sozinho ao happy path.

É diferente das ações das outras telas, que rasuram um registro **já criado** — aqui o desvio nasce na própria resposta do pedido, que é o que a venue real faz.

## Regras

- **Vale uma vez.** O primeiro pedido da perna consome o cenário. Para duas seguidas, arme duas vezes.
- **Um desfecho por perna.** Ligar um desliga o anterior — dois desfechos para o mesmo pedido se contradizem. Pernas diferentes ficam armadas ao mesmo tempo.
- **Os switches globais vencem.** Com o modo outage ligado, o pedido nem chega na perna, e o cenário armado continua de pé para depois.
- **O ledger acompanha o desfecho.** Um fill parcial credita só a fração; uma recusa não move nada.
- **Editar o parâmetro re-arma.** Trocar fração, código ou status com o switch já ligado grava o valor novo na hora e avisa. Digitar com todos os switches desligados não arma nada.

## Conversão spot

- **Preencher parcial e expirar o resto** — executa só a fração informada e responde `EXPIRED`. A fração vai de 0 a 1; vazio usa metade.
- **Recusar com código** — responde o envelope de erro de `/api/v3` e não cria ordem nenhuma. A lista traz só os códigos que essa perna sabe emitir.
- **Responder REJECTED** — HTTP 200 com `REJECTED` e fill zerado.
- **Emitir vocabulário desconhecido** — executa normal, mas a wire responde o status que você digitar. Vazio usa `SOME_FUTURE_STATE`.
