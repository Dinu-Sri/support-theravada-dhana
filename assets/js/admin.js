/**
 * Admin Panel JavaScript for Dhana Booking System
 */

function escapeAdminHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[character]));
}

function safeAdminId(value) {
    const id = Number.parseInt(value, 10);
    return Number.isSafeInteger(id) && id > 0 ? id : 0;
}

function safeAdminStatus(value) {
    const statuses = ['pending', 'receipt_submitted', 'payment_pending', 'confirmed', 'completed', 'cancelled'];
    return statuses.includes(value) ? value : 'pending';
}

async function readAdminJsonResponse(response, fallbackMessage) {
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.success === false) {
        throw new Error(data?.error || fallbackMessage || `Request failed (${response.status}).`);
    }
    return data;
}

function getAdminDayAvailability(dayBookings, dhanaTypes, isBlocked) {
    if (isBlocked) return 'fully-booked';

    const activeBookings = (Array.isArray(dayBookings) ? dayBookings : [])
        .filter(booking => safeAdminStatus(booking.status) !== 'cancelled');
    if (activeBookings.some(booking => booking.booking_time_slot === 'whole_day')) {
        return 'fully-booked';
    }
    if (activeBookings.length === 0) return 'available';

    const bookableOfferings = (Array.isArray(dhanaTypes) ? dhanaTypes : [])
        .filter(type => Number(type.price) > 0 && ['morning', 'lunch'].includes(type.time_slot))
        .map(type => `${safeAdminId(type.id)}-${type.time_slot}`);
    const occupiedOfferings = new Set(activeBookings.map(
        booking => `${safeAdminId(booking.dhana_type_id)}-${booking.booking_time_slot}`
    ));

    return bookableOfferings.length > 0 && bookableOfferings.every(key => occupiedOfferings.has(key))
        ? 'fully-booked'
        : 'partially-booked';
}

