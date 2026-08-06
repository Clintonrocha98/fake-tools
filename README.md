# Fake Tools

## Overview

**fake-tools** is a dev-environment support suite for the `brd-digital` monolith: it stands in for third-party APIs
that have no usable sandbox, so the monolith can be exercised end to end locally. One module per faked provider —
`fake-binance` today, with dakota and starkbank foreseen.

It is built on a modular Laravel scaffold (Filament for the control panel, Livewire, Pest, Tailwind), and follows a
**modular monolith** architecture: each faked provider is self-contained under `app-modules/`.

This is internal tooling for the team, not a product. It is never deployed anywhere that faces real users, and its
data is disposable by design.

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
| `make db-create`     |       | Create both databases inside `brd-db`       |
| `make env-up`        |       | Docker Compose up                           |
| `make env-down`      |       | Docker down (clean)                         |
| `make dev`           |       | `composer run dev` (Vite)                   |
| `make setup`         |       | Full project setup                          |

## Quick Start

This project does **not** run its own database container — it reuses the Postgres
instance from `brd-digital`'s compose stack (`brd-db`), with its own databases:
`dev_fake_tools` and `test_fake_tools`.

```bash
# 1. Bring up brd-digital's stack first — it owns brd-db and the dev-brd network
cd ../brd-digital && make env-up && cd -

# 2. Create this project's two databases (idempotent)
make db-create

# 3. Point .env / .env.testing at the port brd-db publishes on YOUR machine
docker port brd-db     # e.g. 5432/tcp -> 127.0.0.1:5434  =>  DB_PORT=5434

make setup            # Install deps, copy .env files, migrate
make migrate-fresh    # Reset & seed
make dev              # Frontend build/watch
```

`DB_PORT` is the only value that varies per machine: `brd-db` publishes `5432`
internally, but the host port shifts when another container already holds `5432`.
Inside the `dev-brd` network (i.e. the `fake-binance` container) it is always
`brd-db:5432` — see `docker-compose.yml`.

Access admin panel (SuperAdmin required): `/admin` (create via tinker or seed).

## Additional Info

- **Docker**: `docker-compose.yml` for dev env.
- **Vite**: Tailwind v4 CSS-first config.
- **RBAC**: Spatie Permission; sync via `php artisan sync:permissions`.
- Docs: Use Laravel/Filament v4 guides.

For contributions, follow Laravel standards.

## The fake-binance module — Container

The `fake-binance` module is a sandbox server the `brd-digital` monolith points at
instead of the real Binance API in local/dev environments (see
`app-modules/fake-binance`). It runs as its own container, built from the root `Dockerfile`
(FrankenPHP), with its state in the `dev_fake_tools` database on the shared
`brd-db` Postgres — so the ledger survives a restart, and survives the container
and its volume being recreated.

### Running it locally

```bash
docker compose up fake-tools
# or: make fake-tools-up
```

The container joins `brd-db`'s network (`dev-brd`, declared `external`), so
**brd-digital's stack must be up first** and `dev_fake_tools` must exist
(`make db-create`). The entrypoint waits for the database to accept connections
before migrating, then runs the seeders on every start — they are idempotent, so a
restart never re-credits an already-evolved ledger (see `LedgerAccountSeeder`).
The container exposes the app on `http://localhost:8080`, and the
Filament admin panel at `http://localhost:8080/admin` — the seeder creates
`admin@admin.com` / `password` (prefilled on the login form) with no extra steps.

Stop it with `make fake-tools-down`, or `make env-down` for a full teardown.
Neither wipes the ledger any more: the state lives in `dev_fake_tools` on
`brd-db`, not in the `fake-tools-data` volume (which now only holds
logs/sessions/compiled views). To actually reset the ledger, drop and recreate the
database — `docker exec brd-db psql -U postgres -c 'DROP DATABASE dev_fake_tools'`
then `make db-create`.

### Env contract with the consumer

The monolith configures its own `BINANCE_API_KEY` / `BINANCE_API_SECRET` to match
whatever this container is running with. Every env below is documented with its
default in `.env.example`, grouped by the config file that reads it.

| Env (fake-binance) | Meaning |
| --- | --- |
| `FAKE_BINANCE_API_KEY` / `FAKE_BINANCE_API_SECRET` | The pair the monolith configures as `BINANCE_API_KEY` / `BINANCE_API_SECRET` in dev. |
| `FAKE_BINANCE_RECV_WINDOW` | Default signature window (ms) when the request omits `recvWindow`. Capped at 60000. |
| `FAKE_BINANCE_SEED_BALANCES` | Starting ledger balances. Format: comma-separated `ASSET:AMOUNT` pairs (e.g. `BRL:100000,USDT:5000`). |
| `FAKE_BINANCE_FIAT_ADVANCE_SECONDS` / `FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS` / `FAKE_BINANCE_DEPOSIT_ADVANCE_SECONDS` | Fiat / withdraw / crypto-deposit auto-advance cadences, in seconds. |
| `FAKE_BINANCE_FIAT_STATUS_DIALECT` | `live` (SCREAMING_SNAKE, observed) or `classic` (documented). See ADR-0001. |
| `FAKE_BINANCE_FIAT_DEPOSIT_ENABLED` | `false` makes every fiat deposit refuse with `100001`. |
| `FAKE_BINANCE_FIAT_SUPPORTED_CURRENCY` / `..._PAYMENT_METHOD` | The only accepted pair; anything else refuses with `-16010`. |
| `FAKE_BINANCE_FIAT_DEPOSIT_LIMIT` | Optional per-deposit ceiling; unset means no ceiling. |
| `FAKE_BINANCE_USDCBRL_PRICE` / `..._SPREAD` | Fixed mid price and bid/ask spread for USDCBRL. See ADR-0002. |
| `FAKE_BINANCE_USDTBRL_PRICE` / `..._SPREAD` | Fixed mid price and bid/ask spread for USDTBRL — the main flow's intermediate asset pair. |
| `FAKE_BINANCE_SPOT_COMMISSION_RATE` | Taker fee applied to the received asset. |
| `FAKE_BINANCE_TRAVEL_RULE_COUNTRY` | Travel rule questionnaire country. Unset/empty or `NIL` = no requirement (wallet delivery happy path); any country code makes the consumer refuse the delivery. |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | The reused `brd-db` Postgres. Inside `dev-brd` it is `brd-db:5432`. |

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
      - fake-tools-data:/app/storage
    networks:
      - deployed-network
    # No `ports:` mapping here on purpose — see below.

volumes:
  fake-tools-data:
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
