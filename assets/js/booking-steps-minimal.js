/**
 * Minimal Booking Steps JavaScript - Ultra Safe Version
 * This version removes all error-prone functions and keeps only essentials
 */

console.log('🔧 MINIMAL BOOKING STEPS LOADED - Version ' + new Date().getTime());
console.log('✅ NEW VERSION LOADED - If you see this, cache busting worked!');

let currentStep = 1;
const totalSteps = 4;

// Safe element getter
function safeGet(id) {
    try {
        return document.getElementById(id);
    } catch (e) {
        return null;
    }
}

// Safe query selector
function safeQuery(selector) {
    try {
        return document.querySelector(selector);
    } catch (e) {
        return null;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 DOM ready - starting minimal initialization...');
    
    // Immediate diagnostics
    console.log('dhanaTypes available:', typeof dhanaTypes !== 'undefined', dhanaTypes?.length || 0);
    console.log('Container exists:', !!document.getElementById('dhana-types-container'));
    
    // Try immediate execution first
    console.log('🚀 IMMEDIATE EXECUTION TEST...');
    try {
        loadDhanaTypes();
        showStep(1);
        setupNavigation();
        console.log('✅ Immediate execution completed');
    } catch (error) {
        console.error('❌ Immediate execution failed:', error);
    }
    
    // Also try with timeout as backup
    setTimeout(() => {
        try {
            console.log('🔍 Starting delayed initialization sequence...');
            
            // 1. Load dhana types first
            console.log('1. Loading dhana types...');
            loadDhanaTypes();
            
            // 2. Show first step
            console.log('2. Showing step 1...');
            showStep(1);
            
            // 3. Setup navigation
            console.log('3. Setting up navigation...');
            setupNavigation();
            
            // 4. Diagnostic check
            setTimeout(() => {
                console.log('🔍 === DIAGNOSTIC CHECK ===');
                const container = document.getElementById('dhana-types-container');
                const step1 = document.querySelector('[data-step="1"]');
                const cards = document.querySelectorAll('.dhana-type-card');
                
                console.log('Container:', container);
                console.log('Container display:', container?.style.display);
                console.log('Container innerHTML length:', container?.innerHTML?.length || 0);
                console.log('Step 1 element:', step1);
                console.log('Step 1 display:', step1?.style.display);
                console.log('Step 1 classes:', step1?.className);
                console.log('Dhana cards found:', cards.length);
                
                if (cards.length === 0) {
                    console.log('⚠️ NO CARDS FOUND - Forcing reload...');
                    loadDhanaTypes();
                }
            }, 500);
            
            console.log('✅ Minimal initialization completed successfully');
            
        } catch (error) {
            console.error('❌ Error in initialization:', error);
        }
    }, 1000);
});

