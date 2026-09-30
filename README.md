# jeytekdev/api-sync

Generates and updates request collections (Postman, Insomnia, Bruno) straight from your backend's source code — routes, apidoc.js docblocks and PHP 8 attributes — so front-end and QA always work against an up-to-date collection without manual syncing.

Works through **pure static code analysis** (via `nikic/php-parser`): it doesn't require the target framework to be installed or bootable, and never executes anything from the target project. It can be installed as a dev dependency in any project, or used as a standalone tool.

## Installation

```bash
composer require --dev jeytekdev/api-sync
cp vendor/jeytekdev/api-sync/api-sync.php.dist api-sync.php
```

Edit `api-sync.php`:

```php
return [
    'name' => 'My API',
    'baseUrl' => 'https://api.example.test',
    'source' => [__DIR__ . '/src'],
    'out' => __DIR__ . '/api-collections',
    'formats' => ['postman', 'insomnia', 'bruno'],
    'prune' => false,
    'environmentName' => null, // defaults to 'name' above
];
```

## Usage

```bash
# generate/update the collections
vendor/bin/api-sync generate --config=api-sync.php

# CI gate: writes nothing, exits non-zero if the collections are stale
vendor/bin/api-sync check --config=api-sync.php
```

The `--source`, `--format`, `--out`, `--name`, `--base-url` and `--prune` options override the values from the config file.

## Where the data comes from

