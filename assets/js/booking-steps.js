/**
 * Step-by-Step Reservation Form JavaScript
 * Version: 2024-01-01-ULTRA-SAFE - Bulletproof null checking
 */

console.log('🔧 BOOKING STEPS JS LOADED - Version 2024-01-01-FINAL');
console.log('✅ System ready with enhanced features and visibility fixes');

let currentStep = 1;
const totalSteps = 4;

// Safe function to check if element exists before accessing
function safeGetElement(id) {
    try {
        const element = document.getElementById(id);
        if (!element) {
            console.log(`Element not found: ${id}`);
            return null;
        }
        return element;
    } catch (error) {
        console.error(`Error getting element ${id}:`, error);
        return null;
    }
}

// Safe function to check if element exists by selector
function safeQuerySelector(selector) {
    try {
        const element = document.querySelector(selector);
        if (!element) {
            console.log(`Element not found: ${selector}`);
            return null;
        }
        return element;
    } catch (error) {
        console.error(`Error querying selector ${selector}:`, error);
        return null;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Booking system initializing...');

    // Give extra time for all elements to load
    setTimeout(() => {
        try {
            console.log('✅ Starting enhanced initialization...');

            // 1. Show initial step
            safeShowStep(1);

            // 2. Load dhana types
            safeLoadDhanaTypes();

            // 3. Set up navigation
            safeSetupEventListeners();

            // 4. Update progress bar
            safeUpdateProgressBar();

            // 5. Set up form validation
            try {
                document.querySelectorAll('input, select, textarea').forEach(element => {
                    if (element && typeof validateCurrentStep === 'function') {
                        element.addEventListener('change', validateCurrentStep);
                    }
                });
                console.log('✅ Enhanced booking system ready');
            } catch (error) {
                console.error('❌ Error setting up form validation:', error);
            }

            // 6. Update prices if date is pre-selected
            setTimeout(() => {
                const bookingDateInput = safeGetElement('booking_date');
                if (bookingDateInput && bookingDateInput.value) {
                    console.log('📅 Pre-selected date found, updating prices...');
                    updateDhanaPricesForDate(bookingDateInput.value);
                }
            }, 1000);

        } catch (error) {
            console.error('❌ Error during initialization:', error);
        }
    }, 800);
});

// Ultra-safe function to show a specific step
function safeShowStep(step) {
    console.log('=== SAFE SHOW STEP DEBUG ===');
    console.log('Showing step:', step);

    try {
        // Update the global currentStep variable
        currentStep = step;
        console.log(`🔄 Current step updated to: ${currentStep}`);

        // First hide all steps safely
        const allSteps = document.querySelectorAll('.form-step');
        console.log('Found form steps:', allSteps.length);

        allSteps.forEach(stepElement => {
            if (stepElement) {
                stepElement.classList.remove('active');
                stepElement.style.display = 'none';
                stepElement.style.visibility = 'hidden';
            }
        });

        // Now show the target step
        const targetStep = safeQuerySelector(`.form-step[data-step="${step}"]`);
        if (targetStep) {
            targetStep.classList.add('active');
            targetStep.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';
            console.log(`✅ Step ${step} is now active`);
        } else {
            console.error(`Target step ${step} not found`);
        }

        // Restore content visibility for each step
        if (step === 1) {
            console.log('🔧 Restoring Step 1 content (Dhana Types)');

            // First check if dhana container was removed from DOM and restore it
            if (window.removedDhanaContainer && !document.getElementById('dhana-types-container')) {
                const { element, parent, nextSibling } = window.removedDhanaContainer;
                if (parent) {
                    if (nextSibling) {
                        parent.insertBefore(element, nextSibling);
                    } else {
                        parent.appendChild(element);
                    }
                    console.log('✅ Dhana container restored to DOM');
                }
                window.removedDhanaContainer = null;
            }

            restoreStep1Content();
        } else if (step === 2) {
            console.log('🔧 Restoring Step 2 content (Date & Time)');

            // First check if step 2 container was removed from DOM and restore it
            if (window.removedStep2Container && !document.querySelector('.form-step[data-step="2"]')) {
                const { element, parent, nextSibling } = window.removedStep2Container;
                if (parent) {
                    if (nextSibling) {
                        parent.insertBefore(element, nextSibling);
                    } else {
                        parent.appendChild(element);
                    }
                    console.log('✅ Step 2 container restored to DOM');
                }
                window.removedStep2Container = null;
            }

            restoreStep2Content();
        } else if (step === 3) {
            console.log('🔧 Restoring Step 3 content (Additional Info)');
            restoreStep3Content();
        }

        // Update step indicators safely
        const stepIndicators = document.querySelectorAll('.step-indicator');
        stepIndicators.forEach(indicator => {
            if (indicator) {
                indicator.classList.remove('active', 'completed');
                const indicatorStep = parseInt(indicator.dataset.step);
                if (indicatorStep === step) {
                    indicator.classList.add('active');
                } else if (indicatorStep < step) {
                    indicator.classList.add('completed');
                }
            }
        });

        // Update navigation buttons safely
        safeUpdateNavigationButtons();

    } catch (error) {
        console.error('Error in safeShowStep:', error);
    }
}

// Function to restore Step 1 content
function restoreStep1Content() {
    console.log('🔧 Restoring Step 1 content with original styling');

    const container = document.getElementById('dhana-types-container');
    if (container) {
        // Apply the exact same styling as the original showStep() function
        // IMPORTANT: Use display: grid to match the .dhana-types-grid CSS class
        container.style.cssText = 'display: grid !important; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important; gap: 20px !important; margin-bottom: 25px !important; visibility: visible !important; opacity: 1 !important; position: static !important; left: auto !important; top: auto !important; width: auto !important; height: auto !important; margin: auto !important; padding: auto !important; z-index: auto !important; pointer-events: auto !important; max-height: none !important; max-width: none !important; min-height: auto !important; min-width: auto !important;';

        // Ensure all dhana cards are visible with original styling
        const cards = container.querySelectorAll('.dhana-type-card');
        cards.forEach((card, index) => {
            card.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 15px; border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: white;';
        });

        // Reload dhana types if none are present
        if (cards.length === 0) {
            safeLoadDhanaTypes();
        }

        // Update prices if date is selected
        const bookingDateInput = safeGetElement('booking_date');
        if (bookingDateInput && bookingDateInput.value) {
            console.log('📅 Date is selected, updating prices for Step 1...');
            updateDhanaPricesForDate(bookingDateInput.value);
        }

        console.log('✅ Step 1 dhana types restored with grid layout');
    }

    // Also restore the dhana grid class styling if it exists separately
    const dhanaGrid = document.querySelector('.dhana-types-grid');
    if (dhanaGrid && dhanaGrid !== container) {
        dhanaGrid.style.cssText = 'display: grid !important; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important; gap: 20px !important; margin-bottom: 25px !important; visibility: visible !important; opacity: 1 !important;';
        console.log('✅ Dhana grid class also restored');
    }

    console.log('✅ Step 1 restoration with proper grid layout completed');
}

// Function to restore Step 2 content
function restoreStep2Content() {
    console.log('🔧 Restoring Step 2 content with original styling');

    // Find all Step 2 elements
    const step2Container = document.querySelector('.form-step[data-step="2"]');
    const dateTimeSection = document.querySelector('.date-time-selection');
    const dateField = document.getElementById('booking_date');
    const dateDisplayField = document.getElementById('booking_date_display');
    const timeSlotField = document.getElementById('booking_time_slot');
    const calendarLink = document.querySelector('.calendar-link');
    const availabilityChecker = document.querySelector('.availability-checker');
    const stepHeader = step2Container ? step2Container.querySelector('.step-header') : null;

    // Force restore all Step 2 elements with original styling
    if (step2Container) {
        step2Container.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
    }

    // Apply original step header styling
    if (stepHeader) {
        stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 20px;';
        console.log('✅ Step 2 header styling applied');
    }

    // Apply original date-time section styling
    if (dateTimeSection) {
        dateTimeSection.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; visibility: visible !important; opacity: 1 !important; position: relative !important;';
        console.log('✅ Step 2 date-time section styling applied');
    }

    // Remove any error messages that might be taking space
    if (step2Container) {
        const errorMessages = step2Container.querySelectorAll('.error-message, .validation-error, .js-step-validation-error');
        errorMessages.forEach(msg => {
            console.log('Removing error message from step 2:', msg);
            msg.remove();
        });
    }

    // Remove any phantom/invisible elements spacing - apply original logic
    if (step2Container) {
        const allChildren = Array.from(step2Container.children);
        allChildren.forEach((child, index) => {
            if (index === 0) { // First child (step-header)
                child.style.marginTop = '0';
                child.style.paddingTop = '0';
            }
        });
    }

    // Restore all form elements
    [dateField, dateDisplayField, timeSlotField].forEach(field => {
        if (field) {
            field.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
        }
    });

    if (calendarLink) {
        calendarLink.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
    }

    if (availabilityChecker) {
        availabilityChecker.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
    }

    // Hide dhana containers (important for Step 2 layout)
    const dhanaContainer = document.getElementById('dhana-types-container');
    const dhanaGrid = document.querySelector('.dhana-types-grid');

    if (dhanaContainer) {
        dhanaContainer.style.cssText = `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            z-index: -1000 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            max-height: 0 !important;
            max-width: 0 !important;
            min-height: 0 !important;
            min-width: 0 !important;
        `;
    }

    if (dhanaGrid) {
        dhanaGrid.style.cssText = `
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            left: -9999px !important;
            z-index: -1000 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        `;
    }

    // AUTO-SELECT TIME SLOT BASED ON STEP 1 SELECTION (original logic)
    setTimeout(() => {
        const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
        const timeSlotSelect = document.getElementById('booking_time_slot');

        if (selectedDhanaType && timeSlotSelect) {
            const dhanaTypeTimeSlot = selectedDhanaType.getAttribute('data-time-slot');
            console.log('🔄 Auto-selecting time slot from step 1:', dhanaTypeTimeSlot);

            if (dhanaTypeTimeSlot) {
                // First update the time slot options based on selected dhana type
                if (typeof updateTimeSlotOptions === 'function') {
                    updateTimeSlotOptions();
                }

                // Then auto-select the time slot after a brief delay
                setTimeout(() => {
                    let timeSlotValue = dhanaTypeTimeSlot;

                    // Handle special cases for 'extra' dhana types
                    if (dhanaTypeTimeSlot === 'extra') {
                        timeSlotValue = 'whole_day';
                    }

                    timeSlotSelect.value = timeSlotValue;
                    console.log('✅ Time slot auto-selected:', timeSlotValue);

                    // Trigger change event to update availability
                    timeSlotSelect.dispatchEvent(new Event('change'));

                    // Visual feedback for auto-selection
                    timeSlotSelect.style.transition = 'all 0.3s ease';
                    timeSlotSelect.style.backgroundColor = '#e8f5e8';
                    timeSlotSelect.style.borderColor = '#28a745';

                    setTimeout(() => {
                        timeSlotSelect.style.backgroundColor = '#ffffff';
                        timeSlotSelect.style.borderColor = '#e1e5e9';
                    }, 1000);
                }, 100);
            }
        }
    }, 100);

    console.log('✅ Step 2 restoration with original styling completed');
}

