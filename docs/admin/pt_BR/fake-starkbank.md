---
title: Fake StarkBank
icon: heroicon-o-qr-code
order: 5
type: group
---

# Fake StarkBank

O **controle remoto de cenários** da malha PIX do fake StarkBank: cash-in (invoice), funding da venue (pagamento de BR Code) e cash-out (transfer), mais os webhooks assinados que a aplicação consumidora recebe.

O happy path avança sozinho — a invoice vira `paid`, a transfer chega a `success`, o pagamento liquida, tudo na leitura e sem nenhum clique aqui. Estas páginas existem para os desvios que a aplicação consumidora precisa exercitar sob comando.

O switchboard é **próprio**: ligar o modo outage aqui derruba as rotas `/v2/*` deste fake e **nenhuma** da seção Fake Binance, que tem tela e tabela separadas.
