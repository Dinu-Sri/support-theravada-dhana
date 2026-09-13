/**
 * Dashboard JavaScript for Dhana Booking System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling for action buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add loading animation
            const icon = this.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';
            
            // Restore icon after a short delay (the page will navigate anyway)
            setTimeout(() => {
                icon.className = originalClass;
            }, 1000);
        });
    });
    
    // Add animation to step guide
    const steps = document.querySelectorAll('.step');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const stepObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    steps.forEach((step, index) => {
        step.style.opacity = '0';
        step.style.transform = 'translateY(30px)';
        step.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
        stepObserver.observe(step);
    });
    
    // Add hover effects to booking items
    const bookingItems = document.querySelectorAll('.booking-item');
    bookingItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Add click-to-expand functionality for booking items
    bookingItems.forEach(item => {
        item.addEventListener('click', function() {
            // This could be expanded to show more details
            // For now, just add a subtle feedback
            this.style.background = '#e3f2fd';
            setTimeout(() => {
                this.style.background = '#f8f9fa';
            }, 200);
        });
    });
    
    // Add keyboard navigation for action buttons
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            // Enhance tab navigation visual feedback
            const focusedElement = document.activeElement;
            if (focusedElement.classList.contains('action-btn')) {
                focusedElement.style.outline = '2px solid #667eea';
                focusedElement.style.outlineOffset = '2px';
            }
        }
    });
    
    // Remove outline when not using keyboard
    document.addEventListener('mousedown', function() {
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach(button => {
            button.addEventListener('blur', function() {
                this.style.outline = 'none';
            });
        });
    });
    
    // Add welcome animation
    const dashboardHeader = document.querySelector('.dashboard-header');
    if (dashboardHeader) {
        dashboardHeader.style.opacity = '0';
        dashboardHeader.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            dashboardHeader.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            dashboardHeader.style.opacity = '1';
            dashboardHeader.style.transform = 'translateY(0)';
        }, 100);
    }
    
    // Add progress indicator for steps
    addStepProgressIndicator();
    
    // Add quick stats if there are bookings
    addQuickStats();
});

// Add step progress indicator
function addStepProgressIndicator() {
    const stepGuide = document.querySelector('.step-guide');
    const steps = document.querySelectorAll('.step');
    
    if (!stepGuide || steps.length === 0) return;
    
    // Create progress bar
    const progressContainer = document.createElement('div');
    progressContainer.className = 'step-progress';
    progressContainer.innerHTML = `
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="progress-text">Follow these steps to complete your booking</div>
    `;
    
    // Add CSS for progress bar
    const style = document.createElement('style');
    style.textContent = `
        .step-progress {
            margin-bottom: 30px;
            text-align: center;
        }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 2s ease;
            border-radius: 2px;
        }
        .progress-text {
            color: #666;
            font-size: 0.9rem;
        }
    `;
    document.head.appendChild(style);
    
    // Insert progress bar
    const stepGuideTitle = stepGuide.querySelector('h2');
    stepGuideTitle.insertAdjacentElement('afterend', progressContainer);
    
    // Animate progress bar
    setTimeout(() => {
        const progressFill = progressContainer.querySelector('.progress-fill');
        progressFill.style.width = '100%';
    }, 500);
}

// Add quick stats
function addQuickStats() {
    const bookingsList = document.querySelector('.bookings-list');
    if (!bookingsList) return;
    
    const bookingItems = bookingsList.querySelectorAll('.booking-item');
    if (bookingItems.length === 0) return;
    
    // Count bookings by status
    const stats = {};
    bookingItems.forEach(item => {
        const statusBadge = item.querySelector('.status-badge');
        if (statusBadge) {
            const status = statusBadge.textContent.trim().toLowerCase();
            stats[status] = (stats[status] || 0) + 1;
        }
    });
    
    // Create stats display
    const statsContainer = document.createElement('div');
    statsContainer.className = 'booking-stats';
    statsContainer.innerHTML = `
        <h4><i class="fas fa-chart-pie"></i> Quick Stats</h4>
        <div class="stats-grid">
            ${Object.entries(stats).map(([status, count]) => `
                <div class="stat-item">
                    <div class="stat-number">${count}</div>
                    <div class="stat-label">${status.charAt(0).toUpperCase() + status.slice(1)}</div>
                </div>
            `).join('')}
        </div>
    `;
    
    // Add CSS for stats
    const style = document.createElement('style');
    style.textContent = `
        .booking-stats {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #d4822a;
        }
        .booking-stats h4 {
            margin: 0 0 15px 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .booking-stats h4 i {
            color: #d4822a;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #d4822a;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
        }
    `;
    document.head.appendChild(style);
    
    // Insert stats before bookings list
    const bookingsSection = bookingsList.closest('.step-guide');
    const bookingsTitle = bookingsSection.querySelector('h2');
    bookingsTitle.insertAdjacentElement('afterend', statsContainer);
}

// Add notification system for updates
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.dashboard-notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.className = `dashboard-notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add CSS for notifications
    const style = document.createElement('style');
    style.textContent = `
        .dashboard-notification {
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
        .notification-info { border-left: 4px solid #17a2b8; }
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notification-content i {
            font-size: 1.2rem;
        }
        .notification-success i { color: #28a745; }
        .notification-error i { color: #dc3545; }
        .notification-info i { color: #17a2b8; }
        .notification-close {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 5px;
            margin-left: 15px;
        }
        .notification-close:hover { color: #333; }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 480px) {
            .dashboard-notification {
                right: 10px;
                left: 10px;
                min-width: auto;
            }
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

// Check for URL parameters to show notifications
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const type = urlParams.get('type') || 'info';
    
    if (message) {
        showNotification(decodeURIComponent(message), type);
        
        // Clean URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
});
