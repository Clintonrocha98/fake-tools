# Testing

The suite runs on **Pest 4**. Create tests with `php artisan make:test --pest {name}`
(add `--unit` for a unit test) and run them with `php artisan test --compact` (filter with
`--filter=` or `--testsuite=`).

## Structure

Four suites, declared in **both** `phpunit.xml` (testsuite `<directory>` globs) and
`tests/Pest.php` (the `pest()->…->in(...)` bindings). Each spans the root `tests/` dir
**and** every module's `app-modules/*/tests/` dir — keep the two config files in sync when
you add a suite or a module.

| Suite | Location | Binding | DB |
|-------|----------|---------|----|
| `Unit` | `tests/Unit`, `app-modules/*/tests/Unit` | group `unit` | no |
| `Feature` | `tests/Feature`, `app-modules/*/tests/Feature` | group `feature` + `LazilyRefreshDatabase` | yes |
| `Arch` | `tests/Arch`, `app-modules/*/tests/Arch` | group `arch` | no |
| `Contract` | `tests/Contract`, `app-modules/*/tests/Contract` | group `contract` + `LazilyRefreshDatabase` | yes |

Run each with `composer test:unit` · `test:feature` · `test:arch` · `test:contract`, or
`php artisan test --group=<suite>` / `make test-<suite>`.

## Architecture test suite (`arch()` + reflection guards)

The `Arch` suite is first-class — a `phpunit.xml` testsuite **and** a `->group('arch')`
binding in `tests/Pest.php` (no `RefreshDatabase`; arch tests never touch the DB),
runnable in isolation:

- `composer test:arch` · `php artisan test --group=arch` · `make test-arch`

### Where each rule lives

- **System-wide invariants → `tests/Arch/ArchTest.php`.** Readable `arch('…')` rules:
  strict types on `App` and `He4rt\PanelAdmin`, and no-debug
  (`dd`/`dump`/`ray`/`var_dump`/`var_export`).
- **Module-specific rules → `app-modules/<slug>/tests/Arch/<Module>ArchTest.php`** (glob
  `app-modules/*/tests/Arch`). This is where the **domain → never → presentation**
  direction lives (`He4rt\Identity` not→toUse `He4rt\PanelAdmin`), the no-`stdClass`
  guard, and enum-contract / model-base rules scoped to the sub-namespace where they
  actually hold — a module-wide `toBeEnums()->toImplement(...)` breaks on internal
  (non-UI) enums, so scope it (e.g. `He4rt\Identity\ExternalIdentity\Enums`).
- **Reflection guards live here too, not in `tests/Unit`.** Any structural gate written
  with `test()` + reflection (`NoLooseArrayCastsTest`, `ModuleTestNamespacesRegisteredTest`)
  belongs in `tests/Arch/`. Put new structural/convention gates here.

### Validate every rule against the real code BEFORE writing it

An arch rule must **pass**. Confirm the fact with `rg`/reflection first; if it isn't
already true, either fix the code (the rule becomes the gate) or don't write the rule.
Concrete facts that shape the current rules: external-identity models **extend the shared
`App\Models\BaseModel`** (so `toExtend(BaseModel::class)` holds — a model extending
`Model` directly would fail it); domain enums implement `Filament\Support\Contracts\*`,
so "domain doesn't depend on presentation" forbids the **presentation module namespace**
(`He4rt\PanelAdmin`), never the Filament framework.

### Gotchas

- `phpunit --list-tests` under-reports `arch()` tests (late registration) — trust the
  `--group=arch` run counts, not the listing.
- Renamed a namespace? Every `expect('He4rt\X')` / `not->toUse('He4rt\Y')` naming it by
  string must change in the same edit, or the rule points at a non-existent namespace.

## Module test autoloading — register every module's `Tests\` namespace in the root

When a module gains a **shared, namespaced test class** (a base `TestCase`, a trait, a
fake/fixture under `tests/Support/`, or a dataset), its `He4rt\<Module>\Tests\` namespace
MUST be declared in the **root** `composer.json` `autoload-dev.psr-4` — in the **same
change** — not only in the module's own `composer.json`.

**Why:** Composer never loads a dependency's `autoload-dev`, and `internachi/modular`
installs each module as a path-repo dependency, so the module's own
`He4rt\<Module>\Tests\ → tests/` mapping is **inert** in the aggregate build. PHPUnit
includes `*Test.php` files by path, so plain test classes still run — but a shared class
that doesn't match the `*Test.php` suffix resolves **only** through PSR-4 → fatal
`Class not found` without the root entry. This is not automated (`modules:sync` only
touches `phpunit.xml`; upstream declined — `InterNACHI/modular#105`).

<code-snippet name="Root composer.json autoload-dev" lang="json">
{
    "autoload-dev": {
        "psr-4": {
            "He4rt\\PanelAdmin\\Tests\\": "app-modules/panel-admin/tests/",
            "He4rt\\Identity\\Tests\\": "app-modules/identity/tests/",
            "Tests\\": "tests/"
        }
    }
}
</code-snippet>

- Keep **both** blocks — the module's (isolated scope, dropped by `--no-dev`) and the
  root's (aggregate suite). Don't deduplicate by deleting the module's — `make:module`
  recreates it.
- One root `psr-4` entry per module that has a `tests/` dir, matching the module's own
  test namespace (e.g. `panel-admin` → `He4rt\PanelAdmin\Tests\`) — read the prefix from
  the module's `composer.json` PSR-4 rather than guessing it.
- Order alphabetically for predictable diffs, then run `composer dump-autoload`.
- When you `make:module <slug>`, add its root entry in the same change.

**Verification:** `tests/Arch/ModuleTestNamespacesRegisteredTest.php` fails if any module
with a `tests/` dir is missing from the root `autoload-dev`, printing the exact line to paste.
