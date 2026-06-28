=== Wesender e-mail ===
Contributors: wesender
Tags: mail, email, smtp, woocommerce, transactional
Requires at least: 5.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stuur alle WordPress e-mails via Wesender. Verbind je account met een klik, geen SMTP-instellingen nodig.

== Description ==

De Wesender e-mail plugin stuurt alle WordPress e-mails - inclusief WooCommerce-bestellingen, contactformulieren en wachtwoordresets - via de Wesender API.

= Kenmerken =

* Verbinding via OAuth - geen API-sleutel kopieren
* Werkt met WooCommerce, Contact Form 7, Gravity Forms en alle plugins die wp_mail() gebruiken
* Statistieken en e-maillogboek in je Wesender-dashboard
* Bijlagen worden automatisch meegestuurd

== Installation ==

1. Download wesender-wp.zip van wesender.nl/apps/wordpress/
2. Ga naar Plugins > Nieuwe plugin toevoegen > Plugin uploaden
3. Selecteer de ZIP en klik op Nu installeren
4. Activeer de plugin
5. Ga naar Instellingen > Wesender e-mail en klik op Verbinden met Wesender

Zie docs.wesender.nl/apps/wordpress voor de volledige handleiding.

== Third-Party Service ==

This plugin sends email via the Wesender API (api.wesender.nl). When WordPress sends a
mail using wp_mail(), the plugin forwards it to Wesender's servers for delivery.

No data is sent before you connect your Wesender account via the settings page.

* Service: https://wesender.nl
* Privacy policy: https://wesender.nl/privacy
* Terms of service: https://wesender.nl/voorwaarden

== Frequently Asked Questions ==

= Werkt dit met WooCommerce? =

Ja. WooCommerce gebruikt wp_mail() voor alle e-mails. De plugin onderschept deze automatisch.

= Moet mijn domein geverifieerd zijn? =

Ja. Het afzenderdomein moet geverifieerd zijn in je Wesender-account met SPF, DKIM en DMARC.

= Wat als ik "Plugin bestand bestaat niet" krijg? =

Zie de troubleshooting-sectie in de documentatie: docs.wesender.nl/apps/wordpress#probleemoplossing

== Changelog ==

= 1.4.0 =
* Verwijderd: eigen update-mechanisme (WordPress.org levert updates via het officiele plugin-kanaal)
* Fix: Text Domain bijgewerkt naar wesender-e-mail
* Update: Tested up to WordPress 7.0


= 1.3.2 =
* Nieuw: automatische update-melding in WordPress (geen handmatige installatie meer nodig)
* Fix: "We" logo in sidebar menu in plaats van envelop-icoon
* Fix: "We" logo in plugin-header vergroot voor betere leesbaarheid
* Verbetering: modern pill-navigatie, grotere logo-mark, kaart met schaduw
* Hernoemd: "Blokkeren" menu heet nu "Plugins blokkeren"
* Verplaatst: Wesender-menu staat nu onderaan de WordPress-navigatie

= 1.3.1 =
* Fix: Wesender logo (We) in plugin-header in plaats van envelop-icoon
* Fix: Blokkeren-pagina toont nu alle geinstalleerde plugins via get_plugins()
* Toevoeging: "Meld fout" link in maillog bij mislukte e-mails

= 1.3.0 =
* Toevoeging: eigen top-level menu "Wesender" in WordPress admin (Verbinding / Maillog / Blokkeren)
* Toevoeging: maillog - alle verstuurde e-mails worden geregistreerd met tijdstip, ontvanger, onderwerp, bron en status
* Toevoeging: blokkeren - blokkeer specifieke plugins of thema's van het versturen van e-mail via Wesender
* Toevoeging: automatische brondetectie - de plugin herkent welke plugin of welk thema wp_mail() aanroept
* Toevoeging: database-tabel voor maillog met automatische installatie bij activatie

= 1.2.1 =
* Fix: verwijder ook verouderde wesender-wp* mappen bij activatie (voorkomt "Plugin bestand bestaat niet")
* Fix: PHP 7.4 compatibiliteit (str_contains en match vervangen door equivalenten)
* Toevoeging: externe service disclosure in readme.txt

= 1.2.0 =
* Fix: verwijder verouderde plugin-entries bij activatie om conflicten na herinstallatie te voorkomen
* Toevoeging: readme.txt

= 1.1.0 =
* Plugin URI bijgewerkt naar installatiehandleiding
* Versienummer bijgewerkt in plugin-header

= 1.0.0 =
* Eerste versie
