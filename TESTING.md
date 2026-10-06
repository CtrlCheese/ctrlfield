# CtrlField — Testing Guide

Three layers of automated tests, each targeting a different scope.

---

## Layer 1 — Unit Tests (PHPUnit, no WP required)

**365 tests, zero dependencies on WordPress.**

```bash
cd ctrlfield/
vendor/bin/phpunit
```

### What's covered
| Area | Tests |
|---|---|
| Field types (13 types) + getDefinition() | `tests/Unit/Fields/` |
| Save pipeline — all 8 stages in isolation | `tests/Unit/Core/Pipeline/Stages/` |
| Full pipeline flow (nonce, required, XSS, unknown keys) | `SavePipelineFullFlowTest` |
| Type coercion edge cases (null, group, repeater, int vs float) | `TypeCoercionNullEdgeCasesTest` |
| Migration engine (rollback, dry-run, chained migrations) | `tests/Unit/Core/Migration/` |
| Storage adapters (PostMeta + Options) with in-memory drivers | `tests/Unit/Storage/` |
| Builder API (CPT, Taxonomy, FieldGroup, OptionsPage) | `tests/Unit/Builder/` |
| Context registry (post_type, options_page resolution) | `tests/Unit/Registry/` |
| Blade directives (@field, @field_raw, @repeater) | `tests/Unit/Integrations/Blade/` |
| REST API showInRest filtering contract | `tests/Unit/Integrations/REST/` |
| Renderers (all 13 field types, Alpine x-model output) | `tests/Unit/Fields/Renderers/` |

### Coverage (requires pcov or Xdebug)

```bash
# Install pcov (once):
pecl install pcov

# Run with coverage:
vendor/bin/phpunit --coverage-html coverage/
open coverage/index.html
```

---

## Layer 2 — Integration Tests (PHPUnit, real WordPress)

**Tests that require a running WordPress install and database.**

### Option A — wp-env (recommended)

```bash
# Start the test environment (Docker required)
npx @wordpress/env start

# Run integration tests inside the container
npx @wordpress/env run tests-cli vendor/bin/phpunit \
    --configuration phpunit-integration.xml
```

### Option B — Local WordPress install

```bash
# Install WP test suite (one-time setup)
bash bin/install-wp-tests.sh wordpress_test root root localhost

# Run
vendor/bin/phpunit --configuration phpunit-integration.xml
```

### What's covered
| Test class | Acceptance criterion |
|---|---|
| `SavePostHookTest` | save_post writes to `_ctrlfield_data`; indexed fields get separate meta rows; WP_Query meta_query works |
| `AutosaveTest` | Autosave request does NOT write field data |
| `NonceRejectionTest` | Invalid/missing nonce → no data written |

The `SavePostHookTest` also verifies the **visible_when preservation** rule: a field hidden by `visibleWhen` must survive save with its previous value intact.

---

## Layer 3 — E2E Tests (Playwright, real browser)

**Browser-driven tests against a live wp-env environment.**

### Setup

```bash
# Install Playwright and browsers (one-time)
npm install
npx playwright install chromium

# Start wp-env
npx @wordpress/env start
```

### Run

```bash
# Run all E2E specs
npm run test:e2e

# Interactive UI mode (recommended for debugging)
npm run test:e2e:ui

# Run a single spec
npx playwright test tests/E2E/repeater.spec.js
```

### What's covered
| Spec | Acceptance criterion |
|---|---|
| `repeater.spec.js` | Add rows → fill → save → reload → verify data; reorder rows → save → verify order |
| `visible-when.spec.js` | Select condition field → dependent appears instantly; change value → hides; value preserved after save |
| `options-page.spec.js` | Fill options page → save → "Settings saved." notice → values retained |
| `wysiwyg.spec.js` | Type in TinyMCE → save → reload → content retrieved; live sync to adminState |

### Prerequisites for E2E
The `dev-test.php` registrations must be loaded. Confirm `CTRLFIELD_DEV_TEST: true` is set in `.wp-env.json` (it is by default in this repo).

---

## Running all layers in CI

```yaml
# .github/workflows/test.yml (example)
- name: Unit tests
  run: vendor/bin/phpunit

- name: Start wp-env
  run: npx @wordpress/env start

- name: Integration tests
  run: npx @wordpress/env run tests-cli vendor/bin/phpunit --configuration phpunit-integration.xml

- name: E2E tests
  run: npm run test:e2e
```
