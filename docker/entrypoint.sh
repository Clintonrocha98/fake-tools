#!/bin/sh
set -e

# O volume em /app/storage pode chegar vazio (primeiro boot de um volume novo)
# e sobrepõe os diretórios que o repositório versiona com .gitkeep — sem eles
# a view/cache do Laravel falha ao gravar. Recria a árvore em todo start,
# idempotente.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

php artisan storage:link --ansi || true

# O Postgres é de OUTRO compose (o brd-db do brd-digital), então não há
# `depends_on` que garanta ordem: este container pode subir antes do banco
# aceitar conexão. Sem a espera, o `migrate` abaixo mata o start.
echo "Aguardando o banco em ${DB_HOST}:${DB_PORT}..."
attempt=1
until php artisan db:show --quiet 2>/dev/null; do
    if [ "$attempt" -ge 30 ]; then
        echo "Banco inacessível em ${DB_HOST}:${DB_PORT} após 30 tentativas." >&2
        echo "A stack do brd-digital está no ar e o database '${DB_DATABASE}' existe?" >&2
        # Repete sem suprimir: o loop acima engole a causa, e "driver ausente"
        # não é a mesma falha que "banco ainda subindo".
        echo "Erro real:" >&2
        php artisan db:show --quiet >&2 || true
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 2
done

php artisan migrate --force --ansi

# Sem marcador externo: o estado vive no Postgres, que sobrevive ao volume e ao
# container, então o próprio banco é a fonte da verdade sobre "já semeado".
# Todos os seeders são idempotentes — o do ledger não recredita um ledger que já
# tem contas (ver LedgerAccountSeeder), e o admin é firstOrCreate.
php artisan db:seed --force --ansi

exec "$@"
