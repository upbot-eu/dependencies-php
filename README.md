# UpBot dependency reporter for PHP

[English](README.md) · [Deutsch](README.de.md) · [Slovenčina](README.sk.md)

PHP 8+ with cURL, Composer 2.2+. Framework-independent CLI. Reads `composer.lock`
and/or npm `package-lock.json` v1–v3. Uses `vlucas/phpdotenv` for dotenv parsing.
Never executes project scripts, installs or updates packages.

**Packagist publication is pending.** After publication:

```sh
composer require upbot/dependencies
```

Add the unique monitoring token to the existing private `.env` or process environment:

```dotenv
UPBOT_TOKEN=replace_with_monitor_token
```

```sh
vendor/bin/upbot doctor
vendor/bin/upbot report
```

The production endpoint and current project directory are defaults. Install as a
normal dependency; `composer install --no-dev` removes dev dependencies.
No framework adapter is required for plain PHP, Symfony or WordPress projects
managed through Composer. Laravel applications can use the Artisan adapter below.

Optionally run `vendor/bin/upbot init` to create `.env.upbot` with mode 600.
It refuses to overwrite existing files. Optional settings:

```dotenv
# UPBOT_ENDPOINT=https://app.upbot.eu/api/v1/dependencies/report
# UPBOT_RELEASE=deploy-42
# UPBOT_PRIVATE_PACKAGES=company/internal,@company/private
```

Until publication, developers can use a Composer path repository pointing at this
package with `"options": {"versions": {"upbot/dependencies": "0.1.0"}}`.

`doctor` checks lockfiles, connection and token through `/v1/dependencies/verify`
without changing inventory. `report` sends only package names, versions, ecosystem,
dev/source flags and an optional release. The scan happens asynchronously in UpBot.
Failures return a nonzero exit status. No tokens or response bodies are logged.

Both commands accept `--project-dir /app` and `--dotenv /private/project.env`.
Default file: `.env.upbot`, then `.env`; existing process environment wins.
`UPBOT_PROJECT_DIR` defaults to the current directory; the explicit CLI directory
wins. Store config outside the public web root, `chmod 600` it and exclude it from Git.
The CLI does not boot the framework or read its cached configuration; use the
Laravel adapter's Artisan command when using Laravel config caching.

Daily cron (adjust PHP and project paths with `command -v php`):

```cron
17 3 * * * /usr/bin/php /var/www/project/current/vendor/bin/upbot report --project-dir /var/www/project/current --dotenv /home/deploy/.config/upbot/project.env >> /home/deploy/upbot-report.log 2>&1
```

Set expected interval to 24 hours, or use `17 */6 * * *` with 6 hours. Cron uses
server time; quote paths with spaces and escape `%` in crontab. Also run after a
successful deployment. Keep and rotate logs outside the web root. No automatic
cron installation or retries; HTTP 429 means wait at least a minute.

Lockfiles must remain available and describe the active release. Private packages
in `UPBOT_PRIVATE_PACKAGES` and nonregistry npm packages are unverified; private
names reach UpBot but are not sent to OSV. Yarn/pnpm are unsupported. Use separate
monitors/tokens for separate applications/environments. The reporter does not
verify the actual contents of `vendor` or `node_modules`.

## License

MIT. See [LICENSE](LICENSE).
