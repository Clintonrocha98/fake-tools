# Armar o cenário do próximo pedido

> Spec de design — 05/ago/2026. Origem: [issue #10](https://github.com/Clintonrocha98/fake-tools/issues/10).
> Perna nova do fluxo inverso fica na [issue #12](https://github.com/Clintonrocha98/fake-tools/issues/12) — não espera por esta.

## 1. Contexto

Todo cenário de falha do fake hoje é **pós-fato**: o happy path executa, o registro nasce
bem-sucedido, e o painel muta o registro depois. Consequência para o consumidor
(`brd-digital`): o desvio só aparece na **releitura** (`GET`), nunca na resposta do próprio
`POST`.

```
  HOJE — o painel rasura um registro já emitido
  ────────────────────────────────────────────────────────────────
   1. consumidor: POST  ──►  fake executa, grava FILLED, credita ledger
   2. consumidor lê a resposta:  FILLED     ← sempre, sem exceção
   3. operador clica "Recusar" no painel
   4. fake regrava REJECTED e ESTORNA o ledger
   5. consumidor: GET   ──►  REJECTED       ← só aqui o desvio existe
```

Três problemas concretos:

1. **Código do consumidor nunca exercitado.**
   `BinanceMarketExecution::neutralStatus()` (`brd-digital`, módulo `integration-binance`)
   decide `PartiallyFilled` quando o POST volta com `executedQty > 0` e status ≠ `FILLED`.
   O fake é incapaz de produzir essa resposta, então esse branch nunca roda em dev — só
   num teste unitário de lá, contra um payload montado à mão.
2. **Fidelidade contábil.** Porque o fill já aconteceu quando o operador clica,
   `RejectSpotOrder` e `ExpireSpotOrderPartially` precisam **estornar**
   (`ReverseSpotOrderLedgerFill`). Uma venue que recusa nunca executou — não há o que
   estornar. O fake gera um crédito e um estorno onde a realidade não tem movimento.
3. **Cenário exige clique.** Nada disso é acionável por um teste automatizado ponta a
   ponta: alguém precisa abrir o painel no meio do fluxo.

## 2. O que muda

```
  PROPOSTO — o cenário é armado antes e o desvio nasce de origem
  ────────────────────────────────────────────────────────────────
   1. operador arma:  "próxima conversão sai pela metade"
   2. consumidor: POST  ──►  fake consulta o armado, executa SÓ metade,
                             grava EXPIRED, credita SÓ metade
   3. consumidor lê a resposta:  EXPIRED + executedQty parcial
                                             ← o desvio está aqui
   4. o armado é consumido: o próximo pedido volta ao happy path
```

### Régua do escopo

> Para **cada pedido** que o `brd-digital` faz, o fake sabe produzir **todos os desfechos**
> daquele pedido — sob comando, sem mexer em dinheiro no ledger.

O limite é o do mapa (issue #1): só o que o consumidor chama. Não é "emular a Binance
inteira".

## 3. Decisões

| # | Decisão | Razão |
|---|---------|-------|
| D1 | **Vale uma vez.** O armado é consumido pelo primeiro pedido da perna e o fake volta ao happy path. | Os três switches globais (`outage`, `rate_limit`, `clock_skew`) já cobrem "fica ligado até desligar". Este mecanismo cobre o complementar — o desvio pontual — sem risco de deixar o fake quebrado e esquecer. |
| D2 | **Um armado por perna**, simultâneos entre si. | Armar falha no depósito e parcial na conversão ao mesmo tempo é um cenário legítimo de fluxo. |
| D3 | **O ledger obedece ao desfecho, nunca executa-e-estorna.** Parcial credita a fração; recusa não toca o ledger. | É o problema 2 do contexto. Estorno é artefato do desenho pós-fato. |
| D4 | **Persistido em banco**, como o switchboard. | Sobrevive a restart; o fake roda em container e em dev implantado. |
| D5 | **Consumo atômico.** Dois pedidos concorrentes: só um recebe o armado. | O poller do treasury pode disparar concorrente; um cenário aplicado duas vezes não é reprodutível. |
| D6 | **As ações pós-fato continuam existindo.** | Rasurar um registro já emitido segue sendo um cenário real (a venue muda o status entre o POST e o GET). O mecanismo novo não substitui, complementa. |
| D7 | **Mecanismo genérico desde o começo**, com desfechos declarados por perna. | As pernas do fluxo inverso (issue #12) herdam o mecanismo sem redesenho. |

## 4. Duas naturezas de perna

A distinção governa **onde** o desfecho aparece, e é o que impede um desenho único ingênuo:

```
  SÍNCRONA — conversão spot
    POST /api/v3/order  ──►  executa e responde o resultado no mesmo request
    o desfecho armado muda A RESPOSTA DO POST

  ASSÍNCRONA — depósito fiat, saque de stablecoin
    POST (anuncia)  ──►  ...tempo...  ──►  GET (consulta o desfecho)
    o desfecho armado muda O DESTINO do registro criado
    (e o POST pode ele mesmo recusar, no envelope da família)
```

Na perna assíncrona o valor é diferente do da síncrona: o registro **nasce destinado** ao
desfecho, então o avanço por tempo entrega a falha sem clique nenhum — cenário acionável
por teste automatizado, que hoje é impossível.

## 5. Desfechos por perna

### 5.1 Conversão spot — `POST /api/v3/order`

| Desfecho | Resposta do POST | Ledger |
|---|---|---|
| `fill_partial_expired` | 200 · `status: EXPIRED` · `executedQty` = fração · `fills` com um fill da fração | credita só a fração |
| `refuse_with_code` | erro da família `/api/v3` · `code` escolhido (`-2010` `NEW_ORDER_REJECTED`, `-1013` `FILTER_FAILURE`) | intocado · nenhuma `SpotOrder` criada |
| `respond_rejected` | 200 · `status: REJECTED` · `executedQty: 0` | intocado · `SpotOrder` criada zerada |
| `emit_unknown_status` | 200 · `status` = string arbitrária | credita normal (só a wire mente) |

`fill_partial_expired` aceita a fração como parâmetro, default `0.5`.

`respond_rejected` fica no catálogo apesar da dúvida de fidelidade: a doc da Binance
descreve `REJECTED` como *"not accepted by the engine and not processed"*, o que na
colocação chega como envelope de erro, não como 200. Mantido porque o custo é uma linha e
o mapeamento do consumidor (`BinanceOrderStatus::Rejected → ConversionStatus::Rejected`)
existe.

### 5.2 Depósito fiat — `POST /sapi/v1/fiat/deposit` → `GET /sapi/v1/fiat/get-order-detail`

| Desfecho | Efeito |
|---|---|
| `credit_immediately` | pula o relógio: o depósito já nasce creditado no ledger |
| `fail`, `expire`, `cancel`, `need_additional_action` | o registro nasce destinado ao status terminal; a primeira consulta já o entrega. **Nunca credita.** |
| `refuse_on_announce` | o POST recusa no envelope fiat (HTTP 200 + `code` ≠ `000000`, ADR-0001), com o código escolhido: `-16007` limite, `-16009` KYC, `-16010` moeda/método, `-16012` canal indisponível |
| `emit_unknown_status` | vocabulário de wire arbitrário na consulta |

`refuse_on_announce` é ganho novo: hoje nenhum desses quatro códigos é provocável.

### 5.3 Saque de stablecoin — `POST /sapi/v1/capital/withdraw/apply` → `GET .../history`

| Desfecho | Efeito | Ledger |
|---|---|---|
| `complete_immediately` | nasce em `Completed` (6) com `txId` sintético | debita `amount` + `fee` (happy path) |
| `cancel`, `reject`, `fail` | nasce no terminal de falha (1 / 3 / 5) com `info` | **devolve** o que o apply debitou |
| `refuse_on_apply` | o apply recusa com o código escolhido (`-2010`) | intocado · nenhuma `Withdrawal` criada |
| `emit_unknown_status` | código fora de 0–6 na wire | debita normal |

**Divergência que este trabalho corrige:** `ForceWithdrawStatus` (pós-fato) grava o status
terminal de falha e **não devolve** o valor debitado — o ledger fica com o dinheiro fora da
conta num saque que a venue diz ter falhado. A venue real devolve. O mesmo estorno usado
pelo desfecho armado passa a ser aplicado pela ação pós-fato.

## 6. Componentes

```
  fake-binance/src/Scenarios/
  ├── Enums/
  │   ├── VenueLeg.php                  fiat_deposit · spot_conversion · stablecoin_withdraw
  │   ├── SpotConversionOutcome.php     os 4 desfechos da §5.1
  │   ├── FiatDepositOutcome.php        os desfechos da §5.2
  │   └── StablecoinWithdrawOutcome.php os desfechos da §5.3
  ├── Models/ArmedScenario.php          uma linha por perna armada
  ├── DTOs/ArmedScenarioPayload.php     VO tipado do parâmetro do desfecho
  ├── Casts/AsArmedScenarioPayload.php  ponte JSON ↔ VO
  └── Actions/
      ├── ArmScenario.php               arma (substitui o armado da perna)
      ├── DisarmScenario.php            desarma
      └── ConsumeArmedScenario.php      consome atomicamente · devolve ?ArmedScenario

  fake-binance/src/Spot/
  ├── DTOs/SpotExecutionPlan.php        fração · status final · recusa
  └── Actions/PlanNextSpotExecution.php consome o armado e devolve o plano (neutro se nada)

  fake-binance/src/Fiat/
  ├── DTOs/FiatDepositPlan.php          destino do registro · recusa no anúncio
  └── Actions/PlanNextFiatDeposit.php

  fake-binance/src/Withdraw/
  ├── DTOs/WithdrawPlan.php             destino do registro · recusa no apply · devolução
  └── Actions/PlanNextWithdraw.php
```

Cada `PlanNext*` é o único ponto que fala com `ConsumeArmedScenario`; a Action de execução
recebe o plano e nunca conhece o vocabulário de cenário.

### Tabela

`fake_binance_armed_scenarios` — uma linha por perna armada, `leg` único:

| Coluna | Tipo | Nota |
|---|---|---|
| `id` | uuid pk | |
| `leg` | string, **unique** | `VenueLeg` — a unicidade é o que garante D2 |
| `outcome` | string | valor do enum de desfecho **da perna**; validado no `ArmScenario` |
| `payload` | jsonb | VO tipado (`AsArmedScenarioPayload`), nunca cast `array` solto |
| `armed_at` | timestampTz | |
| `created_at` / `updated_at` | timestampTz | |

`payload` carrega só o que o desfecho precisa: `fraction` (parcial), `errorCode` (recusa),
`rawStatus` (vocabulário desconhecido), `reason` (`info` do saque).

### O plano, e por que ele existe

A Action de execução não deve aprender a vocabulário de cenário. Ela pede um **plano** e
obedece:

```php
// ANTES — PlaceMarketOrder sempre produz o happy path
return SpotOrder::query()->create([
    'status' => OrderStatus::Filled,
    'executed_qty' => $executedQty,
    // ...
]);

// DEPOIS — a Action executa o plano; o cenário vive fora dela
$plan = $this->planNextExecution->handle();   // SpotExecutionPlan (neutro se nada armado)

if ($plan->refusal instanceof BinanceErrorCode) {
    throw ScenarioRefusalException::withCode($plan->refusal);   // controller mapeia
}

$executedQty = $plan->applyFraction($executedQty);              // 1.0 no happy path
// ... swap credita exatamente $executedQty ...

return SpotOrder::query()->create([
    'status' => $plan->finalStatus,                             // Filled no happy path
    'executed_qty' => $executedQty,
    // ...
]);
```

Sem cenário armado, o plano é o **plano neutro** (fração `1`, status `FILLED`, sem recusa)
— o happy path não ganha um `if` de cenário, ganha um plano que não desvia.

### Consumo atômico (D5)

```php
return DB::transaction(function (): ?ArmedScenario {
    $armed = ArmedScenario::query()
        ->where('leg', $leg)
        ->lockForUpdate()          // o segundo request espera aqui
        ->first();

    $armed?->delete();             // consumido: quem chegar depois vê o happy path

    return $armed;
});
```

## 7. Superfície no painel

Uma página **Cenários armados** no grupo Fake Binance, um card por perna:

```
  ┌─ Conversão spot ─────────────────────── armado: nada ──┐
  │  ( ) preencher parcial e expirar   fração [ 0.5    ]   │
  │  ( ) recusar             código [ -2010          ▾ ]   │
  │  ( ) responder REJECTED                                │
  │  ( ) vocabulário desconhecido    status [          ]   │
  │                                       [ armar ]        │
  └────────────────────────────────────────────────────────┘
  ┌─ Depósito fiat ──────────── armado: falhar (há 2 min) ─┐
  │  ...                                  [ desarmar ]     │
  └────────────────────────────────────────────────────────┘
  ┌─ Saque de stablecoin ────────────────── armado: nada ──┐
  │  ...                                                   │
  └────────────────────────────────────────────────────────┘
```

Cada Resource de perna (`SpotOrders`, `FiatOrders`, `Withdrawals`) ganha uma **ação de
cabeçalho** "Armar próximo" que abre o mesmo formulário — descoberta onde o operador já
está olhando, com a página como fonte da verdade.

Enums de desfecho implementam `HasLabel`/`HasColor`/`HasDescription` (e `HasIcon` onde for
significativo), como todo enum de domínio do repo.

## 8. Comportamento esperado (BDD)

**Conversão — o caso que destrava o consumidor**

- **Dado** um cenário `fill_partial_expired` com fração `0.5` armado para `spot_conversion`
  **quando** o consumidor manda `POST /api/v3/order` de BUY com `quoteOrderQty: 1000`
  **então** a resposta é 200 com `status: EXPIRED`, `executedQty` igual à metade do que o
  happy path preencheria e um `fills` de um fill dessa metade,
  **e** o ledger debitou metade do BRL e creditou metade do USDC — sem nenhum estorno,
  **e** um segundo POST idêntico volta a preencher `FILLED` (o armado foi consumido).

- **Dado** um cenário `refuse_with_code` com `-2010` armado
  **quando** o consumidor manda o POST
  **então** a resposta é o envelope de erro da família `/api/v3` com `code: -2010`,
  **e** nenhuma `SpotOrder` foi criada,
  **e** o ledger está intocado.

**Depósito fiat — cenário sem clique**

- **Dado** um cenário `fail` armado para `fiat_deposit`
  **quando** o consumidor anuncia o depósito e consulta `get-order-detail` depois do
  `advance_seconds`
  **então** a consulta responde o status terminal de falha no dialeto configurado,
  **e** o ledger nunca foi creditado.

- **Dado** um cenário `refuse_on_announce` com `-16009` (KYC) armado
  **quando** o consumidor manda `POST /sapi/v1/fiat/deposit`
  **então** a resposta é HTTP 200 com o envelope fiat e `code` `-16009` (ADR-0001),
  **e** nenhuma `FiatOrder` foi criada.

**Saque — coerência contábil**

- **Dado** um cenário `reject` armado para `stablecoin_withdraw`
  **quando** o consumidor manda o apply e depois lê o history
  **então** o history reporta `3` (Rejected) com `info`,
  **e** o saldo da stablecoin está de volta ao valor de antes do apply (`amount` + `fee`
  devolvidos).

**Borda e compatibilidade**

- **Dado** nenhum cenário armado **quando** qualquer perna é chamada **então** o
  comportamento é bit a bit o de hoje — plano neutro, nenhum desvio.
- **Dado** um cenário armado para `spot_conversion` **quando** dois POSTs chegam
  concorrentes **então** exatamente um recebe o desvio e o outro o happy path.
- **Dado** um switch global ligado (`outage`) **e** um cenário armado **quando** a perna é
  chamada **então** o switch global vence (ele age no middleware, antes da rota) **e** o
  armado **não** é consumido — segue de pé para quando o outage sair.
- **Dado** um cenário armado **quando** o processo reinicia **então** o armado continua de
  pé (persistido).
- As ações pós-fato do painel seguem funcionando sem mudança de assinatura (D6).

## 9. Testes

| Suíte | O que cobre |
|---|---|
| `tests/Feature/Scenarios/` | armar · desarmar · substituir o armado da perna · consumo one-shot · consumo atômico sob concorrência · precedência do switch global |
| `tests/Feature/Spot/` | cada desfecho da §5.1 pela rota, conferindo wire **e** ledger |
| `tests/Feature/Fiat/` | cada desfecho da §5.2, incluindo o envelope de recusa no anúncio |
| `tests/Feature/Withdraw/` | cada desfecho da §5.3, incluindo a devolução ao ledger |
| `tests/Contract/` | as respostas desviadas continuam casando por shape com os envelopes gravados do consumidor |
| `tests/Arch/` | todo enum de desfecho implementa os contratos Filament; nenhum cast `array` solto no model novo |
| `panel-admin/tests/Feature/` | a página arma/desarma cada perna e a ação de cabeçalho de cada Resource abre o formulário |

O plano neutro precisa de um teste nomeado: sem cenário armado, o happy path de cada perna
é idêntico ao de hoje.

## 10. Fora de escopo

- **As pernas do fluxo inverso** (depósito de cripto, saque em reais, travel rule) — issue
  #12. Elas declaram desfechos e herdam este mecanismo quando existirem.
- **Modificadores contínuos** (`congelar`, `atrasar brcode`) — não são desfechos terminais;
  seguem como ações por registro.
- **Armar por chave de API / por consumidor** — o fake assume um consumidor (mesma decisão
  do ledger único, mapa issue #1).
- **Histórico de cenários disparados** — o armado é deletado ao ser consumido. Se rastrear
  "o que disparou quando" virar dor, é ticket próprio.
