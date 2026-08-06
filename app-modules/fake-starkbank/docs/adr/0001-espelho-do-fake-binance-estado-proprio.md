# ADR-0001: Espelhar o desenho do fake-binance com estado próprio

## Status
Aceita.

## Contexto
O fluxo forex do consumidor trava na perna PIX porque não há sandbox utilizável
do StarkBank. O fake-binance já resolve o mesmo problema para a perna Binance
com um desenho testado: config-driven, avanço lazy na leitura, cenários armados
via painel Filament, estado próprio em Postgres.

## Decisão
O fake-starkbank replica esse desenho: switchboard de cenários e estado
próprios, sem módulo compartilhado de Scenarios e sem acoplamento entre os
dois fakes em runtime. O contrato cruzado do funding cross-fake (o brcode que
a Binance emite e que o StarkBank paga) viaja por constantes de config
combinadas nos dois módulos, nunca por chamada HTTP de um fake para o outro.

## Consequências
Duplicação deliberada do mecanismo de cenários entre os dois módulos até que
um terceiro fake justifique extrair um módulo compartilhado. Nenhum dos dois
fakes pode assumir que o outro está no ar.
