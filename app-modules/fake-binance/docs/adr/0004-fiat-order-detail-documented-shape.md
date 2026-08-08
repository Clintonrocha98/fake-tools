# ADR-0004: O shape documentado do get-order-detail e o casing do apiPaymentMethod

## Status

Aceita.

## Contexto

O `data` de `GET /sapi/v1/fiat/get-order-detail` foi montado a partir do que o
consumidor lê, não do que a doc descreve. Comparando com
`legacy-docs/fiat/rest-api/Get-Order-Detail`, faltavam `orderId`, `fee`,
`errorCode`, `errorMessage` e `ext` (OBJECT), e sobrava `pixcode` na raiz — um
campo que a doc não documenta em lugar nenhum.

Dois desses buracos importam:

1. **`ext`.** A doc não diz onde o payload PIX viaja. O consumidor se protegeu
   com uma varredura **recursiva** (`BinanceVenueFundingGateway::firstString()`):
   chaves conhecidas primeiro, qualquer string com preâmbulo EMV depois. Servindo
   o brcode sempre na raiz, o ramo recursivo — o que existe justamente porque a
   Binance tem um `ext` onde o payload provavelmente mora — nunca rodava contra o
   fake. Era um caminho de código cuja primeira execução seria em produção.
2. **`apiPaymentMethod`.** A doc do `POST /sapi/v1/fiat/deposit` escreve o método
   em minúsculo (`pix`); o consumidor manda `Pix`; o fake comparava
   case-insensitive e aceitava os dois. Se a venue for case-sensitive, o
   consumidor toma `-16010` em produção e o fake nunca teria avisado.

## Decisão

1. **Onde doc e observação ao vivo dão nomes diferentes ao mesmo dado, o fake
   serve os DOIS.** `orderNo`/`orderId` e `fee`/`totalFee` saem juntos, com o
   mesmo valor — a disciplina que `status`/`orderStatus` já seguia. Nenhum campo
   que já funcionava foi removido.
2. **`errorCode`/`errorMessage` existem sempre**, `null` enquanto a ordem pode
   ainda ser paga ou já foi, preenchidos quando ela morre. O par é derivado do
   status (`FiatOrderStatus::errorCode()`/`errorMessage()`); o corte entre
   "falha" e "não falha" é o mesmo que já decidia se o brcode sai na wire, para
   não existirem duas classificações de terminalidade no módulo. Um
   `forced_wire_status` — vocabulário fora do enum — deixa o par `null`: o fake
   não sabe o que aquele estado significa e não inventa um motivo.
   Os **valores** do par são vocabulário do fake: a doc lista os campos e não
   define conteúdo, e nenhuma observação de produção os capturou. O contrato que
   importa é a presença e a nulidade, não a string.
3. **`ext` (OBJECT) sempre presente, com o brcode movível para dentro dele.**
   `fake-binance-fiat.brcode_placement` (`root` default, `ext`) decide: em `ext`,
   o brcode vai para `data.ext.pixCode` e a raiz fica **sem** `pixcode`, de modo
   que só a varredura recursiva o encontre. É o cenário que exercita o ramo
   recursivo do consumidor antes da venue real.
4. **Modo estrito de casing sob comando**, `fake-binance-fiat.strict_payment_method_casing`
   (default `false`). Ligado, a comparação de `apiPaymentMethod` vira literal e
   um `Pix` contra um `pix` recusa com `-16010`. O fake não decide qual é a
   verdade — ele permite que a pergunta seja respondida na bancada. O switch vale
   para as duas pernas fiat (deposit e withdraw): são o mesmo guard, e uma
   assimetria entre elas seria um comportamento que ninguém consegue explicar.

## Consequências

- O ramo recursivo do `extractBrcode` do consumidor passa a ter cobertura contra
  o fake — é um teste de contrato, não uma promessa.
- O happy path não muda: sem tocar em config, o `data` ganha campos novos e
  mantém `pixcode` na raiz, então nenhum consumidor existente quebra.
- Os valores de `errorCode` são do fake. Se uma observação de produção capturar
  os reais, eles entram em `FiatOrderStatus` e este ADR é revisado — nenhum
  consumidor decide por eles hoje.
