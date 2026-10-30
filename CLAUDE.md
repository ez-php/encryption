# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `check_module_deps.php` — fails when a package's code imports an ez-php package its `composer.json` does not declare (module `src`: `require`/`suggest`; tests: `require`/`require-dev` and their dependencies), or requires one it never uses
4. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
5. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
6. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
composer module-deps:check   # undeclared / unused ez-php package dependencies only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis --extensions=gmp
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `asyncapi` → `AsyncApi`, `asyncapi-ui` → `AsyncApiUi`,
`bignum` → `BigNum`, `dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `graphql-subscriptions` → `GraphQLSubscriptions`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `openapi` → `OpenApi`, `qrcode` → `QrCode`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services`/`--extensions` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `ez-php/docker` in `require-dev` — like every ez-php requirement, constrained to the current release, e.g. `"^2.6.0"`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders (`docker/db/`, the MySQL init script, only with `--services=mysql`). Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch/Memcached/Mailpit service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
vendor/bin/docker-init --services=memcached,mailpit
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`, `memcached`, `apcu` (with `apc.enable_cli=1`):

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
vendor/bin/docker-init --extensions=memcached,apcu
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | 7702 (opt-in service) |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| `ez-php/broadcast` | — | 6384 (`REDIS_HOST_PORT`) | — |
| `ez-php/feature-flags` | — | 6385 (`REDIS_HOST_PORT`) | — |
| `ez-php/scheduler` | — | 6386 (`REDIS_HOST_PORT`) | — |
| `ez-php/session` | — | 6387 (`REDIS_HOST_PORT`) | — |
| `ez-php/metrics` | — | 6388 (`REDIS_HOST_PORT`) | — |
| `ez-php/event-store-snapshots` | 3312 | — | — |
| **next free** | **3313** | **6389** | **7703** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. Every module row maps it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here. Services reached only over the Compose network publish no host port and need no entry at all: Memcached (`memcached:11211` in the root stack and `ez-php/cache`) and the opt-in Elasticsearch/Typesense backends in `modules/search/docker-compose.ci.yml`.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Every multi-package script sources it — `update_all.sh`, `fullcheck.sh`, `bump_version.sh` and the `git_*_all.sh` scripts (`git_push_all.sh`, `git_pull_all.sh`, `git_tag_all.sh`, `git_delete_all_tags.sh`) — so the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/encryption

Symmetric authenticated encryption for data at rest and in transit through the client:
an `Encrypter` over ext-sodium's XChaCha20-Poly1305 AEAD with key rotation, a `Crypt`
static façade, plus two optional pieces of glue — `EncryptedCookieMiddleware` and the
`EncryptedString` ORM cast.

---

## Source Structure

```
src/
├── Encrypter.php                          — encrypt()/decrypt() with versioned payload, context binding, current + previous keys
├── Key.php                                — generate()/parse() of the `base64:` key format
├── Crypt.php                              — static façade over Encrypter (setEncrypter/setResolver/resetEncrypter)
├── EncryptionServiceProvider.php          — binds Encrypter + EncryptedCookieMiddleware from config, wires Crypt, registers encryption:key
├── EncryptionException.php                — base exception (configuration errors: missing/malformed key)
├── DecryptException.php                   — payload cannot be decrypted (malformed, unknown key, tampered, wrong context)
├── Console/
│   └── GenerateKeyCommand.php             — `encryption:key`: prints a new key, never writes .env
├── Middleware/
│   └── EncryptedCookieMiddleware.php      — decrypts incoming cookies, encrypts queued response cookies
└── Orm/
    └── EncryptedString.php                — ez-php/orm CastableInterface for an encrypted string column