// Export CSV functionality
function exportCSV() {
    const table = document.getElementById('bookingsTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = '';

    // Process each row
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll(index === 0 ? 'th' : 'td');
        const rowData = [];

        cells.forEach((cell, cellIndex) => {
            // Skip the Actions column (last column)
            if (cellIndex < cells.length - 1) {
                let cellText = cell.textContent.trim();
                // Clean up the text and escape quotes
                cellText = cellText.replace(/"/g, '""');
                rowData.push(`"${cellText}"`);
            }
        });

        csvContent += rowData.join(',') + '\n';
    });

    // Create and download the file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `bookings_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Refresh bookings functionality
function refreshBookings() {
    const refreshBtn = document.querySelector('.section-actions .btn-primary');
    if (!refreshBtn) return;

    const originalText = refreshBtn.innerHTML;

    // Show loading state
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    refreshBtn.disabled = true;

    // Reload the page after a short delay
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Search/filter functionality
function filterBookings() {
    const searchInput = document.getElementById('bookingSearch');
    const searchTerm = searchInput.value.toLowerCase();
    const tableRows = document.querySelectorAll('.booking-row');

    tableRows.forEach(row => {
        const donor = row.dataset.customer.toLowerCase();
        const email = row.dataset.email.toLowerCase();
        const reservationId = row.dataset.id.toLowerCase();

        const isVisible = donor.includes(searchTerm) ||
                         email.includes(searchTerm) ||
                         reservationId.includes(searchTerm);

        row.style.display = isVisible ? '' : 'none';
    });

    // Show/hide "no results" message
    const visibleRows = document.querySelectorAll('.booking-row[style=""], .booking-row:not([style])');
    const tableContainer = document.querySelector('.bookings-table-container');

    let noResultsMsg = document.querySelector('.no-results-message');
    if (visibleRows.length === 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.className = 'no-results-message';
            noResultsMsg.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No bookings found</h3>
                    <p>Try adjusting your search terms</p>
                </div>
            `;
            tableContainer.appendChild(noResultsMsg);
        }
    } else {
        if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const statusForms = document.querySelectorAll('.status-form');
    statusForms.forEach(form => {
        const select = form.querySelector('select');
        const submitButton = form.querySelector('button[type="submit"]');
        const originalStatus = select?.dataset.originalStatus || select?.value;
        const updateDirtyState = () => {
            if (!select || !submitButton) return;
            const changed = select.value !== originalStatus;
            submitButton.hidden = !changed;
            form.classList.toggle('is-dirty', changed);
        };
        select?.addEventListener('change', updateDirtyState);
        updateDirtyState();
        form.addEventListener('submit', function(event) {
            let confirmationMessage = '';
            if (select.value === 'cancelled') {
                confirmationMessage = 'Cancel this reservation?';
            } else if (select.value === 'confirmed') {
                confirmationMessage = 'Confirm this reservation and mark its payment receipt as verified?';
            }

            if (confirmationMessage && !window.confirm(confirmationMessage)) {
                event.preventDefault();
                return;
            }

            form.classList.add('loading');
            if (submitButton) submitButton.disabled = true;
        });
    });
    
    // Add search functionality
    addSearchFunctionality();
    
    // Export functionality is now in HTML
    
    // Add real-time stats updates
    updateStatsRealTime();
    
    // Add keyboard shortcuts
    addKeyboardShortcuts();
});

// Add search functionality to bookings table
function addSearchFunctionality() {
    const tableContainer = document.querySelector('.bookings-table-container');
    if (!tableContainer) return;
    
    // Create search input
    const searchContainer = document.createElement('div');
    searchContainer.className = 'search-container';
    searchContainer.innerHTML = `
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="reservationSearch" placeholder="Search dāna reservations by donor name, email, or reservation ID...">
            <button type="button" id="clearSearch" style="display: none;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add CSS for search
    const style = document.createElement('style');
    style.textContent = `
        .search-container {
            margin-bottom: 20px;
        }
        .search-box {
            position: relative;
            max-width: 400px;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 35px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        .search-box button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }
    `;
    document.head.appendChild(style);
    
    // Insert search box
    tableContainer.parentNode.insertBefore(searchContainer, tableContainer);
    
    const searchInput = document.getElementById('bookingSearch');
    const clearButton = document.getElementById('clearSearch');
    const tableRows = document.querySelectorAll('.bookings-table tbody tr');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        let visibleRows = 0;
        
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide clear button
        clearButton.style.display = searchTerm ? 'block' : 'none';
        
        // Show no results message
        showNoResultsMessage(visibleRows === 0 && searchTerm);
    });
    
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
}

// Show no results message
function showNoResultsMessage(show) {
    let noResultsRow = document.querySelector('.no-results-row');
    
    if (show && !noResultsRow) {
        const table = document.querySelector('.bookings-table tbody');
        noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `
            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No bookings found matching your search.</p>
            </td>
        `;
        table.appendChild(noResultsRow);
    } else if (!show && noResultsRow) {
        noResultsRow.remove();
    }
}

// Export functionality is now handled by HTML buttons

// Export bookings to CSV
function exportToCSV() {
    const table = document.querySelector('.bookings-table');
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            // Skip the actions column
            if (index === cells.length - 1) return;
            
            let cellText = cell.textContent.trim();
            // Clean up the text
            cellText = cellText.replace(/\s+/g, ' ');
            cellText = cellText.replace(/"/g, '""'); // Escape quotes
            rowData.push(`"${cellText}"`);
        });
        
        if (rowData.length > 0) {
            csvContent.push(rowData.join(','));
        }
    });
    
    // Create and download CSV file
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `dhana_bookings_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Update stats in real-time (simulated)
function updateStatsRealTime() {
    const statCards = document.querySelectorAll('.stat-card');
    
    // Add pulse animation to stats when they might change
    const statusForms = document.querySelectorAll('.status-form');
    statusForms.forEach(form => {
        form.addEventListener('submit', function() {
            statCards.forEach(card => {
                card.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    card.style.animation = '';
                }, 500);
            });
        });
    });
}

// Add keyboard shortcuts
function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            const searchInput = document.getElementById('bookingSearch');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
        
        // Ctrl/Cmd + E to export
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            exportToCSV();
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('bookingSearch');
            const clearButton = document.getElementById('clearSearch');
            if (searchInput && searchInput.value) {
                clearButton.click();
            }
        }
    });
}

// Add notification system
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.admin-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `admin-notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${escapeAdminHtml(message)}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add CSS for notifications
    const style = document.createElement('style');
    style.textContent = `
        .admin-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        }
        .notification-success { border-left: 4px solid #28a745; }
        .notification-error { border-left: 4px solid #dc3545; }
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-success i { color: #28a745; }
        .notification-error i { color: #dc3545; }
        .notification-close {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            margin-left: 15px;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Monitor form submissions for notifications
document.addEventListener('DOMContentLoaded', function() {
    const statusForms = document.querySelectorAll('.status-form');
    statusForms.forEach(form => {
        form.addEventListener('submit', function() {
            // Show success notification after form submission
            setTimeout(() => {
                showNotification('Reservation status updated successfully!');
            }, 500);
        });
    });
});

// Add auto-refresh functionality
let autoRefreshInterval;

function toggleAutoRefresh() {
    const button = document.getElementById('autoRefreshBtn');
    
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        button.innerHTML = '<i class="fas fa-play"></i> Auto Refresh';
        button.classList.remove('active');
    } else {
        autoRefreshInterval = setInterval(() => {
            location.reload();
        }, 30000); // Refresh every 30 seconds
        
        button.innerHTML = '<i class="fas fa-pause"></i> Auto Refresh';
        button.classList.add('active');
    }
}

// Auto-refresh functionality is now handled by HTML buttons

// Filter bookings by status
function filterBookings(status) {
    const currentUrl = new URL(window.location);

    if (status === 'all') {
        currentUrl.searchParams.delete('status');
    } else {
        currentUrl.searchParams.set('status', status);
    }

    // Reset to page 1 when filtering
    currentUrl.searchParams.delete('page');

    // Navigate to filtered URL
    window.location.href = currentUrl.toString();
}

// Calendar View Functionality
let currentCalendarMonth = new Date().getMonth() + 1;
let currentCalendarYear = new Date().getFullYear();
let isCalendarView = false;

// Toggle between table and calendar view
function toggleCalendarView() {
    const tableContainer = document.querySelector('.bookings-table-container');
    const calendarContainer = document.getElementById('adminCalendarContainer');
    const calendarBtn = document.getElementById('calendarViewBtn');

    isCalendarView = !isCalendarView;

    if (isCalendarView) {
        tableContainer.style.display = 'none';
        calendarContainer.style.display = 'block';
        calendarBtn.innerHTML = '<i class="fas fa-table"></i> Table View';
        calendarBtn.classList.remove('btn-info');
        calendarBtn.classList.add('btn-warning');

        // Load calendar data
        loadCalendarData();
    } else {
        tableContainer.style.display = 'block';
        calendarContainer.style.display = 'none';
        calendarBtn.innerHTML = '<i class="fas fa-calendar-alt"></i> Calendar View';
        calendarBtn.classList.remove('btn-warning');
        calendarBtn.classList.add('btn-info');
    }
}

// Change calendar month
function changeMonth(direction) {
    currentCalendarMonth += direction;

    if (currentCalendarMonth > 12) {
        currentCalendarMonth = 1;
        currentCalendarYear++;
    } else if (currentCalendarMonth < 1) {
        currentCalendarMonth = 12;
        currentCalendarYear--;
    }

    loadCalendarData();
}

// Load calendar data via AJAX
function loadCalendarData() {
    const calendarGrid = document.getElementById('adminCalendarGrid');
    const monthYearElement = document.getElementById('calendarMonthYear');

    // Show loading state
    calendarGrid.innerHTML = '<div class="calendar-loading">Loading calendar...</div>';

    // Update month/year display
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    monthYearElement.textContent = `${monthNames[currentCalendarMonth - 1]} ${currentCalendarYear}`;

    // Fetch calendar data
    fetch(`admin-calendar-data.php?month=${currentCalendarMonth}&year=${currentCalendarYear}`)
        .then(response => readAdminJsonResponse(response, 'Unable to load calendar data.'))
        .then(data => {
            renderCalendar(data);
        })
        .catch(error => {
            console.error('Error loading calendar data:', error);
            calendarGrid.innerHTML = `<div class="calendar-error">${escapeAdminHtml(error.message)}</div>`;
        });
}

// Render calendar grid
function renderCalendar(data) {
    const calendarGrid = document.getElementById('adminCalendarGrid');
    const { bookings, blockedDates, dhanaTypes, daysInMonth, firstDayOfWeek } = data;

    let calendarHTML = `
        <div class="calendar-grid">
            <div class="day-header">Sun</div>
            <div class="day-header">Mon</div>
            <div class="day-header">Tue</div>
            <div class="day-header">Wed</div>
            <div class="day-header">Thu</div>
            <div class="day-header">Fri</div>
            <div class="day-header">Sat</div>
    `;

    // Empty cells for days before the first day of the month
    for (let i = 0; i < firstDayOfWeek; i++) {
        calendarHTML += '<div class="day-cell empty"></div>';
    }

    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${currentCalendarYear}-${String(currentCalendarMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayBookings = bookings.filter(b => b.booking_date === date);
        const isBlocked = blockedDates.some(bd => bd.blocked_date === date);
        const today = new Date().toISOString().split('T')[0];

        let cssClass = 'day-cell admin-day';
        if (date < today) cssClass += ' past';
        if (isBlocked) cssClass += ' blocked';
        if (date === today) cssClass += ' today';

        cssClass += ` ${getAdminDayAvailability(dayBookings, dhanaTypes, isBlocked)}`;

        calendarHTML += `
            <div class="${cssClass}" data-date="${date}">
                <span class="day-number">${day}</span>
                <div class="booking-thumbnails">
        `;

        // Add booking thumbnails with key information
        dayBookings.slice(0, 3).forEach(booking => {
            const isAnnual = booking.is_annual_event || booking.year_start;
            const isMonk = booking.is_monk == 1;
            const statusClass = safeAdminStatus(booking.status).replace('_', '-');
            const bookingId = safeAdminId(booking.id);

            calendarHTML += `
                <div class="booking-thumbnail ${isAnnual ? 'annual' : ''} ${isMonk ? 'monk-booking' : ''}"
                     onclick="event.stopPropagation(); showBookingDetails(${bookingId});"
                     title="Click to view full details">
                    <div class="thumbnail-header">
                        <span class="thumbnail-status status-${statusClass}"></span>
                        ${isAnnual ? '<i class="fas fa-repeat annual-icon"></i>' : ''}
                        ${isMonk ? '<i class="fas fa-user-tie monk-icon"></i>' : ''}
                    </div>
                    <div class="thumbnail-content">
                        <div class="thumbnail-donor">${escapeAdminHtml(booking.first_name)} ${escapeAdminHtml(booking.last_name)}</div>
                        <div class="thumbnail-dhana">${escapeAdminHtml(booking.dhana_type_name)}</div>
                    </div>
                </div>
            `;
        });

        // Show "more" indicator if there are more than 3 bookings
        if (dayBookings.length > 3) {
            calendarHTML += `
                <div class="booking-more" onclick="showDayBookings('${date}')">
                    +${dayBookings.length - 3} more
                </div>
            `;
        }

        calendarHTML += `
                </div>
            </div>
        `;
    }

    calendarHTML += '</div>';
    calendarGrid.innerHTML = calendarHTML;
}

// Show booking details for a specific day
function showDayBookings(date) {
    const detailsContainer = document.getElementById('calendarBookingDetails');
    const selectedDateElement = document.getElementById('selectedDate');
    const bookingDetailsList = document.getElementById('bookingDetailsList');

    selectedDateElement.textContent = formatDateForDisplay(date);

    // Fetch booking details for the selected date
    fetch(`admin-calendar-data.php?month=${currentCalendarMonth}&year=${currentCalendarYear}&date=${date}`)
        .then(response => readAdminJsonResponse(response, 'Unable to load reservations for this date.'))
        .then(data => {
            const dayBookings = data.bookings.filter(b => b.booking_date === date);

            if (dayBookings.length === 0) {
                bookingDetailsList.innerHTML = '<p class="no-bookings">No bookings for this date.</p>';
            } else {
                let detailsHTML = '<div class="booking-details-list">';

                dayBookings.forEach(booking => {
                    const isAnnual = booking.is_annual_event || booking.year_start;
                    const safeStatus = safeAdminStatus(booking.status);
                    const statusClass = safeStatus.replace('_', '-');
                    const bookingId = safeAdminId(booking.id);

                    detailsHTML += `
                        <div class="booking-detail-card ${isAnnual ? 'annual-booking' : ''}">
                            <div class="booking-header">
                                <h5>${escapeAdminHtml(booking.dhana_type_name)}</h5>
                                <span class="status-badge status-${statusClass}">
                                    ${escapeAdminHtml(safeStatus.replace('_', ' ').toUpperCase())}
                                </span>
                            </div>
                            <div class="booking-info">
                                <p><strong>Customer:</strong> ${escapeAdminHtml(booking.first_name)} ${escapeAdminHtml(booking.last_name)}</p>
                                <p><strong>Amount:</strong> Rs. ${Number(booking.total_amount).toLocaleString()}</p>
                                <p><strong>Time Slot:</strong> ${escapeAdminHtml(String(booking.booking_time_slot || '').replace('_', ' ').toUpperCase())}</p>
                                ${isAnnual ? '<p class="annual-badge"><i class="fas fa-calendar-check"></i> Annual Event</p>' : ''}
                            </div>
                            <div class="booking-actions">
                                <button class="btn btn-sm btn-primary" onclick="showBookingDetails(${bookingId})">
                                    <i class="fas fa-eye"></i> View full details
                                </button>
                            </div>
                        </div>
                    `;
                });

                detailsHTML += '</div>';
                bookingDetailsList.innerHTML = detailsHTML;
            }

            detailsContainer.style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading booking details:', error);
            bookingDetailsList.innerHTML = `<p class="error">${escapeAdminHtml(error.message)}</p>`;
            detailsContainer.style.display = 'block';
        });
}

// Format date for display
function formatDateForDisplay(date) {
    const dateObj = new Date(date + 'T00:00:00');
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    return dateObj.toLocaleDateString('en-US', options);
}

// Month/Year Picker functionality
function showMonthYearPicker() {
    const monthYearElement = document.getElementById('calendarMonthYear');

    // Create month/year picker modal
    const modal = document.createElement('div');
    modal.className = 'month-year-picker-modal';
    modal.innerHTML = `
        <div class="month-year-picker-content">
            <div class="picker-header">
                <h4>Select Month & Year</h4>
                <button class="close-picker" onclick="closeMonthYearPicker()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="picker-body">
                <div class="year-selector">
                    <label>Year:</label>
                    <select id="yearSelect">
                        ${generateYearOptions()}
                    </select>
                </div>
                <div class="month-selector">
                    <label>Month:</label>
                    <div class="month-grid">
                        ${generateMonthButtons()}
                    </div>
                </div>
            </div>
            <div class="picker-footer">
                <button class="btn btn-secondary" onclick="closeMonthYearPicker()">Cancel</button>
                <button class="btn btn-primary" onclick="applyMonthYearSelection()">Apply</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Set current values
    document.getElementById('yearSelect').value = currentCalendarYear;
    document.querySelector(`.month-btn[data-month="${currentCalendarMonth}"]`).classList.add('selected');

    // Add event listeners
    document.querySelectorAll('.month-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.month-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
}

