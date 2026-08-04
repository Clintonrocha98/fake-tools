# Sycorax - Modular Laravel Application

## Overview

Sycorax is a modular Laravel 12 scaffold application built with Filament v4 for admin panels, Livewire v3, Pest v4 for
testing, and Tailwind CSS v4.

It follows a **modular monolith** architecture for better organization, scalability, and maintainability.

## Modular Architecture

Core code lives in `app/` (standard Laravel), while business domains and UIs are separated into self-contained
**modules** under `app-modules/`.

### Module Structure

```
app-modules/{module-name}/
├── src/                      # Core PHP classes
│   ├── Models/               # Eloquent models
│   ├── Policies/             # Authorization policies
│   ├── Resources/            # Filament resources (panels only)
│   ├── Schemas/              # Form/Table schemas
│   ├── Tables/               # Table definitions
│   └── {Module}ServiceProvider.php  # Module bootstrap
├── tests/
│   └── Feature/              # Pest feature/unit tests
├── database/
│   ├── factories/            # Model factories
│   └── migrations/           # Module migrations (if any)
└── config/                   # Module-specific config (e.g., rbac.php)
```

### Domain Modules

Handle business logic:

- `users`: User management, models, policies, factories.
- `permissions`: RBAC (Roles/Permissions via Spatie), policies.
- `panel-admin`: Admin panel with User/Role resources.

> Modules prefixed with `panel-` are for **Filament-based UIs**

## Development Conventions

- **Namespaces**: `He4rt\{PascalCasedModule}` (e.g., `He4rt\PanelAdmin`).
- **Service Providers**: One per module for auto-registration.
- **Policies**: Attached via `#[UsePolicy(...)]` attributes on models.
- **Testing**: Pest v4 feature tests per module; use factories; assertions like `assertSuccessful()`, `livewire()`.
- **Filament v4**: Use schemas, `relationship()`, Heroicons; tests with `livewire(Class::class)`.
- **PHP**: Strict types, constructor promotion, explicit types/returns.
- **Formatting**: Laravel Pint v1.
- **Analysis**: PHPStan (Larastan v3), Rector v2.
- Reuse existing components; descriptive names (e.g., `isRegisteredForDiscounts`).

## Makefile

Development workflow powered by [Makefile](Makefile). Run `make help` for all commands.

### Key Commands

| Command              | Alias | Description                                 |
| :------------------- | :---: | :------------------------------------------ |
| `make test`          |  `t`  | Run all Pest tests (`--parallel --compact`) |
| `make test-feature`  |       | Feature tests only                          |
| `make pint`          |       | Run Pint formatter                          |
| `make phpstan`       |  `p`  | PHPStan analysis                            |
| `make check`         |  `c`  | Dry-run: Rector/Pint/PHPStan                |
| `make format`        |  `f`  | Rector + Pint fixes                         |
| `make route-list`    | `rl`  | List routes (`--except-vendor`)             |
| `make migrate-fresh` |       | Reset & seed DB                             |
| `make env-up`        |       | Docker Compose up                           |
| `make env-down`      |       | Docker down (clean)                         |
| `make dev`           |       | `composer run dev` (Vite)                   |
| `make setup`         |       | Full project setup                          |

## Quick Start

```bash
make setup          # Install deps, etc.
make env-up         # Start Docker (DB, etc.)
make migrate-fresh  # DB setup
make dev            # Frontend build/watch
```

Access admin panel (SuperAdmin required): `/admin` (create via tinker or seed).

## Additional Info

- **Docker**: `docker-compose.yml` for dev env.
- **Vite**: Tailwind v4 CSS-first config.
- **RBAC**: Spatie Permission; sync via `php artisan sync:permissions`.
- Docs: Use Laravel/Filament v4 guides.

For contributions, follow Laravel standards.

## Fake Binance Venue — Container

This repo also ships a **fake Binance venue**: a sandbox server the `brd-digital`
monolith points at instead of the real Binance API in local/dev environments (see
`app-modules/venue`). It runs as its own container, built from the root `Dockerfile`
(FrankenPHP) with SQLite persisted on a named volume so state survives a restart.

### Running it locally

```bash
docker compose up fake-binance
# or: make fake-binance-up
```