function loadDhanaTypes() {
    console.log('🔍 loadDhanaTypes called');
    
    const container = safeGet('dhana-types-container');
    console.log('Container found:', container);
    console.log('dhanaTypes available:', typeof dhanaTypes !== 'undefined', dhanaTypes);
    
    if (!container) {
        console.error('❌ dhana-types-container not found!');
        // Show PHP fallback if container missing
        const phpFallback = safeGet('php-fallback');
        if (phpFallback) {
            phpFallback.style.display = 'block';
            console.log('✅ PHP fallback activated');
        }
        return;
    }
    
    if (typeof dhanaTypes === 'undefined' || !dhanaTypes || dhanaTypes.length === 0) {
        console.error('❌ dhanaTypes not available!');
        // Show PHP fallback if dhanaTypes missing
        const phpFallback = safeGet('php-fallback');
        if (phpFallback) {
            phpFallback.style.display = 'block';
            console.log('✅ PHP fallback activated due to missing dhanaTypes');
        } else {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: red; background: yellow; border: 2px solid red; margin: 10px;">No dhana types available. Please refresh the page.</div>';
        }
        return;
    }

    console.log('✅ Generating HTML for', dhanaTypes.length, 'dhana types');
    
    // Hide loading message
    const loadingMsg = container.querySelector('.loading-message');
    if (loadingMsg) {
        loadingMsg.style.display = 'none';
    }
    
    let html = '';
    dhanaTypes.forEach(type => {
        const disabled = type.price == 0;
        console.log('Processing dhana type:', type.name, 'Price:', type.price, 'Disabled:', disabled);
        
        html += `
            <div class="dhana-type-card" style="margin-bottom: 15px; border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: white; display: block !important; visibility: visible !important;">
                <input type="radio" id="dhana_${type.id}" name="dhana_type_id" 
                       value="${type.id}" data-price="${type.price}" 
                       data-name="${type.name}" ${disabled ? 'disabled' : ''} required>
                <label for="dhana_${type.id}" class="dhana-type-label ${disabled ? 'disabled' : ''}" style="display: block; cursor: pointer; width: 100%;">
                    <div class="dhana-type-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0; color: #333;">${type.name}</h4>
                        <div class="dhana-type-price" style="font-weight: bold; color: #d4822a;">
                            ${type.price > 0 ? `Rs. ${type.price.toLocaleString()}` : 
                              '<span class="coming-soon" style="color: #999;">Coming Soon</span>'}
                        </div>
                    </div>
                    ${type.description ? `<p class="dhana-type-description" style="margin: 0 0 10px 0; color: #666;">${type.description}</p>` : ''}
                    <div class="time-slot-info" style="color: #888; font-size: 0.9em;">
                        <i class="fas fa-clock"></i>
                        <span>Whole Day</span>
                    </div>
                    ${disabled ? '<div class="disabled-overlay" style="background: rgba(0,0,0,0.1); padding: 5px; text-align: center; color: #999;"><i class="fas fa-clock"></i> Coming Soon</div>' : ''}
                </label>
            </div>
        `;
    });

    console.log('✅ Generated HTML length:', html.length);
    console.log('HTML preview:', html.substring(0, 200) + '...');
    
    container.innerHTML = html;
    console.log('✅ HTML inserted into container');
    
    // Force container to be visible
    container.style.cssText = 'display: grid !important; visibility: visible !important; opacity: 1 !important;';
    console.log('✅ Container made visible');
    
    // Verify the content was inserted
    setTimeout(() => {
        const radioButtons = document.querySelectorAll('input[name="dhana_type_id"]');
        console.log('✅ Radio buttons found after insertion:', radioButtons.length);
        
        radioButtons.forEach((radio, index) => {
            console.log(`Radio ${index + 1}:`, radio.id, radio.value, radio.checked);
            radio.addEventListener('change', function() {
                console.log('📻 Radio button changed:', this.value, this.getAttribute('data-name'));
                updateReview();
            });
        });
        
        // If still no radio buttons, show PHP fallback
        if (radioButtons.length === 0) {
            console.log('⚠️ Still no radio buttons found, showing PHP fallback...');
            const phpFallback = safeGet('php-fallback');
            if (phpFallback) {
                phpFallback.style.display = 'block';
                console.log('✅ PHP fallback activated as last resort');
            }
        }
    }, 100);
}

