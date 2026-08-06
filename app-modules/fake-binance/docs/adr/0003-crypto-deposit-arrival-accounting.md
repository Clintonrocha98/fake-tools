# ADR-0003: Contabilidade da chegada de cripto e da saída em reais (fluxo inverso)

## Status

Aceita.

## Contexto

O fluxo inverso (Dakota manda stablecoin → conversão SELL → BRL sai por PIX)
exige duas pernas que não nascem de um pedido do consumidor pela wire spot:

1. **A chegada de cripto** aparece de fora — alguém transferiu para o endereço
   de depósito. O fake precisa de um jeito de fazer um depósito "aparecer" e de
   decidir quando o ledger é creditado.
2. **A saída em reais** (`POST /sapi/v2/fiat/withdraw`) debita BRL e devolve um
   `orderId`; a idempotência é por `clientOrderId`.

## Decisão

1. **Semeadura por Action + comando artisan, nunca por rota de wire.** Uma
   chegada é anunciada por `AnnounceCryptoDeposit` (via
   `php artisan fake-binance:announce-deposit`, e pronta para o painel). A
   Binance real não tem rota "faça um depósito aparecer" — inventar uma na
   wire misturaria vocabulário de controle com o contrato que o fake dubla.
2. **Progressão de status por idade, lazy: 0 Pending → 6 Credited → 1 Success**,
   dirigida por `fake-binance-deposit.advance_seconds` e disparada apenas pela
   leitura do hisrec — a mesma disciplina das pernas fiat e withdraw. `7 Wrong
   Deposit` e `8 Waiting User Confirm` existem no vocabulário
   (`DepositStatus`), mas nunca são produzidos pelo avanço automático.
3. **O ledger é creditado ao ENTRAR em Credited (6)** — é quando a Binance real
   torna o saldo negociável (a conversão SELL já pode rodar; só o saque segue
   bloqueado, distinção que o fake não modela). `credited_at` é o guard de
   idempotência: um depósito que salta direto de Pending a Success credita uma
   única vez no caminho.
4. **Endereço de depósito fixo por rede, via config**
   (`fake-binance-deposit.addresses`) — o formato do endereço é da chain, não
   do coin; rede fora do mapa é recusada, nunca inventada. O endereço que
   `deposit/address` anuncia é o mesmo que as chegadas carimbam.
5. **A saída em reais debita o `amount` inteiro no aceite** e nasce
   `Processing`; recusas (moeda/método não suportado, saldo insuficiente) saem
   síncronas no envelope fiat HTTP 200, estendendo a ADR-0001 à perna de
   saída. Registro em tabela própria (`fake_binance_fiat_withdrawals`) — a
   `FiatOrder` de entrada carrega brcode/máscaras que não existem na saída, e
   `get-order-detail` resolve só ordens de entrada.

## Consequências

- O consumidor pode implementar a orquestração do fluxo inverso ponta a ponta
  contra o fake: anuncia-se a chegada na bancada, o poll do hisrec a vê
  amadurecer e o saldo aparecer, a SELL converte e o fiat withdraw drena o BRL.
- `status=6` ("credited but cannot withdraw") com saldo já livre no ledger é
  uma simplificação deliberada: o fake não modela a janela "negociável mas não
  sacável". Se um teste futuro precisar dessa janela, ela nasce aqui.
- O código fiat de saldo insuficiente (`-16006`) não tem verbatim na doc
  pública (a doc fiat não documenta o caso); o consumidor decide apenas por
  `code !== '000000'` e lê a mensagem, então o valor exato não o afeta.
