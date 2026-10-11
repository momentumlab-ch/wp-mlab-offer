=== MomentumLab Angebote ===
Contributors: momentumlab
Requires at least: 6.0
Requires PHP: 7.4
License: GPLv2 or later

Angebote, Kurse und Termine einfach erfassen (auch vom Smartphone) und als Karten anzeigen.

== Installation ==
1. Neuestes «wp-mlab-offer.zip» von https://github.com/momentumlab-ch/wp-mlab-offer/releases herunterladen.
2. WordPress > Plugins > Installieren > Plugin hochladen > ZIP wählen > Aktivieren.
3. Wer vorher «Alpine Flow Angebote» genutzt hat: Das alte Plugin wird beim Aktivieren automatisch deaktiviert
   und kann danach gelöscht werden. Alle Angebote und der Shortcode bleiben erhalten.

== Updates ==
Das Plugin holt Updates direkt von GitHub. Sie erscheinen wie gewohnt unter Dashboard > Aktualisierungen
(sofort prüfen mit «Erneut prüfen»). Neue Version veröffentlichen: Versionsnummer im Header von
wp-mlab-offer.php und in MLAB_OFFER_VERSION erhöhen und auf «main» mergen. Der GitHub-Workflow
erstellt daraus automatisch das Release mit dem ZIP.

== Erfassen vom Smartphone ==
Adresse: https://deine-seite.ch/angebote-erfassen/
- Anmeldung über die normale WordPress-Anmeldung, danach nur die Angebotsliste und das Formular, kein WordPress-Menü.
- Tipp: Seite auf dem Smartphone «Zum Home-Bildschirm» hinzufügen, dann wirkt sie wie eine App.
- Empfohlen: für Mitarbeitende ein eigenes Konto mit der Rolle «Autor» anlegen (darf Angebote und Bilder erfassen, aber nichts anderes ändern).
  Mit der Rolle «Mitarbeiter» werden Angebote erst nach Freigabe sichtbar.
- Login-Schutz-Plugins (Sperre nach Fehlversuchen, Captcha, 2FA) gelten dadurch auch hier.
- Andere Adresse: per Filter «mlab_offer_capture_slug».

== Verwendung im Admin ==
1. Im Menü «Angebote» > «Neues Angebot».
2. Felder ausfüllen (alle freiwillig), Bild wählen, Veröffentlichen.
3. Ausgabe auf einer Seite mit dem Shortcode: [mlab_angebote]
   Optionen: eyebrow="…" titel="…" intro="…" max="3"
   Der alte Shortcode [alpine_flow_angebote] funktioniert weiterhin.

== Verhalten ==
- 1, 2 oder 3+ Angebote: Layout passt sich automatisch an. 0 Angebote: Section wird nicht ausgegeben.
- Vergangene Termine verschwinden; ein Angebot mit nur vergangenen Terminen wird ausgeblendet.
- «Anzeigen bis» blendet Angebote ohne Termin ab einem Datum aus.
- Termine werden als schema.org Event (mit Preis als Offer) ausgegeben, anpassbar per Filter «mlab_offer_event».
- Buttons mit Link «#anfrage» (Standard) tragen data-anfrage="Titel" für die Vorauswahl im Anfrageformular.
- Keine öffentlichen URLs für Angebote (public=false).

== Changelog ==
= 2.0.2 =
* Name «MomentumLab Angebote».
* Schalter «Automatische Aktualisierungen aktivieren» erscheint direkt nach dem Aktivieren und auch, wenn GitHub kurz nicht erreichbar ist.

= 2.0.1 =
* Upload über den Plugin-Installer auf Servern mit strenger Firewall: keine base64-/Datei-Lese-Muster mehr im Code.

= 2.0.0 =
* Umbenannt zu «MLab Angebote» (Ordner wp-mlab-offer), bestehende Angebote bleiben erhalten.
* Mobile Erfassung unter /angebote-erfassen mit eigenem Login, ohne WordPress-Menü.
* Automatische Updates aus GitHub-Releases.
* Neues Plugin-Icon.