// Function to restore Step 3 content
function restoreStep3Content() {
    console.log('🔧 Restoring Step 3 content with original ULTRA-AGGRESSIVE styling');

    const additionalInfo = document.querySelector('.additional-info-section');
    const toggleGroups = document.querySelectorAll('.toggle-group');
    const specialRequests = document.querySelector('.special-requests-group');
    const step3Container = document.querySelector('.form-step[data-step="3"]');

    // Restore Step 3 elements
    if (additionalInfo) {
        additionalInfo.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
    }
    toggleGroups.forEach(group => {
        if (group) {
            group.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
        }
    });
    if (specialRequests) {
        specialRequests.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important;';
    }

    // Apply ULTRA-AGGRESSIVE dhana container hiding (original Step 3 logic)
    const dhanaTypesContainer = document.getElementById('dhana-types-container');
    if (dhanaTypesContainer) {
        dhanaTypesContainer.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; top: -9999px !important; width: 0 !important; height: 0 !important; margin: 0 !important; padding: 0 !important; border: none !important; overflow: hidden !important; max-height: 0 !important; max-width: 0 !important; min-height: 0 !important; min-width: 0 !important; z-index: -9999 !important; pointer-events: none !important;';
        console.log('✅ Step 3 dhana container ultra-aggressively hidden');
    }

    // Apply Step 3 header styling (consistent with other steps)
    const stepHeader = step3Container ? step3Container.querySelector('.step-header') : null;
    if (stepHeader) {
        stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 20px;';
        console.log('✅ Step 3 header styling applied');
    }

    // Remove any phantom/invisible elements spacing
    if (step3Container) {
        const allChildren = Array.from(step3Container.children);
        allChildren.forEach((child, index) => {
            if (index === 0) { // First child (step-header)
                child.style.marginTop = '0';
                child.style.paddingTop = '0';
            }
        });

        // Remove error messages that might be taking space
        const errorMessages = step3Container.querySelectorAll('.error-message, .validation-error, .js-step-validation-error');
        errorMessages.forEach(msg => {
            console.log('Removing error message from step 3:', msg);
            msg.remove();
        });
    }

    console.log('✅ Step 3 restoration with ULTRA-AGGRESSIVE styling completed');
}

// Ultra-safe function to load dhana types
function safeLoadDhanaTypes() {
    try {
        const dhanaTypesContainer = safeGetElement('dhana-types-container');
        if (!dhanaTypesContainer) {
            console.error('dhana-types-container not found');
            return;
        }

        console.log('Loading dhana types...', typeof dhanaTypes, dhanaTypes);

        // Check if dhanaTypes is available
        if (typeof dhanaTypes === 'undefined' || !dhanaTypes || dhanaTypes.length === 0) {
            console.error('No dhana types available:', dhanaTypes);
            dhanaTypesContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>No dāna types available. Please contact the administrator.</p>
                </div>
            `;
            return;
        }

        // Generate dhana type options
        let html = '';
        dhanaTypes.forEach(type => {
            const isDisabled = type.price == 0;
            html += `
                <div class="dhana-type-card">
                    <input type="radio"
                           id="dhana_${type.id}"
                           name="dhana_type_id"
                           value="${type.id}"
                           data-price="${type.price}"
                           data-name="${safeEscapeHtml(type.name)}"
                           data-time-slot="${type.time_slot || 'whole_day'}"
                           ${isDisabled ? 'disabled' : ''}
                           required>

                    <label for="dhana_${type.id}" class="dhana-type-label ${isDisabled ? 'disabled' : ''}">
                        <div class="dhana-type-header">
                            <h4>${safeEscapeHtml(type.name)}</h4>
                            <div class="dhana-type-price" data-base-price="${type.price}">
                                ${type.price > 0 ?
                                    '<span class="price-pending"><i class="fas fa-info-circle"></i> Price will be shown after selecting date</span>' :
                                    '<span class="coming-soon">Coming Soon</span>'
                                }
                            </div>
                        </div>

                        ${type.description ?
                            `<p class="dhana-type-description">${safeEscapeHtml(type.description)}</p>` :
                            ''
                        }

                        <div class="time-slot-info">
                            <i class="fas fa-clock"></i>
                            <span>${safeGetTimeSlotLabel(type.time_slot)}</span>
                        </div>

                        ${isDisabled ?
                            `<div class="disabled-overlay">
                                <i class="fas fa-clock"></i>
                                Coming Soon
                            </div>` :
                            ''
                        }
                    </label>
                </div>
            `;
        });

        console.log('Generated HTML length:', html.length);
        dhanaTypesContainer.innerHTML = html;

        // Force container to be visible with the working fix
        dhanaTypesContainer.style.cssText = 'display: grid !important; visibility: visible !important; opacity: 1 !important;';
        console.log('✅ Dhana types container made visible');

        // Force all dhana cards to be visible too
        setTimeout(() => {
            const cards = dhanaTypesContainer.querySelectorAll('.dhana-type-card');
            console.log('✅ Dhana type cards found:', cards.length);

            cards.forEach((card, index) => {
                card.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 15px; border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: white;';
            });

            if (cards.length === 0) {
                console.log('⚠️ No dhana cards found, possible DOM issue');
            }
        }, 50);

        // Set up event listeners for radio buttons safely
        setTimeout(() => {
            safeSetupDhanaTypeListeners();
        }, 100);

    } catch (error) {
        console.error('Error in safeLoadDhanaTypes:', error);
    }
}

// Function to update dhana prices when date is selected
async function updateDhanaPricesForDate(selectedDate) {
    if (!selectedDate) {
        console.log('⚠️ No date selected, skipping price update');
        return;
    }

    console.log('🔄 Updating prices for date:', selectedDate);

    // Get all dhana type cards
    const dhanaTypeCards = document.querySelectorAll('.dhana-type-card');
    if (!dhanaTypeCards || dhanaTypeCards.length === 0) {
        console.warn('⚠️ No dhana type cards found, cannot update prices');
        return;
    }

    console.log(`📊 Found ${dhanaTypeCards.length} dhana type cards to update`);

    // Update each dhana type price
    for (const card of dhanaTypeCards) {
        const radioInput = card.querySelector('input[type="radio"]');
        const priceElement = card.querySelector('.dhana-type-price');

        if (!radioInput || !priceElement) {
            console.warn('⚠️ Card missing radio or price element, skipping');
            continue;
        }

        const dhanaTypeId = radioInput.value;
        const dhanaTypeName = radioInput.getAttribute('data-name');

        try {
            // Fetch monthly price from API
            const apiUrl = `api/get-monthly-price.php?dhana_type_id=${dhanaTypeId}&date=${selectedDate}`;
            console.log(`🌐 Fetching price from: ${apiUrl}`);

            const response = await fetch(apiUrl);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log(`📥 API response for ${dhanaTypeName}:`, data);

            if (data.success && data.price > 0) {
                // Update the price display
                const formattedPrice = `Rs. ${safeFormatPrice(data.price)}`;

                // Add tentative indicator if needed
                if (data.is_tentative) {
                    priceElement.innerHTML = `<span class="price-amount">${formattedPrice}</span> <span class="tentative-badge" title="Price may change">*</span>`;
                } else {
                    priceElement.innerHTML = `<span class="price-amount">${formattedPrice}</span>`;
                }

                // Update the data-price attribute for later use
                radioInput.setAttribute('data-price', data.price);

                // Re-enable if it was disabled
                radioInput.disabled = false;
                card.querySelector('.dhana-type-label')?.classList.remove('disabled');

                console.log(`✅ Updated price for ${dhanaTypeName}: Rs. ${data.price}${data.is_tentative ? ' (tentative)' : ''}`);
            } else if (data.success && data.price == 0) {
                // Price is 0, show "Coming Soon"
                priceElement.innerHTML = '<span class="coming-soon">Coming Soon</span>';
                radioInput.disabled = true;
                card.querySelector('.dhana-type-label')?.classList.add('disabled');
                console.log(`⚠️ ${dhanaTypeName} is coming soon (price = 0)`);
            } else {
                console.warn(`⚠️ API returned unsuccessful response for ${dhanaTypeName}:`, data);
            }
        } catch (error) {
            console.error(`❌ Error fetching price for dhana type ${dhanaTypeId} (${dhanaTypeName}):`, error);
            // Keep the existing price on error
        }
    }

    console.log('✅ Price update complete');
}

// Function to update inline price display in Step 2
function updateStep2PriceSummary(selectedDate) {
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const inlinePriceDisplay = document.getElementById('inline_price_display');

    if (!selectedDhanaType || !selectedDate || !inlinePriceDisplay) {
        if (inlinePriceDisplay) inlinePriceDisplay.style.display = 'none';
        return;
    }

    const dhanaTypeId = selectedDhanaType.value;
    const dhanaTypeName = selectedDhanaType.getAttribute('data-name');

    console.log('💰 Updating inline price display for:', dhanaTypeName, selectedDate);

    // Fetch price and update inline display
    fetch(`api/get-monthly-price.php?dhana_type_id=${dhanaTypeId}&date=${selectedDate}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Format price with tentative indicator if needed
                const formattedPrice = `Rs. ${safeFormatPrice(data.price)}`;
                const tentativeIndicator = data.is_tentative ? ' <span class="tentative-badge" title="Price may change">*</span>' : '';

                // Update inline display: | Rs. 180,000
                inlinePriceDisplay.innerHTML = `| <strong>${formattedPrice}</strong>${tentativeIndicator}`;
                inlinePriceDisplay.style.display = 'inline';

                console.log('✅ Inline price display updated:', data.price);
            } else {
                console.warn('⚠️ Failed to get price for inline display');
                inlinePriceDisplay.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('❌ Error fetching price for inline display:', error);
            inlinePriceDisplay.style.display = 'none';
        });
}

