# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Composer dependencies — app-modules/* are path repositories (symlinked),
# so the full module trees must be present before `composer install`, not
# just their composer.json files.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY app-modules ./app-modules

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Frontend assets — Tailwind's Filament theme imports CSS straight out of
# vendor/filament/filament, so the build needs composer's output first.
# ---------------------------------------------------------------------------
FROM oven/bun:1.3.14 AS assets

WORKDIR /app

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY vite.config.js ./

RUN bun run build

# ---------------------------------------------------------------------------
# Runtime — FrankenPHP serves the app directly (`php_server` in the stock
# Caddyfile), no Laravel Octane / worker mode involved.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions \
    bcmath \
    intl \
    gd \
    zip \
    pcntl \
    opcache

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

COPY docker/entrypoint.sh /usr/local/bin/fake-binance-entrypoint
RUN chmod +x /usr/local/bin/fake-binance-entrypoint

# Chave de app fixa e não-secreta: este servidor nunca vê produção, então
# travar a chave no build evita exigir configuração extra do dev do
# consumidor para o `docker compose up` funcionar de primeira.
#
# APP_ENV=production apesar de este ser um servidor de dev: `composer install`
# roda sem --dev, e o DatabaseSeeder padrão do scaffold só cria um usuário
# admin via factory (dependente de fakerphp/faker, pacote de dev) quando
# isLocal() — em "local" o seed automático do entrypoint quebraria.
ENV APP_NAME="Fake Binance" \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:uQcyFJ7QyCFaqb0MmokJ/swwR2CrGQqnsRPcYEvqz3A= \
    APP_TIMEZONE=Etc/UTC \
    APP_DISPLAY_TIMEZONE=America/Sao_Paulo \
    LOG_CHANNEL=stack \
    LOG_STACK=single \
    LOG_LEVEL=info \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/storage/app/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    FILESYSTEM_DISK=local \
    MAIL_MAILER=log \
    SERVER_NAME=":8080"

EXPOSE 8080

VOLUME ["/app/storage"]

ENTRYPOINT ["fake-binance-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
