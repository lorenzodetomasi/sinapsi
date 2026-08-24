<?php
// The Donation Php template for inclusion
// Assicurati che PAYPAL_URL, SATISPAY_URL e STRIPE_URL siano definiti prima di questo file
if (!defined('PAYPAL_URL'))  define('PAYPAL_URL', '');
if (!defined('SATISPAY_URL')) define('SATISPAY_URL', '');
if (!defined('STRIPE_URL'))  define('STRIPE_URL', '');
?>
<aside id="isotype-donation-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Donazione">
    <div id="isotype-donation-modal">
        <h3>Sostieni questo progetto</h3>
        <p>Il servizio è gratuito. Se ti è utile, considera una piccola donazione per sostenerne lo sviluppo.</p>
        
        <div class="donation-amount-selector">
            <button type="button" class="amt-btn" data-value="2">2€</button>
            <button type="button" class="amt-btn active" data-value="5">5€</button>
            <button type="button" class="amt-btn" data-value="10">10€</button>
            <input type="number" id="custom-amount" placeholder="Altro" min="1" max="999">
        </div>

        <div class="donation-methods">
            <button type="button" class="method-btn" data-method="paypal">
                <strong>PayPal</strong>
            </button>
            <button type="button" class="method-btn" data-method="satispay">
                <strong>Satispay</strong>
            </button>
            <button type="button" class="method-btn" data-method="stripe">
                <strong>Carta (Stripe)</strong>
            </button>
        </div>

        <div class="modal-footer">
            <button id="donation-continue-btn">Continua senza donare</button>
        </div>
    </div>
</aside>

<style>
#isotype-donation-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85); z-index: 99999;
    display: none; align-items: center; justify-content: center;
    font-family: -apple-system, system-ui, sans-serif;
}
#isotype-donation-modal {
    background: #fff; padding: 30px; border-radius: 16px;
    max-width: 400px; width: 90%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4);
}
.donation-amount-selector {
    display: flex; gap: 8px; justify-content: center; margin: 25px 0;
}
.amt-btn {
    padding: 10px 14px; border: 2px solid #f0f0f0; background: #fff;
    border-radius: 10px; cursor: pointer; font-weight: 600; transition: 0.2s;
}
.amt-btn.active { border-color: #0070ba; background: #f0faff; color: #0070ba; }
#custom-amount { width: 65px; padding: 8px; border: 2px solid #f0f0f0; border-radius: 10px; outline: none; }
#custom-amount:focus { border-color: #0070ba; }

.donation-methods {
    display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 25px;
}
.method-btn {
    padding: 16px; border: none; border-radius: 10px; cursor: pointer;
    font-size: 16px; color: white; transition: transform 0.1s, opacity 0.2s;
}
.method-btn[data-method="paypal"] { background: #0070ba; }
.method-btn[data-method="satispay"] { background: #fa5355; }
.method-btn[data-method="stripe"] { background: #635bff; }
.method-btn:hover { opacity: 0.9; transform: translateY(-2px); }

#donation-continue-btn {
    background: none; border: none; color: #aaa; text-decoration: underline; cursor: pointer; font-size: 0.9em;
}
</style>

<script>
(function() {
    // 1. SICUREZZA: Validazione e output sicuro delle costanti PHP
    const CONFIG = {
        paypal_url: <?php echo json_encode(filter_var(PAYPAL_URL, FILTER_VALIDATE_URL) ? rtrim(PAYPAL_URL, '/') : '', JSON_UNESCAPED_SLASHES); ?>, 
        satispay_url: <?php echo json_encode(filter_var(SATISPAY_URL, FILTER_VALIDATE_URL) ? SATISPAY_URL : '', JSON_UNESCAPED_SLASHES); ?>, 
        stripe_url: <?php echo json_encode(filter_var(STRIPE_URL, FILTER_VALIDATE_URL) ? STRIPE_URL : '', JSON_UNESCAPED_SLASHES); ?>
    };

    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('isotype-donation-overlay');
        const customAmt = document.getElementById('custom-amount');
        const continueBtn = document.getElementById('donation-continue-btn');
        
        let selectedAmount = 5;
        let activeElement = null; // Memorizziamo l'elemento, non la funzione, per gestire il loop

        // Gestione importi
        overlay.addEventListener('click', (e) => {
            if (e.target.classList.contains('amt-btn')) {
                document.querySelectorAll('.amt-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                customAmt.value = '';
                selectedAmount = e.target.dataset.value;
            }
        });

        // --- FUNZIONE DI ESECUZIONE (POST-DONAZIONE) ---
        function proceed() {
            if (!activeElement) return;

            overlay.style.display = 'none';
            
            if (activeElement.tagName === 'FORM') {
                activeElement.submit();
            } else if (activeElement.tagName === 'A') {
                // SICUREZZA: Per evitare il loop infinito, usiamo un flag temporaneo
                activeElement.dataset.donating = "true";
                activeElement.click();
                delete activeElement.dataset.donating;
            }
            activeElement = null;
        }

        // --- GESTIONE PAGAMENTI ---
        document.querySelectorAll('.method-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const method = this.dataset.method;
                const amount = parseFloat(selectedAmount) || 5;
                let finalUrl = "";

                // Costruzione URL sicura
                const safeAmount = Math.min(Math.max(Math.floor(amount * 100) / 100, 0.01), 999);
                if (method === 'paypal' && CONFIG.paypal_url) {
                    finalUrl = `${CONFIG.paypal_url}/${safeAmount}`;
                } else if (method === 'satispay' && CONFIG.satispay_url) {
                    const sep = CONFIG.satispay_url.includes('?') ? '&' : '?';
                    finalUrl = `${CONFIG.satispay_url}${sep}amount=${Math.round(safeAmount * 100)}`;
                } else if (method === 'stripe' && CONFIG.stripe_url) {
                    finalUrl = CONFIG.stripe_url;
                }

                if (finalUrl) {
                    // SICUREZZA: Rel='noopener' previene il tab-nabbing (attacchi dalla nuova scheda)
                    const a = document.createElement('a');
                    a.href = finalUrl;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer'; 
                    a.click();
                }

                setTimeout(proceed, 300);
            });
        });

        continueBtn.addEventListener('click', (e) => {
            e.preventDefault();
            proceed();
        });

        // --- INTERCETTAZIONE (LINK & FORM) ---
        document.addEventListener('click', function(e) {
            const el = e.target.closest('a[data-donation]');
            // Se l'elemento ha il flag "donating", ignoriamo l'intercettazione e lasciamo scaricare
            if (el && !el.dataset.donating) {
                e.preventDefault();
                activeElement = el;
                overlay.style.display = 'flex';
            }
        });

        document.addEventListener('submit', function(e) {
            const el = e.target.closest('form[data-donation]');
            if (el && window.getComputedStyle(overlay).display === 'none') {
                e.preventDefault();
                activeElement = el;
                overlay.style.display = 'flex';
            }
        });

        // --- CHIUSURA MODALE ---
        // Click sull'overlay (fuori dal modal)
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.style.display = 'none';
                activeElement = null;
            }
        });

        // Tasto Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && window.getComputedStyle(overlay).display !== 'none') {
                overlay.style.display = 'none';
                activeElement = null;
            }
        });
    });
})();
</script>