// Ultra-safe helper functions
function safeGetTimeSlotLabel(timeSlot) {
    const labels = {
        'whole_day': 'Whole Day',
        'morning': 'Morning Dāna',
        'lunch': 'Lunch Dāna',
        'evening': 'Evening Dāna',
        'extra': 'Flexible timing'
    };
    return labels[timeSlot] || 'Whole Day';
}

function safeEscapeHtml(text) {
    try {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    } catch (error) {
        console.error('Error escaping HTML:', error);
        return '';
    }
}

function safeFormatPrice(price) {
    try {
        if (price == 0) {
            return 'Coming Soon';
        }
        return new Intl.NumberFormat('en-US').format(price);
    } catch (error) {
        console.error('Error formatting price:', error);
        return price.toString();
    }
}

// Ultra-safe function to setup dhana type listeners
function safeSetupDhanaTypeListeners() {
    try {
        const dhanaTypeRadios = document.querySelectorAll('input[name="dhana_type_id"]');
        console.log('Setting up listeners for radio buttons:', dhanaTypeRadios.length);

        dhanaTypeRadios.forEach(radio => {
            if (radio) {
                radio.addEventListener('change', function() {
                    console.log('Dhana type selection changed:', this.value);

                    // Clear the inline price display when dhana type changes
                    const inlinePriceDisplay = document.getElementById('inline_price_display');
                    if (inlinePriceDisplay) {
                        inlinePriceDisplay.style.display = 'none';
                        inlinePriceDisplay.innerHTML = '';
                        console.log('🧹 Cleared inline price display');
                    }

                    // Update price if date is already selected
                    const bookingDateInput = document.getElementById('booking_date');
                    if (bookingDateInput && bookingDateInput.value) {
                        console.log('📅 Date already selected, updating price for new dhana type...');
                        updateStep2PriceSummary(bookingDateInput.value);
                    }

                    // Call the full enhanced functions
                    if (typeof updateTimeSlotOptions === 'function') {
                        updateTimeSlotOptions();
                    }

                    if (typeof updateReviewSection === 'function') {
                        updateReviewSection();
                    } else if (typeof safeUpdateReviewSection === 'function') {
                        safeUpdateReviewSection();
                    }

                    if (typeof saveFormData === 'function') {
                        saveFormData();
                    }
                });
            }
        });
    } catch (error) {
        console.error('Error setting up dhana type listeners:', error);
    }
}

// Ultra-safe function to setup event listeners
function safeSetupEventListeners() {
    try {
        // Navigation buttons with safety checks
        const nextBtn = safeGetElement('nextBtn');
        const prevBtn = safeGetElement('prevBtn');

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (typeof nextStep === 'function') {
                    nextStep();
                } else {
                    safeNextStep();
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (typeof prevStep === 'function') {
                    prevStep();
                } else {
                    safePrevStep();
                }
            });
        }

        // Review navigation buttons
        const reviewPrevBtn = safeGetElement('reviewPrevBtn');
        const reviewSubmitBtn = safeGetElement('reviewSubmitBtn');

        if (reviewPrevBtn) {
            reviewPrevBtn.addEventListener('click', safePrevStep);
        }

        if (reviewSubmitBtn) {
            reviewSubmitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Trigger the form's submit event (which will show the disclaimer modal)
                const form = safeGetElement('bookingForm');
                if (form) {
                    // Create and dispatch a submit event to trigger our disclaimer modal
                    const submitEvent = new Event('submit', {
                        bubbles: true,
                        cancelable: true
                    });
                    form.dispatchEvent(submitEvent);
                }
            });
        }

        // ✅ FIX: Add event listeners for date and time slot changes to trigger availability check
        const bookingDateInput = safeGetElement('booking_date');
        const timeSlotSelect = safeGetElement('booking_time_slot');

        if (bookingDateInput) {
            bookingDateInput.addEventListener('change', function() {
                console.log('📅 Date changed, triggering availability check and price update...');
                // Clear previous availability status to force re-check
                currentAvailabilityStatus = null;
                // Update prices based on selected date
                updateDhanaPricesForDate(this.value);
                // Update Step 2 price summary
                updateStep2PriceSummary(this.value);
                // Trigger auto-check if all fields are filled
                if (typeof autoCheckAvailability === 'function') {
                    autoCheckAvailability();
                }
            });
            console.log('✅ Date change listener added');
        }

        if (timeSlotSelect) {
            timeSlotSelect.addEventListener('change', function() {
                console.log('⏰ Time slot changed, triggering availability check...');
                // Clear previous availability status to force re-check
                currentAvailabilityStatus = null;
                // Trigger auto-check if all fields are filled
                if (typeof autoCheckAvailability === 'function') {
                    autoCheckAvailability();
                }
            });
            console.log('✅ Time slot change listener added');
        }

        console.log('✅ Event listeners setup completed');

    } catch (error) {
        console.error('Error setting up event listeners:', error);
    }
}

// Ultra-safe navigation functions
function safeNextStep() {
    try {
        if (currentStep < totalSteps) {
            currentStep++;
            safeShowStep(currentStep);
            safeUpdateProgressBar();
        }
    } catch (error) {
        console.error('Error in safeNextStep:', error);
    }
}

function safePrevStep() {
    try {
        console.log(`🔄 Going back from step ${currentStep}`);
        if (currentStep > 1) {
            currentStep--;
            console.log(`✅ Now at step ${currentStep}`);

            // Update current step and navigation
            safeShowStep(currentStep);
            safeUpdateProgressBar();

            // Force update navigation buttons after a small delay
            setTimeout(() => {
                safeUpdateNavigationButtons();
            }, 50);
        } else {
            console.log('⚠️ Cannot go back - already at step 1');
        }
    } catch (error) {
        console.error('Error in safePrevStep:', error);
    }
}

// Ultra-safe function to update progress bar
function safeUpdateProgressBar() {
    try {
        const progressFill = safeGetElement('progressFill');
        if (progressFill) {
            const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = progressPercentage + '%';
        }
    } catch (error) {
        console.error('Error updating progress bar:', error);
    }
}

// Ultra-safe function to update navigation buttons
function safeUpdateNavigationButtons() {
    try {
        const prevBtn = safeGetElement('prevBtn');
        const nextBtn = safeGetElement('nextBtn');
        const submitBtn = safeGetElement('submitBtn');
        const reviewNavigation = safeQuerySelector('.review-navigation');
        const formNavigation = safeQuerySelector('.form-navigation');

        console.log(`🔧 Updating navigation for step ${currentStep}`);
        console.log('Elements found:', {
            prevBtn: !!prevBtn,
            nextBtn: !!nextBtn,
            submitBtn: !!submitBtn,
            reviewNavigation: !!reviewNavigation,
            formNavigation: !!formNavigation
        });

        if (currentStep === totalSteps) {
            // Last step (Step 4) - show review navigation, hide form navigation
            console.log('📋 Showing review navigation for step 4');

            if (formNavigation) {
                formNavigation.style.display = 'none';
            }
            if (reviewNavigation) {
                reviewNavigation.style.display = 'flex';
            }

            // Ensure form navigation buttons are hidden
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';

        } else {
            // Other steps (1, 2, 3) - show form navigation, hide review navigation
            console.log(`📝 Showing form navigation for step ${currentStep}`);

            if (reviewNavigation) {
                reviewNavigation.style.display = 'none';
            }
            if (formNavigation) {
                formNavigation.style.display = 'flex';
            }

            // Update form navigation button visibility
            if (prevBtn) {
                prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = currentStep < totalSteps ? 'inline-flex' : 'none';
            }
            if (submitBtn) {
                submitBtn.style.display = 'none'; // Never show this in form navigation
            }
        }

        // Debug output
        setTimeout(() => {
            console.log('Final button visibility:', {
                step: currentStep,
                prevBtn: prevBtn ? prevBtn.style.display : 'not found',
                nextBtn: nextBtn ? nextBtn.style.display : 'not found',
                reviewNav: reviewNavigation ? reviewNavigation.style.display : 'not found',
                formNav: formNavigation ? formNavigation.style.display : 'not found'
            });
        }, 100);

    } catch (error) {
        console.error('Error updating navigation buttons:', error);
    }
}