function showStep(step) {
    try {
        console.log('🔍 showStep called with step:', step);
        
        // Hide all steps
        const allSteps = document.querySelectorAll('.form-step');
        console.log('Found form steps:', allSteps.length);
        
        allSteps.forEach((stepEl, index) => {
            stepEl.classList.remove('active');
            stepEl.style.display = 'none';
            stepEl.style.visibility = 'hidden';
            console.log(`Hidden step ${index + 1}:`, stepEl.dataset.step);
        });
        
        // Show current step
        const stepEl = safeQuery(`.form-step[data-step="${step}"]`);
        console.log('Target step element found:', stepEl);
        
        if (stepEl) {
            stepEl.classList.add('active');
            stepEl.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';
            console.log('✅ Step', step, 'is now visible');
            
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
                
                const container = safeGet('dhana-types-container');
                if (container) {
                    container.style.cssText = 'display: grid !important; visibility: visible !important; opacity: 1 !important; position: static !important; left: auto !important; top: auto !important; width: auto !important; height: auto !important; margin: auto !important; padding: auto !important; z-index: auto !important; pointer-events: auto !important; max-height: none !important; max-width: none !important; min-height: auto !important; min-width: auto !important;';
                    console.log('✅ Dhana types container made visible');
                    
                    // Ensure all dhana cards are visible
                    const cards = container.querySelectorAll('.dhana-type-card');
                    console.log('✅ Dhana type cards found:', cards.length);
                    
                    cards.forEach((card, index) => {
                        card.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; margin-bottom: 15px; border: 2px solid #ddd; padding: 15px; border-radius: 8px; background: white;';
                    });
                    
                    if (cards.length === 0) {
                        console.log('⚠️ No dhana cards found, reloading...');
                        loadDhanaTypes();
                    }
                } else {
                    console.log('⚠️ Dhana types container not found, trying to reload...');
                    loadDhanaTypes();
                }
            } else {
                // NOT STEP 1 - COMPLETELY HIDE DHANA TYPES CONTAINER TO PREVENT SPACE ISSUES
                const dhanaContainer = safeGet('dhana-types-container');
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
                
                // AUTO-SELECT TIME SLOT FOR STEP 2 AND RESTORE IF REMOVED
                if (step === 2) {
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
                    
                    setTimeout(() => {
                        const selectedDhanaType = safeQuery('input[name="dhana_type_id"]:checked');
                        const timeSlotSelect = safeGet('booking_time_slot');
                        
                        if (selectedDhanaType && timeSlotSelect) {
                            const dhanaTypeTimeSlot = selectedDhanaType.getAttribute('data-time-slot');
                            console.log('🔄 Auto-selecting time slot from step 1:', dhanaTypeTimeSlot);
                            
                            if (dhanaTypeTimeSlot) {
                                // Map dhana type time slots to booking time slots
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
                            }
                        }
                    }, 200);
                }
                
                // ULTRA-AGGRESSIVE SPACING FIXES FOR STEPS 3 AND 4
                if (step === 3 || step === 4) {
                    // ULTRA-AGGRESSIVE: For step 3, completely remove dhana-types-container AND step 2 from layout flow
                    if (step === 3) {
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
                    }
                    
                    setTimeout(() => {
                        const stepEl = safeQuery(`.form-step[data-step="${step}"]`);
                        if (stepEl) {
                            // ULTRA-AGGRESSIVE: Force remove ALL spacing from the entire step
                            stepEl.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; margin-top: 0 !important; padding-top: 0 !important; margin-bottom: 0 !important; padding-bottom: 0 !important;';
                            
                            // Force remove top margins and padding from step header
                            const stepHeader = stepEl.querySelector('.step-header');
                            if (stepHeader) {
                                stepHeader.style.cssText = 'margin-top: 0 !important; padding-top: 0 !important; text-align: center; margin-bottom: 15px !important;';
                                console.log(`✅ Step ${step} header spacing fixed`);
                            }
                            
                            // Special ultra-aggressive fixes for step 3
                            if (step === 3) {
                                const additionalInfoSection = stepEl.querySelector('.additional-info-section');
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
                                
                                // ULTRA-AGGRESSIVE: Force the entire step form to have no top spacing
                                const stepForm = document.querySelector('.step-form');
                                if (stepForm) {
                                    stepForm.style.paddingTop = '0px !important';
                                    stepForm.style.marginTop = '0px !important';
                                }
                            }
                            
                            // Remove any phantom/invisible elements that might cause spacing
                            const allChildren = Array.from(stepEl.children);
                            allChildren.forEach((child, index) => {
                                if (index === 0) { // First child (step-header)
                                    child.style.marginTop = '0';
                                    child.style.paddingTop = '0';
                                }
                            });
                            
                            // ULTRA-AGGRESSIVE: Remove spacing from any other potential space-taking elements
                            const allDivs = stepEl.querySelectorAll('div');
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
                            
                            console.log(`✅ Step ${step} ULTRA-AGGRESSIVE empty space fix completed`);
                        }
                    }, 100);
                }
            }
        } else {
            console.error('❌ Step element not found for step:', step);
        }
        
        // Update indicators
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            indicator.classList.remove('active', 'completed');
            if (index + 1 === step) {
                indicator.classList.add('active');
            } else if (index + 1 < step) {
                indicator.classList.add('completed');
            }
        });
        
        // Update navigation
        updateNavigation();
        updateProgress();
        
    } catch (error) {
        console.error('Error showing step:', error);
    }
}

