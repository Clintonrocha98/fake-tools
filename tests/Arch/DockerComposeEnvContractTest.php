<?php

declare(strict_types=1);

/*
 * Gate: toda env `FAKE_*` documentada no `.env.example` precisa de uma linha no
 * bloco `environment:` do serviço `fake-tools` em `docker-compose.yml`.
 *
 * POR QUÊ: `.env` está no `.dockerignore` e o serviço não declara `env_file:`.
 * Uma env fora daquele bloco NUNCA chega ao container — exportá-la no host não
 * basta, e nada avisa. O dev segue o `.env.example`, roda
 * `docker compose up fake-tools` e o fake ignora a variável em silêncio: um
 * cenário armado por env (workspace bloqueado, DICT sem chave) simplesmente não
 * acontece, e o taxId do funding volta ao default sem aviso.
 *
 * Envs comentadas no `.env.example` ficam de fora de propósito: são as
 * opcionais sem default (PEM inline, teto de depósito, travel rule), e declará-las
 * no compose as entregaria como string VAZIA, que é diferente de ausente para
 * `env()`.
 */

/**
 * @return list<string> as envs `FAKE_*` ativas (não comentadas) do .env.example
 */
function documentedFakeEnvKeys(): array
{
    $lines = (array) file(base_path('.env.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $keys = [];

    foreach ($lines as $line) {
        if (preg_match('/^(FAKE_[A-Z0-9_]+)=/', (string) $line, $matches) === 1) {
            $keys[] = $matches[1];
        }
    }

    return $keys;
}

/**
 * @return list<string> as envs `FAKE_*` declaradas no bloco environment: do compose
 */
function composeFakeEnvKeys(): array
{
    $compose = (string) file_get_contents(base_path('docker-compose.yml'));

    preg_match_all('/^\s+(FAKE_[A-Z0-9_]+):/m', $compose, $matches);

    /** @var list<string> $keys */
    $keys = $matches[1];

    return $keys;
}

test('toda env FAKE_* documentada chega ao container pelo bloco environment do compose', function (): void {
    $ausentes = array_values(array_diff(documentedFakeEnvKeys(), composeFakeEnvKeys()));

    expect($ausentes)->toBe([], sprintf(
        "Envs documentadas no .env.example que NUNCA chegam ao container (adicione ao bloco environment: do serviço fake-tools):\n  %s",
        implode("\n  ", $ausentes),
    ));
});

test('o compose não declara env FAKE_* que o .env.example não documenta', function (): void {
    // A direção oposta: uma env só no compose é indocumentada para quem lê o
    // .env.example, e o dev nunca descobre que pode configurá-la.
    $indocumentadas = array_values(array_diff(composeFakeEnvKeys(), documentedFakeEnvKeys()));

    expect($indocumentadas)->toBe([], sprintf(
        "Envs no compose que o .env.example não documenta:\n  %s",
        implode("\n  ", $indocumentadas),
    ));
});
