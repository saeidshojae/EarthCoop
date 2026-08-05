/**
 * Najm Bahar Shared JavaScript Utilities
 * توابع مشترک برای بهبود UX و Accessibility
 */

const NajmBahar = {
    /**
     * Modal Management
     */
    modal: {
        /**
         * Open modal with accessibility features
         */
        open(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Focus first focusable element
            const firstFocusable = this.getFirstFocusable(modal);
            if (firstFocusable) {
                setTimeout(() => firstFocusable.focus(), 100);
            }
            
            // Store the element that triggered modal
            modal.dataset.triggerElement = document.activeElement.id || '';
        },

        /**
         * Close modal and return focus
         */
        close(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.add('hidden');
            document.body.style.overflow = '';
            
            // Return focus to trigger element
            const triggerId = modal.dataset.triggerElement;
            if (triggerId) {
                const trigger = document.getElementById(triggerId);
                if (trigger) {
                    trigger.focus();
                }
            }
        },

        /**
         * Setup modal with keyboard and click handlers
         */
        setup(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            // ESC key handler
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.close(modalId);
                }
                if (e.key === 'Tab') {
                    this.trapFocus(modal, e);
                }
            });

            // Click outside handler
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close(modalId);
                }
            });
        },

        /**
         * Trap focus inside modal
         */
        trapFocus(element, event) {
            const focusableElements = Array.from(
                element.querySelectorAll(
                    'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"]):not(:disabled)'
                )
            );

            if (focusableElements.length === 0) return;

            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            if (event.shiftKey && document.activeElement === firstFocusable) {
                event.preventDefault();
                lastFocusable.focus();
            } else if (!event.shiftKey && document.activeElement === lastFocusable) {
                event.preventDefault();
                firstFocusable.focus();
            }
        },

        /**
         * Get first focusable element
         */
        getFirstFocusable(element) {
            const focusable = element.querySelector(
                'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"]):not(:disabled)'
            );
            return focusable;
        }
    },

    /**
     * Form Utilities
     */
    form: {
        /**
         * Add loading state to button
         */
        setLoading(button, loading = true) {
            if (loading) {
                button.dataset.originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = `
                    <span class="nb-spinner" aria-label="در حال بارگذاری"></span>
                    <span>${button.dataset.loadingText || 'در حال پردازش...'}</span>
                `;
            } else {
                button.disabled = false;
                button.innerHTML = button.dataset.originalText || button.innerHTML;
            }
        },

        /**
         * Setup form with loading state on submit
         */
        setupLoadingState(formId) {
            const form = document.getElementById(formId);
            if (!form) return;

            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) {
                    this.setLoading(submitBtn, true);
                }
            });
        },

        /**
         * Validate numeric input with max value
         */
        validateNumeric(input, maxValue = null) {
            const value = parseFloat(input.value);
            
            if (isNaN(value) || value < 0) {
                this.showError(input, 'لطفاً یک عدد معتبر وارد کنید');
                return false;
            }

            if (maxValue !== null && value > maxValue) {
                this.showError(input, `حداکثر مقدار مجاز ${maxValue} است`);
                return false;
            }

            this.clearError(input);
            return true;
        },

        /**
         * Show error message for input
         */
        showError(input, message) {
            input.classList.add('error');
            input.setAttribute('aria-invalid', 'true');
            
            let errorDiv = input.parentElement.querySelector('.nb-error-text');
            if (!errorDiv) {
                errorDiv = document.createElement('span');
                errorDiv.className = 'nb-error-text';
                errorDiv.setAttribute('role', 'alert');
                input.parentElement.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        },

        /**
         * Clear error message for input
         */
        clearError(input) {
            input.classList.remove('error');
            input.removeAttribute('aria-invalid');
            
            const errorDiv = input.parentElement.querySelector('.nb-error-text');
            if (errorDiv) {
                errorDiv.remove();
            }
        },

        /**
         * Setup real-time validation for numeric input
         */
        setupNumericValidation(inputId, maxValue = null) {
            const input = document.getElementById(inputId);
            if (!input) return;

            // Set inputmode for mobile keyboards
            input.setAttribute('inputmode', 'decimal');
            
            // Validate on blur
            input.addEventListener('blur', () => {
                if (input.value) {
                    this.validateNumeric(input, maxValue);
                }
            });

            // Clear error on input
            input.addEventListener('input', () => {
                if (input.classList.contains('error')) {
                    this.clearError(input);
                }
            });
        }
    },

    /**
     * Alert/Toast Management
     */
    alert: {
        /**
         * Show auto-dismissing alert
         */
        show(message, type = 'success', duration = 5000) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `nb-alert nb-alert-${type} nb-fade-in`;
            alertDiv.setAttribute('role', 'alert');
            alertDiv.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
            
            const iconMap = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            alertDiv.innerHTML = `
                <i class="fas ${iconMap[type]}" aria-hidden="true"></i>
                <span>${message}</span>
                <button type="button" class="nb-focusable" onclick="this.parentElement.remove()" aria-label="بستن">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Insert at top of main content
            const main = document.querySelector('main') || document.body;
            main.insertBefore(alertDiv, main.firstChild);
            
            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    alertDiv.classList.add('nb-fade-out');
                    setTimeout(() => alertDiv.remove(), 300);
                }, duration);
            }
        },

        /**
         * Setup auto-dismiss for existing alerts
         */
        setupAutoDismiss(selector = '[data-auto-dismiss]') {
            document.querySelectorAll(selector).forEach(alert => {
                const duration = parseInt(alert.dataset.autoDismiss) || 5000;
                setTimeout(() => {
                    alert.classList.add('nb-fade-out');
                    setTimeout(() => alert.remove(), 300);
                }, duration);
            });
        }
    },

    /**
     * Accessibility Utilities
     */
    a11y: {
        /**
         * Announce message to screen readers
         */
        announce(message, priority = 'polite') {
            const announcer = document.getElementById('nb-announcer') || this.createAnnouncer();
            announcer.setAttribute('aria-live', priority);
            announcer.textContent = message;
            
            // Clear after announcement
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        },

        /**
         * Create ARIA live region for announcements
         */
        createAnnouncer() {
            const announcer = document.createElement('div');
            announcer.id = 'nb-announcer';
            announcer.className = 'nb-sr-only';
            announcer.setAttribute('role', 'status');
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            document.body.appendChild(announcer);
            return announcer;
        },

        /**
         * Setup keyboard navigation for custom components
         */
        setupKeyboardNav(selector, onEnter, onSpace) {
            document.querySelectorAll(selector).forEach(element => {
                element.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && onEnter) {
                        e.preventDefault();
                        onEnter(element);
                    }
                    if (e.key === ' ' && onSpace) {
                        e.preventDefault();
                        onSpace(element);
                    }
                });
            });
        }
    },

    /**
     * Initialize all features
     */
    init() {
        // Setup auto-dismiss alerts
        this.alert.setupAutoDismiss();
        
        // Create announcer for screen readers
        this.a11y.createAnnouncer();
        
        // Mark cards as loaded after animation
        setTimeout(() => {
            document.querySelectorAll('.nb-card, .nb-stat').forEach(el => {
                el.classList.add('loaded');
            });
        }, 1000);
        
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NajmBahar.init());
} else {
    NajmBahar.init();
}

// Export to global scope
window.NajmBahar = NajmBahar;
