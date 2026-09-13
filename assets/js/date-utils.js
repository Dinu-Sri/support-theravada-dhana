/**
 * Date Utility Functions
 * Timezone-safe date formatting and manipulation
 */

/**
 * Format a Date object to YYYY-MM-DD string (timezone-safe)
 * This avoids the timezone shift bug that occurs with toISOString()
 * 
 * @param {Date} date - The date object to format
 * @returns {string} - Date string in YYYY-MM-DD format
 */
function formatDateForInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Format a date string or Date object for display
 * 
 * @param {string|Date} date - The date to format
 * @param {object} options - Intl.DateTimeFormat options
 * @returns {string} - Formatted date string
 */
function formatDateForDisplay(date, options = {}) {
    const defaultOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    
    const dateObj = typeof date === 'string' ? new Date(date + 'T00:00:00') : date;
    return dateObj.toLocaleDateString('en-US', { ...defaultOptions, ...options });
}

/**
 * Get tomorrow's date (timezone-safe)
 * 
 * @returns {Date} - Tomorrow's date
 */
function getTomorrow() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(0, 0, 0, 0);
    return tomorrow;
}

/**
 * Get today's date (timezone-safe)
 * 
 * @returns {Date} - Today's date at midnight
 */
function getToday() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
}

/**
 * Add days to a date (timezone-safe)
 * 
 * @param {Date} date - The starting date
 * @param {number} days - Number of days to add (can be negative)
 * @returns {Date} - New date object
 */
function addDays(date, days) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
}

/**
 * Add years to a date (timezone-safe)
 * 
 * @param {Date} date - The starting date
 * @param {number} years - Number of years to add
 * @returns {Date} - New date object
 */
function addYears(date, years) {
    const result = new Date(date);
    result.setFullYear(result.getFullYear() + years);
    return result;
}

/**
 * Check if a date is in the past
 * 
 * @param {string|Date} date - The date to check
 * @returns {boolean} - True if date is in the past
 */
function isDateInPast(date) {
    const dateObj = typeof date === 'string' ? new Date(date + 'T00:00:00') : date;
    const today = getToday();
    return dateObj < today;
}

/**
 * Check if a date is in the future
 * 
 * @param {string|Date} date - The date to check
 * @returns {boolean} - True if date is in the future
 */
function isDateInFuture(date) {
    const dateObj = typeof date === 'string' ? new Date(date + 'T00:00:00') : date;
    const today = getToday();
    return dateObj > today;
}

/**
 * Parse a date string safely (avoids timezone issues)
 * 
 * @param {string} dateString - Date string in YYYY-MM-DD format
 * @returns {Date} - Date object at midnight local time
 */
function parseDateSafe(dateString) {
    // Add T00:00:00 to ensure local timezone interpretation
    return new Date(dateString + 'T00:00:00');
}

/**
 * Get the difference in days between two dates
 * 
 * @param {Date} date1 - First date
 * @param {Date} date2 - Second date
 * @returns {number} - Number of days difference
 */
function getDaysDifference(date1, date2) {
    const oneDay = 24 * 60 * 60 * 1000; // milliseconds in a day
    const diffTime = Math.abs(date2 - date1);
    return Math.round(diffTime / oneDay);
}

/**
 * Format a date for filename (YYYY-MM-DD)
 * 
 * @param {Date} date - The date to format
 * @returns {string} - Date string suitable for filenames
 */
function formatDateForFilename(date = new Date()) {
    return formatDateForInput(date);
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatDateForInput,
        formatDateForDisplay,
        getTomorrow,
        getToday,
        addDays,
        addYears,
        isDateInPast,
        isDateInFuture,
        parseDateSafe,
        getDaysDifference,
        formatDateForFilename
    };
}

