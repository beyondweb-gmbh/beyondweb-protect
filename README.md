# Beyondweb Protect

WordPress-Plugin für Websites, die Beyondweb technisch betreut. Aktuell ein Modul: **Login** (einheitliche Login-Seite, Kundenlogo automatisch, deutsche Texte für die 2FA-Anmeldung von ASE Pro).

Das Plugin verarbeitet keine Benutzereingaben, hat keine Einstellungsseite, keine REST- oder AJAX-Endpunkte und baut keine Verbindungen nach aussen auf. Die Sicherheitseinstellungen selbst bleiben in ASE Pro.

## Installation und Update (WP-CLI, z. B. über Kinsta)

```
wp plugin install https://github.com/beyondweb-gmbh/beyondweb-protect/releases/download/v1.0.4/beyondweb-protect-1.0.4.zip --activate --force
```

Derselbe Befehl aktualisiert eine bestehende Installation. Zurück auf eine ältere Version: denselben Befehl mit der älteren Versionsnummer.

Kinsta erlaubt in WP-CLI-Befehlen nur Buchstaben, Zahlen, Leerzeichen, einfache Anführungszeichen und `_ - . / : =`. Release-Links erfüllen das, Links mit `?`, `&` oder `%` nicht.

## Schalter pro Site

| Befehl | Wirkung |
|---|---|
| `wp option update bw_protect_logo name` | Site-Name als Text statt Logo (`auto` = Logo, `none` = nichts) |
| `wp option update bw_protect_logo_id 123` | Bestimmtes Bild aus der Mediathek als Logo |
| `wp option update bw_protect_logo_light yes` | Helles Logo dunkel einfärben erzwingen (`no` = nie) |
| `wp option update bw_protect_disabled_modules '["login"]' --format=json` | Login-Modul auf dieser Site abschalten |
| `wp plugin deactivate beyondweb-protect` | Alles aus, Login sieht wieder aus wie WordPress-Standard |

## Neue Version veröffentlichen

1. Version an zwei Stellen in `beyondweb-protect/beyondweb-protect.php` erhöhen, `CHANGELOG.md` ergänzen.
2. Auf GitHub: Actions, links «Release» wählen, «Run workflow», Version eingeben (z. B. `1.0.4`).
3. Die Action prüft Version und PHP-Syntax, baut die ZIP und legt das Release `v<version>` mit SHA-256-Prüfsumme an.
4. Zuerst auf einer Site installieren und die Anmeldung testen, danach gestaffelt ausrollen.
