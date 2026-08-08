# ADR-0005: Os formatos de identificador da wire e o txId por rede

## Status

Aceita.

## Contexto

Três divergências entre o módulo Withdraw do fake e a venue real. Nenhuma
quebrava o consumidor de então — todas quebram um consumidor que ainda não
existe, que é exatamente para isso que o fake serve.

```
  POST /sapi/v1/capital/withdraw/apply
    fake  → {"id": "019fd5a8-d279-72f7-b8ff-0bbd97779bc9"}   (UUID v7)
    venue → {"id": "7213fea8e94b4a5593d507237e5a555b"}       (hex, 32 chars)

  POST /sapi/v2/fiat/withdraw
    fake  → {"data": {"orderId": "0cd75857-f7a1-4e8e-88e7-9f0eb6a5dee4"}}
    venue → {"data": {"orderId": "0453xxxxxxxxxxx"}}          (numérico)

  saque na rede SOL, txId servido pelo fake:
    0xrvuv3jy7toqety0szwlx4s1vsedbrrcjir5ekiksyjpgza2fsbwcsb1m19hxkzwu
    └┬┘
     └── prefixo 0x — Ethereum. Na Solana a assinatura é base58, sem prefixo.
```

Um id com hífen num campo que a venue entrega sem hífen vaza para um regex, um
índice ou uma coluna dimensionada. Um `txId` vira o `settlementTransactionHash`
do recibo do consumidor e, cedo ou tarde, um link de explorer numa tela: um hash
`0x` num explorer de Solana é um link morto, e o painel que o montar vai parecer
quebrado sem estar.

Somava-se um quarto problema, escondido: o gerador antigo usava `Str::random(64)`,
que produz alfanumérico — o "hash hex" do fake nunca foi hex.

## Decisão

1. **O `id` do withdraw sai na wire como 32 hex, sem hífen** (`WithdrawWireId`).
   A PK continua sendo o UUID v7 do banco: tirar os hífens de um UUID dá
   exatamente 32 hex, então a conversão é reversível e o id da wire e o do
   registro são o MESMO valor em dois formatos. Trocar a coluna por `string`
   apenas para persistir o formato da venue seria pagar uma migration para
   guardar menos informação.
2. **O `orderId` do fiat withdraw é uma string numérica**, gerada pelo mesmo
   `FiatOrderNumber` que já produzia o `orderNo` do depósito — as duas pernas
   fiat falam o mesmo formato de identificador, como na venue.
3. **O `txId` é gerado no formato da REDE** (`TxIdFormat`): `0x` + 64 hex na ETH,
   88 caracteres base58 na SOL, 64 hex sem prefixo na TRX. Uma rede que o fake
   não conhece cai no hex sem prefixo — o formato neutro, que nunca finge ser um
   hash EVM numa chain que não é EVM. O hex agora é hex de verdade
   (`bin2hex(random_bytes(...))`).
4. **`network` é opcional no apply.** A doc marca o parâmetro como opcional:
   omitido, a venue usa a rede default da coin. O fake resolve essa default como
   a **primeira** rede de `fake-binance-withdraw.fees`, a mesma disciplina que
   `GetDepositAddress` já aplicava ao endereço. Uma rede **informada** mas fora do
   mapa continua recusando: a default nunca é plano B para uma chain não mapeada,
   porque um withdraw na chain errada é irrecuperável.

## Consequências

- Um `-1102` onde a venue teria sacado deixa de existir — o falso negativo que
  mandaria alguém depurar o lugar errado.
- Quem consome o fake passa a ver ids no formato que a venue entrega, então um
  regex ou uma coluna dimensionada contra o fake continua valendo contra a venue.
- O `txId` de um saque Solana pode ser colado num explorer de Solana. O de ETH,
  num explorer EVM.
- Testes que fixavam o id hifenizado ou o prefixo `0x` universal foram
  reescritos; nenhum comportamento de negócio mudou junto.
