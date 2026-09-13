/**
 * Dāna Reservation Form JavaScript for Dhana Reservation System
 */

// Update total amount when dhana type is selected
function updateTotalAmount() {
    const selectedRadio = document.querySelector('input[name="dhana_type_id"]:checked');
    const selectedDhanaTypeSpan = document.getElementById('selectedDhanaType');
    const totalAmountSpan = document.getElementById('totalAmount');
    
    if (selectedRadio) {
        const dhanaTypeId = parseInt(selectedRadio.value);
        const dhanaType = dhanaTypes.find(type => type.id === dhanaTypeId);
        
        if (dhanaType) {
            selectedDhanaTypeSpan.textContent = dhanaType.name;
            totalAmountSpan.textContent = `Rs. ${formatPrice(dhanaType.price)}`;
        }
    } else {
        selectedDhanaTypeSpan.textContent = 'Please select a dhana type';
        totalAmountSpan.textContent = 'Rs. 0';
    }
}

// Update selected date in summary
function updateSelectedDate() {
    const dateInput = document.getElementById('booking_date');
    const selectedDateSpan = document.getElementById('selectedDate');
    
    if (dateInput.value) {
        const date = new Date(dateInput.value + 'T00:00:00');
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        selectedDateSpan.textContent = date.toLocaleDateString('en-US', options);
    } else {
        selectedDateSpan.textContent = 'Please select a date';
    }
}

// Check date availability
async function checkDateAvailability() {
    const dateInput = document.getElementById('booking_date');
    const availabilityDiv = document.getElementById('dateAvailability');
    const selectedRadio = document.querySelector('input[name="dhana_type_id"]:checked');
    
    updateSelectedDate();
    
    if (!dateInput.value) {
        availabilityDiv.style.display = 'none';
        return;
    }
    
    if (!selectedRadio) {
        availabilityDiv.innerHTML = `
            <i class="fas fa-info-circle"></i>
            Please select a dhana type first to check availability.
        `;
        availabilityDiv.className = 'date-availability unavailable';
        return;
    }
    
    const dhanaTypeId = selectedRadio.value;
    const selectedDate = dateInput.value;
    
    try {
        // Show loading
        availabilityDiv.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i>
            Checking availability...
        `;
        availabilityDiv.className = 'date-availability';
        availabilityDiv.style.display = 'block';
        
        // Make AJAX request to check availability
        const response = await fetch('api/check-availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                date: selectedDate,
                dhana_type_id: dhanaTypeId
            })
        });
        
        const result = await response.json();
        
        if (result.available) {
            availabilityDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                Great! This dhana type is available on the selected date.
            `;
            availabilityDiv.className = 'date-availability available';
        } else {
            availabilityDiv.innerHTML = `
                <i class="fas fa-times-circle"></i>
                Sorry, this dhana type is already booked for the selected date.
            `;
            availabilityDiv.className = 'date-availability unavailable';
        }
    } catch (error) {
        console.error('Error checking availability:', error);
        availabilityDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Unable to check availability. Please try again.
        `;
        availabilityDiv.className = 'date-availability unavailable';
    }
}

// Format price
function formatPrice(price) {
    if (price == 0) {
        return 'Coming Soon';
    }
    return new Intl.NumberFormat('en-US').format(price);
}

// Form validation
function validateForm() {
    let isValid = true;
    const errors = [];
    
    // Check dhana type selection
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    if (!selectedDhanaType) {
        errors.push('Please select a dhana type');
        isValid = false;
    }
    
    // Check date selection
    const dateInput = document.getElementById('booking_date');
    if (!dateInput.value) {
        errors.push('Please select a booking date');
        isValid = false;
    } else {
        const selectedDate = new Date(dateInput.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate <= today) {
            errors.push('Booking date must be in the future');
            isValid = false;
        }
        
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 30);
        
        if (selectedDate > maxDate) {
            errors.push('Bookings can only be made up to 30 days in advance');
            isValid = false;
        }
    }
    
    return { isValid, errors };
}

// Show validation errors
function showValidationErrors(errors) {
    // Remove existing error messages
    const existingErrors = document.querySelectorAll('.js-validation-error');
    existingErrors.forEach(error => error.remove());
    
    if (errors.length === 0) return;
    
    // Create error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message js-validation-error';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        <ul>
            ${errors.map(error => `<li>${error}</li>`).join('')}
        </ul>
    `;
    
    // Insert at the top of the form
    const form = document.getElementById('bookingForm');
    form.insertBefore(errorDiv, form.firstChild);
    
    // Scroll to error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Initialize form
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for dhana type selection
    const dhanaTypeRadios = document.querySelectorAll('input[name="dhana_type_id"]');
    dhanaTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateTotalAmount();
            checkDateAvailability();
        });
    });
    
    // Add event listener for date change
    const dateInput = document.getElementById('booking_date');
    dateInput.addEventListener('change', checkDateAvailability);
    
    // Form submission
    const form = document.getElementById('bookingForm');
    form.addEventListener('submit', function(e) {
        const validation = validateForm();
        
        if (!validation.isValid) {
            e.preventDefault();
            showValidationErrors(validation.errors);
            return;
        }
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitButton.disabled = true;
        
        // If validation passes, form will submit normally
        // If there's an error, restore button state
        setTimeout(() => {
            if (submitButton.disabled) {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }
        }, 5000);
    });
    
    // Initialize summary if values are pre-selected
    updateTotalAmount();
    updateSelectedDate();
    
    // Auto-check availability if date and dhana type are pre-selected
    const selectedRadio = document.querySelector('input[name="dhana_type_id"]:checked');
    const dateValue = dateInput.value;
    
    if (selectedRadio && dateValue) {
        checkDateAvailability();
    }
});

