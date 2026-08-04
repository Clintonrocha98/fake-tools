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

DB_PATH="${DB_DATABASE:-/app/storage/app/database.sqlite}"
if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
fi

php artisan migrate --force --ansi

# O ledger fake é stateful e o estado precisa sobreviver a restart. Sem essa
# marca, todo restart re-rodaria os seeders sobre um banco que já evoluiu
# (saldos, ordens) e duplicaria dados.
SEED_MARKER="$(dirname "$DB_PATH")/.seeded"
if [ ! -f "$SEED_MARKER" ]; then
    php artisan db:seed --force --ansi
    touch "$SEED_MARKER"
fi

exec "$@"