The entrypoint runs migrations and seeds automatically on first boot (a marker on
the volume prevents re-seeding — and clobbering — an already-evolved ledger on every
restart). The container exposes the app on `http://localhost:8080`, and the
Filament admin panel at `http://localhost:8080/admin` — the seeder creates
`admin@admin.com` / `password` (prefilled on the login form) with no extra steps.

Stop it with `make fake-binance-down` (keeps the `fake-binance-data` volume, so the
ledger survives). `make env-down` also stops it, but runs
`docker compose down --rmi all --volumes` — it deletes `fake-binance-data` along
with everything else, wiping the ledger. Use `fake-binance-down` when you only
want to pause the venue.

### Env contract with the consumer

The monolith configures its own `BINANCE_API_KEY` / `BINANCE_API_SECRET` to match
whatever this container is running with:

| Env (fake-binance) | Meaning |
| --- | --- |
| `FAKE_BINANCE_API_KEY` / `FAKE_BINANCE_API_SECRET` | The pair the monolith configures as `BINANCE_API_KEY` / `BINANCE_API_SECRET` in dev. |
| `FAKE_BINANCE_FIAT_ADVANCE_SECONDS` / `FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS` | Fiat / withdraw auto-advance cadences, in seconds. |
| `FAKE_BINANCE_SEED_BALANCES` | Starting ledger balances. Format: comma-separated `ASSET:AMOUNT` pairs (e.g. `BRL:100000,USDT:5000`). |

Override the defaults via the compose `environment:` block or a shell-exported env
before `docker compose up` (`FAKE_BINANCE_API_KEY=... docker compose up fake-binance`).

### Pointing the monolith at it

`brd-digital`'s Laravel app runs on the **host**, not in its own compose service —
`brd-digital`'s `docker-compose.yml` only has `brd-db`, `brd-redis` and
`brd-mailpit`. Its compose network (`dev-brd`) pins
`host_binding_ipv4: 127.0.0.1`, so this container's `8080:8080` port mapping is
reachable straight from the host. In `brd-digital`'s `.env`:

```bash
BINANCE_BASE_URL=http://127.0.0.1:8080
BINANCE_API_KEY=fake-binance-local-key
BINANCE_API_SECRET=fake-binance-local-secret
```

This is the case for local development. In a **deployed** environment, where both
this container and the monolith run inside the same compose/orchestration network
instead of on a dev's host, join that network here and point `BINANCE_BASE_URL` at
the service name instead:

```yaml
# docker-compose.override.yml, deployed environment only
services:
  fake-binance:
    networks:
      - deployed-network

networks:
  deployed-network:
    external: true
```

```bash
# on the monolith side, in that same environment
BINANCE_BASE_URL=http://fake-binance:8080
```

### Published image

Tagged releases (`vX.Y.Z`) and pushes to `main` publish
`ghcr.io/<owner>/fake-binance` via `.github/workflows/publish-image.yml`, so the
consumer's deployed dev environment can pull the image instead of building it.

### Deployed dev environment

Pull by tag and run alongside the consumer's deployed dev stack:

```yaml
services:
  fake-binance:
    image: ghcr.io/<owner>/fake-binance:vX.Y.Z
    restart: unless-stopped
    environment:
      FAKE_BINANCE_API_KEY: ${FAKE_BINANCE_API_KEY}
      FAKE_BINANCE_API_SECRET: ${FAKE_BINANCE_API_SECRET}
      FAKE_BINANCE_FIAT_ADVANCE_SECONDS: ${FAKE_BINANCE_FIAT_ADVANCE_SECONDS:-60}
      FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS: ${FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS:-60}
      FAKE_BINANCE_SEED_BALANCES: ${FAKE_BINANCE_SEED_BALANCES:-BRL:100000,USDT:5000}
      APP_URL: https://fake-binance.internal.example
    volumes:
      - fake-binance-data:/app/storage
    networks:
      - deployed-network
    # No `ports:` mapping here on purpose — see below.

volumes:
  fake-binance-data:
    external: true

networks:
  deployed-network:
    external: true
```

**The Filament panel must stay internal-only.** Do not publish `8080` on the
deployed host or on any public-facing load balancer/ingress — the panel has no
network-level access control of its own, only the internal network/VPN boundary
protects it. Reach `/admin` through the environment's VPN, and reach the API
container-to-container via `deployed-network` as shown above.
