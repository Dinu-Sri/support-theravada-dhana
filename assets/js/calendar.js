/**
 * Calendar JavaScript for Dhana Booking System
 */

// Show date details modal
function showDateDetails(date) {
    const modal = document.getElementById('dateModal');
    const modalDate = document.getElementById('modalDate');
    const modalContent = document.getElementById('modalContent');
    const bookNowBtn = document.getElementById('bookNowBtn');
    
    // Check if date is in the past
    if (date < today) {
        return;
    }
    
    // Check if date is blocked
    if (blockedDates.includes(date)) {
        modalDate.textContent = formatDate(date);
        modalContent.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #666;">
                <i class="fas fa-ban" style="font-size: 3rem; margin-bottom: 15px; color: #6c757d;"></i>
                <h4>Date Not Available</h4>
                <p>This date is blocked and not available for booking.</p>
            </div>
        `;
        bookNowBtn.style.display = 'none';
        modal.style.display = 'block';
        return;
    }
    
    // Set modal title
    modalDate.textContent = formatDate(date);
    
    // Check if this date has a whole day booking
    const hasWholeDayBooking = wholeDayBookings.includes(date);

    // Generate dhana availability content with time slot information
    let content = '<div class="dhana-availability">';
    let hasAvailable = false;

    dhanaTypes.forEach(dhanaType => {
        const dhanaBookings = bookingsByDate[date] && bookingsByDate[date][dhanaType.id];

        // Determine availability based on whole day logic and dhana type
        let statusClass, statusText, timeSlotInfo = '';

        if (hasWholeDayBooking) {
            // If there's a whole day booking, check what type this dhana is
            const isWholeDayBooking = dhanaBookings && dhanaBookings['whole_day'];

            if (isWholeDayBooking) {
                // This is the dhana type that has the whole day booking
                statusClass = 'booked';
                statusText = 'Booked (Whole Day)';
            } else if (dhanaType.time_slot === 'extra' || dhanaType.name === 'Extra Item') {
                // Extra items are still available even with whole day booking (future feature)
                statusClass = 'available';
                statusText = 'Available';
                hasAvailable = true;
            } else {
                // Morning and Lunch Dana are blocked when whole day is booked
                statusClass = 'blocked';
                statusText = 'Not Available';
                timeSlotInfo = '<small>Blocked by whole day booking</small>';
            }
        } else {
            // No whole day booking, check individual time slots and conflicts

            // Check if there are ANY bookings on this date (across all dhana types)
            const hasAnyBookings = bookingsByDate[date] && Object.keys(bookingsByDate[date]).length > 0;

            if (dhanaType.time_slot === 'whole_day' || dhanaType.name === 'Whole Day') {
                // Whole day booking is blocked if ANY time slot is booked on this date
                if (hasAnyBookings) {
                    statusClass = 'blocked';
                    statusText = 'Not Available';
                    timeSlotInfo = '<small>Blocked by existing bookings</small>';
                } else {
                    statusClass = 'available';
                    statusText = 'Available';
                    hasAvailable = true;
                }
            } else if (dhanaBookings) {
                // Check specific time slot bookings for this dhana type
                const morningBooked = dhanaBookings['morning'];
                const lunchBooked = dhanaBookings['lunch'];

                if ((dhanaType.time_slot === 'morning' || dhanaType.name === 'Morning Dana') && morningBooked) {
                    statusClass = 'booked';
                    statusText = 'Booked';
                } else if ((dhanaType.time_slot === 'lunch' || dhanaType.name === 'Lunch Dana') && lunchBooked) {
                    statusClass = 'booked';
                    statusText = 'Booked';
                } else if (dhanaType.time_slot === 'extra' || dhanaType.name === 'Extra Item') {
                    statusClass = 'available';
                    statusText = 'Available';
                    hasAvailable = true;
                } else {
                    statusClass = 'available';
                    statusText = 'Available';
                    hasAvailable = true;
                }
            } else {
                // No bookings for this specific dhana type
                if (dhanaType.time_slot === 'extra' || dhanaType.name === 'Extra Item') {
                    statusClass = 'available';
                    statusText = 'Available';
                    hasAvailable = true;
                } else {
                    statusClass = 'available';
                    statusText = 'Available';
                    hasAvailable = true;
                }
            }
        }

        content += `
            <div class="dhana-item ${statusClass}">
                <div class="dhana-info">
                    <h4>${dhanaType.name}</h4>
                    <p>Rs. ${formatPrice(dhanaType.price)}</p>
                    ${timeSlotInfo}
                </div>
                <div class="dhana-status ${statusClass}">
                    ${statusText}
                </div>
            </div>
        `;
    });
    
    content += '</div>';
    
    if (hasAvailable) {
        let availabilityMessage = 'Great! This date has available slots.';
        if (hasWholeDayBooking) {
            // Check if only extra items are available
            const onlyExtraAvailable = dhanaTypes.every(type => {
                const dhanaBookings = bookingsByDate[date] && bookingsByDate[date][type.id];
                const isWholeDayBooking = dhanaBookings && dhanaBookings['whole_day'];
                return isWholeDayBooking || type.time_slot === 'extra' || type.name === 'Extra Item';
            });

            if (onlyExtraAvailable) {
                availabilityMessage = 'Only extra items are available (whole day is booked).';
            }
        }

        content += `
            <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px; margin-top: 15px;">
                <i class="fas fa-check-circle" style="color: #28a745; margin-right: 8px;"></i>
                <strong>${availabilityMessage}</strong>
            </div>
        `;
        bookNowBtn.style.display = 'inline-block';
        bookNowBtn.href = `booking-new.php?date=${date}`;
    } else {
        let unavailabilityMessage = 'Sorry, this date is fully booked.';
        if (hasWholeDayBooking) {
            unavailabilityMessage = 'This date is blocked due to a whole day booking.';
        } else {
            // Check if it's blocked due to existing bookings preventing whole day
            const hasExistingBookings = bookingsByDate[date] && Object.keys(bookingsByDate[date]).length > 0;
            if (hasExistingBookings) {
                unavailabilityMessage = 'This date has existing bookings that prevent whole day booking.';
            }
        }

        content += `
            <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px; margin-top: 15px;">
                <i class="fas fa-times-circle" style="color: #dc3545; margin-right: 8px;"></i>
                <strong>${unavailabilityMessage}</strong>
            </div>
        `;
        bookNowBtn.style.display = 'none';
    }
    
    modalContent.innerHTML = content;
    modal.style.display = 'block';
}

// Close date modal
function closeDateModal() {
    const modal = document.getElementById('dateModal');
    modal.style.display = 'none';
}

// Format date for display
function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    return date.toLocaleDateString('en-US', options);
}

// Format price
function formatPrice(price) {
    if (price == 0) {
        return 'Coming Soon';
    }
    return new Intl.NumberFormat('en-US').format(price);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('dateModal');
    if (event.target === modal) {
        closeDateModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDateModal();
    }
});

// Add keyboard navigation for calendar
document.addEventListener('keydown', function(event) {
    const focusedCell = document.activeElement;
    
    if (!focusedCell.classList.contains('day-cell')) {
        return;
    }
    
    let nextCell = null;
    
    switch(event.key) {
        case 'ArrowLeft':
            nextCell = focusedCell.previousElementSibling;
            break;
        case 'ArrowRight':
            nextCell = focusedCell.nextElementSibling;
            break;
        case 'ArrowUp':
            const currentIndex = Array.from(focusedCell.parentNode.children).indexOf(focusedCell);
            const upIndex = currentIndex - 7;
            if (upIndex >= 0) {
                nextCell = focusedCell.parentNode.children[upIndex];
            }
            break;
        case 'ArrowDown':
            const downIndex = Array.from(focusedCell.parentNode.children).indexOf(focusedCell) + 7;
            if (downIndex < focusedCell.parentNode.children.length) {
                nextCell = focusedCell.parentNode.children[downIndex];
            }
            break;
        case 'Enter':
        case ' ':
            const date = focusedCell.getAttribute('data-date');
            if (date) {
                event.preventDefault();
                showDateDetails(date);
            }
            break;
    }
    
    if (nextCell && nextCell.classList.contains('day-cell')) {
        event.preventDefault();
        nextCell.focus();
    }
});

// Make day cells focusable
document.addEventListener('DOMContentLoaded', function() {
    const dayCells = document.querySelectorAll('.day-cell:not(.empty):not(.past)');
    dayCells.forEach(cell => {
        cell.setAttribute('tabindex', '0');
        
        // Add focus styles
        cell.addEventListener('focus', function() {
            this.style.outline = '2px solid #667eea';
            this.style.outlineOffset = '2px';
        });
        
        cell.addEventListener('blur', function() {
            this.style.outline = 'none';
        });
    });
});

// Add loading animation for navigation
document.addEventListener('DOMContentLoaded', function() {
    const navButtons = document.querySelectorAll('.nav-btn');
    
    navButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add loading state
            const originalContent = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.style.pointerEvents = 'none';
            
            // The page will navigate, but if for some reason it doesn't,
            // restore the button after 3 seconds
            setTimeout(() => {
                this.innerHTML = originalContent;
                this.style.pointerEvents = 'auto';
            }, 3000);
        });
    });
});

// Add smooth scrolling to calendar when page loads
document.addEventListener('DOMContentLoaded', function() {
    const calendar = document.querySelector('.calendar-container');
    if (calendar) {
        calendar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

// Add touch support for mobile devices
let touchStartX = 0;
let touchStartY = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
});

document.addEventListener('touchend', function(e) {
    if (!touchStartX || !touchStartY) {
        return;
    }
    
    const touchEndX = e.changedTouches[0].clientX;
    const touchEndY = e.changedTouches[0].clientY;
    
    const diffX = touchStartX - touchEndX;
    const diffY = touchStartY - touchEndY;
    
    // Only handle horizontal swipes on calendar
    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
        const calendarContainer = e.target.closest('.calendar-container');
        if (calendarContainer) {
            const navButtons = document.querySelectorAll('.nav-btn');
            
            if (diffX > 0) {
                // Swipe left - next month
                navButtons[1].click();
            } else {
                // Swipe right - previous month
                navButtons[0].click();
            }
        }
    }
    
    touchStartX = 0;
    touchStartY = 0;
});

// Month/Year Picker Modal Functions
function openMonthYearModal() {
    const modal = document.getElementById('monthYearModal');
    const monthSelect = document.getElementById('jumpMonth');
    const yearSelect = document.getElementById('jumpYear');

    // Set current values
    monthSelect.value = currentMonth;
    yearSelect.value = currentYear;

    modal.style.display = 'block';
}

function closeMonthYearModal() {
    const modal = document.getElementById('monthYearModal');
    modal.style.display = 'none';
}

function jumpToMonthYear() {
    const month = document.getElementById('jumpMonth').value;
    const year = document.getElementById('jumpYear').value;

    // Navigate to the selected month/year
    window.location.href = `?month=${month}&year=${year}`;
}

// Add calendar quick jump functionality
function createQuickJump() {
    const calendarNav = document.querySelector('.calendar-nav');
    const monthYear = calendarNav.querySelector('h2');

    monthYear.style.cursor = 'pointer';
    monthYear.title = 'Click to jump to a specific month';

    // Add a subtle hover effect
    monthYear.addEventListener('mouseenter', function() {
        this.style.textDecoration = 'underline';
    });

    monthYear.addEventListener('mouseleave', function() {
        this.style.textDecoration = 'none';
    });

    monthYear.addEventListener('click', function() {
        openMonthYearModal();
    });
}

// Close month/year modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('monthYearModal');
    if (event.target === modal) {
        closeMonthYearModal();
    }
});

// Handle Enter key in month/year modal
document.addEventListener('DOMContentLoaded', function() {
    const jumpMonth = document.getElementById('jumpMonth');
    const jumpYear = document.getElementById('jumpYear');

    if (jumpMonth && jumpYear) {
        [jumpMonth, jumpYear].forEach(element => {
            element.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    jumpToMonthYear();
                }
            });
        });
    }
});

// Initialize quick jump on page load
document.addEventListener('DOMContentLoaded', createQuickJump);
