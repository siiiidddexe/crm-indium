</main>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });
    }

    // Auto-call functionality
    class AutoCaller {
        constructor() {
            this.isAutoMode = false;
            this.currentIndex = 0;
            this.cards = [];
            this.timerInterval = null;
        }

        init(cards) {
            this.cards = cards;
            this.currentIndex = 0;
        }

        toggleAutoMode() {
            this.isAutoMode = !this.isAutoMode;
            return this.isAutoMode;
        }

        getCurrentCard() {
            return this.cards[this.currentIndex];
        }

        nextCard() {
            if (this.currentIndex < this.cards.length - 1) {
                this.currentIndex++;
                return this.cards[this.currentIndex];
            }
            return null;
        }

        initiateCall(phone) {
            // Use tel: protocol for Android auto-call
            window.location.href = `tel:${phone}`;
        }

        startTimer(seconds, callback) {
            let remaining = seconds;
            const timerEl = document.getElementById('countdownTimer');

            if (timerEl) {
                timerEl.textContent = remaining;
                timerEl.parentElement.classList.remove('hidden');
            }

            this.timerInterval = setInterval(() => {
                remaining--;
                if (timerEl) timerEl.textContent = remaining;

                if (remaining <= 0) {
                    clearInterval(this.timerInterval);
                    if (timerEl) timerEl.parentElement.classList.add('hidden');
                    if (callback) callback();
                }
            }, 1000);
        }

        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        }
    }

    // Global auto-caller instance
    window.autoCaller = new AutoCaller();

    // Utility functions
    function showModal(modalId) {
        document.getElementById(modalId)?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function hideModal(modalId) {
        document.getElementById(modalId)?.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Close modal on backdrop click
    document.querySelectorAll('[data-modal-backdrop]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el) {
                hideModal(el.id);
            }
        });
    });

    // Form validation helper
    function validateForm(formEl) {
        const inputs = formEl.querySelectorAll('[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('border-red-500');
                isValid = false;
            } else {
                input.classList.remove('border-red-500');
            }
        });

        return isValid;
    }

    // Confirm dialog
    function confirmAction(message) {
        return confirm(message);
    }

    // Format phone number for display
    function formatPhone(phone) {
        const cleaned = phone.replace(/\D/g, '');
        if (cleaned.length === 10) {
            return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
        }
        return phone;
    }

    // Toast notification
    function showToast(message, type = 'success') {
        const colors = {
            success: { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-700', btn: 'text-green-500 hover:text-green-700' },
            error: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', btn: 'text-red-500 hover:text-red-700' }
        };
        const c = colors[type] || colors.success;
        const icon = type === 'success'
            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';

        const wrapper = document.createElement('div');
        wrapper.className = 'fixed top-20 lg:top-4 right-4 z-50 animate-fade-in';
        wrapper.innerHTML = `<div class="flex items-center gap-3 px-4 py-3 ${c.bg} border ${c.border} ${c.text} rounded-xl shadow-lg">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icon}</svg>
            <span class="text-sm font-medium">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 ${c.btn}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>`;
        document.body.appendChild(wrapper);
        setTimeout(() => wrapper.remove(), 4000);
    }
</script>

<?php if (isAdmin() && getSetting('auto_assign_enabled', '0') === '1'): ?>
<script>
(function() {
    const autoAssignInterval = <?= intval(getSetting('auto_assign_interval', '20')) * 1000 ?>;
    setInterval(() => {
        fetch('<?= APP_URL ?>/api/auto_assign.php')
            .then(r => r.json())
            .then(d => { if (d.assigned > 0 || d.reassigned > 0) console.log('[AutoAssign]', d); })
            .catch(() => {});
    }, autoAssignInterval);
})();
</script>
<?php endif; ?>
</body>

</html>