function showStep(step) {
    console.log('Showing step:', step);

    // Clear validation errors when showing a step
    hideValidationErrors();

    // Force hide all steps first
    const allSteps = document.querySelectorAll('.form-step');

    allSteps.forEach((stepElement, index) => {
        stepElement.classList.remove('active');
        stepElement.style.display = 'none !important';
        stepElement.style.visibility = 'hidden';
        stepElement.style.opacity = '0';
        stepElement.setAttribute('aria-hidden', 'true');
    });

    // Small delay to ensure DOM updates, then show target step
    setTimeout(() => {
        const currentStepElement = document.querySelector(`.form-step[data-step="${step}"]`);

        if (currentStepElement) {
            // Completely reset the target step and show it
            currentStepElement.className = 'form-step active';
            currentStepElement.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important; left: auto !important;';
            currentStepElement.removeAttribute('aria-hidden');
            currentStepElement.removeAttribute('hidden');

            // Special handling for step 1 - ensure dhana types are visible and restore if removed
            if (step === 1) {
                // Restore dhana container if it was removed from DOM
                if (window.removedDhanaContainer && !document.getElementById('dhana-types-container')) {
                    const { element, parent, nextSibling } = window.removedDhanaContainer;
                    if (parent) {
                        if (nextSibling) {
                            parent.insertBefore(element, nextSibling);
                        } else {
                            parent.appendChild(element);
                        }
                        console.log('✅ Dhana container restored to DOM');
                    }
                    // Clear the stored reference
                    window.removedDhanaContainer = null;
                }

                const container = document.getElementById('dhana-types-container');
                if (container) {
                    container.style.cssText = 'display: grid !important; visibility: visible !important; opacity: 1 !important; position: static !important; left: auto !important; top: auto !important; width: auto !important; height: auto !important; margin: auto !important; padding: auto !important; z-index: auto !important; pointer-events: auto !important; max-height: none !important; max-width: none !important; min-height: auto !important; min-width: auto !important;';

                    // Ensure all dhana cards are visible
                    const cards = container.querySelectorAll('.dhana-type-card');

                    cards.forEach((card, index) => {
                        card.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 15px; border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: white;';
                    });

                    if (cards.length === 0) {
                        safeLoadDhanaTypes();
                    }
                } else {
                    console.log('⚠️ Dhana types container not found, trying to reload...');
                    safeLoadDhanaTypes();
                }
            } else {
                // NOT STEP 1 - COMPLETELY HIDE DHANA TYPES CONTAINER TO PREVENT SPACE ISSUES
                const dhanaContainer = document.getElementById('dhana-types-container');
                const dhanaGrid = document.querySelector('.dhana-types-grid');

                if (dhanaContainer) {
                    dhanaContainer.style.cssText = `
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        position: absolute !important;
                        left: -9999px !important;
                        top: -9999px !important;
                        z-index: -1000 !important;
                        pointer-events: none !important;
                        height: 0 !important;
                        width: 0 !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        border: none !important;
                        max-height: 0 !important;
                        max-width: 0 !important;
                        min-height: 0 !important;
                        min-width: 0 !important;
                    `;
                    console.log('✅ Dhana container completely hidden for step', step);
                }

                if (dhanaGrid) {
                    dhanaGrid.style.cssText = `
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        position: absolute !important;
                        left: -9999px !important;
                        z-index: -1000 !important;
                        pointer-events: none !important;
                        height: 0 !important;
                        width: 0 !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    `;
                    console.log('✅ Dhana grid completely hidden for step', step);
                }
            }

            // Special handling for step 2 - remove empty space AND auto-select time slot AND restore if removed
            if (step === 2) {
                console.log('🔧 Fixing step 2 empty space and auto-selecting time slot...');

                // Restore step 2 container if it was removed from DOM
                if (window.removedStep2Container && !document.querySelector('.form-step[data-step="2"]')) {
                    const { element, parent, nextSibling } = window.removedStep2Container;
                    if (parent) {
                        if (nextSibling) {
                            parent.insertBefore(element, nextSibling);
                        } else {
                            parent.appendChild(element);
                        }
                        console.log('✅ Step 2 container restored to DOM');
                    }
                    // Clear the stored reference
                    window.removedStep2Container = null;
                }

                // Remove any error messages that might be taking space
                const errorMessages = currentStepElement.querySelectorAll('.error-message, .validation-error, .js-step-validation-error');
                errorMessages.forEach(msg => {
                    console.log('Removing error message from step:', msg);
                    msg.remove();
                });

                // Force remove top margins and padding from step header
                const stepHeader = currentStepElement.querySelector('.step-header');
                if (stepHeader) {
                    stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 20px;';
                    console.log('✅ Step header spacing fixed');
                }

                // Force remove any spacing from date-time-selection
                const dateTimeSection = currentStepElement.querySelector('.date-time-selection');
                if (dateTimeSection) {
                    dateTimeSection.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;';
                    console.log('✅ Date-time section spacing fixed');
                }

                // Remove any phantom/invisible elements that might cause spacing
                const allChildren = Array.from(currentStepElement.children);
                allChildren.forEach((child, index) => {
                    if (index === 0) { // First child (step-header)
                        child.style.marginTop = '0';
                        child.style.paddingTop = '0';
                    }
                });

                // AUTO-SELECT TIME SLOT BASED ON STEP 1 SELECTION
                setTimeout(() => {
                    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
                    const timeSlotSelect = document.getElementById('booking_time_slot');

                    if (selectedDhanaType && timeSlotSelect) {
                        const dhanaTypeTimeSlot = selectedDhanaType.getAttribute('data-time-slot');
                        console.log('🔄 Auto-selecting time slot from step 1:', dhanaTypeTimeSlot);

                        if (dhanaTypeTimeSlot) {
                            // First update the time slot options based on selected dhana type
                            updateTimeSlotOptions();

                            // Then auto-select the time slot after a brief delay to ensure options are loaded
                            setTimeout(() => {
                                // Map dhana type time slots to booking time slots
                                let timeSlotValue = dhanaTypeTimeSlot;

                                // Handle special cases for 'extra' dhana types
                                if (dhanaTypeTimeSlot === 'extra') {
                                    // For extra items, default to whole_day
                                    timeSlotValue = 'whole_day';
                                }

                                timeSlotSelect.value = timeSlotValue;
                                console.log('✅ Time slot auto-selected:', timeSlotValue);

                                // Trigger change event to update availability
                                timeSlotSelect.dispatchEvent(new Event('change'));

                                // Visual feedback for auto-selection
                                timeSlotSelect.style.transition = 'all 0.3s ease';
                                timeSlotSelect.style.backgroundColor = '#e8f5e8';
                                timeSlotSelect.style.borderColor = '#28a745';

                                setTimeout(() => {
                                    timeSlotSelect.style.backgroundColor = '#ffffff';
                                    timeSlotSelect.style.borderColor = '#e1e5e9';
                                }, 1000);

                            }, 100);
                        }
                    }
                }, 100);

                console.log('✅ Step 2 empty space fix and auto-selection completed');
            }

            // Special handling for step 3 - remove empty space ULTRA-AGGRESSIVELY
            if (step === 3) {
                console.log('🔧 Fixing step 3 empty space ULTRA-AGGRESSIVELY...');

                // ULTRA-AGGRESSIVE: Completely remove dhana-types-container from layout flow
                const dhanaTypesContainer = document.getElementById('dhana-types-container');
                if (dhanaTypesContainer) {
                    // First, completely hide it from layout
                    dhanaTypesContainer.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; top: -9999px !important; width: 0 !important; height: 0 !important; margin: 0 !important; padding: 0 !important; border: none !important; overflow: hidden !important; max-height: 0 !important; max-width: 0 !important; min-height: 0 !important; min-width: 0 !important; z-index: -9999 !important; pointer-events: none !important;';

                    // Then, temporarily remove it from DOM completely
                    const parent = dhanaTypesContainer.parentNode;
                    const nextSibling = dhanaTypesContainer.nextSibling;
                    if (parent) {
                        parent.removeChild(dhanaTypesContainer);
                        // Store reference to restore later if needed
                        window.removedDhanaContainer = {
                            element: dhanaTypesContainer,
                            parent: parent,
                            nextSibling: nextSibling
                        };
                        console.log('✅ Step 3 dhana container completely removed from DOM');
                    }
                }

                // ULTRA-AGGRESSIVE: Also completely remove step 2 from DOM to prevent phantom spacing
                const step2Container = document.querySelector('.form-step[data-step="2"]');
                if (step2Container) {
                    // First, completely hide it from layout
                    step2Container.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; top: -9999px !important; width: 0 !important; height: 0 !important; margin: 0 !important; padding: 0 !important; border: none !important; overflow: hidden !important; max-height: 0 !important; max-width: 0 !important; min-height: 0 !important; min-width: 0 !important; z-index: -9999 !important; pointer-events: none !important;';

                    // Then, temporarily remove it from DOM completely
                    const parent = step2Container.parentNode;
                    const nextSibling = step2Container.nextSibling;
                    if (parent) {
                        parent.removeChild(step2Container);
                        // Store reference to restore later if needed
                        window.removedStep2Container = {
                            element: step2Container,
                            parent: parent,
                            nextSibling: nextSibling
                        };
                        console.log('✅ Step 3 step2 container completely removed from DOM');
                    }
                }

                // Also hide dhana-types-grid if it exists
                const dhanaTypesGrid = document.querySelector('.dhana-types-grid');
                if (dhanaTypesGrid) {
                    dhanaTypesGrid.style.cssText = 'display: none !important; visibility: hidden !important; opacity: 0 !important; position: absolute !important; left: -9999px !important; top: -9999px !important; width: 0 !important; height: 0 !important; margin: 0 !important; padding: 0 !important; z-index: -9999 !important;';
                    console.log('✅ Step 3 dhana grid completely hidden');
                }

                // Remove any error messages that might be taking space
                const errorMessages = currentStepElement.querySelectorAll('.error-message, .validation-error, .js-step-validation-error');
                errorMessages.forEach(msg => {
                    console.log('Removing error message from step 3:', msg);
                    msg.remove();
                });

                // ULTRA-AGGRESSIVE: Force remove ALL spacing from the entire step
                currentStepElement.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; margin-bottom: 0 !important; padding-bottom: 0 !important;';

                // Force remove top margins and padding from step header
                const stepHeader = currentStepElement.querySelector('.step-header');
                if (stepHeader) {
                    stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 15px !important;';
                    console.log('✅ Step 3 header spacing fixed');
                }

                // ULTRA-AGGRESSIVE: Force remove any spacing from additional-info-section
                const additionalInfoSection = currentStepElement.querySelector('.additional-info-section');
                if (additionalInfoSection) {
                    additionalInfoSection.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; margin-bottom: 0 !important; padding-bottom: 0 !important;';

                    // Also fix all child elements within additional-info-section
                    const toggleGroups = additionalInfoSection.querySelectorAll('.toggle-group');
                    toggleGroups.forEach((toggleGroup, index) => {
                        if (index === 0) {
                            // First toggle group gets no top margin/padding
                            toggleGroup.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important;';
                        }
                    });

                    console.log('✅ Step 3 additional info section spacing fixed');
                }

                // ULTRA-AGGRESSIVE: Remove spacing from any other potential space-taking elements
                const allDivs = currentStepElement.querySelectorAll('div');
                allDivs.forEach(div => {
                    if (div.classList.contains('step-header') || div.classList.contains('additional-info-section')) {
                        return; // Already handled above
                    }
                    // Remove excessive top margins and padding from other divs
                    const computedStyle = window.getComputedStyle(div);
                    if (parseInt(computedStyle.marginTop) > 20 || parseInt(computedStyle.paddingTop) > 20) {
                        div.style.marginTop = '0px';
                        div.style.paddingTop = '0px';
                    }
                });

                // ULTRA-AGGRESSIVE: Force the entire step form to have no top spacing
                const stepForm = document.querySelector('.step-form');
                if (stepForm) {
                    stepForm.style.paddingTop = '0px !important';
                    stepForm.style.marginTop = '0px !important';
                }

                console.log('✅ Step 3 ULTRA-AGGRESSIVE empty space fix completed');
            }

            // Special handling for step 4 - remove empty space
            if (step === 4) {
                console.log('🔧 Fixing step 4 empty space...');

                // Remove any error messages that might be taking space
                const errorMessages = currentStepElement.querySelectorAll('.error-message, .validation-error, .js-step-validation-error');
                errorMessages.forEach(msg => {
                    console.log('Removing error message from step 4:', msg);
                    msg.remove();
                });

                // Force remove top margins and padding from step header
                const stepHeader = currentStepElement.querySelector('.step-header');
                if (stepHeader) {
                    stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 20px;';
                    console.log('✅ Step 4 header spacing fixed');
                }

                // Force remove any spacing from review sections
                const reviewSections = currentStepElement.querySelectorAll('.review-section, .booking-summary');
                reviewSections.forEach(section => {
                    if (section) {
                        section.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important;';
                    }
                });

                console.log('✅ Step 4 empty space fix completed');
            }
        } else {
            console.error('Step element not found for step:', step);
        }

        // Update step indicators
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            indicator.classList.remove('active', 'completed');
            if (index + 1 === step) {
                indicator.classList.add('active');
            } else if (index + 1 < step) {
                indicator.classList.add('completed');
            }
        });

        // Update navigation buttons
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const reviewNavigation = document.querySelector('.review-navigation');
        const formNavigation = document.querySelector('.form-navigation');

        if (step === totalSteps) {
            // Show review navigation buttons, hide form navigation
            if (reviewNavigation) reviewNavigation.style.display = 'flex';
            if (formNavigation) formNavigation.style.display = 'none';
        } else {
            // Show form navigation buttons, hide review navigation
            if (reviewNavigation) reviewNavigation.style.display = 'none';
            if (formNavigation) {
                formNavigation.style.display = 'flex';
                formNavigation.style.visibility = 'visible';
                formNavigation.style.opacity = '1';
            }

            if (prevBtn) {
                prevBtn.style.display = step === 1 ? 'none' : 'inline-flex';
                prevBtn.style.visibility = step === 1 ? 'hidden' : 'visible';
            }
            if (nextBtn) {
                nextBtn.style.display = 'inline-flex';
                nextBtn.style.visibility = 'visible';
            }
            if (submitBtn) submitBtn.style.display = 'none';
        }

        console.log('Button visibility updated for step', step);
        console.log('Form navigation display:', formNavigation ? formNavigation.style.display : 'not found');
        console.log('Previous button display:', prevBtn ? prevBtn.style.display : 'not found');
        console.log('Next button display:', nextBtn ? nextBtn.style.display : 'not found');

        // Update progress bar
        updateProgressBar();

        // Scroll to top
        const bookingContainer = document.querySelector('.booking-container');
        if (bookingContainer) {
            bookingContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }, 10);
}