function generateYearOptions() {
    const currentYear = new Date().getFullYear();
    const startYear = currentYear - 5;
    const endYear = currentYear + 10;
    let options = '';

    for (let year = startYear; year <= endYear; year++) {
        options += `<option value="${year}">${year}</option>`;
    }

    return options;
}

function generateMonthButtons() {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                   'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    let buttons = '';

    months.forEach((month, index) => {
        buttons += `<button class="month-btn" data-month="${index + 1}">${month}</button>`;
    });

    return buttons;
}

function closeMonthYearPicker() {
    const modal = document.querySelector('.month-year-picker-modal');
    if (modal) {
        modal.remove();
    }
}

function applyMonthYearSelection() {
    const selectedYear = parseInt(document.getElementById('yearSelect').value);
    const selectedMonthBtn = document.querySelector('.month-btn.selected');

    if (selectedMonthBtn) {
        const selectedMonth = parseInt(selectedMonthBtn.dataset.month);

        currentCalendarMonth = selectedMonth;
        currentCalendarYear = selectedYear;

        loadCalendarData();
        closeMonthYearPicker();
    }
}

// Edit reservation function
function editBooking(bookingId) {
    // Fetch reservation details first
    fetch(`get-booking-details.php?id=${bookingId}`)
        .then(response => readAdminJsonResponse(response, 'Unable to load reservation details.'))
        .then(booking => {
            showEditBookingModal(booking.data);
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(error.message, 'error');
        });
}

