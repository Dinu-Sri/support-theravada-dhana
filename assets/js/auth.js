/**
/**
 * Authentication JavaScript for Dhana Booking System
 */

// Variables to track monk confirmation
let isMonkConfirmed = false;

// Monk Modal Functions - Defined globally for immediate access
function showMonkModal() {
    console.log('showMonkModal called');
    const modal = document.getElementById('monkModal');
    console.log('Modal element found:', modal);
    
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        console.log('Modal should now be visible');
    } else {
        console.error('Modal element with ID "monkModal" not found!');
    }
}

function closeMonkModal() {
    const modal = document.getElementById('monkModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function cancelMonkRegistration() {
    // Uncheck the monk checkbox
    const monkCheckbox = document.getElementById('isMonk');
    if (monkCheckbox) {
        monkCheckbox.checked = false;
    }
    isMonkConfirmed = false;
    
    // Close modal
    closeMonkModal();
}

function acceptMonkTerms() {
    isMonkConfirmed = true;
    closeMonkModal();
    
    // Show success message
    showSuccess('Monk registration terms accepted. You can now proceed with creating your account.');
}

// Show login form
function showLoginForm() {
    document.getElementById('loginForm').classList.add('active');
    document.getElementById('registerForm').classList.remove('active');
}

// Show register form
function showRegisterForm() {
    document.getElementById('registerForm').classList.add('active');
    document.getElementById('loginForm').classList.remove('active');
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Login form validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            
            if (!email || !password) {
                e.preventDefault();
                showError('Please fill in all fields');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                showError('Please enter a valid email address');
                return;
            }
        });
    }
    
    // Registration form validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        // ✅ FIX #4: Prevent double-submit
        let isSubmitting = false;

        registerForm.addEventListener('submit', function(e) {
            // Prevent double submission
            if (isSubmitting) {
                e.preventDefault();
                console.log('Form already submitting, preventing duplicate submission');
                return;
            }

            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const email = document.getElementById('registerEmail').value;
            const contactNumber = document.getElementById('contactNumber').value;
            const password = document.getElementById('registerPassword').value;
            const submitButton = registerForm.querySelector('button[type="submit"]');

            // Basic validation
            if (!firstName || !lastName || !email || !contactNumber || !password) {
                e.preventDefault();
                showError('Please fill in all fields');
                return;
            }

            if (firstName.length < 2 || lastName.length < 2) {
                e.preventDefault();
                showError('First name and last name must be at least 2 characters');
                return;
            }

            if (!isValidEmail(email)) {
                e.preventDefault();
                showError('Please enter a valid email address');
                return;
            }

            if (contactNumber.length < 10) {
                e.preventDefault();
                showError('Please enter a valid contact number');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                showError('Password must be at least 6 characters');
                return;
            }

            // Check monk confirmation if monk checkbox is checked
            const monkCheckbox = document.getElementById('isMonk');
            if (monkCheckbox && monkCheckbox.checked && (typeof window.isMonkConfirmed !== 'undefined' ? !window.isMonkConfirmed : true)) {
                e.preventDefault();
                showError('Please accept the monk registration terms first.');
                if (typeof window.showMonkModal === 'function') {
                    window.showMonkModal();
                }
                return;
            }

            // All validations passed - lock the form
            isSubmitting = true;

            // Disable submit button and show loading state
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            }

            // Re-enable after 5 seconds as a safety measure (in case of network error)
            setTimeout(function() {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
                }
            }, 5000);
        });
    }
});

// Email validation function
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Show error message
function showError(message) {
    // Remove existing error messages
    const existingErrors = document.querySelectorAll('.js-error-message');
    existingErrors.forEach(error => error.remove());
    
    // Create new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message js-error-message';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    
    // Insert error message at the top of the active form
    const activeForm = document.querySelector('.auth-form.active');
    const formTitle = activeForm.querySelector('h2');
    formTitle.insertAdjacentElement('afterend', errorDiv);
    
    // Auto-remove error after 5 seconds
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
    }, 5000);
}

