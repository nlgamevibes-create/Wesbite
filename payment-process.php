<?php
/**
 * Payment Processing Handler
 * 
 * Dit bestand wordt aangeroepen wanneer een gebruiker terugkomt van Stripe Checkout.
 * Het verifieert de betaling en stuurt emails + maakt tickets aan.
 */

header('Content-Type: application/json');

// Stripe Secret Key
// Configureer je Stripe Secret Key via environment variable of .env bestand
$STRIPE_SECRET_KEY = getenv('STRIPE_SECRET_KEY') ?: 'sk_live_YOUR_SECRET_KEY_HERE'; // TODO: Configureer op server

// Email configuratie
$ADMIN_EMAIL = 'nlgamevibes@gmail.com'; // VERVANG MET JE EMAIL
$FROM_EMAIL = 'nlgamevibes@gmail.com'; // VERVANG MET JE EMAIL
$FROM_NAME = 'GameFlux';

// Ticket API (optioneel - pas aan naar jouw systeem)
$TICKET_API_URL = null; // Of gebruik Discord webhook of eigen API

// Get session ID from request
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? $_GET['session_id'] ?? null;

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

try {
    // Verify payment with Stripe
    $session = verifyStripeSession($sessionId);
    
    if ($session && $session['payment_status'] === 'paid') {
        $customerEmail = $session['customer_details']['email'] ?? null;
        $amount = ($session['amount_total'] ?? 0) / 100;
        $currency = strtoupper($session['currency'] ?? 'eur');
        $orderId = $session['metadata']['order_id'] ?? 'UNKNOWN';
        $package = $session['metadata']['package'] ?? 'Unknown Package';
        
        // Send confirmation email
        if ($customerEmail) {
            sendConfirmationEmail($customerEmail, $orderId, $package, $amount, $currency);
        }
        
        // Send admin notification
        sendAdminNotification($orderId, $package, $amount, $currency, $customerEmail, $sessionId);
        
        // Create support ticket
        createSupportTicket($orderId, $package, $amount, $customerEmail);
        
        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'package' => $package,
            'amount' => $amount,
            'email_sent' => true,
            'ticket_created' => true
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Payment not completed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function verifyStripeSession($sessionId) {
    global $STRIPE_SECRET_KEY;
    
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/$sessionId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Stripe API error');
    }
    
    return json_decode($response, true);
}

function sendConfirmationEmail($email, $orderId, $package, $amount, $currency) {
    global $FROM_EMAIL, $FROM_NAME;
    
    $subject = "Betaling Bevestiging - GameFlux Order #$orderId";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #635BFF; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 30px; background: #f9f9f9; }
            .info-box { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #635BFF; border-radius: 4px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            h2 { color: #635BFF; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>Betaling Succesvol!</h1>
            </div>
            <div class='content'>
                <p>Beste klant,</p>
                <p>Bedankt voor je betaling! Je order is succesvol ontvangen.</p>
                
                <div class='info-box'>
                    <h2 style='margin-top: 0;'>Order Details:</h2>
                    <p><strong>Ordernummer:</strong> $orderId</p>
                    <p><strong>Pakket:</strong> $package</p>
                    <p><strong>Bedrag:</strong> €$amount</p>
                    <p><strong>Status:</strong> Betaald ✅</p>
                </div>
                
                <h2>Wat gebeurt er nu?</h2>
                <ul>
                    <li>Je ontvangt binnen 24 uur toegang tot je server</li>
                    <li>Er is automatisch een support ticket aangemaakt voor je account setup</li>
                    <li>Ons team neemt contact met je op via email</li>
                </ul>
                
                <p><strong>Je kunt je account beheren via:</strong><br>
                <a href='https://panel.gameflux.nl' style='color: #635BFF;'>https://panel.gameflux.nl</a></p>
                
                <p>Bezoek onze website: <a href='https://website.gameflux.nl' style='color: #635BFF;'>https://website.gameflux.nl</a></p>
                
                <p>Met vriendelijke groet,<br><strong>Het GameFlux Team</strong></p>
            </div>
            <div class='footer'>
                <p>GameFlux - FiveM Hosting</p>
                <p>Website: <a href='https://website.gameflux.nl' style='color: #635BFF;'>website.gameflux.nl</a></p>
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
    
    return mail($email, $subject, $message, $headers);
}

function sendAdminNotification($orderId, $package, $amount, $currency, $customerEmail, $sessionId) {
    global $ADMIN_EMAIL, $FROM_EMAIL, $FROM_NAME;
    
    $subject = "Nieuwe Betaling - Order #$orderId";
    
    $message = "
    <html>
    <body>
        <h2>Nieuwe Betaling Ontvangen</h2>
        <div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #635BFF;'>
            <p><strong>Ordernummer:</strong> $orderId</p>
            <p><strong>Pakket:</strong> $package</p>
            <p><strong>Bedrag:</strong> €$amount</p>
            <p><strong>Klant Email:</strong> $customerEmail</p>
            <p><strong>Stripe Session:</strong> $sessionId</p>
            <p><strong>Tijd:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
        <p><strong>Actie vereist:</strong> Maak een support ticket aan voor deze klant.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $FROM_NAME <$FROM_EMAIL>" . "\r\n";
    
    return mail($ADMIN_EMAIL, $subject, $message, $headers);
}

function createSupportTicket($orderId, $package, $amount, $customerEmail) {
    // Klanten maken zelf tickets aan in Discord
    // We loggen alleen voor administratie
    $ticketLog = __DIR__ . '/tickets.txt';
    $ticketEntry = date('Y-m-d H:i:s') . " | Order: $orderId | Package: $package | Email: $customerEmail | Amount: €$amount\n";
    file_put_contents($ticketLog, $ticketEntry, FILE_APPEND);
    
    return true;
}

?>

