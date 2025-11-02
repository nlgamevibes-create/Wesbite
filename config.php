<?php
/**
 * Configuratiebestand voor GameFlux Website
 * 
 * Dit bestand bevat alle domein en URL configuraties
 */

// Domein configuratie
define('WEBSITE_URL', 'https://website.gameflux.nl');
define('PANEL_URL', 'https://panel.gameflux.nl');
define('DISCORD_URL', 'https://discord.gg/zddgyTcpFe');

// Email configuratie
define('ADMIN_EMAIL', 'nlgamevibes@gmail.com');
define('FROM_EMAIL', 'nlgamevibes@gmail.com');
define('FROM_NAME', 'GameFlux');
define('SUPPORT_EMAIL', 'support@gameflux.nl');

// Stripe configuratie - Gebruik environment variables of .env bestand
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_live_YOUR_PUBLISHABLE_KEY_HERE');
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: 'sk_live_YOUR_SECRET_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...'); // Configureer in Stripe Dashboard

// Functie om base URL te krijgen
function getBaseUrl() {
    // In productie gebruik website.gameflux.nl, lokaal detecteer automatisch
    if (isset($_SERVER['HTTP_HOST']) && 
        $_SERVER['HTTP_HOST'] !== 'localhost' && 
        $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
    return WEBSITE_URL;
}

?>

