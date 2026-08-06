# ADR-0002: O extrato pagina do mais novo para o mais antigo

## Status
Aceita.

## Contexto
As três listagens do módulo (`GET /v2/invoice`, `/v2/transfer`,
`/v2/brcode-payment`) nasceram ordenando do mais ANTIGO para o mais novo, com
página fixa de 100 — o teto que a doc oficial do StarkBank confirma
("all paged lists have at most 100 objects").

Dois fatos verificados tornam essa ordem insustentável:

1. O `PollExtratoCommand` do consumidor manda **um** request por recurso e
   **ignora** o `cursor` da resposta — ele só pagina a listagem de workspace,
   nunca a de extrato. Na prática o consumidor lê a página 1 e mais nada.
2. O container compartilha o Postgres `dev_fake_tools`, que sobrevive a
   restart. Passadas 100 linhas na tabela, a página 1 do mais-antigo-primeiro é
   um arquivo morto: a invoice recém-paga da sessão de dev fica fora dela.

Combinados, `starkbank:poll-extrato` reportaria `0 paid invoice(s)` para sempre
e a rede de segurança da conciliação — que o Destination do mapa nomeia como
razão de existir do fake — nunca reconciliaria. Pior: como o avanço lazy só roda
sobre a janela lida, uma invoice além da posição 100 nem amadureceria pelo
caminho da listagem.

**A doc oficial não declara a direção da ordenação.** Ela documenta o teto de
100, o `cursor` e o par `after`/`before` como filtros de data ("filter entities
created after/before this date"), e cala sobre o sentido. Os 14 fixtures
gravados do consumidor também não decidem: todo array de lista tem UM item.

## Decisão
As três listagens ordenam do mais NOVO para o mais antigo (`created` desc,
`id` desc como desempate), e o cursor de keyset caminha para trás no tempo.

Na ausência de declaração da doc, a direção é fixada pelo que o consumidor
precisa e pela convenção universal de extrato bancário: quem lê uma única página
de extrato está perguntando "o que aconteceu agora", não "o que aconteceu
primeiro".

O `after` com data ISO-8601 continua sendo um FILTRO (`created >= data`), não um
sentido de leitura — é a semântica da doc e é como `--after=` do consumidor o
usa. Filtro e ordenação são coisas distintas: `after` recorta a janela, a ordem
decide por onde ela começa.

## Consequências
A página 1 é sempre a janela recente, e é ela que o poll de um request só lê —
o ciclo emitir → avançar → varrer fecha sozinho em qualquer tamanho de tabela.

O avanço lazy passa a favorecer as linhas novas: uma linha antiga além da
posição 100 só amadurece por `GET /v2/{recurso}/{id}` ou pela paginação
completa. É a troca certa — a linha antiga é histórico, a nova é o teste em
curso.

Se algum dia uma chamada real ao StarkBank contradisser esta ordem, é esta ADR
que muda, junto com `scopeInPageOrder` dos três models e o keyset das três
Actions — não há outra fonte da ordem no módulo.