async function nextStep() {
    console.log('=== NEXT STEP DEBUG ===');
    console.log('Current step:', currentStep);
    console.log('Total steps:', totalSteps);

    // Clear any existing validation errors before proceeding
    hideValidationErrors();

    // Special handling for step 2 to ensure availability is checked
    if (currentStep === 2) {
        const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
        const bookingDateElement = document.getElementById('booking_date');
        const timeSlotElement = document.getElementById('booking_time_slot');

        const bookingDate = bookingDateElement ? bookingDateElement.value : '';
        const timeSlot = timeSlotElement ? timeSlotElement.value : '';

        // If all fields are filled but availability hasn't been checked, check it now
        if (selectedDhanaType && bookingDate && timeSlot && currentAvailabilityStatus === null) {
            console.log('Checking availability before proceeding...');
            if (typeof checkAvailability === 'function') {
                const availabilityResult = await checkAvailability();
                if (!availabilityResult || !availabilityResult.available) {
                    console.log('Cannot proceed - slot not available');
                    return; // Don't proceed if not available
                }
            } else {
                console.warn('checkAvailability function not found');
            }
        }
    }

    const isValid = validateCurrentStep();
    console.log('Step validation result:', isValid);

    if (isValid && currentStep < totalSteps) {
        console.log('Moving to next step...');
        currentStep++;
        safeShowStep(currentStep);

        // Update review section when reaching final step
        if (currentStep === totalSteps) {
            updateReviewSection();
        }
    } else {
        console.log('Cannot move to next step. Valid:', isValid, 'Current step:', currentStep, 'Total steps:', totalSteps);
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        safeShowStep(currentStep);
    }
}

function updateProgressBar() {
    const progressFill = document.getElementById('progressFill');
    const progressPercentage = (currentStep / totalSteps) * 100;
    progressFill.style.width = progressPercentage + '%';
}

function validateCurrentStep() {
    let isValid = true;
    const errors = [];

    switch (currentStep) {
        case 1:
            // Validate dhana type selection
            console.log('=== STEP 1 VALIDATION ===');
            const allDhanaRadios = document.querySelectorAll('input[name="dhana_type_id"]');
            const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');

            console.log('All dhana type radios found:', allDhanaRadios.length);
            console.log('Selected dhana type:', selectedDhanaType);

            allDhanaRadios.forEach((radio, i) => {
                console.log(`Radio ${i}:`, {
                    value: radio.value,
                    checked: radio.checked,
                    name: radio.name,
                    id: radio.id
                });
            });

            if (!selectedDhanaType) {
                console.log('❌ No dāna type selected');
                errors.push('Please select a dāna type');
                isValid = false;
            } else {
                console.log('✅ Dhana type selected:', selectedDhanaType.value);
            }
            break;

        case 2:
            console.log('=== STEP 2 VALIDATION ===');
            // Validate date and time slot
            const bookingDateElement = document.getElementById('booking_date');
            const timeSlotElement = document.getElementById('booking_time_slot');

            const bookingDate = bookingDateElement ? bookingDateElement.value : '';
            const timeSlot = timeSlotElement ? timeSlotElement.value : '';

            console.log('Date element:', bookingDateElement);
            console.log('Time slot element:', timeSlotElement);
            console.log('Date value:', bookingDate);
            console.log('Time slot value:', timeSlot);
            console.log('Current availability status:', currentAvailabilityStatus);

            if (!bookingDate) {
                console.log('❌ No reservation date selected');
                errors.push('Please select a reservation date');
                isValid = false;
            }

            if (!timeSlot) {
                console.log('❌ No time slot selected');
                errors.push('Please select a time slot');
                isValid = false;
            }

            // Check if date is not in the past
            if (bookingDate && bookingDate < new Date().toISOString().split('T')[0]) {
                console.log('❌ Date is in the past');
                errors.push('Reservation date cannot be in the past');
                isValid = false;
            }

            // Simplified availability check - only check if both date and time are selected
            if (bookingDate && timeSlot) {
                console.log('✅ Both date and time slot selected');

                // If availability has been checked and it's not available
                if (currentAvailabilityStatus !== null && !currentAvailabilityStatus.available) {
                    console.log('❌ Slot not available:', currentAvailabilityStatus.reason);
                    errors.push(currentAvailabilityStatus.reason || 'Selected slot is not available. Please choose a different date or time.');
                    isValid = false;
                } else {
                    console.log('✅ Step 2 validation passed');
                    // If availability hasn't been checked, trigger it but don't block proceeding
                    if (currentAvailabilityStatus === null && typeof autoCheckAvailability === 'function') {
                        console.log('🔄 Triggering availability check in background...');
                        autoCheckAvailability();
                    }
                }
            }
            break;

        case 3:
            // Travel support step - no validation required
            break;

        case 4:
            // Additional info step - no validation required
            break;

        case 5:
            // Final review - validate all previous steps
            isValid = validateAllSteps();
            break;
    }

    if (!isValid) {
        showValidationErrors(errors);
    } else {
        hideValidationErrors();
    }

    return isValid;
}

