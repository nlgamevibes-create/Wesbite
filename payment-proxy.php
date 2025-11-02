<?php
/**
 * Payment Proxy voor GameFlux - Stripe Only
 * 
 * Dit bestand fungeert als proxy tussen frontend en Stripe.
 * Upload naar je webserver en configureer je Stripe secret key.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Alleen POST toegestaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Lees input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['method']) || !isset($input['package']) || !isset($input['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// ===== CONFIGURATIE =====
// Vervang met je Stripe secret key (begint met sk_live_ of sk_test_)

// Stripe Secret Key (voor Credit Cards)
// BELANGRIJK: Gebruik de SECRET key (sk_), niet de public key (pk_)
// Je public key is al in checkout.js gezet
// Configureer je Stripe Secret Key via environment variable of .env bestand
$STRIPE_SECRET_KEY = getenv('STRIPE_SECRET_KEY') ?: 'sk_live_YOUR_SECRET_KEY_HERE'; // TODO: Configureer op server

$method = $input['method'];
$package = $input['package'];
$amount = floatval($input['amount']);
$currency = $input['currency'] ?? 'EUR';

// Generate unique order ID in format: panel-1, panel-2, etc.
$orderCounterFile = __DIR__ . '/order-counter.txt';
$orderNumber = 1;

if (file_exists($orderCounterFile)) {
    $orderNumber = intval(file_get_contents($orderCounterFile)) + 1;
}

file_put_contents($orderCounterFile, $orderNumber);
$orderId = 'panel-' . $orderNumber;

// Success and cancel URLs
// Gebruik website.gameflux.nl als domein (of detecteer automatisch voor lokale ontwikkeling)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . 
           ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1' ? $_SERVER['HTTP_HOST'] : 'website.gameflux.nl');
// Note: Stripe will append ?session_id={CHECKOUT_SESSION_ID} to the success URL
$successUrl = $baseUrl . '/payment-success.html?order=' . $orderId;
$cancelUrl = $baseUrl . '/checkout.html?package=' . urlencode($package) . '&price=' . urlencode(number_format($amount, 2, ',', ''));

// Handle Stripe payment
try {
    if ($method !== 'stripe') {
        http_response_code(400);
        echo json_encode(['error' => 'Alleen Stripe wordt ondersteund']);
        exit;
    }
    
    $sessionId = createStripeCheckout($amount, $currency, $orderId, $successUrl, $cancelUrl, $package);
    echo json_encode([
        'success' => true,
        'sessionId' => $sessionId,
        'orderId' => $orderId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function createStripeCheckout($amount, $currency, $orderId, $successUrl, $cancelUrl, $package) {
    global $STRIPE_SECRET_KEY;
    
    $data = [
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower($currency),
                'product_data' => [
                    'name' => 'GameFlux ' . $package,
                    'description' => 'Maandelijks FiveM Server Hosting'
                ],
                'unit_amount' => intval($amount * 100) // Convert to cents
            ],
            'quantity' => 1
        ]],
        'mode' => 'payment', // Eenmalige betaling (gebruik 'subscription' voor maandelijkse abonnementen)
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'order_id' => $orderId,
            'package' => $package
        ]
    ];
    
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, $STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Stripe API error: ' . $response);
    }
    
    $result = json_decode($response, true);
    return $result['id'];
}
?>

