# Beyondweb Protect

WordPress-Plugin für Websites, die Beyondweb technisch betreut. Sechs Module:

| Modul | Was es macht |
|---|---|
| `login` | Einheitliche Login-Seite samt «Neues Passwort» und Anmeldefenster bei abgelaufener Sitzung, Kundenlogo automatisch, eigene Texte und die Meldungen von ASE Pro in Deutsch, Französisch, Italienisch und Englisch, Sprachwahl auf mehrsprachigen Websites |
| `path` | Schützt den eigenen Login-Pfad von ASE Pro («Change Login URL») vor dem Auslesen |
| `mail` | Schlichte Mails ohne Farben und Bilder für Anmeldecode (2FA von ASE Pro), Passwort zurücksetzen, neues Konto, Passwort geändert und E-Mail-Adresse geändert |
| `uploads` | Grössenlimit für Uploads in die Mediathek: Bilder 2 MB, alle anderen Dateien 10 MB, mit verständlicher Fehlermeldung |
| `rest` | Benutzerliste der REST-Schnittstelle nur für angemeldete Benutzer |
| `twofa` | Schaltet den Anmeldecode per E-Mail (2FA von ASE Pro) schon bei der ersten Anmeldung ein, statt erst beim Öffnen des Profils |

Das Plugin hat keine Einstellungsseite, keine eigenen REST- oder AJAX-Endpunkte und baut keine Verbindungen nach aussen auf. Es verschickt selbst keine Mails, sondern ersetzt nur Betreff und Inhalt der Mails, die ASE Pro und WordPress ohnehin versenden. Die Sicherheitseinstellungen selbst bleiben in ASE Pro.

## Installation und Update (WP-CLI, z. B. über Kinsta)

```
wp plugin install https://github.com/beyondweb-gmbh/beyondweb-protect/releases/download/v1.0.7/beyondweb-protect-1.0.7.zip --activate --force
```

Derselbe Befehl aktualisiert eine bestehende Installation. Zurück auf eine ältere Version: denselben Befehl mit der älteren Versionsnummer.

Kinsta erlaubt in WP-CLI-Befehlen nur Buchstaben, Zahlen, Leerzeichen, einfache Anführungszeichen und `_ - . / : =`. Release-Links erfüllen das, Links mit `?`, `&` oder `%` nicht.

## Login-Pfad

ASE Pro verlegt die Login-Seite auf einen eigenen Pfad. Einige Adressen von WordPress bleiben aber ohne diesen Pfad erreichbar und geben einen Link zur Login-Seite aus oder leiten auf sie weiter. Das Modul `path` schliesst diese Lücke:

- Die echte Login-Adresse bekommt auf `wp-login.php` nur zu sehen, wer den Pfad nachweislich schon kennt: Er steht in der aufgerufenen Adresse oder in der Herkunft der Anfrage, die Person hat die Login-Seite in den letzten 24 Stunden geöffnet (Cookie `bw_protect_lp`, technisch notwendig, enthält weder den Pfad noch Personendaten), sie hat einen gültigen Link zum Setzen des Passworts geöffnet, oder sie ist angemeldet. Alle anderen bekommen statt der Login-Adresse die Startseite.
- Dasselbe gilt für Weiterleitungen von `wp-admin` und `wp-login.php` auf die Login-Seite. Öffentliche Seiten bleiben unberührt: Schickt eine Website ihre Besucher dort absichtlich zur Anmeldung (Mitgliederbereich, Login-Zwang), funktioniert das weiter und zeigt den Pfad wie bisher.
- Links von der Login-Seite zur Website («Zurück zur Website», Datenschutzerklärung) senden keine Herkunftsangabe. Das Logo führt nicht mehr zur Startseite. So landet der Pfad nicht in Statistik-Skripten der Website.
- Ungewöhnliche Aufrufe von `wp-login.php` (Adresszusatz der REST-Schnittstelle, Anhänge hinter dem Dateinamen) gehen für nicht angemeldete Besucher auf die Fehlerseite von ASE Pro. Solche Anfragen kommen im normalen Betrieb nicht vor.