function validateAllSteps() {
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const bookingDate = document.getElementById('booking_date').value;
    const timeSlot = document.getElementById('booking_time_slot').value;

    return selectedDhanaType && bookingDate && timeSlot;
}

function showValidationErrors(errors) {
    // Remove existing error messages
    const existingErrors = document.querySelectorAll('.js-step-validation-error');
    existingErrors.forEach(error => error.remove());

    if (errors.length === 0) return;

    // Create error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message js-step-validation-error';
    errorDiv.innerHTML = `
        <i class="fas fa-exclamation-circle"></i>
        <ul>
            ${errors.map(error => `<li>${error}</li>`).join('')}
        </ul>
    `;

    // Insert into document body (not current step) to avoid layout impact
    document.body.appendChild(errorDiv);

    console.log('❌ Validation errors shown:', errors);

    // Auto-remove after 5 seconds to avoid cluttering
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.style.animation = 'slideInFromRight 0.3s ease reverse';
            setTimeout(() => errorDiv.remove(), 300);
        }
    }, 5000);
}

function hideValidationErrors() {
    const existingErrors = document.querySelectorAll('.js-step-validation-error');
    existingErrors.forEach(error => {
        if (error.parentNode) {
            error.style.animation = 'slideInFromRight 0.3s ease reverse';
            setTimeout(() => error.remove(), 300);
        }
    });
    console.log('✅ Validation errors cleared');
}

function updateTimeSlotOptions() {
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const timeSlotSelect = document.getElementById('booking_time_slot');

    if (!selectedDhanaType) return;

    const dhanaTypeData = dhanaTypes.find(type => type.id == selectedDhanaType.value);
    if (!dhanaTypeData) return;

    // Clear existing options except the first one
    timeSlotSelect.innerHTML = '<option value="">Select time slot</option>';

    // Add appropriate options based on dhana type with enhanced descriptions
    const timeSlotOptions = {
        'morning': {
            value: 'morning',
            label: 'Morning Dāna',
            description: '6:00 AM - 12:00 PM'
        },
        'lunch': {
            value: 'lunch',
            label: 'Lunch Dāna',
            description: '11:00 AM - 2:00 PM'
        },
        'whole_day': {
            value: 'whole_day',
            label: 'Whole Day',
            description: 'Full day reservation'
        }
    };

    // Add appropriate options based on dhana type
    switch (dhanaTypeData.time_slot) {
        case 'morning':
            const morningOption = timeSlotOptions.morning;
            timeSlotSelect.innerHTML += `<option value="${morningOption.value}">${morningOption.label}</option>`;
            break;
        case 'lunch':
            const lunchOption = timeSlotOptions.lunch;
            timeSlotSelect.innerHTML += `<option value="${lunchOption.value}">${lunchOption.label}</option>`;
            break;
        case 'whole_day':
            const wholeDayOption = timeSlotOptions.whole_day;
            timeSlotSelect.innerHTML += `<option value="${wholeDayOption.value}">${wholeDayOption.label}</option>`;
            break;
        default:
            // Add all options for flexible dhana types
            Object.values(timeSlotOptions).forEach(option => {
                timeSlotSelect.innerHTML += `<option value="${option.value}">${option.label}</option>`;
            });
    }

    // AUTO-SELECT THE APPROPRIATE TIME SLOT BASED ON DHANA TYPE
    setTimeout(() => {
        let autoSelectValue = dhanaTypeData.time_slot;

        // Handle special cases
        if (dhanaTypeData.time_slot === 'extra') {
            autoSelectValue = 'whole_day'; // Default for extra items
        }

        // Check if the option exists before selecting
        const optionExists = Array.from(timeSlotSelect.options).some(option => option.value === autoSelectValue);

        if (optionExists) {
            timeSlotSelect.value = autoSelectValue;
            console.log('🎯 Time slot auto-selected in updateTimeSlotOptions:', autoSelectValue);

            // Trigger change event for availability checking
            timeSlotSelect.dispatchEvent(new Event('change'));

            // Visual feedback
            timeSlotSelect.style.transition = 'all 0.3s ease';
            timeSlotSelect.style.backgroundColor = '#e8f5e8';
            timeSlotSelect.style.borderColor = '#28a745';

            setTimeout(() => {
                timeSlotSelect.style.backgroundColor = '#ffffff';
                timeSlotSelect.style.borderColor = '#e1e5e9';
            }, 1000);
        }
    }, 50);

    // Add a subtle animation to indicate the options have been updated
    timeSlotSelect.style.transition = 'all 0.3s ease';
    timeSlotSelect.style.backgroundColor = '#f0f8ff';
    setTimeout(() => {
        timeSlotSelect.style.backgroundColor = '#ffffff';
    }, 500);
}



// Global variable to track availability status
let currentAvailabilityStatus = null;

async function checkAvailability() {
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const bookingDate = document.getElementById('booking_date').value;
    const timeSlot = document.getElementById('booking_time_slot').value;
    const resultDiv = document.getElementById('availabilityResult');

    if (!selectedDhanaType || !bookingDate || !timeSlot) {
        resultDiv.innerHTML = `
            <i class="fas fa-info-circle"></i>
            Please select dāna type, date, and time slot first.
        `;
        resultDiv.className = 'availability-result unavailable';
        currentAvailabilityStatus = null;
        return null;
    }

    try {
        // Show loading
        resultDiv.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i>
            Checking availability...
        `;
        resultDiv.className = 'availability-result';
        resultDiv.style.display = 'block';

        // Make AJAX request
        const response = await fetch('api/check-availability-slots.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                date: bookingDate,
                dhana_type_id: selectedDhanaType.value,
                time_slot: timeSlot
            })
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);

        const responseText = await response.text();
        console.log('Raw response:', responseText);

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON response from server');
        }

        currentAvailabilityStatus = result;

        if (result.available) {
            resultDiv.innerHTML = `
                <i class="fas fa-check-circle"></i>
                Great! This slot is available for reservation.
            `;
            resultDiv.className = 'availability-result available';
        } else {
            const reason = result.reason || 'Sorry, this slot is already booked. Please choose a different date or time.';
            resultDiv.innerHTML = `
                <i class="fas fa-times-circle"></i>
                ` + reason;
            resultDiv.className = 'availability-result unavailable';
        }

        return result;
    } catch (error) {
        console.error('Error checking availability:', error);
        resultDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Unable to check availability. Please try again.
        `;
        resultDiv.className = 'availability-result unavailable';
        currentAvailabilityStatus = { available: false, reason: 'Network error' };
        return { available: false, reason: 'Network error' };
    }
}

// Auto-check availability when both date and time slot are selected
async function autoCheckAvailability() {
    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const bookingDate = document.getElementById('booking_date').value;
    const timeSlot = document.getElementById('booking_time_slot').value;
    const resultDiv = document.getElementById('availabilityResult');

    // Only auto-check if all required fields are filled
    if (selectedDhanaType && bookingDate && timeSlot) {
        // Show immediate feedback that we're checking
        resultDiv.innerHTML = `
            <i class="fas fa-spinner fa-spin"></i>
            Checking availability for your selected date and time...
        `;
        resultDiv.className = 'availability-result';
        resultDiv.style.display = 'block';
        currentAvailabilityStatus = null;

        // Small delay to avoid too many rapid requests
        setTimeout(async () => {
            await checkAvailability();
        }, 500);
    } else {
        // Clear results if not all fields are filled
        resultDiv.style.display = 'none';
        currentAvailabilityStatus = null;
    }
}