function showEditBookingModal(booking) {
    const bookingId = safeAdminId(booking.id);
    const bookingDate = escapeAdminHtml(booking.booking_date);
    const totalAmount = Number.isFinite(Number(booking.total_amount)) ? Number(booking.total_amount) : 0;
    const modal = document.createElement('div');
    modal.className = 'edit-booking-modal';
    modal.innerHTML = `
        <div class="edit-booking-content">
            <div class="modal-header">
                <h4>Edit Booking #${String(bookingId).padStart(6, '0')}</h4>
                <button class="close-modal" onclick="closeEditBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editBookingForm">
                    <input type="hidden" name="booking_id" value="${bookingId}">

                    ${booking.is_annual_event ? `
                    <div class="form-group annual-edit-scope">
                        <label for="edit_scope">Apply changes to:</label>
                        <select id="edit_scope" name="edit_scope">
                            <option value="occurrence" ${booking.parent_booking_id ? 'selected' : ''}>This occurrence only</option>
                            <option value="series" ${booking.parent_booking_id ? '' : 'selected'}>Entire annual series</option>
                        </select>
                        <small>Changing the date, dāna type, or time slot requires “Entire annual series”. That option applies every field shown here, including status and amount, after checking every affected year.</small>
                    </div>` : '<input type="hidden" name="edit_scope" value="occurrence">'}

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_booking_date">Booking Date:</label>
                            <input type="date" id="edit_booking_date" name="booking_date"
                                   value="${bookingDate}" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_dhana_type">Dhana Type:</label>
                            <select id="edit_dhana_type" name="dhana_type_id" required>
                                ${generateDhanaTypeOptions(booking.dhana_type_id)}
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_time_slot">Time Slot:</label>
                            <select id="edit_time_slot" name="booking_time_slot" required>
                                <option value="morning" ${booking.booking_time_slot === 'morning' ? 'selected' : ''}>Morning</option>
                                <option value="lunch" ${booking.booking_time_slot === 'lunch' ? 'selected' : ''}>Lunch</option>
                                <option value="whole_day" ${booking.booking_time_slot === 'whole_day' ? 'selected' : ''}>Whole Day</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status:</label>
                            <select id="edit_status" name="status" required>
                                <option value="pending" ${booking.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="receipt_submitted" ${booking.status === 'receipt_submitted' ? 'selected' : ''}>Receipt Submitted</option>
                                <option value="payment_pending" ${booking.status === 'payment_pending' ? 'selected' : ''}>Payment Pending</option>
                                <option value="confirmed" ${booking.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                <option value="completed" ${booking.status === 'completed' ? 'selected' : ''}>Completed</option>
                                <option value="cancelled" ${booking.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_total_amount">Total Amount (Rs.):</label>
                        <input type="number" id="edit_total_amount" name="total_amount"
                               value="${totalAmount}" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_special_requests">Special Requests:</label>
                        <textarea id="edit_special_requests" name="special_requests"
                                  rows="3">${escapeAdminHtml(booking.special_requests || '')}</textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="travel_support" value="1"
                                       ${booking.travel_support ? 'checked' : ''}>
                                Travel Support Required
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_monk" value="1"
                                       ${booking.is_monk ? 'checked' : ''}>
                                Monk Booking
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditBookingModal()">Cancel</button>
                ${window.ADMIN_CAPABILITIES?.canDeleteBookings
                    ? `<button class="btn btn-danger" onclick="deleteBooking(${bookingId})">Delete</button>`
                    : ''}
                <button class="btn btn-primary edit-booking-save" onclick="saveBookingChanges()">Save Changes</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
}

function generateDhanaTypeOptions(selectedId) {
    if (!window.adminCalendarData || !window.adminCalendarData.dhanaTypes) {
        return '<option value="">Loading...</option>';
    }

    let options = '';
    window.adminCalendarData.dhanaTypes.forEach(type => {
        const selected = type.id == selectedId ? 'selected' : '';
        options += `<option value="${safeAdminId(type.id)}" ${selected}>${escapeAdminHtml(type.name)} - Rs. ${Number(type.price).toLocaleString()}</option>`;
    });

    return options;
}

function closeEditBookingModal() {
    const modal = document.querySelector('.edit-booking-modal');
    if (modal) {
        modal.remove();
    }
}

function saveBookingChanges() {
    const form = document.getElementById('editBookingForm');
    const formData = new FormData(form);
    formData.set('csrf_token', window.ADMIN_CSRF_TOKEN || '');

    // Show loading state
    const saveBtn = form.closest('.edit-booking-modal').querySelector('.edit-booking-save');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    fetch('update-booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => readAdminJsonResponse(response, 'Unable to update the reservation.'))
    .then(data => {
        closeEditBookingModal();
        loadCalendarData(); // Refresh calendar
        showNotification(data.message || 'Reservation updated successfully!', 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showBookingConflictError(error.message);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Show booking conflict error in a formatted dialog
function showBookingConflictError(errorMessage) {
    // Create a custom modal for error display
    const errorModal = document.createElement('div');
    errorModal.className = 'edit-booking-modal';
    errorModal.style.zIndex = '10001'; // Higher than edit modal

    // Format the error message (preserve line breaks)
    const formattedError = escapeAdminHtml(errorMessage).replace(/\n/g, '<br>');

    errorModal.innerHTML = `
        <div class="edit-booking-content" style="max-width: 600px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white;">
                <h4><i class="fas fa-exclamation-triangle"></i> Booking Conflict Detected</h4>
                <button class="close-modal" onclick="this.closest('.edit-booking-modal').remove()" style="color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                    <strong><i class="fas fa-info-circle"></i> Why did this happen?</strong>
                    <p style="margin: 10px 0 0 0; color: #856404;">
                        The system detected a conflict with existing bookings. This prevents double-booking and ensures data integrity.
                    </p>
                </div>
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; border-radius: 4px; white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                    ${formattedError}
                </div>
                <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; border-radius: 4px; margin-top: 15px;">
                    <strong><i class="fas fa-lightbulb"></i> What can you do?</strong>
                    <ul style="margin: 10px 0 0 20px; color: #0c5460;">
                        <li>Choose a different date</li>
                        <li>Select a different time slot (if available)</li>
                        <li>Cancel the conflicting booking first (if appropriate)</li>
                        <li>Contact the user who made the conflicting booking</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="this.closest('.edit-booking-modal').remove()">
                    <i class="fas fa-check"></i> I Understand
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(errorModal);
}

