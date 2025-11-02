<?php
/**
 * Registration Proxy voor GameFlux Panel
 * 
 * Deze proxy maakt automatisch gebruikers aan via het admin panel.
 * Upload dit bestand naar je webserver en pas de configuratie aan.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

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

// ===== CONFIGURATIE - PAS DIT AAN =====
// 1. Upload dit bestand naar je webserver
// 2. Pas onderstaande variabelen aan met je panel gegevens
// 3. Zet USE_PROXY op true in script.js en voeg PROXY_URL toe

$PANEL_URL = 'https://panel.gameflux.nl';
$WEBSITE_URL = 'https://website.gameflux.nl';
$API_KEY = 'ptla_WbUAgqMR8jkiETBemTYIeN9OdTP9h43MqIfycviuwX5'; // API Key van je panel

// ✅ API Key is geconfigureerd!
// Voor Pterodactyl heb je alleen de API Key nodig (geen secret).
// De key moet de juiste permissions hebben om gebruikers aan te maken.

// Lees input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Valideer required fields
$required = ['email', 'username', 'first_name', 'last_name', 'password'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '{$field}' is required"]);
        exit;
    }
}

// Verify reCAPTCHA (recommended for security)
// PAS DE SECRET KEY AAN - krijg deze van: https://www.google.com/recaptcha/admin
if (!empty($input['recaptcha_token'])) {
    $recaptchaSecret = 'YOUR_RECAPTCHA_SECRET_KEY'; // Secret key van Google reCAPTCHA (niet de site key!)
    $recaptchaVerify = @file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$input['recaptcha_token']}");
    $recaptchaData = json_decode($recaptchaVerify, true);
    
    if (!$recaptchaData || !$recaptchaData['success']) {
        http_response_code(400);
        echo json_encode(['error' => 'reCAPTCHA verificatie mislukt. Probeer het opnieuw.']);
        exit;
    }
} else {
    // Optional: uncomment next lines to require reCAPTCHA
    // http_response_code(400);
    // echo json_encode(['error' => 'reCAPTCHA token is required']);
    // exit;
}

// Prepare data for panel API
$userData = [
    'email' => $input['email'],
    'username' => $input['username'],
    'name_first' => $input['first_name'],
    'name_last' => $input['last_name'],
    'password' => $input['password'],
    'language' => $input['language'] ?? 'en',
    'root_admin' => false
];

// Maak API request naar panel
$ch = curl_init($PANEL_URL . '/api/application/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $API_KEY,
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: GameFlux-Website/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Panel connection error: ' . $curlError]);
    exit;
}

// Return panel response
http_response_code($httpCode);
echo $response;
?>

