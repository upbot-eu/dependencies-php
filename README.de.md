# UpBot: Abhängigkeitsmonitoring für PHP

[English](README.md) · [Deutsch](README.de.md) · [Slovenčina](README.sk.md)

PHP 8+ mit cURL, Composer 2.2+. Frameworkunabhängiger CLI-Client. Liest
`composer.lock` und/oder npm `package-lock.json` v1–v3. Für dotenv wird
`vlucas/phpdotenv` verwendet. Führt keine Projektskripte aus und installiert
oder aktualisiert keine Pakete.

**Die Veröffentlichung auf Packagist steht noch aus.** Danach:

```sh
composer require upbot/dependencies
```

Das Token dieses Monitors in die vorhandene private `.env` oder Prozessumgebung eintragen:

```dotenv
UPBOT_TOKEN=replace_with_monitor_token
```

```sh
vendor/bin/upbot doctor
vendor/bin/upbot report
```

Der Produktionsendpunkt und das aktuelle Projektverzeichnis sind voreingestellt.
Als normale Abhängigkeit installieren; `composer install --no-dev` entfernt
Entwicklungsabhängigkeiten. Reine PHP-, Symfony- und WordPress-Projekte, die über
Composer verwaltet werden, benötigen keinen Adapter. Für Laravel gibt es
`upbot/laravel-dependencies` mit Artisan-Anbindung.

Optional erstellt `vendor/bin/upbot init` eine `.env.upbot` mit Dateirechten 600.
Vorhandene Dateien werden nicht überschrieben. Weitere optionale Einstellungen:

```dotenv
# UPBOT_ENDPOINT=https://app.upbot.eu/api/v1/dependencies/report
# UPBOT_RELEASE=deploy-42
# UPBOT_PRIVATE_PACKAGES=company/internal,@company/private
```

Bis zur Veröffentlichung können Entwickler ein Composer-Path-Repository für
dieses Paket mit `"options": {"versions": {"upbot/dependencies": "0.1.0"}}` verwenden.

## Prüfung und Konfiguration

`doctor` prüft Lockdateien, Verbindung und Token über `/v1/dependencies/verify`,
ohne das Inventar zu ändern. `report` übermittelt nur Paketnamen, Versionen,
Ökosystem, Kennzeichen für Entwicklungsabhängigkeiten und Quellen sowie eine
optionale Release-Kennung. Die Prüfung erfolgt anschließend asynchron in UpBot.
Fehler führen zu einem Exitstatus ungleich null. Token und Antwortinhalte werden
nicht protokolliert.

Beide Befehle akzeptieren `--project-dir /app` und `--dotenv /private/project.env`.
Standarddatei ist `.env.upbot`, andernfalls `.env`; vorhandene Prozessvariablen
haben Vorrang. `UPBOT_PROJECT_DIR` entspricht standardmäßig dem Arbeitsverzeichnis;
ein explizites CLI-Verzeichnis hat Vorrang. Konfiguration außerhalb des öffentlich
zugänglichen Webverzeichnisses speichern, `chmod 600` setzen und von Git ausschließen.
Der CLI-Client startet das Framework nicht und liest dessen Konfigurationscache
nicht. Bei Laravel mit Konfigurationscache den Artisan-Adapter verwenden.

## Cron und Deployment

Täglicher Cronjob; PHP- und Projektpfade anpassen (`command -v php`):

```cron
17 3 * * * /usr/bin/php /var/www/project/current/vendor/bin/upbot report --project-dir /var/www/project/current --dotenv /home/deploy/.config/upbot/project.env >> /home/deploy/upbot-report.log 2>&1
```

Das erwartete Intervall auf 24 Stunden einstellen oder `17 */6 * * *` mit
6 Stunden verwenden. Cron nutzt die Serverzeitzone; Pfade mit Leerzeichen in
Anführungszeichen setzen und `%` in der Crontab maskieren. Auch nach einem
erfolgreichen Deployment ausführen. Logs außerhalb des Webverzeichnisses
speichern und rotieren. Keine automatische Croninstallation und keine automatischen
Wiederholungen; bei HTTP 429 mindestens eine Minute warten.

## Umfang

Lockdateien müssen verfügbar bleiben und das aktive Release beschreiben.
Private Pakete in `UPBOT_PRIVATE_PACKAGES` und npm-Pakete außerhalb der öffentlichen
Registry werden als ungeprüft markiert. Ihre Namen erreichen UpBot, werden aber
nicht an OSV gesendet. Yarn/pnpm werden nicht unterstützt. Für jede Anwendung
und Umgebung einen eigenen Monitor mit eigenem Token verwenden. Der Client
prüft nicht den tatsächlichen Inhalt von `vendor` oder `node_modules`.

## Lizenz

MIT. Siehe [LICENSE](LICENSE).