function deleteBooking(bookingId) {
    if (!confirm('Are you sure you want to delete this reservation? This action cannot be undone.')) {
        return;
    }

    fetch('delete-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            booking_id: bookingId,
            csrf_token: window.ADMIN_CSRF_TOKEN || ''
        })
    })
    .then(response => readAdminJsonResponse(response, 'Unable to delete the reservation.'))
    .then(data => {
        closeEditBookingModal();
        loadCalendarData(); // Refresh calendar
        showNotification(data.message || 'Reservation deleted successfully!', 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message, 'error');
    });
}

// Report Generator Functions
function showReportGenerator() {
    const modal = document.getElementById('reportGeneratorModal');
    modal.style.display = 'flex';

    // Initialize form
    initializeReportGenerator();
}

function closeReportGenerator() {
    const modal = document.getElementById('reportGeneratorModal');
    modal.style.display = 'none';
}

function initializeReportGenerator() {
    // Populate year dropdowns
    const currentYear = new Date().getFullYear();
    const startYear = currentYear - 5;
    const endYear = currentYear + 2;

    const yearSelects = ['monthly_year', 'quarterly_year'];
    yearSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        select.innerHTML = '';

        for (let year = endYear; year >= startYear; year--) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) option.selected = true;
            select.appendChild(option);
        }
    });

    // Set current month
    const currentMonth = new Date().getMonth() + 1;
    document.getElementById('monthly_month').value = currentMonth;

    // Set current quarter
    const currentQuarter = Math.ceil(currentMonth / 3);
    document.getElementById('quarterly_quarter').value = currentQuarter;

    // Populate dhana types
    if (window.adminCalendarData && window.adminCalendarData.dhanaTypes) {
        const dhanaTypeSelect = document.getElementById('report_dhana_type');
        dhanaTypeSelect.innerHTML = '<option value="all">All Types</option>';

        window.adminCalendarData.dhanaTypes.forEach(type => {
            const option = document.createElement('option');
            option.value = type.id;
            option.textContent = `${type.name} - Rs. ${Number(type.price).toLocaleString()}`;
            dhanaTypeSelect.appendChild(option);
        });
    }

    // Add event listeners for report type changes
    const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');
    reportTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            showDateOption(this.value);
        });
    });

    // Show initial date option
    showDateOption('monthly');
}

