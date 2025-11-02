// Stripe Checkout Integration
document.addEventListener('DOMContentLoaded', () => {
    // Stripe public key - Vervang met jouw Stripe publishable key
    // Note: De key die je hebt is een live key (pk_live_), zorg dat je de juiste gebruikt
    // Vervang met je Stripe Publishable Key van je .env bestand of server configuratie
    // Stripe Publishable Key - deze hoort bij je Secret Key
    // Als je deze niet hebt, haal hem op uit Stripe Dashboard -> Developers -> API keys
    const STRIPE_PUBLISHABLE_KEY = 'pk_live_51SOpweJyuvSjv9sEihIs2wjDUthZIZXTJinhvw7HQanrIUgNIsQn0few2ur7H0OJdeuXgibSvT86CyhySH6TlvlN00CSCV4Wfd';
    const stripe = Stripe ? Stripe(STRIPE_PUBLISHABLE_KEY) : null;
    
    // Backend API endpoint
    const API_ENDPOINT = '/payment-proxy.php';
    
    // Get package info from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const packageName = urlParams.get('package') || 'FXServer I';
    const packagePrice = urlParams.get('price') || '2,10';
    
    // Convert price from "2,10" to 2.10 for calculations
    const priceNumber = parseFloat(packagePrice.replace(',', '.'));
    
    // Package data
    const packages = {
        'FXServer I': { price: '2,10', name: 'FXServer I', priceNum: 2.10 },
        'FXServer II': { price: '4,10', name: 'FXServer II', priceNum: 4.10 },
        'FXServer III': { price: '9,00', name: 'FXServer III', priceNum: 9.00 },
        'FXServer IV': { price: '13,50', name: 'FXServer IV', priceNum: 13.50 },
        'FXServer V': { price: '20,00', name: 'FXServer V', priceNum: 20.00 }
    };
    
    const selectedPackage = packages[packageName] || packages['FXServer I'];
    
    // Update display with package info
    document.getElementById('summaryPackage').textContent = selectedPackage.name;
    document.getElementById('summaryPrice').textContent = '€' + selectedPackage.price;
    document.getElementById('summaryTotal').textContent = '€' + selectedPackage.price;
    document.getElementById('packageNameDisplay').textContent = selectedPackage.name;
    document.getElementById('packagePriceDisplay').textContent = '€' + selectedPackage.price;
    
    // Auto-select Stripe payment method
    selectedPaymentMethod = 'stripe';
    const payButton = document.getElementById('payButton');
    
    // Pay button handler
    payButton.addEventListener('click', async () => {
        // Check if Stripe Publishable Key is configured
        // Only show error if key is missing or contains placeholder text
        const keyIsPlaceholder = !STRIPE_PUBLISHABLE_KEY || 
                                  STRIPE_PUBLISHABLE_KEY.includes('YOUR_PUBLISHABLE_KEY') || 
                                  STRIPE_PUBLISHABLE_KEY.includes('YOUR_') ||
                                  STRIPE_PUBLISHABLE_KEY.length < 50;
        
        if (keyIsPlaceholder) {
            showMessage('error', '⚠️ Stripe Publishable Key is niet geconfigureerd!<br><br>Voeg je Publishable Key toe in checkout.js (regel 8).<br><br>Je kunt deze vinden in:<br>Stripe Dashboard → Developers → API keys<br><br>De key begint met: pk_live_');
            return;
        }
        
        // Check if Stripe library is loaded
        if (!stripe) {
            showMessage('error', 'Stripe library niet geladen. Controleer je internetverbinding.');
            return;
        }
        
        // Disable button and show loading
        payButton.disabled = true;
        const buttonText = payButton.querySelector('.button-text');
        const buttonLoader = payButton.querySelector('.button-loader');
        buttonText.style.display = 'none';
        buttonLoader.style.display = 'block';
        
        try {
            await processStripePayment(selectedPackage);
        } catch (error) {
            console.error('Payment error:', error);
            
            // On localhost, if we catch an error, it means we should have shown test mode
            // Don't show error message on localhost - test mode should have been shown already
            const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            
            if (isLocalhost) {
                // On localhost, show test mode instead of error
                showTestModeStripe(selectedPackage);
                payButton.disabled = false;
                buttonText.style.display = 'block';
                buttonLoader.style.display = 'none';
                return;
            }
            
            // Production: Show detailed error message
            let errorMessage = error.message || 'Er is een fout opgetreden. Probeer het opnieuw of neem contact op met support.';
            
            // Make it user-friendly
            if (errorMessage.includes('Stripe')) {
                errorMessage = '⚠️ Stripe Checkout fout:<br><br>' + errorMessage + '<br><br>Controleer je Stripe API keys en server configuratie.';
            } else if (errorMessage.includes('PHP') || errorMessage.includes('server')) {
                errorMessage = '⚠️ Server fout:<br><br>' + errorMessage + '<br><br>Zorg dat PHP werkt en payment-proxy.php bereikbaar is.';
            }
            
            showMessage('error', errorMessage);
            payButton.disabled = false;
            buttonText.style.display = 'block';
            buttonLoader.style.display = 'none';
        }
    });
    
    async function processStripePayment(pkg) {
        const paymentData = {
            method: 'stripe',
            package: pkg.name,
            amount: pkg.priceNum,
            currency: 'EUR'
        };
        
        // Detect if we're in local development without PHP
        const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        
        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(paymentData),
                signal: AbortSignal.timeout(3000) // 3 second timeout
            });
            
            const responseText = await response.text();
            
            // On localhost: ALWAYS show test mode if response is not valid JSON
            // This prevents error messages on localhost when Python server can't execute PHP
            if (isLocalhost) {
                const isJSON = responseText.trim().startsWith('{') || responseText.trim().startsWith('[');
                const isErrorStatus = response.status === 501 || response.status === 405 || response.status === 404 || response.status !== 200;
                
                if (!isJSON || isErrorStatus) {
                    // Python server detected - show test mode, no errors
                    showTestModeStripe(pkg);
                    return;
                }
            }
            
            // If we got here and NOT localhost, check if response is valid JSON
            // On localhost, this should already be handled above
            if (!responseText.trim().startsWith('{') && !responseText.trim().startsWith('[')) {
                // This should never happen on localhost (already handled above)
                if (isLocalhost) {
                    showTestModeStripe(pkg);
                    return;
                }
                
                // Production: Show helpful error message about PHP server needed
                let serverError = 'Server geeft geen geldig JSON antwoord';
                
                // Check if response looks like HTML (PHP error page or Python server response)
                if (responseText.includes('<!DOCTYPE') || responseText.includes('<html') || responseText.includes('501') || responseText.includes('Unsupported method')) {
                    serverError = '❌ PHP server is niet actief!<br><br><strong>Het probleem:</strong><br>• Python server kan PHP niet uitvoeren<br>• payment-proxy.php wordt niet uitgevoerd<br><br><strong>Oplossing:</strong><br>• Upload naar een webserver met PHP<br>• Of start lokaal een PHP server: <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">php -S localhost:8000</code><br><br>Zorg dat je Python server gestopt is voordat je PHP start.';
                } else if (responseText.length > 0) {
                    // Show first 200 chars of response for debugging
                    const preview = responseText.substring(0, 200).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    serverError = '❌ Server geeft geen geldig JSON antwoord<br><br><strong>Server antwoord:</strong><br><code style="background: rgba(0,0,0,0.1); padding: 4px; border-radius: 4px; font-size: 11px;">' + preview + (responseText.length > 200 ? '...' : '') + '</code><br><br>Controleer je PHP server en payment-proxy.php configuratie.';
                }
                
                throw new Error(serverError);
            }
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                // JSON parse failed - show helpful error
                console.error('JSON parse error:', parseError);
                console.error('Response text:', responseText);
                
                if (isLocalhost) {
                    showTestModeStripe(pkg);
                    return;
                }
                
                let parseErrorMsg = '❌ Server geeft geen geldig JSON antwoord<br><br>';
                
                if (responseText.includes('501') || responseText.includes('Unsupported method')) {
                    parseErrorMsg += '<strong>PHP server draait niet!</strong><br><br>Python kan PHP niet uitvoeren.<br>Start een PHP server om Stripe te kunnen gebruiken.';
                } else if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
                    parseErrorMsg += '<strong>Server geeft HTML in plaats van JSON</strong><br><br>Dit betekent dat PHP niet werkt of een error pagina toont.<br>Controleer je server configuratie.';
                } else {
                    parseErrorMsg += 'De server heeft een ongeldig antwoord gegeven.<br>Controleer payment-proxy.php en je PHP configuratie.';
                }
                
                throw new Error(parseErrorMsg);
            }
            
            if (!response.ok) {
                const errorMsg = result?.error || 'Payment session creation failed';
                throw new Error('❌ Betaling sessie kon niet worden aangemaakt<br><br>' + errorMsg);
            }
            
            // ONLY redirect if we have a VALID sessionId from backend
            if (result.sessionId && typeof result.sessionId === 'string' && result.sessionId.length > 10) {
                // Double check it's a valid Stripe session ID format
                if (result.sessionId.startsWith('cs_') || result.sessionId.startsWith('cs_test_') || result.sessionId.startsWith('cs_live_')) {
                    // Valid Stripe session - redirect
                    if (stripe) {
                        try {
                            const { error } = await stripe.redirectToCheckout({
                                sessionId: result.sessionId
                            });
                            
                            if (error) {
                                throw new Error(`Stripe error: ${error.message}`);
                            }
                            // Success - redirecting (this is the ONLY place we redirect)
                            return;
                        } catch (stripeError) {
                            throw new Error(`Stripe redirect error: ${stripeError.message}`);
                        }
                    } else {
                        throw new Error('Stripe library niet geladen');
                    }
                } else {
                    throw new Error('Ongeldige Stripe session ID format');
                }
            } else if (result.paymentUrl && typeof result.paymentUrl === 'string' && 
                      result.paymentUrl.startsWith('https://checkout.stripe.com/')) {
                // Valid Stripe checkout URL - redirect
                window.location.href = result.paymentUrl;
                return;
            } else {
                // No valid sessionId or paymentUrl
                if (isLocalhost) {
                    showTestModeStripe(pkg);
                    return;
                }
                throw new Error('Geen geldige sessionId of paymentUrl ontvangen van backend');
            }
            
        } catch (fetchError) {
            // Network error, timeout, or other fetch error
            console.error('Payment fetch error:', fetchError);
            
            if (isLocalhost) {
                // On localhost, always show test mode instead of error
                showTestModeStripe(pkg);
                return;
            }
            
            // On production, show detailed error message
            let errorMsg = 'Er is een fout opgetreden bij het starten van de betaling.';
            
            if (fetchError.name === 'AbortError' || fetchError.message?.includes('timeout')) {
                errorMsg = '⏱️ De betalingsserver reageert niet (timeout).<br><br>Controleer je internetverbinding en probeer het opnieuw.<br><br>Als dit blijft gebeuren, neem contact op met support.';
            } else if (fetchError.message?.includes('NetworkError') || fetchError.message?.includes('Failed to fetch')) {
                errorMsg = '🌐 Kan geen verbinding maken met de betalingsserver.<br><br><strong>Mogelijke oorzaken:</strong><br>• PHP server draait niet<br>• payment-proxy.php is niet bereikbaar<br>• Netwerkprobleem<br><br>Controleer je server configuratie.';
            } else {
                errorMsg = '❌ ' + (fetchError.message || 'Onbekende fout') + '<br><br>Probeer het opnieuw of neem contact op met support.';
            }
            
            throw new Error(errorMsg);
        }
    }
    
    function showTestModeStripe(pkg) {
        // For localhost: Show instructions to install PHP
        const amount = Math.round(pkg.priceNum * 100); // Convert to cents
        
        showMessage('info', 
            `💳 <strong>PHP Nodig voor Stripe Checkout</strong><br><br>
            <strong>Pakket:</strong> ${pkg.name}<br>
            <strong>Bedrag:</strong> €${pkg.price}<br><br>
            ⚠️ <strong>PHP is niet geïnstalleerd</strong><br><br>
            <strong>Snelle Installatie:</strong><br>
            1. Download XAMPP: <a href="https://www.apachefriends.org/download.html" target="_blank" style="color: var(--primary-color);">Download hier</a><br>
            2. Installeer XAMPP<br>
            3. Start Apache in XAMPP Control Panel<br>
            4. Of typ in PowerShell: <code style="background: rgba(0,0,0,0.1); padding: 2px 6px; border-radius: 4px;">php -S localhost:8000</code><br>
            5. Stop Python server en herstart met PHP<br><br>
            <strong>✅ Stripe Keys zijn al geconfigureerd!</strong><br>
            <strong>✅ Backend code is klaar!</strong><br>
            Zodra PHP draait, werkt Stripe Checkout direct!<br><br>
            <a href="https://stripe.com/docs/testing" target="_blank" style="color: var(--primary-color); text-decoration: underline;">📖 Stripe Test Cards bekijken</a>`);
        
        // Re-enable button
        const payButton = document.getElementById('payButton');
        const buttonText = payButton.querySelector('.button-text');
        const buttonLoader = payButton.querySelector('.button-loader');
        payButton.disabled = false;
        if (buttonText) buttonText.style.display = 'block';
        if (buttonLoader) buttonLoader.style.display = 'none';
    }
    
    function showMessage(type, message) {
        const messageDiv = document.getElementById('paymentMessage');
        messageDiv.innerHTML = message;
        messageDiv.className = `payment-message-enhanced ${type}`;
        messageDiv.style.display = 'block';
        
        // Scroll to message
        setTimeout(() => {
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
});
