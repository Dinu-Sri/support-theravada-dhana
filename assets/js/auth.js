/** Accessible, compact authentication interactions. */
(function () {
    'use strict';

    let submittingForm = null;

    function activeForm() {
        return document.querySelector('.auth-form.active');
    }

    function showMessage(message, type) {
        document.querySelectorAll('.js-form-message').forEach((node) => node.remove());
        const form = activeForm();
        if (!form) return;
        const box = document.createElement('div');
        box.className = `${type}-message js-form-message`;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const icon = document.createElement('i');
        icon.className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        box.append(icon, document.createTextNode(` ${message}`));
        form.querySelector('h2')?.insertAdjacentElement('afterend', box);
    }

    window.showLoginForm = function () {
        document.getElementById('loginForm')?.classList.add('active');
        document.getElementById('registerForm')?.classList.remove('active');
        setTimeout(() => document.getElementById('loginEmail')?.focus(), 50);
    };

    window.showRegisterForm = function () {
        document.getElementById('registerForm')?.classList.add('active');
        document.getElementById('loginForm')?.classList.remove('active');
        setTimeout(() => document.getElementById('firstName')?.focus(), 50);
    };

    window.showMonkModal = function () {
        const modal = document.getElementById('monkModal');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        modal.querySelector('button')?.focus();
    };

    window.closeMonkModal = function () {
        const modal = document.getElementById('monkModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        document.getElementById('isMonk')?.focus();
    };

    window.cancelMonkRegistration = function () {
        const checkbox = document.getElementById('isMonk');
        const accepted = document.getElementById('vinayaAccepted');
        if (checkbox) checkbox.checked = false;
        if (accepted) accepted.value = '0';
        window.closeMonkModal();
    };

    window.acceptMonkTerms = function () {
        const accepted = document.getElementById('vinayaAccepted');
        if (accepted) accepted.value = '1';
        window.closeMonkModal();
        showMessage('Vinaya guidance accepted. You can continue creating the account.', 'success');
    };

    function validEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function validate(form) {
        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }
        const email = form.querySelector('input[type="email"]');
        if (email && !validEmail(email.value.trim())) {
            showMessage('Please enter a valid email address.', 'error');
            email.focus();
            return false;
        }
        if (form.id === 'registerForm') {
            const password = document.getElementById('registerPassword');
            const confirmation = document.getElementById('confirmPassword');
            if (password.value !== confirmation.value) {
                showMessage('Passwords do not match.', 'error');
                confirmation.focus();
                return false;
            }
            const monk = document.getElementById('isMonk');
            const accepted = document.getElementById('vinayaAccepted');
            if (monk.checked && accepted.value !== '1') {
                showMessage('Please read and accept the Vinaya guidance first.', 'error');
                window.showMonkModal();
                return false;
            }
        }
        return true;
    }

    function setLoading(form) {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Please wait…';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.auth-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (submittingForm === form || !validate(form)) {
                    event.preventDefault();
                    return;
                }
                submittingForm = form;
                setLoading(form);
            });
        });

        const monk = document.getElementById('isMonk');
        const accepted = document.getElementById('vinayaAccepted');
        monk?.addEventListener('change', function () {
            if (!this.checked && accepted) accepted.value = '0';
            if (this.checked && accepted?.value !== '1') window.showMonkModal();
        });

        const modal = document.getElementById('monkModal');
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) window.cancelMonkRegistration();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal?.style.display === 'flex') window.cancelMonkRegistration();
        });
    });
}());