// Add smooth scrolling between form sections
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit form
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const form = document.getElementById('bookingForm');
        form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
});

// Add auto-save functionality for form data
function saveFormData() {
    const formData = {
        dhana_type_id: document.querySelector('input[name="dhana_type_id"]:checked')?.value || '',
        booking_date: document.getElementById('booking_date').value,
        special_requests: document.getElementById('special_requests').value
    };
    
    localStorage.setItem('dhana_reservation_form', JSON.stringify(formData));
}

function loadFormData() {
    const savedData = localStorage.getItem('dhana_booking_form');
    if (savedData) {
        try {
            const formData = JSON.parse(savedData);
            
            // Restore dhana type selection
            if (formData.dhana_type_id) {
                const radio = document.querySelector(`input[name="dhana_type_id"][value="${formData.dhana_type_id}"]`);
                if (radio) {
                    radio.checked = true;
                    updateTotalAmount();
                }
            }
            
            // Restore date
            if (formData.booking_date) {
                document.getElementById('booking_date').value = formData.booking_date;
                updateSelectedDate();
            }
            
            // Restore special requests
            if (formData.special_requests) {
                document.getElementById('special_requests').value = formData.special_requests;
            }
            
            // Check availability if both dhana type and date are restored
            if (formData.dhana_type_id && formData.booking_date) {
                checkDateAvailability();
            }
        } catch (error) {
            console.error('Error loading saved form data:', error);
        }
    }
}

// Clear saved form data on successful submission
function clearFormData() {
    localStorage.removeItem('dhana_booking_form');
}

// Auto-save form data on input changes
document.addEventListener('DOMContentLoaded', function() {
    // Load saved data
    loadFormData();
    
    // Save data on changes
    const form = document.getElementById('bookingForm');
    form.addEventListener('input', saveFormData);
    form.addEventListener('change', saveFormData);
    
    // Clear saved data on successful form submission
    form.addEventListener('submit', function(e) {
        if (validateForm().isValid) {
            clearFormData();
        }
    });
});