function updateReviewSection() {
    try {
        console.log('updateReviewSection called');

        // Update dhana type with safety checks
        const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
        const bookingDateElement = document.getElementById('booking_date');
        const bookingDate = bookingDateElement ? bookingDateElement.value : '';

        if (selectedDhanaType && typeof dhanaTypes !== 'undefined' && dhanaTypes && bookingDate) {
            const dhanaTypeData = dhanaTypes.find(type => type.id == selectedDhanaType.value);
            const reviewDhanaType = document.getElementById('review-dhana-type');
            const reviewTotalAmount = document.getElementById('review-total-amount');
            const tentativeDisclaimer = document.getElementById('tentative-price-disclaimer');

            if (reviewDhanaType && dhanaTypeData) {
                reviewDhanaType.textContent = dhanaTypeData.name || 'Unknown';
            }

            // Fetch dynamic price from API
            if (reviewTotalAmount && dhanaTypeData) {
                fetch(`api/get-monthly-price.php?dhana_type_id=${selectedDhanaType.value}&date=${bookingDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const formattedAmount = `Rs. ${formatPrice(data.price) || '0'}`;
                            reviewTotalAmount.textContent = formattedAmount;

                            // Show/hide tentative price disclaimer
                            if (tentativeDisclaimer) {
                                if (data.is_tentative) {
                                    tentativeDisclaimer.style.display = 'flex';
                                } else {
                                    tentativeDisclaimer.style.display = 'none';
                                }
                            }
                        } else {
                            // Fallback to base price
                            const formattedAmount = `Rs. ${formatPrice(dhanaTypeData.price) || '0'}`;
                            reviewTotalAmount.textContent = formattedAmount;
                            if (tentativeDisclaimer) {
                                tentativeDisclaimer.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching dynamic price:', error);
                        // Fallback to base price
                        const formattedAmount = `Rs. ${formatPrice(dhanaTypeData.price) || '0'}`;
                        reviewTotalAmount.textContent = formattedAmount;
                        if (tentativeDisclaimer) {
                            tentativeDisclaimer.style.display = 'none';
                        }
                    });
            }
        }

        // Update date with safety checks and clean formatting
        // bookingDateElement already declared above, reuse it
        if (bookingDate) {
            const reviewDate = document.getElementById('review-date');
            if (reviewDate) {
                try {
                    const date = new Date(bookingDate + 'T00:00:00');
                    const formattedDate = date.toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    reviewDate.textContent = formattedDate;
                } catch (dateError) {
                    console.warn('Date parsing error:', dateError);
                    reviewDate.textContent = bookingDate;
                }
            }
        }

        // Update time slot with clean formatting
        const timeSlotElement = document.getElementById('booking_time_slot');
        const timeSlot = timeSlotElement ? timeSlotElement.value : '';
        if (timeSlot) {
            const reviewTimeSlot = document.getElementById('review-time-slot');
            if (reviewTimeSlot) {
                const timeSlotLabels = {
                    'morning': 'Morning Dāna',
                    'lunch': 'Lunch Dāna',
                    'whole_day': 'Whole Day'
                };
                reviewTimeSlot.textContent = timeSlotLabels[timeSlot] ||
                    timeSlot.charAt(0).toUpperCase() + timeSlot.slice(1).replace('_', ' ');
            }
        }

        // Update checkboxes with simple Yes/No
        const reviewTravelSupport = document.getElementById('review-travel-support');
        if (reviewTravelSupport) {
            const travelSupportElement = document.getElementById('travel_support');
            const isChecked = travelSupportElement ? travelSupportElement.checked : false;
            reviewTravelSupport.textContent = isChecked ? 'Yes' : 'No';
        }

        const reviewAnnualEvent = document.getElementById('review-annual-event');
        if (reviewAnnualEvent) {
            const annualEventElement = document.getElementById('is_annual_event');
            const isChecked = annualEventElement ? annualEventElement.checked : false;
            reviewAnnualEvent.textContent = isChecked ? 'Yes' : 'No';
        }

        console.log('updateReviewSection completed successfully');
    } catch (error) {
        console.error('Error in updateReviewSection:', error);
        console.error('Error stack:', error.stack);
    }
}function formatPrice(price) {
    if (price == 0) {
        return 'Coming Soon';
    }
    return new Intl.NumberFormat('en-US').format(price);
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        if (currentStep < totalSteps) {
            nextStep();
        }
    }

    if (e.key === 'Escape') {
        if (currentStep > 1) {
            prevStep();
        }
    }
});

// Auto-save form data
function saveFormData() {
    try {
        console.log('saveFormData called');

        const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
        const bookingDateElement = document.getElementById('booking_date');
        const bookingTimeSlotElement = document.getElementById('booking_time_slot');
        const travelSupportElement = document.getElementById('travel_support');
        const annualEventElement = document.getElementById('is_annual_event');

        const formData = {
            dhana_type_id: selectedDhanaType ? selectedDhanaType.value : '',
            booking_date: bookingDateElement ? bookingDateElement.value : '',
            booking_time_slot: bookingTimeSlotElement ? bookingTimeSlotElement.value : '',
            travel_support: travelSupportElement ? travelSupportElement.checked : false,
            is_annual_event: annualEventElement ? annualEventElement.checked : false,
            current_step: currentStep
        };

        localStorage.setItem('dhana_booking_steps', JSON.stringify(formData));
        console.log('Form data saved successfully');
    } catch (error) {
        console.error('Error in saveFormData:', error);
    }
}

function loadFormData() {
    console.log('loadFormData called - SAFE VERSION');

    // Temporarily disable loadFormData to prevent errors
    return;

    const savedData = localStorage.getItem('dhana_booking_steps');
    if (!savedData) {
        console.log('No saved form data found');
        return;
    }

    try {
        const formData = JSON.parse(savedData);
        console.log('Parsed saved data:', formData);

        // Only restore basic step information
        if (formData.current_step && formData.current_step > 1 && formData.current_step <= totalSteps) {
            currentStep = formData.current_step;
            console.log('Restored current step:', currentStep);
        }

        // Don't try to restore form values immediately - too error prone
        console.log('Form data loading completed (minimal restore)');

    } catch (error) {
        console.error('Error loading saved form data:', error);
        localStorage.removeItem('dhana_booking_steps');
    }
}

// Auto-save on form changes - TEMPORARILY DISABLED to prevent errors
function setupAutoSave() {
    console.log('Auto-save temporarily disabled to prevent errors');
    return; // Disable auto-save for now

    console.log('Setting up auto-save functionality...');

    try {
        // Only attach auto-save to specific form elements that we know exist
        const formElements = [
            'input[name="dhana_type_id"]',
            '#booking_date',
            '#booking_time_slot',
            '#travel_support',
            '#is_annual_event',
            '#special_requests'
        ];

        formElements.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            console.log(`Found ${elements.length} elements for selector: ${selector}`);
            elements.forEach(element => {
                if (element) {
                    element.addEventListener('input', function() {
                        console.log('Auto-save triggered by input:', selector);
                        saveFormData();
                    });
                    element.addEventListener('change', function() {
                        console.log('Auto-save triggered by change:', selector);
                        saveFormData();
                    });
                }
            });
        });
    } catch (error) {
        console.error('Error setting up auto-save:', error);
    }
}



// Enhanced date picker functionality
function enhanceDatePicker() {
    const dateInput = document.getElementById('booking_date');
    if (!dateInput) return;

    // Set minimum date to tomorrow - TIMEZONE SAFE
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const minYear = tomorrow.getFullYear();
    const minMonth = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const minDay = String(tomorrow.getDate()).padStart(2, '0');
    dateInput.min = `${minYear}-${minMonth}-${minDay}`;

    // Set maximum date to 10 years from now (as per user preference) - TIMEZONE SAFE
    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() + 10);
    const maxYear = maxDate.getFullYear();
    const maxMonth = String(maxDate.getMonth() + 1).padStart(2, '0');
    const maxDay = String(maxDate.getDate()).padStart(2, '0');
    dateInput.max = `${maxYear}-${maxMonth}-${maxDay}`;

    // Add custom styling wrapper
    const dateWrapper = document.createElement('div');
    dateWrapper.className = 'custom-date-picker';
    dateInput.parentNode.insertBefore(dateWrapper, dateInput);
    dateWrapper.appendChild(dateInput);

    // Enhanced focus and blur effects
    dateInput.addEventListener('focus', function() {
        this.style.transform = 'scale(1.02)';
        this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.15)';
        this.style.borderColor = '#667eea';
        dateWrapper.classList.add('calendar-active');
    });

    dateInput.addEventListener('blur', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 2px 8px rgba(102, 126, 234, 0.1)';
        this.style.borderColor = '#e1e5e9';
        dateWrapper.classList.remove('calendar-active');
    });

    // Detect calendar picker click
    dateInput.addEventListener('click', function(e) {
        // Add a class to indicate calendar is being opened
        dateWrapper.classList.add('calendar-opening');

        // Remove the class after a short delay
        setTimeout(() => {
            dateWrapper.classList.remove('calendar-opening');
        }, 500);
    });

    // Add keyboard shortcuts
    dateInput.addEventListener('keydown', function(e) {
        const currentDate = this.value ? new Date(this.value) : new Date();
        let newDate;

        // Helper function to format date - TIMEZONE SAFE
        const formatDateSafe = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        switch(e.key) {
            case 'ArrowUp':
                e.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(currentDate.getDate() + 7); // Next week
                this.value = formatDateSafe(newDate);
                this.dispatchEvent(new Event('change'));
                break;

            case 'ArrowDown':
                e.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(currentDate.getDate() - 7); // Previous week
                if (newDate >= tomorrow) {
                    this.value = formatDateSafe(newDate);
                    this.dispatchEvent(new Event('change'));
                }
                break;

            case 'ArrowRight':
                e.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(currentDate.getDate() + 1); // Next day
                this.value = formatDateSafe(newDate);
                this.dispatchEvent(new Event('change'));
                break;

            case 'ArrowLeft':
                e.preventDefault();
                newDate = new Date(currentDate);
                newDate.setDate(currentDate.getDate() - 1); // Previous day
                if (newDate >= tomorrow) {
                    this.value = formatDateSafe(newDate);
                    this.dispatchEvent(new Event('change'));
                }
                break;
        }
    });

    // Add visual feedback for date selection
    dateInput.addEventListener('change', function() {
        if (this.value) {
            this.style.backgroundColor = '#fafbff';
            this.style.borderColor = '#28a745';

            // Add animation class
            this.classList.add('date-selected');

            // Add a subtle success animation
            this.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                this.style.backgroundColor = '#ffffff';
                this.style.borderColor = '#e1e5e9';
                this.classList.remove('date-selected');
            }, 1000);
        }
    });
}

// Enhanced time slot picker functionality
function enhanceTimeSlotPicker() {
    const timeSlotSelect = document.getElementById('booking_time_slot');
    if (!timeSlotSelect) return;

    // Add custom styling wrapper
    const timeSlotWrapper = document.createElement('div');
    timeSlotWrapper.className = 'custom-time-slot-picker';
    timeSlotSelect.parentNode.insertBefore(timeSlotWrapper, timeSlotSelect);
    timeSlotWrapper.appendChild(timeSlotSelect);

    // Enhanced focus and blur effects
    timeSlotSelect.addEventListener('focus', function() {
        this.style.transform = 'scale(1.02)';
        this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.15)';
        this.style.borderColor = '#667eea';
        timeSlotWrapper.classList.add('time-slot-active');
    });

    timeSlotSelect.addEventListener('blur', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 2px 8px rgba(102, 126, 234, 0.1)';
        this.style.borderColor = '#e1e5e9';
        timeSlotWrapper.classList.remove('time-slot-active');
    });

    // Add keyboard shortcuts for time slot navigation
    timeSlotSelect.addEventListener('keydown', function(e) {
        const options = Array.from(this.options).filter(option => option.value !== '');
        const currentIndex = options.findIndex(option => option.selected);
        let newIndex;

        switch(e.key) {
            case 'ArrowUp':
                e.preventDefault();
                newIndex = currentIndex > 0 ? currentIndex - 1 : options.length - 1;
                this.value = options[newIndex].value;
                this.dispatchEvent(new Event('change'));
                break;

            case 'ArrowDown':
                e.preventDefault();
                newIndex = currentIndex < options.length - 1 ? currentIndex + 1 : 0;
                this.value = options[newIndex].value;
                this.dispatchEvent(new Event('change'));
                break;

            case 'Home':
                e.preventDefault();
                this.value = options[0].value;
                this.dispatchEvent(new Event('change'));
                break;

            case 'End':
                e.preventDefault();
                this.value = options[options.length - 1].value;
                this.dispatchEvent(new Event('change'));
                break;
        }
    });

    // Add visual feedback for time slot selection
    timeSlotSelect.addEventListener('change', function() {
        if (this.value) {
            this.style.backgroundColor = '#fafbff';
            this.style.borderColor = '#28a745';

            // Add animation class
            this.classList.add('time-slot-selected');

            // Add a subtle success animation
            this.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                this.style.backgroundColor = '#ffffff';
                this.style.borderColor = '#e1e5e9';
                this.classList.remove('time-slot-selected');
            }, 1000);
        }
    });

    // Add click effect
    timeSlotSelect.addEventListener('click', function() {
        timeSlotWrapper.classList.add('time-slot-opening');

        setTimeout(() => {
            timeSlotWrapper.classList.remove('time-slot-opening');
        }, 300);
    });
}


// ============================================================================
// ANNUAL BOOKING PRICE BREAKDOWN - INLINE SUMMARY
// ============================================================================

/**
 * Show annual price breakdown inline when user checks Annual Event checkbox
 */
async function showAnnualPriceBreakdown() {
    console.log('📅 Showing annual price breakdown...');

    const selectedDhanaType = document.querySelector('input[name="dhana_type_id"]:checked');
    const bookingDateInput = document.getElementById('booking_date');
    const summaryContainer = document.getElementById('annual_price_summary');

    if (!summaryContainer) {
        console.error('Annual price summary container not found');
        return;
    }

    if (!selectedDhanaType) {
        summaryContainer.style.display = 'block';
        summaryContainer.innerHTML = '<div class="error-inline"><i class="fas fa-exclamation-circle"></i> Please select a dāna type first</div>';
        document.getElementById('is_annual_event').checked = false;
        return;
    }

    if (!bookingDateInput || !bookingDateInput.value) {
        summaryContainer.style.display = 'block';
        summaryContainer.innerHTML = '<div class="error-inline"><i class="fas fa-exclamation-circle"></i> Please select a date first</div>';
        document.getElementById('is_annual_event').checked = false;
        return;
    }

    const dhanaTypeId = selectedDhanaType.value;
    const dhanaTypeName = selectedDhanaType.getAttribute('data-name');
    const bookingDate = bookingDateInput.value;

    console.log(`Fetching annual prices for: ${dhanaTypeName} starting ${bookingDate}`);

    // Show loading state
    summaryContainer.style.display = 'block';
    summaryContainer.innerHTML = '<div class="loading-inline"><i class="fas fa-spinner fa-spin"></i> Loading price breakdown...</div>';

    try {
        // Fetch annual prices from API
        const response = await fetch(`api/get-annual-prices.php?dhana_type_id=${dhanaTypeId}&start_date=${bookingDate}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            displayAnnualPriceInline(data);
        } else {
            summaryContainer.innerHTML = `<div class="error-inline"><i class="fas fa-exclamation-triangle"></i> ${data.error}</div>`;
        }
    } catch (error) {
        console.error('Error fetching annual prices:', error);
        summaryContainer.innerHTML = '<div class="error-inline"><i class="fas fa-exclamation-triangle"></i> Failed to load prices. Please try again.</div>';
    }
}

/**
 * Display annual price breakdown inline (compact version)
 */
function displayAnnualPriceInline(data) {
    console.log('📊 Displaying annual price breakdown:', data);

    const summaryContainer = document.getElementById('annual_price_summary');

    let html = `
        <div class="annual-inline-header">
            <i class="fas fa-calendar-check"></i>${data.dhana_type_name} - ${data.annual_years} Years
        </div>
        <div class="annual-inline-stats">
            <div class="stat-item confirmed">
                <div class="stat-item-header">
                    <i class="fas fa-check-circle"></i><span class="stat-item-label">${data.summary.confirmed_count} year${data.summary.confirmed_count > 1 ? 's' : ''} with confirmed prices:</span>
                </div>
                <span class="stat-amount">Rs. ${formatPrice(data.summary.total_confirmed)}</span>
            </div>
    `;

    // Only show "not set" stat if there are any
    if (data.summary.not_set_count > 0) {
        html += `
            <div class="stat-item warning">
                <div class="stat-item-header">
                    <i class="fas fa-exclamation-circle"></i><span class="stat-item-label">${data.summary.not_set_count} year${data.summary.not_set_count > 1 ? 's' : ''} with prices not set yet:</span>
                </div>
                <span class="stat-amount">To be determined</span>
            </div>
        `;
    }

    html += `
        </div>
        <div class="annual-inline-details">
            <button type="button" class="btn-link-small" onclick="toggleAnnualDetails()">
                <i class="fas fa-chevron-down" id="toggle-icon"></i>
                <span id="toggle-text">Show detailed breakdown</span>
            </button>
            <div id="annual_details_table" class="annual-details-table" style="display: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Date</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    data.prices.forEach(yearData => {
        const priceDisplay = yearData.price !== null
            ? `<span class="price-confirmed">Rs. ${formatPrice(yearData.price)}</span>`
            : '<span class="price-not-set">Not set yet</span>';

        html += `
            <tr>
                <td>Year ${yearData.year_number}</td>
                <td>${yearData.display_date}</td>
                <td>${priceDisplay}</td>
            </tr>
        `;
    });

    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;

    summaryContainer.innerHTML = html;
}

/**
 * Toggle annual details table visibility
 */
function toggleAnnualDetails() {
    const detailsTable = document.getElementById('annual_details_table');
    const toggleIcon = document.getElementById('toggle-icon');
    const toggleText = document.getElementById('toggle-text');

    if (detailsTable && toggleIcon && toggleText) {
        if (detailsTable.style.display === 'none') {
            detailsTable.style.display = 'block';
            toggleIcon.className = 'fas fa-chevron-up';
            toggleText.textContent = 'Hide detailed breakdown';
        } else {
            detailsTable.style.display = 'none';
            toggleIcon.className = 'fas fa-chevron-down';
            toggleText.textContent = 'Show detailed breakdown';
        }
    }
}

/**
 * Hide annual price summary
 */
function hideAnnualPriceSummary() {
    const summaryContainer = document.getElementById('annual_price_summary');
    if (summaryContainer) {
        summaryContainer.style.display = 'none';
        summaryContainer.innerHTML = '';
    }
}

/**
 * Format price with thousand separators
 */
function formatPrice(price) {
    if (price === null || price === undefined) {
        return '0';
    }
    return parseFloat(price).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
}

// Add event listener for Annual Event checkbox
document.addEventListener('DOMContentLoaded', function() {
    const annualEventCheckbox = document.getElementById('is_annual_event');

    if (annualEventCheckbox) {
        annualEventCheckbox.addEventListener('change', function() {
            console.log('Annual Event checkbox changed:', this.checked);

            if (this.checked) {
                showAnnualPriceBreakdown();
            } else {
                hideAnnualPriceSummary();
            }
        });
    }
});