tests/
├── TestCase.php                           — Base PHPUnit test case
├── EncrypterTest.php                      — round trip, payload format, context binding, tampering, rotation, malformed payloads
├── EncryptionKeyTest.php                  — Key::generate()/parse(), invalid key formats
├── CryptTest.php                          — façade: unset failure, delegation, lazy resolver
├── EncryptionServiceProviderTest.php      — config → Encrypter/middleware bindings, lazy Crypt wiring, missing-key failure, command registration
├── Encryption/ApplicationTestCase.php     — bootstrapped app with a temp config/encryption.php
├── Console/GenerateKeyCommandTest.php     — prints a parseable key
├── Middleware/EncryptedCookieMiddlewareTest.php — decrypt/drop incoming, encrypt outgoing with attributes kept, except list
└── Orm/EncryptedStringTest.php            — hydrate/extract, memoised ciphertext keeps unchanged entities clean
```

---

## Key Classes and Responsibilities

| Class | Responsibility |
|---|---|
| `Encrypter` | `encrypt(plaintext, context)` / `decrypt(payload, context)`. Payload `ez1.` + base64url(key id ‖ nonce ‖ ciphertext+tag). Encrypts with the current key; decrypts with the current or any previous key. |
| `Key` | The configured key format: `base64:` + 32 random bytes. `parse()` throws `EncryptionException` on anything else. |
| `Crypt` | Static façade, resolved lazily from the container after `EncryptionServiceProvider::boot()`. |
| `EncryptionServiceProvider` | `encryption.key`, `encryption.previous_keys` (list or comma-separated), `encryption.cookie_except`. |
| `EncryptedCookieMiddleware` | Transparent cookie encryption, context `cookie:<name>`. |
| `Orm\EncryptedString` | Cast value object; `of(plaintext)`, `value()`, memoised `castTo()`. |
| `Console\GenerateKeyCommand` | `encryption:key`. |

---

## Design Decisions and Constraints

- **XChaCha20-Poly1305 AEAD instead of `secretbox`.** Both are in ext-sodium with a 24-byte random nonce; the AEAD variant adds authenticated additional data, which binds the version prefix, key id and the caller's `context` to the ciphertext. That is what stops an encrypted cookie from being replayed under another cookie name, without packing the name into the plaintext.
- **Key id in the payload.** The first 4 bytes of a BLAKE2b hash of the key select the key directly on decrypt instead of trying every key; a (negligible) id collision still works because every key with a matching id is tried. 4 bytes of a hash of a 256-bit random key reveal nothing usable.
- **Rotation is decrypt-only for previous keys.** `encrypt()` always uses `encryption.key`. Re-encrypting stored data with the new key is the application's job (read + write through the cast does it).
- **Keys are parsed, not derived.** A key must be exactly 32 random bytes in `base64:` form — no passphrase/KDF, so a weak human-chosen secret cannot slip in.
- **Lazy key resolution.** The provider binds closures; `Crypt` uses a resolver. A missing key fails on first use, so `ez encryption:key` and unrelated commands run before a key exists.
- **`Crypt` is global state on purpose.** `CastableInterface::castFrom()` is static — the cast has no other way to reach the encrypter. Everything else takes `Encrypter` by constructor injection.
- **`EncryptedString` memoises its ciphertext.** Each encryption uses a fresh nonce; without memoising, `DirtyTracker` and `Hydrator` (both call `castTo()`) would see different ciphertexts and an unchanged value would look dirty or be stored twice differently. A loaded value writes back the exact ciphertext it came from. It is deliberately not `Stringable`, so plaintext never leaks through implicit string conversion (logs, templates).
- **Cookie middleware drops what it cannot decrypt.** A tampered, foreign or retired-key cookie reaches the handler as absent, never as attacker-controlled plaintext. `XSRF-TOKEN` is excluded by default because client-side JavaScript must read it; deletion cookies (negative TTL) pass through. Only the concrete `EzPhp\Http\Request` can carry rewritten cookies (`Request::withCookies()`); other `RequestInterface` implementations are handed on unchanged.
- **Needs `ez-php/http` ≥ 2.6.4** for `Request::withCookies()` and `ResponseInterface::withCookies()` (added for this module). `ez-php/console` and `ez-php/orm` are `suggest`s — the command is registered by class name and the cast is only autoloaded when an entity uses it.
- **Strings only.** No serialisation of arrays/objects — callers `json_encode()` explicitly.

---

## Testing Approach

- Pure unit tests for `Encrypter`, `Key`, `Crypt`, the middleware (real `Request`/`Response`) and the ORM cast (`Hydrator`/`DirtyTracker` without a database).
- `EncryptionServiceProviderTest` bootstraps an application via `ez-php/testing-application` with a temporary `config/encryption.php` reading `ENCRYPTION_KEY`/`ENCRYPTION_PREVIOUS_KEYS`.
- No external services; needs ext-sodium (bundled with the official PHP images).
- Test classes live in the shared `Tests\` namespace but must be uniquely named
  across the whole monorepo — the root `phpunit.xml` loads every package in one
  process, so a duplicate name is a fatal error, not a test failure. Prefix with
  `Encryption` when the obvious name is already taken.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Password hashing | `ez-php/auth` (`password_hash()`) |
| Asymmetric crypto, signatures, JWT | `ez-php/auth` (JWT), `ez-php/webhook` (HMAC), `ez-php/push` (Web Push encryption) |
| KMS / HSM / envelope encryption with remote key stores | Application layer or a future module |
| Writing keys into `.env`, automated re-encryption of stored data | Application / deployment |
| Session storage, and the native session cookie | `ez-php/session` — PHP's session cookie is sent by `session_start()` via `header()` and read from `$_COOKIE`, never through `Response::withCookies()`/`Request::cookies()`, so `EncryptedCookieMiddleware` does not see it (it holds only an opaque id anyway) |