function showDateOption(reportType) {
    // Hide all date options
    document.querySelectorAll('.date-option').forEach(option => {
        option.style.display = 'none';
    });

    // Show selected option
    document.querySelector(`.${reportType}-option`).style.display = 'block';
}

function generateReport(format, generateBtn) {
    const form = document.getElementById('reportGeneratorForm');
    const modal = form?.closest('.report-generator-modal');
    if (!form || !modal || !generateBtn) {
        showNotification('The report form is unavailable. Refresh the page and try again.', 'error');
        return;
    }
    const formData = new FormData(form);
    formData.set('format', format);
    formData.set('csrf_token', window.ADMIN_CSRF_TOKEN || '');

    // Show loading state
    const buttons = modal.querySelectorAll('.modal-footer .btn');
    buttons.forEach(btn => btn.disabled = true);

    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

    // Validate form
    const reportType = formData.get('report_type');
    let isValid = true;
    let errorMessage = '';

    if (reportType === 'custom') {
        const startDate = formData.get('custom_start_date');
        const endDate = formData.get('custom_end_date');

        if (!startDate || !endDate) {
            isValid = false;
            errorMessage = 'Please select both start and end dates for custom range.';
        } else if (new Date(startDate) > new Date(endDate)) {
            isValid = false;
            errorMessage = 'Start date cannot be after end date.';
        }
    }

    if (!isValid) {
        alert(errorMessage);
        buttons.forEach(btn => btn.disabled = false);
        generateBtn.innerHTML = originalText;
        return;
    }

    // Handle print format differently
    if (format === 'print') {
        // Open report in new window for printing
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generate-report.php';
        form.target = '_blank';

        // Add all form data as hidden inputs
        const reportForm = document.getElementById('reportGeneratorForm');
        const reportFormData = new FormData(reportForm);
        reportFormData.set('format', 'print');
        reportFormData.set('csrf_token', window.ADMIN_CSRF_TOKEN || '');

        for (let [key, value] of reportFormData.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        // Close modal and show success message
        closeReportGenerator();
        showNotification('Print ready report opened in new window!', 'success');

        // Reset button state
        buttons.forEach(btn => btn.disabled = false);
        generateBtn.innerHTML = originalText;
        return;
    }

    // Generate CSV report
    fetch('generate-report.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        if (!response.ok) {
            const contentType = response.headers.get('Content-Type') || '';
            const data = contentType.includes('application/json')
                ? await response.json().catch(() => null)
                : null;
            throw new Error(data?.error || `Report request failed (${response.status}).`);
        }

        // Get filename from response headers
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `dhana_report_${new Date().toISOString().split('T')[0]}.${format}`;

        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="(.+)"/);
            if (filenameMatch) {
                filename = filenameMatch[1];
            }
        }

        const blob = await response.blob();
        return { blob, filename };
    })
    .then(({ blob, filename }) => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Close modal and show success message
        closeReportGenerator();
        showNotification(`${format.toUpperCase()} report generated successfully!`, 'success');
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(error.message || 'Error generating report. Please try again.', 'error');
    })
    .finally(() => {
        buttons.forEach(btn => btn.disabled = false);
        generateBtn.innerHTML = originalText;
    });
}
