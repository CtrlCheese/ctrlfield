# Contributing to CtrlField

Thanks for helping. Bug reports, fixes and documentation improvements are all welcome.

## Before you start

- **Security problems**: do not open an issue. Follow [SECURITY.md](SECURITY.md).
- **Bugs**: open an issue with the CtrlField, WordPress and PHP versions and the steps to reproduce. A minimal schema file that shows the problem helps most.
- **New features**: open an issue first, so we can agree on the approach before you write code.

## Development setup

Requirements: PHP 8.2+, Composer 2, Node 24+ and, for integration tests, Docker.

```bash
git clone https://github.com/CtrlCheese/ctrlfield.git
cd ctrlfield
composer install
npm ci
npm run build          # admin JS/CSS and the Gutenberg sidebar
```

Symlink or copy the folder into `wp-content/plugins/` of a local site, or start one with `npx wp-env start`.

While working on the admin UI, `npm run dev:admin` rebuilds on every change.

## Checks

Every pull request must pass the same checks as CI:

```bash
vendor/bin/phpunit                     # unit tests (core)
npm run test:js                        # Vitest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpcs                       # WordPress coding standards + security sniffs
```

Integration tests run against a real WordPress in `wp-env`. See `.github/workflows/ci.yml`.

## Guidelines

- **PHP 8.2**, `declare(strict_types=1);`, PSR-4 under `src/`.
- **Admin screens**: check the capability and the nonce in every handler. Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- **Tests**: add a test with every fix or feature. Pure logic goes in a small class with a unit test.
- **Built files**: `assets/admin/ctrlfield.js` and `ctrlfield.css` are committed. Run `npm run build` before you commit changes to `assets/admin/src/`.
- **Docs**: update `docs/` and, when it affects users, the README in the same pull request.
- **Commits**: one topic per commit, with a message that says what changes for the user.

## License

By contributing, you agree that your contribution is licensed under the [GPL-2.0-or-later](LICENSE).