function updateNavigation() {
    try {
        const prevBtn = safeGet('prevBtn');
        const nextBtn = safeGet('nextBtn');
        const reviewNav = safeQuery('.review-navigation');
        
        if (prevBtn) prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
        if (nextBtn) nextBtn.style.display = currentStep < totalSteps ? 'inline-flex' : 'none';
        if (reviewNav) reviewNav.style.display = currentStep === totalSteps ? 'flex' : 'none';
        
    } catch (error) {
        console.error('Error updating navigation:', error);
    }
}

function updateProgress() {
    try {
        const fill = safeGet('progressFill');
        if (fill) {
            const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            fill.style.width = percentage + '%';
        }
    } catch (error) {
        console.error('Error updating progress:', error);
    }
}

function nextStep() {
    if (currentStep < totalSteps) {
        currentStep++;
        showStep(currentStep);
        if (currentStep === totalSteps) {
            updateReview();
        }
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
}

function updateReview() {
    try {
        // Update dhana type
        const selected = safeQuery('input[name="dhana_type_id"]:checked');
        const reviewType = safeGet('review-dhana-type');
        const reviewAmount = safeGet('review-total-amount');
        
        if (selected && reviewType) {
            reviewType.textContent = selected.getAttribute('data-name') || '';
            if (reviewAmount) {
                const price = parseFloat(selected.getAttribute('data-price') || '0');
                reviewAmount.textContent = `Rs. ${price.toLocaleString()}`;
            }
        }
        
        // Update date
        const dateInput = safeGet('booking_date');
        const reviewDate = safeGet('review-date');
        if (dateInput && reviewDate) {
            reviewDate.textContent = dateInput.value ? 
                new Date(dateInput.value).toLocaleDateString('en-US', {
                    year: 'numeric', month: 'long', day: 'numeric'
                }) : 'Not selected';
        }
        
        // Update time slot
        const timeSlot = safeGet('booking_time_slot');
        const reviewTime = safeGet('review-time-slot');
        if (timeSlot && reviewTime) {
            const labels = {
                'morning': 'Morning Dhana',
                'lunch': 'Lunch Dhana', 
                'whole_day': 'Whole Day'
            };
            reviewTime.textContent = labels[timeSlot.value] || 'Not selected';
        }
        
        // Update travel support
        const travel = safeGet('travel_support');
        const reviewTravel = safeGet('review-travel-support');
        if (travel && reviewTravel) {
            reviewTravel.textContent = travel.checked ? 'Yes' : 'No';
        }
        
        // Update annual event
        const annual = safeGet('is_annual_event');
        const reviewAnnual = safeGet('review-annual-event');
        if (annual && reviewAnnual) {
            reviewAnnual.textContent = annual.checked ? 'Yes' : 'No';
        }
        
    } catch (error) {
        console.error('Error updating review:', error);
    }
}

function setupNavigation() {
    try {
        const nextBtn = safeGet('nextBtn');
        const prevBtn = safeGet('prevBtn');
        const reviewPrev = safeGet('reviewPrevBtn');
        const reviewSubmit = safeGet('reviewSubmitBtn');
        
        if (nextBtn) nextBtn.addEventListener('click', nextStep);
        if (prevBtn) prevBtn.addEventListener('click', prevStep);
        if (reviewPrev) reviewPrev.addEventListener('click', prevStep);
        if (reviewSubmit) {
            reviewSubmit.addEventListener('click', function() {
                const form = safeGet('bookingForm');
                if (form) form.submit();
            });
        }
        
    } catch (error) {
        console.error('Error setting up navigation:', error);
    }
}

// Also try window load event as backup
window.addEventListener('load', function() {
    console.log('🌐 Window load event - trying again...');
    
    setTimeout(() => {
        console.log('dhanaTypes on window load:', typeof dhanaTypes !== 'undefined', dhanaTypes?.length || 0);
        
        if (typeof dhanaTypes !== 'undefined' && dhanaTypes && dhanaTypes.length > 0) {
            console.log('🎉 dhanaTypes found on window load - initializing...');
            loadDhanaTypes();
            showStep(1);
            setupNavigation();
        } else {
            console.log('⚠️ dhanaTypes still not available on window load');
            // Force show PHP fallback
            const phpFallback = document.getElementById('php-fallback');
            if (phpFallback) {
                phpFallback.style.display = 'block';
                console.log('✅ PHP fallback shown via window load');
            }
        }
    }, 100);
});