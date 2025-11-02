<?php
/**
 * Stripe Webhook Handler voor GameFlux
 * 
 * Deze handler verwerkt Stripe webhook events en:
 * - Stuurt bevestigingsemails naar klanten
 * - Maakt support tickets aan
 * - Logt betalingen
 * 
 * Configureer deze URL in Stripe Dashboard → Webhooks
 */

// Stripe webhook secret (configureer in Stripe Dashboard)
// Configureer je Stripe Secret Key via environment variable of .env bestand
$STRIPE_SECRET_KEY = getenv('STRIPE_SECRET_KEY') ?: 'sk_live_YOUR_SECRET_KEY_HERE'; // TODO: Configureer op server
$STRIPE_WEBHOOK_SECRET = 'whsec_...'; // Vervang met je webhook secret uit Stripe Dashboard

// Email configuratie
$ADMIN_EMAIL = 'nlgamevibes@gmail.com'; // Vervang met je admin email
$FROM_EMAIL = 'nlgamevibes@gmail.com'; // Vervang met je sender email
$FROM_NAME = 'GameFlux';

// Ticket/Support API endpoint (bijv. Pterodactyl, Discord webhook, of eigen systeem)
$TICKET_API_URL = 'https://panel.gameflux.nl/api/tickets'; // Pas aan naar jouw ticket systeem

// Log bestand
$LOG_FILE = __DIR__ . '/payment-logs.txt';

// Lees webhook payload
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verifieer webhook signature (optioneel maar aanbevolen)
// https://stripe.com/docs/webhooks/signatures

// Parse event
$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit('Invalid event');
}

// Log event
logEvent('Webhook received: ' . $event['type']);

// Handle different event types
switch ($event['type']) {
    case 'checkout.session.completed':
        handlePaymentSuccess($event['data']['object']);
        break;
    
    case 'payment_intent.succeeded':
        handlePaymentIntentSuccess($event['data']['object']);
        break;
    
    default:
        logEvent('Unhandled event type: ' . $event['type']);
}

http_response_code(200);
echo json_encode(['received' => true]);

/**
 * Handle successful checkout session
 */
function handlePaymentSuccess($session) {
    global $ADMIN_EMAIL, $FROM_EMAIL, $FROM_NAME, $TICKET_API_URL;
    
    $customerEmail = $session['customer_details']['email'] ?? null;
    $amount = ($session['amount_total'] ?? 0) / 100; // Convert from cents
    $currency = strtoupper($session['currency'] ?? 'eur');
    $orderId = $session['metadata']['order_id'] ?? 'UNKNOWN';
    $package = $session['metadata']['package'] ?? 'Unknown Package';
    $sessionId = $session['id'];
    
    logEvent("Payment successful - Order: $orderId, Package: $package, Amount: $currency $amount, Email: $customerEmail");
    
    // Send confirmation email to customer
    if ($customerEmail) {
        sendConfirmationEmail($customerEmail, $orderId, $package, $amount, $currency);
    }
    
    // Send notification to admin
    sendAdminNotification($orderId, $package, $amount, $currency, $customerEmail, $sessionId);
    
    // Create support ticket
    createSupportTicket($orderId, $package, $amount, $customerEmail);
}

/**
 * Handle payment intent success
 */
function handlePaymentIntentSuccess($paymentIntent) {
    // Similar handling if needed
    logEvent('Payment intent succeeded: ' . $paymentIntent['id']);
}

/**
 * Send confirmation email to customer
 */
function sendConfirmationEmail($email, $orderId, $package, $amount, $currency) {
    global $FROM_EMAIL, $FROM_NAME;
    
    $subject = "Betaling Bevestiging - GameFlux Order #$orderId";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #635BFF; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .info-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #635BFF; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Betaling Succesvol!</h1>
            </div>
            <div class='content'>
                <p>Beste klant,</p>
                <p>Bedankt voor je betaling! Je order is succesvol ontvangen.</p>
                
                <div class='info-box'>
                    <h3>Order Details:</h3>
                    <p><strong>Ordernummer:</strong> $orderId</p>
                    <p><strong>Pakket:</strong> $package</p>
                    <p><strong>Bedrag:</strong> €$amount</p>
                    <p><strong>Status:</strong> Betaald</p>
                </div>
                
                <p><strong>Wat gebeurt er nu?</strong></p>
                <ul>
                    <li>Je ontvangt binnen 24 uur toegang tot je server</li>
                    <li><strong>Maak een ticket aan in Discord met je bestelnummer: $orderId</strong></li>
                    <li>Ons team neemt contact met je op via Discord</li>
                </ul>
                
                <p>Je kunt je account beheren via: <a href='https://panel.gameflux.nl'>https://panel.gameflux.nl</a></p>
                <p>Bezoek onze website: <a href='https://website.gameflux.nl'>https://website.gameflux.nl</a></p>
                
                <p>Met vriendelijke groet,<br>Het GameFlux Team</p>
            </div>
            <div class='footer'>
                <p>GameFlux - FiveM Hosting</p>
                <p>Website: <a href='https://website.gameflux.nl'>website.gameflux.nl</a></p>
                <p>Als je vragen hebt, neem contact op via support@gameflux.nl</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $FROM_NAME <$FROM_EMAIL>" . "\r\n";
    $headers .= "Reply-To: $FROM_EMAIL" . "\r\n";
    
    $sent = mail($email, $subject, $message, $headers);
    
    if ($sent) {
        logEvent("Confirmation email sent to: $email for order: $orderId");
    } else {
        logEvent("FAILED to send email to: $email for order: $orderId");
    }
    
    return $sent;
}

/**
 * Send notification email to admin
 */
function sendAdminNotification($orderId, $package, $amount, $currency, $customerEmail, $sessionId) {
    global $ADMIN_EMAIL, $FROM_EMAIL, $FROM_NAME;
    
    $subject = "Nieuwe Betaling Ontvangen - Order #$orderId";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .info-box { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #635BFF; }
        </style>
    </head>
    <body>
        <h2>Nieuwe Betaling Ontvangen</h2>
        <div class='info-box'>
            <p><strong>Ordernummer:</strong> $orderId</p>
            <p><strong>Pakket:</strong> $package</p>
            <p><strong>Bedrag:</strong> €$amount</p>
            <p><strong>Klant Email:</strong> $customerEmail</p>
            <p><strong>Stripe Session ID:</strong> $sessionId</p>
            <p><strong>Tijd:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
        <p><strong>Actie vereist:</strong> Maak een support ticket aan voor deze klant.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $FROM_NAME <$FROM_EMAIL>" . "\r\n";
    
    mail($ADMIN_EMAIL, $subject, $message, $headers);
    logEvent("Admin notification sent for order: $orderId");
}

/**
 * Create support ticket
 */
function createSupportTicket($orderId, $package, $amount, $customerEmail) {
    // Klanten maken zelf tickets aan in Discord
    // We loggen alleen voor administratie
    $ticketLog = __DIR__ . '/tickets.txt';
    $ticketEntry = date('Y-m-d H:i:s') . " | Order: $orderId | Package: $package | Email: $customerEmail | Amount: €$amount\n";
    file_put_contents($ticketLog, $ticketEntry, FILE_APPEND);
    
    logEvent("Payment logged for order: $orderId (Klant moet zelf ticket aanmaken in Discord)");
    return true;
}

/**
 * Log events
 */
function logEvent($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($LOG_FILE, $logEntry, FILE_APPEND);
}

