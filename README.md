# Wesender WordPress Plugin

Stuur alle WordPress e-mails via [Wesender](https://wesender.nl) - geen SMTP-instellingen nodig.

## Wat doet deze plugin?

De plugin vervangt de standaard `wp_mail()` functie van WordPress en stuurt alle e-mails via de Wesender API. Verbinding verloopt via OAuth - je API-sleutel nooit handmatig instellen.

## Installatie

1. Download `wesender-wp.zip` via [wesender.nl/apps/wordpress](https://wesender.nl/apps/wordpress/)
2. Ga in WordPress naar **Plugins > Nieuwe plugin toevoegen > Plugin uploaden**
3. Selecteer de ZIP en klik op **Nu installeren**
4. Activeer de plugin
5. Ga naar **Wesender > Verbinding** en klik op **Verbinden met Wesender**

Volledige installatiehhandleiding: [docs.wesender.nl/apps/wordpress](https://wesender.nl/docs/apps/wordpress)

## Vereisten

- WordPress 5.7 of hoger
- PHP 7.4 of hoger
- Een Wesender-account ([gratis aanmaken](https://app.wesender.nl/registreren))
- Een geverifieerd afzenderdomein in Wesender

## Compatibiliteit

Alle plugins die `wp_mail()` gebruiken werken automatisch - inclusief:

- WooCommerce
- Contact Form 7
- Gravity Forms
- Elementor Forms
- WP User e-mail (registratie, wachtwoordreset)

## Auto-updates

Vanaf v1.3.2 toont WordPress automatisch een updatemelding zodra er een nieuwe versie beschikbaar is. Updaten kan via **Dashboard > Updates** of de pluginlijst - net als elke andere plugin.

## Licentie

GPL v2 or later - zie [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
