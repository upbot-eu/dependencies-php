# UpBot: monitoring závislostí pre PHP

[English](README.md) · [Deutsch](README.de.md) · [Slovenčina](README.sk.md)

PHP 8+ s cURL, Composer 2.2+. CLI nezávisí od frameworku. Číta `composer.lock`
a/alebo npm `package-lock.json` v1–v3. Dotenv spracúva cez `vlucas/phpdotenv`.
Nespúšťa skripty projektu ani neinštaluje a neaktualizuje balíky.

Inštalácia v projekte:

```sh
composer require upbot/dependencies
```

Do existujúceho súkromného `.env` alebo prostredia procesu pridaj token sledovania:

```dotenv
UPBOT_TOKEN=replace_with_monitor_token
```

```sh
vendor/bin/upbot doctor
vendor/bin/upbot report
```

Produkčný endpoint a aktuálny adresár projektu sú prednastavené. Balík inštaluj
ako bežnú závislosť; `composer install --no-dev` odstráni dev závislosti.
Samostatné PHP, Symfony ani WordPress projekty spravované cez Composer nepotrebujú
adaptér. Pre Laravel možno použiť `upbot/laravel-dependencies` s Artisanom.

Voliteľný `vendor/bin/upbot init` vytvorí `.env.upbot` s právami 600.
Existujúci súbor neprepíše. Ďalšie voliteľné nastavenia:

```dotenv
# UPBOT_ENDPOINT=https://app.upbot.eu/api/v1/dependencies/report
# UPBOT_RELEASE=deploy-42
# UPBOT_PRIVATE_PACKAGES=company/internal,@company/private
```

## Overenie a konfigurácia

`doctor` overí lock súbory, spojenie a token cez `/v1/dependencies/verify`
bez zmeny inventára. `report` odošle iba názvy balíkov, verzie, ekosystém,
príznaky dev/zdroja a voliteľný identifikátor release. Kontrola prebehne
asynchrónne v UpBote. Chyby vracajú nenulový exit status. Tokeny ani obsah
odpovede servera sa nelogujú.

Oba príkazy podporujú `--project-dir /app` a `--dotenv /private/project.env`.
Predvolený súbor je `.env.upbot`, prípadne `.env`; existujúce premenné procesu
majú prednosť. `UPBOT_PROJECT_DIR` je predvolene pracovný adresár; explicitný
adresár v CLI má prednosť. Konfiguráciu ulož mimo verejného webového adresára,
nastav `chmod 600` a vylúč ju z Gitu. CLI nenačíta framework ani jeho cache
konfigurácie; v Laraveli s cache konfigurácie používaj Artisan adaptér.

## Cron a nasadenie

Denný cron; uprav cesty k PHP a projektu (`command -v php`):

```cron
17 3 * * * /usr/bin/php /var/www/project/current/vendor/bin/upbot report --project-dir /var/www/project/current --dotenv /home/deploy/.config/upbot/project.env >> /home/deploy/upbot-report.log 2>&1
```

Očakávaný interval nastav na 24 hodín, prípadne použi `17 */6 * * *` a 6 hodín.
Cron používa čas servera; cesty s medzerami daj do úvodzoviek a `%` v crontabe
escapuj. `report` spusti aj po úspešnom nasadení. Logy uchovávaj mimo webrootu
a rotuj ich. Cron sa nenastavuje automaticky a odoslanie sa neopakuje;
HTTP 429 znamená počkať aspoň minútu.

## Rozsah

Lock súbory musia zostať dostupné a opisovať aktívny release. Privátne balíky
v `UPBOT_PRIVATE_PACKAGES` a npm balíky mimo verejného registra sú neoverené;
ich názvy sa odošlú UpBotu, ale nie do OSV. Yarn/pnpm nie sú podporované.
Pre jednotlivé aplikácie a prostredia používaj samostatné sledovania a tokeny.
Klient neoveruje skutočný obsah `vendor` či `node_modules`.

## Licencia

MIT. Pozri [LICENSE](LICENSE).