Abgesehen davon blockiert das Modul keine Anfrage und ändert nichts an der Anmeldung selbst. Ohne aktiven Login-Pfad in ASE Pro tut es nichts. Der Pfad steht nur in den ASE-Einstellungen der Website, nie in diesem Repository.

Grenzen: Wer ein Konto hat, kennt den Pfad oder kann ihn erfahren. Gibt das Theme auf öffentlichen Seiten selbst einen Login-Link aus, steht der Pfad dort weiterhin. Der Pfad darf kein Wort sein, das WordPress selbst als Adresszusatz verwendet (z. B. `action`, `key`). Der Pfad ersetzt weder 2FA noch Login-Limit.

## Anmeldecode (2FA)

ASE Pro verlangt den Anmeldecode per E-Mail für die Rollen, die in seinen Einstellungen angehakt sind. Eingeschaltet wird er für ein Konto aber erst, wenn dessen Profil im Backend geladen wird. Bis dahin meldet sich die Person ohne Code an und wird je nach Frist auf ihr Profil geleitet, mit englischen Hinweisen.

Das Modul `twofa` zieht diesen Schritt vor:

- Bei der Anmeldung auf `wp-login.php`, sobald das Passwort stimmt. Der Code wird schon für diese Anmeldung verlangt, die Person sieht direkt die Code-Seite.
- Bei einer offenen Sitzung im Backend, beim nächsten Seitenaufruf und ohne Umleitung aufs Profil. Der Code gilt dann ab der nächsten Anmeldung.

Das Modul entscheidet nichts selbst: Es wirkt nur, wenn in ASE Pro die 2FA aktiv ist, E-Mail als Methode erlaubt ist, die Option «Enable the email method by default» eingeschaltet ist und die Rolle des Kontos zur Pflicht gehört. Wer schon eine Methode nutzt, bleibt unverändert. Es wird nur ein Faktor eingeschaltet, nie einer entfernt. Anmeldungen über REST, XML-RPC und Application Passwords lösen das Einschalten nicht aus. Ist der Code für ein Konto eingeschaltet, lehnt ASE Pro in seiner Standardeinstellung Anmeldungen mit Benutzername und Passwort über REST und XML-RPC für dieses Konto ab.

Vor dem Update auf 1.0.7 und vor dem Einschalten der 2FA beachten:

- Sind die Bedingungen oben auf einer Website schon erfüllt, stellt das Modul alle Konten der Pflichtrollen um, sobald sie sich das nächste Mal anmelden oder eine Seite im Backend aufrufen. Das gilt auch für Administrator-Konten. Die Frist von ASE Pro bis zur Einrichtung entfällt für diese Konten.
- Automatisch umgestellte Konten haben keine Backup-Codes. Der Code per E-Mail ist ihr einziger Weg. Kommt er nicht an, kann sich das Konto nicht anmelden.
- Deshalb vorher den Mailversand der Website an ein echtes Postfach prüfen.

Notausgang per WP-CLI, schaltet die 2FA von ASE Pro für die ganze Website aus: `wp option patch update admin_site_enhancements two_factor_authentication false --format=json`. Ein einzelnes Konto lässt sich bei aktivem Modul nicht über die Benutzer-Meta befreien, die nächste Anmeldung schaltet den Code wieder ein. Dauerhaft hilft nur, die Rolle in ASE Pro aus der Pflicht zu nehmen. Modul abschalten und Meta löschen hilft nur, bis das Profil des Kontos im Backend geöffnet wird: Dann schaltet ASE Pro den Code selbst wieder ein.

Die 2FA muss von ASE Pro kommen. Der Betrieb neben dem eigenständigen Plugin «Two Factor» ist nicht geprüft.

## Sprache

