# Changelog

## 1.0.7
- Neues Modul `twofa`: Verlangt ASE Pro für ein Konto den Anmeldecode per E-Mail, schaltet das Plugin ihn bei der ersten Anmeldung ein, sobald das Passwort stimmt. Der Code gilt damit schon für diese Anmeldung. Bisher aktivierte ASE Pro den Code erst beim Öffnen des Profils im Backend, mit englischen Hinweisen. Bei einer offenen Sitzung wird der Code beim nächsten Seitenaufruf im Backend eingeschaltet, ohne Umleitung aufs Profil. Ob die Pflicht gilt, bestimmt weiterhin ASE Pro.
- Vor dem Update beachten: Ist die 2FA von ASE Pro auf einer Website schon aktiv und dort die Option «Enable the email method by default» eingeschaltet, stellt das neue Modul alle Konten der Pflichtrollen bei der nächsten Anmeldung oder beim nächsten Seitenaufruf im Backend auf den Code per E-Mail um, auch Administrator-Konten und ohne Frist. Diese Konten haben keine Backup-Codes. Vorher den Mailversand der Website prüfen, Details und Notausgang im README.
- Anmeldefenster bei abgelaufener Sitzung, Schritt mit dem Code: «Bestätigen» als Hauptbutton, «Code erneut senden» als Link darunter, wie auf der Login-Seite. Das Fenster bleibt auch nach einem falschen Code und nach «Code erneut senden» im Fenster-Design.
- Seite «Administrator-E-Mail bestätigen»: «Aktualisieren» als zweiter Button über dem Hauptbutton.
- Korrektur: In den Mails «Passwort geändert» und «E-Mail-Adresse geändert» erschien ein Apostroph im Namen der Website oder in der Adresse als `&#039;`.
- Rückfrage von WordPress beim Abmelden ohne gültigen Link in der du-Form.

## 1.0.6
- Meldungen von ASE Pro auf der Login-Seite in Deutsch, Französisch, Italienisch und Englisch statt nur Englisch: falsche Anmeldung, falscher Code, Hinweis auf fehlgeschlagene Anmeldungen, Sperre nach zu vielen Versuchen, Passwortregeln.
- Deutsch durchgehend in der du-Form: Meldungen von WordPress auf der Login-Seite werden angeglichen.
- Seite «Neues Passwort» im Design der Login-Seite: «Passwort generieren» als zweiter Button, ruhige Anzeige der Passwortstärke, Hinweis zu den Passwortregeln in der Sprache der Seite.
- Anmeldefenster bei abgelaufener Sitzung im Backend im selben Stil.
- Modul `mail`: Hinweise «Passwort geändert» und «E-Mail-Adresse geändert» im Stil der übrigen Mails.
- Modul `uploads`: Im Upload-Feld der Mediathek steht der Hinweis mit den Limits anstelle der Obergrenze des Servers.

## 1.0.5
- Sprachwahl auf mehrsprachigen Websites: ein Link je Sprache, oben rechts, auf schmalen Bildschirmen unter der Karte. Englisch ist immer dabei. Auf einsprachigen Websites ändert sich nichts.
- Eigene Texte in Deutsch, Französisch, Italienisch und Englisch. Die Login-Seite folgt der Sprache der Website, bisher waren die eigenen Texte immer deutsch.
- Das Sprach-Auswahlfeld von WordPress entfällt. Die neuen Links behalten die Adresszusätze der Login-Seite.
- Neues Modul `mail`: schlichte Mails für Anmeldecode, Passwort zurücksetzen und neues Konto, in der Sprache der Login-Seite bzw. des Benutzers.
- Neues Modul `uploads`: Grössenlimit für die Mediathek (Bilder 2 MB, andere Dateien 10 MB) mit Fehlermeldung.
- Neues Modul `rest`: Benutzerliste der REST-Schnittstelle nur für angemeldete Benutzer.
- Neues Modul `path`: Der Login-Pfad von ASE Pro ist auf `wp-login.php` und in Weiterleitungen aus dem Backend nur noch für Besucher sichtbar, die ihn schon kennen. Links von der Login-Seite zur Website senden keine Herkunftsangabe, das Logo führt nicht mehr zur Startseite. Ungewöhnliche Aufrufe von `wp-login.php` (Adresszusatz der REST-Schnittstelle, Anhänge hinter dem Dateinamen) gehen für nicht angemeldete Besucher auf die Fehlerseite von ASE Pro.
- Schalter `bw_protect_lang_switcher`, `bw_protect_upload_max_image`, `bw_protect_upload_max_file`.
- Robustheit: Benutzerlisten-Sperre hält auch gegen fremde REST-Filter und lässt OPTIONS-Anfragen durch, Upload-Limit erkennt Bilder über XML-RPC am Inhalt, Sprachlinks übernehmen die Adresszusätze unverändert, Fehler werden pro Anfrage nur einmal protokolliert, Link in der Mail «Neues Konto» öffnet die Seite in der Sprache des Benutzers.

## 1.0.4
- Keine Änderung am Code gegenüber 1.0.3. Erste öffentliche Version.

## 1.0.3
- Logo: Bild-URLs von fremden Servern werden nicht mehr verwendet.
- Texte der Login-Seite werden nur noch auf wp-login.php registriert, auf allen anderen Seiten läuft kein Code des Login-Moduls mehr mit.
- Aufräumen beim Löschen des Plugins (uninstall.php).
- Update URI gesetzt: WordPress nimmt nie ein Update von wordpress.org an.
- index.php in allen Ordnern.

## 1.0.2
- Fusszeile in jeder Fenstergrösse zweizeilig (Label unter dem Text).

## 1.0.1
- Layout passt sich der Fensterhöhe an, kein Scrollen auf Laptops.
- Schalter `bw_protect_logo` (auto, name, none).

## 1.0.0
- Erste Version als normales Plugin: Login-Design, Logo automatisch, deutsche Texte für die 2FA-Anmeldung von ASE Pro.
- Schutz gegen übergrosse Logo-Bilder, Fehler in einem Modul blockieren die Site nicht.