- **Routes** — read from the code, without executing it, from four independent sources, all authoritative over a naming-convention guess:
  1. PHP 8 attributes `#[Route]`/`#[Get]`/`#[Post]`/... on methods (any framework, or plain PHP);
  2. **Any** `@api {verb} path` apidoc.js comment block in the file. Unlike a per-method docblock lookup, this scans every doc-comment in the file, because real codebases routinely write apidoc.js blocks as a free-standing header before the class (one block per action), completely decoupled from the method they describe — a per-node lookup would miss them entirely;
  3. A matching Yii2 `urlManager` rule, auto-discovered from the project's own config (see below);
  4. Otherwise: Yii2 controllers by convention (`class FooController`, `actionBar`, `yii\rest\ActiveController` for REST-style paths) or Laravel `Route::get('/path', ...)` in `routes/*.php` — a best-effort *guess*.

  If a file contains **any** apidoc.js `@api` block, all naming-convention guesses from that same file are dropped in favor of the documented routes — showing both a guessed and a documented entry for the same action would be more confusing than trusting the documentation. An attribute route is never dropped this way, even when a conflicting `@api` block exists in the same file (it just surfaces as its own, separate endpoint — apidoc.js itself wouldn't know the two are related either). A urlManager-resolved route only replaces the guess for the specific action it covers; other actions in the same controller still fall back to the guess.
- **Endpoint description** (group, parameters, request body, responses, auth) — attributes take priority:
  1. `Jeytekdev\ApiSync\Attribute\{Param,Response,Auth,Group,Version,Summary}` attributes (see `src/Attribute/`); a `Param` with `in: 'body'` is rendered as a JSON request body in all three formats.
  2. Fallback — apidoc.js tags in the docblock (`@api`, `@apiParam`, `@apiSuccess`, `@apiError`, `@apiHeader`, `@apiGroup`, `@apiVersion`, `@apiPermission`, `@apiName`, `@apiUse`).

  A field set by an attribute overrides the docblock; everything else falls back to the docblock. `@apiUse <Name>` pulls in the tags of the matching `@apiDefine <Name>` block, wherever it is — apidoc.js define blocks are routinely shared across many files (e.g. one common "auth header" block used by every controller), so every scanned file's doc-comments are registered as potential `@apiDefine` sources before any endpoint is resolved, not just the current one.

## Yii2 `urlManager`

No config needed — app config files are found under the hood: `config/*.php` at the project root for a basic-template app, and `<entry-point>/config/*.php` for every top-level directory in an advanced-template app (`api/config`, `frontend/config`, `console/config`, ...; `vendor`, `node_modules`, `runtime`, `storage`, `.git` and a few other well-known directories are never descended into).

Each discovered file is statically checked for `components.urlManager`/`urlManager` and resolved as a plain literal array — both inline rules and a `urlManager` value that's itself `require`'d from a separate file (`'urlManager' => require __DIR__ . '/url-manager.php'`) are followed automatically (string concatenation, `__DIR__`, `dirname()` are understood; anything more dynamic, like a Yii alias `@common/...` or a variable, isn't and falls back to a plain naming guess). A file that isn't reachable that way but is clearly named for it (`url-manager.php`, `url_rules.php`, ...) is also picked up directly.

Both plain string rules and `yii\rest\UrlRule` REST rules (`controller`, `except`, `only`, `extraPatterns`, `tokens`, `pluralize`) are resolved into a route per action. Each module's URL prefix (e.g. `v1`) is auto-detected from the standard `'modules' => ['v1' => ['class' => SomeModule::class]]` registration — reading the module class's own `$controllerNamespace` property when present, falling back to Yii2's own default (the module class's namespace) otherwise. Console controllers (anything extending `yii\console\Controller`) are always skipped — they're CLI commands, not routes.

Scope, called out rather than silently dropped: only `yii\rest\UrlRule`/plain-string rules are resolved (dynamic/closure-based rules are skipped, guess still applies); `OPTIONS` actions are never generated; pluralization is a conservative best-effort guess (appends `s` unless the controller ID already ends in one) rather than an exact port of Yii2's inflector — set `'pluralize' => false` on the rule for exact control, same as you would for Yii2 itself.

## Authentication

Whether an endpoint needs auth, and which scheme, is inferred from two independent sources - either is enough on its own:

1. **Code**: the controller's `behaviors()` (including on a parent class, walked via the target project's own Composer PSR-4 map — no config needed) declares an `'authenticator'` filter (`HttpBasicAuth`, `HttpBearerAuth`, `HttpDigestAuth`, `QueryParamAuth`; both `Foo::class` and Yii2's legacy `Foo::className()` are recognized).
2. **Docs**: the endpoint documents an `@apiHeader {String} Authorization ...` (commonly via `@apiUse` pulling in a shared `@apiDefine` block, as in apidoc.js's own recommended pattern for a reusable auth-header note) whose description mentions `basic`/`bearer`/`digest`.

Either way, every such endpoint gets an `Authorization` header with a ready-to-use value:

- basic → `Authorization: Basic {{authToken}}`
- bearer → `Authorization: Bearer {{authToken}}`

If the header was already documented (source 2) but had no value, the description is kept and only the value is filled in. Paste an already-encoded value into the `authToken` environment variable (for Basic, that's `base64(login:password)`). An explicit `@apiPermission`/`#[Auth(...)]` on the endpoint always overrides the inferred scheme.

## Environments

Each format also gets a `baseUrl`/`authToken` environment, generated once and never overwritten afterwards (regenerating it would wipe out a value you already filled in):

- **Postman**: `environment.postman_environment.json`, imported alongside the collection.
- **Insomnia**: inline in `collection.insomnia.json`, already preserved by the merge logic.
- **Bruno**: `environments/environment.bru`.

## Manual edits are never lost

Regenerating the collections doesn't blindly overwrite what's already there:

- **Postman/Insomnia** — every generated request is tagged `_apiSyncId`/`_apiSyncManaged`; on update, manually added tests (`event`), saved response examples (`response`), environment values, and any hand-added requests/folders are preserved. A manually written request body is kept unless the code declares one via a `Param(in: 'body')` attribute.
- **Bruno** — every generated file starts with a `# apisync:managed` marker. Remove the marker (or edit the file without keeping it) and `api-sync` will stop overwriting it.

Endpoints removed from the code are never deleted silently — they're moved into a `_deprecated` section/folder, unless `--prune` is passed.

## CI

See `.github/workflows/api-sync.yml`: `api-sync check` fails the PR if the committed collections have drifted from the code.

## Package development

```bash
composer install
vendor/bin/phpunit
```