Login-Seite und Mails erscheinen in der Sprache der Website. Die eigenen Texte gibt es in Deutsch, Französisch, Italienisch und Englisch, für alle anderen Sprachen gilt Englisch. Dasselbe gilt für die Meldungen von ASE Pro auf der Login-Seite (falsche Anmeldung, falscher Code, Sperre, Passwortregeln), die ASE Pro nur auf Englisch liefert.

Deutsch ist durchgehend in der du-Form: Auf der Login-Seite ersetzt das Plugin dafür auch die Meldungen von WordPress, die je nach Sprachpaket in der Sie-Form stehen. Französisch bleibt beim «vous», Italienisch beim «tu».

Die Sprachwahl erscheint nur, wenn die Website mindestens zwei Sprachen hat. Massgebend sind die Sprache der Website, die installierten WordPress-Sprachpakete und Englisch, wenn die Website englisch ist oder WPML, Polylang oder TranslatePress Englisch führen. Varianten derselben Sprache (z. B. Deutsch und Deutsch Schweiz) zählen als eine Sprache. Sobald die Sprachwahl erscheint, ist Englisch immer dabei.

## Uploads

Das Limit gilt für alle Rollen und nur für die Mediathek: Upload-Fenster, «Datei hinzufügen», Block-Editor sowie Uploads über die REST-Schnittstelle (`/wp/v2/media`) und XML-RPC, also auch aus Apps und Fremdsystemen. Plugin- und Theme-Uploads, Importe und Formulare anderer Plugins bleiben unberührt. ASE Pro verkleinert Bilder erst nach dem Upload, das Limit greift vorher.

Im Upload-Feld der Mediathek steht anstelle der technischen Obergrenze des Servers («Maximale Dateigrösse für Uploads») der Hinweis mit den beiden Limits.

## Schalter pro Site

| Befehl | Wirkung |
|---|---|
| `wp option update bw_protect_logo name` | Site-Name als Text statt Logo (`auto` = Logo, `none` = nichts) |
| `wp option update bw_protect_logo_id 123` | Bestimmtes Bild aus der Mediathek als Logo |
| `wp option update bw_protect_logo_light yes` | Helles Logo dunkel einfärben erzwingen (`no` = nie) |
| `wp option update bw_protect_lang_switcher off` | Sprachwahl aus (`auto` = nur bei mehreren Sprachen, `on` = immer, auch auf einsprachigen Websites) |
| `wp option update bw_protect_upload_max_image 5` | Limit für Bilder in MB (Standard 2, `0` = kein Limit) |
| `wp option update bw_protect_upload_max_file 50` | Limit für alle anderen Dateien in MB (Standard 10, `0` = kein Limit) |
| `wp option update bw_protect_disabled_modules twofa` | Ein Modul auf dieser Site abschalten (`login`, `path`, `mail`, `uploads`, `rest`, `twofa`). Ersetzt eine vorhandene Liste. Alle wieder einschalten: `wp option delete bw_protect_disabled_modules` |
| `wp option update bw_protect_disabled_modules '["uploads","twofa"]' --format=json` | Mehrere Module abschalten. Wegen der Zeichen `[ " ,` nicht über WP-CLI bei Kinsta, dort per SSH |
| `wp plugin deactivate beyondweb-protect` | Alles aus, Login sieht wieder aus wie WordPress-Standard |

Die Funktionen des Plugins lassen sich aus anderem Code nicht einzeln per `remove_filter` oder `remove_action` aushängen. Abschalten geht nur modulweise über `bw_protect_disabled_modules`.

## Neue Version veröffentlichen

1. Version an zwei Stellen in `beyondweb-protect/beyondweb-protect.php` erhöhen, `CHANGELOG.md` ergänzen.
2. Auf GitHub: Actions, links «Release» wählen, «Run workflow», Version eingeben (z. B. `1.0.7`).
3. Die Action prüft Version und PHP-Syntax, baut die ZIP und legt das Release `v<version>` mit SHA-256-Prüfsumme an.
4. Zuerst auf einer Site installieren und die Anmeldung testen, danach gestaffelt ausrollen.
