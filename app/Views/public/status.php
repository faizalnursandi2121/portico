<?php
$title = 'Check Voucher Status';
include ROOT.'/app/Views/layouts/header_public.php';
?>

    <!-- Main Container -->
    <main class="flex-grow flex items-center justify-center w-full">
    <div class="w-full max-w-lg z-10 p-4 md:p-6 animate-fade-in-up">
        
        <div class="flex flex-col space-y-8 text-center">
            
            <!-- Brand -->
            <div class="flex justify-center">
                <div class="relative group">
                     <img src="/assets/img/logo-m.svg" alt="MIVO Logo" class="relative h-12 w-auto block dark:hidden transform transition-transform duration-300 group-hover:scale-105">
                     <img src="/assets/img/logo-m-dark.svg" alt="MIVO Logo" class="relative h-12 w-auto hidden dark:block transform transition-transform duration-300 group-hover:scale-105">
                </div>
            </div>

            <!-- Text -->
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight mb-3 text-foreground" data-i18n="status.check_title">Check Voucher Status</h1>
                <p class="text-accents-5 text-sm md:text-base leading-relaxed max-w-sm mx-auto" data-i18n="status.check_desc">
                    Monitor your data usage and voucher validity in real-time without needing to re-login.
                </p>
            </div>

            <!-- Check Form -->
            <div class="card p-6 sm:p-8 relative overflow-hidden w-full text-left">
                <form onsubmit="checkStatus(event)" class="relative z-10">
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-accents-5 uppercase tracking-wider ml-1 mb-1 block" data-i18n="status.voucher_code_label">Voucher Code</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none z-10">
                                    <i data-lucide="ticket" class="h-4 w-4 text-accents-5"></i>
                                </div>
                                <input type="text" id="voucher-code" class="form-input pl-10 h-11 text-lg font-mono tracking-wide" placeholder="Ex: QWASZX" data-i18n="status.code_placeholder" required autofocus autocomplete="off">
                            </div>
                        </div>
                        
                        <button type="submit" id="chk-btn" class="w-full btn btn-primary h-11 text-base font-bold shadow-lg hover:shadow-primary/20">
                            <span id="btn-text" data-i18n="status.check_now">Check Now</span>
                            <i id="btn-loader" data-lucide="loader-2" class="w-4 h-4 animate-spin hidden"></i>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
    </main>

    <?php include ROOT.'/app/Views/layouts/footer_public.php'; ?>

    <script src="/assets/js/status-renderer.js"></script>

    <!-- Logic Script -->
    <script>

        async function checkStatus(e) {
            e.preventDefault();
            const code = document.getElementById('voucher-code').value.trim();
            if (!code) return;

            const btn = document.getElementById('chk-btn');
            const btnText = document.getElementById('btn-text');
            const loader = document.getElementById('btn-loader');
            
            // Set Loading
            btn.disabled = true;
            btnText.classList.add('hidden');
            loader.classList.remove('hidden');

            try {
                const pathParts = window.location.pathname.split('/');
                const session = pathParts[1]; 

                const response = await fetch('/api/status/check', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session: session, code: code })
                });

                const json = await response.json();

                if (json.success) {
                    const htmlContent = window.renderStatusDetails(json.data, (key) => window.i18n.t(key));

                    Mivo.alert('success', window.i18n.t('status.details_title'), htmlContent, {
                        customClass: { popup: 'w-full max-w-md' } // Override width only, others merged
                    });

                } else {
                    const errorMessage = document.createElement('span');
                    errorMessage.textContent = json.message && json.message !== 'Voucher Not Found'
                        ? json.message
                        : window.i18n.t('status.not_found_desc');
                    Mivo.alert('error', 
                        window.i18n.t('status.not_found_title'), 
                        errorMessage,
                        {
                            confirmButtonText: window.i18n.t('status.try_again'),
                            didClose: () => {
                                 setTimeout(() => {
                                     const el = document.getElementById('voucher-code');
                                     if(el) { el.focus(); el.select(); }
                                 }, 100);
                            }
                        }
                    );
                }

            } catch (err) {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: window.i18n.t('errors.500_title'),
                    text: window.i18n.t('errors.500_desc'),
                    confirmButtonText: 'Close',
                    customClass: {
                        popup: 'swal2-premium-card',
                        confirmButton: 'btn btn-secondary',
                    },
                    buttonsStyling: false
                });
            } finally {
                btn.disabled = false;
                btnText.classList.remove('hidden');
                loader.classList.add('hidden');
            }
        }
    </script>
