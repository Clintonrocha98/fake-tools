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

# The `composer:2` image is a minimal dependency-resolution tool, not the
# runtime — it never executes app code, so it doesn't ship `ext-intl`/`ext-exif`
# the way the `dunglas/frankenphp` runtime stage does (see its
# `install-php-extensions` list below). Scoped to those two so a genuinely
# missing extension elsewhere still fails the build loudly.
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --ignore-platform-req=ext-intl \
    --ignore-platform-req=ext-exif

# ---------------------------------------------------------------------------
# Frontend assets — Tailwind's Filament theme imports CSS straight out of
# vendor/filament/filament, so the build needs composer's output first.
# ---------------------------------------------------------------------------
FROM oven/bun:1.3.14 AS assets

WORKDIR /app

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY --from=vendor /app/vendor ./vendor
COPY app ./app
COPY app-modules ./app-modules
COPY resources ./resources
COPY vite.config.js ./

# Tailwind v4 `@source` globs point at app/, app-modules/*/resources/views and
# storage/framework/views — a Tailwind scan of a directory that does not exist
# yields no error and no classes, so every source tree it names must be present
# before `bun run build`, not just resources/.
RUN mkdir -p storage/framework/views

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
    exif \
    pcntl \
    opcache \
    pdo_pgsql

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
# APP_ENV=local (nunca production): `URL::forceHttps()` e o prefill de login
# do painel (App\Filament\Shared\Pages\LoginPage) ficam ligados a
# isProduction() (app/Providers/AppServiceProvider.php), e o FrankenPHP aqui
# só serve HTTP puro em :8080 — em "production" toda URL absoluta viraria
# https:// e o painel não carregaria.
ENV APP_NAME="Fake Binance" \
    APP_ENV=local \
    APP_URL=http://localhost:8080 \
    APP_DEBUG=false \
    APP_KEY=base64:uQcyFJ7QyCFaqb0MmokJ/swwR2CrGQqnsRPcYEvqz3A= \
    APP_TIMEZONE=Etc/UTC \
    APP_DISPLAY_TIMEZONE=America/Sao_Paulo \
    LOG_CHANNEL=stack \
    LOG_STACK=single \
    LOG_LEVEL=info \
    DB_CONNECTION=pgsql \
    DB_HOST=brd-db \
    DB_PORT=5432 \
    DB_DATABASE=dev_fake_binance \
    DB_USERNAME=postgres \
    DB_PASSWORD=postgres \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    FILESYSTEM_DISK=local \
    MAIL_MAILER=log \
    SERVER_NAME=":8080"

# Assets publicados do Filament (public/js|css/filament/**) são artefatos de
# build, ignorados no .gitignore e no .dockerignore: sem este passo explícito
# a imagem serve um painel com JS/CSS 404, porque a stage `vendor` roda
# `composer install --no-scripts` (pula o `filament:upgrade` do
# post-autoload-dump) e nenhuma outra stage os gera.
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi

EXPOSE 8080

VOLUME ["/app/storage"]

ENTRYPOINT ["fake-binance-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
