# GameFlux Website

FiveM Hosting Website met Stripe Payment Integration

## 🚀 Features

- Modern, responsive design
- Stripe Checkout integratie
- Account registratie via Pterodactyl API
- Email notificaties na betaling
- Discord ticket systeem
- Automatische order nummering (panel-1, panel-2, etc.)

## 📁 Project Structuur

```
├── index.html              # Hoofdpagina
├── checkout.html           # Checkout pagina
├── checkout.js            # Stripe checkout logica
├── payment-proxy.php       # Backend voor Stripe sessies
├── payment-process.php     # Verwerkt betalingen en stuurt emails
├── payment-webhook.php     # Stripe webhook handler
├── payment-success.html    # Success pagina na betaling
├── register.html           # Account registratie pagina
├── register-proxy.php      # Pterodactyl API proxy
├── config.php              # Centrale configuratie
└── styles.css              # Styling

```

## ⚙️ Setup

### Vereisten

- PHP 7.4+ met cURL extensie
- Webserver met HTTPS (voor productie)
- Stripe account met API keys

### Installatie

1. **Clone repository:**
   ```bash
   git clone https://github.com/nlgamevibes-create/Wesbite.git
   cd Wesbite
   ```

2. **Configureer Stripe keys:**
   - Open `checkout.js` en zet je Stripe Publishable Key
   - Open `payment-proxy.php` en zet je Stripe Secret Key

3. **Configureer email:**
   - Pas `ADMIN_EMAIL` en `FROM_EMAIL` aan in `payment-process.php` en `payment-webhook.php`

4. **Upload naar webserver:**
   - Upload alle bestanden naar je webserver
   - Zorg dat PHP werkt en cURL is geïnstalleerd

5. **Stripe Webhook setup:**
   - Ga naar Stripe Dashboard → Webhooks
   - Voeg endpoint toe: `https://website.gameflux.nl/payment-webhook.php`
   - Selecteer events: `checkout.session.completed`, `payment_intent.succeeded`
   - Kopieer webhook secret naar `payment-webhook.php`

## 🔧 Configuratie

### Domeinen

Het domein is ingesteld op `website.gameflux.nl`. Voor lokaal gebruik wordt automatisch `localhost` gebruikt.

### Order Nummering

Orders worden automatisch genummerd als `panel-1`, `panel-2`, etc. via `order-counter.txt`.

### Tickets

Na betaling moeten klanten zelf een ticket aanmaken in Discord. Betalingen worden gelogd in `tickets.txt`.

## 📝 Licentie

© 2025 GameFlux. Alle rechten voorbehouden.

