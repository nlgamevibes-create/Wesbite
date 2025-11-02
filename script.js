// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add scroll animation to cards
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe all cards
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.pricing-card, .feature-card');
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});

// Add hover effect to pricing cards
document.querySelectorAll('.pricing-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Registration Form Handler
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm) return;

    // API Endpoints om te proberen - probeert automatisch meerdere opties
    // OPTIE 1: Gebruik een proxy server (aanbevolen)
    // Upload register-proxy.php naar je webserver en gebruik die URL hier:
    // const PROXY_URL = 'https://jouw-website.nl/register-proxy.php';
    
    // OPTIE 2: Direct panel API endpoints (vereist API key configuratie)
    const API_ENDPOINTS = [
        'https://panel.gameflux.nl/api/application/users', // Pterodactyl standaard endpoint
        'https://panel.gameflux.nl/api/users', // Alternatief endpoint
        'https://panel.gameflux.nl/api/register' // Register endpoint
    ];
    
    // PROXY MODE: Als je een proxy hebt, zet dan deze variabele op true en gebruik PROXY_URL
    // INSTRUCTIES: 
    // 1. Upload register-proxy.php naar je webserver
    // 2. Configureer API keys in register-proxy.php
    // 3. Zet USE_PROXY op true en voeg je PROXY_URL toe hieronder
    const USE_PROXY = false; // Zet op true om proxy te gebruiken
    const PROXY_URL = ''; // Vul hier je proxy URL in (bijv: 'https://jouw-website.nl/register-proxy.php')
    
    // TEST MODE: Zet op true om lokaal te testen zonder backend (data wordt in browser opgeslagen)
    const TEST_MODE = false; // Zet op false wanneer backend API werkt
    const FALLBACK_TO_TEST_MODE = true; // Als alle endpoints falen, gebruik dan test mode

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitButton = registerForm.querySelector('.submit-button');
        const buttonText = submitButton.querySelector('.button-text');
        const buttonLoader = submitButton.querySelector('.button-loader');
        const messageDiv = document.getElementById('registerMessage');

        // Get form values
        const email = document.getElementById('email').value.trim();
        const username = document.getElementById('username').value.trim();
        const firstName = document.getElementById('firstName').value.trim();
        const lastName = document.getElementById('lastName').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const terms = document.getElementById('terms').checked;

        // Hide previous messages
        messageDiv.style.display = 'none';
        messageDiv.className = 'form-message';

        // Validation
        if (password !== confirmPassword) {
            showMessage(messageDiv, 'error', 'Wachtwoorden komen niet overeen!');
            return;
        }

        if (password.length < 8) {
            showMessage(messageDiv, 'error', 'Wachtwoord moet minimaal 8 tekens lang zijn!');
            return;
        }

        if (!/(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
            showMessage(messageDiv, 'error', 'Wachtwoord moet een hoofdletter en cijfer bevatten!');
            return;
        }

        if (!terms) {
            showMessage(messageDiv, 'error', 'Je moet akkoord gaan met de voorwaarden!');
            return;
        }

        // Validate reCAPTCHA
        if (typeof grecaptcha === 'undefined') {
            showMessage(messageDiv, 'error', 'reCAPTCHA is niet geladen. Ververs de pagina en probeer het opnieuw.');
            return;
        }

        const recaptchaResponse = grecaptcha.getResponse();
        if (!recaptchaResponse) {
            showMessage(messageDiv, 'error', 'Verifieer dat je geen robot bent door de reCAPTCHA te voltooien!');
            return;
        }

        // Disable button and show loader
        submitButton.disabled = true;
        buttonText.style.display = 'none';
        buttonLoader.style.display = 'block';

        try {
            // TEST MODE: Sla lokaal op voor testing (totdat API werkt)
            if (TEST_MODE) {
                console.warn('⚠️ TEST MODE ACTIEF - Data wordt lokaal opgeslagen');
                
                // Sla registratie data lokaal op
                const registrationData = {
                    email: email,
                    username: username,
                    first_name: firstName,
                    last_name: lastName,
                    timestamp: new Date().toISOString()
                };
                
                // Opslaan in localStorage
                const existingRegistrations = JSON.parse(localStorage.getItem('pendingRegistrations') || '[]');
                existingRegistrations.push(registrationData);
                localStorage.setItem('pendingRegistrations', JSON.stringify(existingRegistrations));
                
                console.log('Registratie opgeslagen lokaal:', registrationData);
                console.log('Alle lokale registraties:', existingRegistrations);
                
                // Toon succes bericht zonder alert
                showMessage(messageDiv, 'success', 
                    `✅ TEST MODE: Registratie lokaal opgeslagen!<br><br>
                    <strong>Email:</strong> ${email}<br>
                    <strong>Username:</strong> ${username}<br>
                    <strong>Naam:</strong> ${firstName} ${lastName}<br><br>
                    ⚠️ <em>Let op: Dit is alleen voor testing. Data wordt alleen in je browser opgeslagen. Configureer de API endpoint om echte registraties te doen.</em><br><br>
                    Je wordt doorgestuurd...`);
                
                // Redirect naar success page na 3 seconden
                setTimeout(() => {
                    window.location.href = 'register-success.html';
                }, 3000);
                
                return;
            }

            // Get reCAPTCHA token
            const recaptchaToken = grecaptcha.getResponse();

            // Prepare registration data
            const registrationData = {
                email: email,
                username: username,
                first_name: firstName, // Client First Name
                last_name: lastName,   // Client Last Name
                password: password,
                password_confirmation: confirmPassword,
                language: 'en', // Default language (English)
                root_admin: false, // Administrator: No (standaard)
                recaptcha_token: recaptchaToken // reCAPTCHA verification token
            };

            // Try multiple API endpoints
            let lastError = null;
            let success = false;

            // If using proxy, try that first
            const endpointsToTry = USE_PROXY && PROXY_URL ? [PROXY_URL, ...API_ENDPOINTS] : API_ENDPOINTS;

            for (const endpoint of endpointsToTry) {
                try {
                    console.log(`Trying endpoint: ${endpoint}`);
                    
                    // Different headers for proxy vs direct API
                    const headers = USE_PROXY && endpoint === PROXY_URL ? {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    } : {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    };

                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify(registrationData),
                        credentials: USE_PROXY && endpoint === PROXY_URL ? 'same-origin' : 'include',
                        mode: 'cors'
                    });

                    // Handle response
                    let data;
                    const contentType = response.headers.get('content-type');
                    
                    if (contentType && contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        const text = await response.text();
                        data = { 
                            message: `Server error (${response.status}): ${response.statusText}`,
                            raw: text 
                        };
                    }

                    if (response.ok) {
                        console.log(`✅ Success with endpoint: ${endpoint}`);
                        // Account is succesvol aangemaakt en staat nu in de database
                        window.location.href = 'register-success.html';
                        success = true;
                        break;
                    } else {
                        // Endpoint exists but returned error - don't try others
                        console.warn(`Endpoint ${endpoint} returned error:`, data);
                        const errorMsg = data.message || 
                                       data.error || 
                                       (data.errors ? Object.values(data.errors).flat().join(', ') : null) ||
                                       `Server error (${response.status}): ${response.statusText}`;
                        showMessage(messageDiv, 'error', errorMsg);
                        success = true; // Don't try other endpoints if we got a response
                        break;
                    }
                } catch (error) {
                    console.warn(`Endpoint ${endpoint} failed:`, error);
                    lastError = error;
                    // Continue to next endpoint
                }
            }

            // If all endpoints failed and fallback is enabled, use test mode
            if (!success && FALLBACK_TO_TEST_MODE && lastError) {
                console.warn('⚠️ All API endpoints failed, falling back to TEST MODE');
                
                // Save locally as fallback
                const registrationDataLocal = {
                    email: email,
                    username: username,
                    first_name: firstName,
                    last_name: lastName,
                    timestamp: new Date().toISOString()
                };
                
                const existingRegistrations = JSON.parse(localStorage.getItem('pendingRegistrations') || '[]');
                existingRegistrations.push(registrationDataLocal);
                localStorage.setItem('pendingRegistrations', JSON.stringify(existingRegistrations));
                
                showMessage(messageDiv, 'error', 
                    `⚠️ API endpoints niet bereikbaar. Registratie is lokaal opgeslagen als backup.<br><br>
                    <strong>Email:</strong> ${email}<br>
                    <strong>Username:</strong> ${username}<br><br>
                    <a href="view-registrations.html" style="color: var(--primary-color); text-decoration: underline; display: inline-block; margin-top: 0.5rem;">📋 Bekijk alle lokale registraties</a><br><br>
                    <em>Configureer het backend API endpoint om automatisch gebruikers aan te maken. Data staat lokaal klaar voor handmatige import.</em>`);
                return;
            }

            // If we reach here and no success, show error
            if (!success) {
                throw lastError || new Error('All endpoints failed');
            }
        } catch (error) {
            console.error('Registration error:', error);
            console.error('Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            // Better error messages based on error type
            let errorMessage = 'Er is een fout opgetreden. Probeer het later opnieuw of neem contact op met support.';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = `Kan geen verbinding maken met de server. Alle endpoints zijn geprobeerd:<br>
                    ${API_ENDPOINTS.map(e => `• ${e}`).join('<br>')}<br><br>
                    <strong>Mogelijke oorzaken:</strong><br>
                    • De API endpoints zijn nog niet geconfigureerd<br>
                    • Er is een CORS probleem<br>
                    • De server is tijdelijk niet bereikbaar<br><br>
                    <strong>Oplossing:</strong><br>
                    1. Configureer het backend API endpoint op je panel server<br>
                    2. Zorg voor CORS configuratie<br>
                    3. Bekijk API_SETUP.md voor instructies<br>
                    4. Check de browser console (F12) voor gedetailleerde errors<br><br>
                    <em>Registratie data is lokaal opgeslagen als backup.</em>`;
            } else if (error.name === 'SyntaxError') {
                errorMessage = 'Server gaf een ongeldig antwoord. Neem contact op met support.';
            } else if (error.message) {
                errorMessage = `Fout: ${error.message}`;
            }
            
            showMessage(messageDiv, 'error', errorMessage);
        } finally {
            // Re-enable button and hide loader
            submitButton.disabled = false;
            buttonText.style.display = 'block';
            buttonLoader.style.display = 'none';
        }
    });

    function showMessage(element, type, message) {
        element.innerHTML = message; // Use innerHTML to support HTML formatting
        element.className = `form-message ${type}`;
        element.style.display = 'block';
        
        // Scroll to message
        element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Test API endpoint connectivity (optional - can be called manually)
    window.testAPIEndpoint = async function() {
        console.log('Testing API endpoint:', API_ENDPOINT);
        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'OPTIONS', // Preflight request
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                mode: 'cors'
            });
            console.log('API Response:', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });
            return response;
        } catch (error) {
            console.error('API Test Error:', error);
            return null;
        }
    };

    // Real-time password validation
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    [passwordInput, confirmPasswordInput].forEach(input => {
        input.addEventListener('input', () => {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword && password !== confirmPassword) {
                confirmPasswordInput.setCustomValidity('Wachtwoorden komen niet overeen');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
    });
});