// Show success message
function showSuccess(message) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.js-success-message, .js-error-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new success message
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message js-success-message';
    successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    
    // Insert success message at the top of the active form
    const activeForm = document.querySelector('.auth-form.active');
    const formTitle = activeForm.querySelector('h2');
    formTitle.insertAdjacentElement('afterend', successDiv);
    
    // Auto-remove success message after 3 seconds
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.remove();
        }
    }, 3000);
}

// Add loading state to buttons
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
    } else {
        button.disabled = false;
        // Restore original text based on form type
        if (button.closest('#loginForm')) {
            button.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        } else {
            button.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        }
    }
}

// Enhanced form submission with loading states
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.auth-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true);
            
            // If validation fails, restore button
            setTimeout(() => {
                if (submitButton.disabled) {
                    setButtonLoading(submitButton, false);
                }
            }, 100);
        });
    });
});

// Auto-focus first input when switching forms
function showLoginForm() {
    document.getElementById('loginForm').classList.add('active');
    document.getElementById('registerForm').classList.remove('active');
    setTimeout(() => {
        document.getElementById('loginEmail').focus();
    }, 100);
}

function showRegisterForm() {
    document.getElementById('registerForm').classList.add('active');
    document.getElementById('loginForm').classList.remove('active');
    setTimeout(() => {
        document.getElementById('firstName').focus();
    }, 100);
}

// Handle Enter key navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const activeForm = document.querySelector('.auth-form.active');
        if (activeForm) {
            const inputs = activeForm.querySelectorAll('input');
            const currentInput = document.activeElement;
            const currentIndex = Array.from(inputs).indexOf(currentInput);
            
            if (currentIndex >= 0 && currentIndex < inputs.length - 1) {
                e.preventDefault();
                inputs[currentIndex + 1].focus();
            }
        }
    }
});

// Password strength indicator (for registration)
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('registerPassword');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = getPasswordStrength(password);
            updatePasswordStrengthIndicator(strength);
        });
    }
});

function getPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

function updatePasswordStrengthIndicator(strength) {
    // Remove existing indicator
    const existingIndicator = document.querySelector('.password-strength');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    if (strength === 0) return;
    
    const passwordGroup = document.getElementById('registerPassword').closest('.form-group');
    const indicator = document.createElement('div');
    indicator.className = 'password-strength';
    
    const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['#ff4757', '#ff6b7a', '#ffa502', '#2ed573', '#20bf6b'];
    
    indicator.innerHTML = `
        <div style="margin-top: 5px; font-size: 0.8rem;">
            Password strength: 
            <span style="color: ${strengthColors[strength - 1]}; font-weight: bold;">
                ${strengthTexts[strength - 1]}
            </span>
        </div>
    `;
    
    passwordGroup.appendChild(indicator);
}

// Handle monk checkbox change and modal events
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up monk functionality');
    
    const monkCheckbox = document.getElementById('isMonk');
    console.log('Monk checkbox found:', monkCheckbox);
    
    if (monkCheckbox) {
        monkCheckbox.addEventListener('change', function() {
            console.log('Monk checkbox changed. Checked:', this.checked, 'Confirmed:', isMonkConfirmed);
            if (this.checked && !isMonkConfirmed) {
                console.log('Showing monk modal...');
                showMonkModal();
            }
        });
    } else {
        console.error('Monk checkbox with ID "isMonk" not found!');
    }
    
    // Handle modal outside click
    const modal = document.getElementById('monkModal');
    console.log('Modal for outside click found:', modal);
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                cancelMonkRegistration();
            }
        });
    }
    
    // Handle ESC key for modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('monkModal');
            if (modal && modal.style.display === 'flex') {
                cancelMonkRegistration();
            }
        }
    });
});


