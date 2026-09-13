/**
 * Analytics Dashboard JavaScript
 * Handles chart rendering and interactions
 */

// Buddhist theme colors
const colors = {
    primary: '#d4822a',
    secondary: '#b8860b',
    success: '#28a745',
    warning: '#ffc107',
    danger: '#dc3545',
    info: '#17a2b8',
    light: '#f8f9fa',
    dark: '#343a40'
};

// Chart color palettes
const chartColors = [
    '#d4822a', '#b8860b', '#28a745', '#17a2b8', '#ffc107', 
    '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#6c757d'
];

// Chart.js default configuration
Chart.defaults.font.family = 'Arial, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#666';

// Carousel state
let currentSlide = 0;
const totalSlides = 4;
const slideData = [
    { title: 'Reservation Status Distribution', icon: 'fas fa-chart-pie' },
    { title: 'Monthly Trends', icon: 'fas fa-chart-line' },
    { title: 'Dāna Type Distribution', icon: 'fas fa-chart-donut' },
    { title: 'Revenue by Status', icon: 'fas fa-chart-bar' }
];

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeCarousel();
});

function initializeCharts() {
    // Initialize charts with a slight delay to ensure DOM is ready
    setTimeout(() => {
        // Status Distribution Pie Chart
        if (statusData && statusData.length > 0) {
            createStatusChart();
        }

        // Monthly Trends Line Chart (create but may not be visible initially)
        if (monthlyData && monthlyData.length > 0) {
            createMonthlyChart();
        }

        // Dhana Type Distribution Doughnut Chart
        if (dhanaTypeData && dhanaTypeData.length > 0) {
            createDhanaTypeChart();
        }

        // Revenue by Status Bar Chart
        if (statusData && statusData.length > 0) {
            createRevenueChart();
        }
    }, 100);
}

function createStatusChart() {
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    const labels = statusData.map(item => capitalizeFirst(item.status.replace('_', ' ')));
    const data = statusData.map(item => parseInt(item.count));
    const backgroundColors = chartColors.slice(0, statusData.length);
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                duration: 1000
            }
        }
    });
}

function createMonthlyChart() {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    
    const labels = monthlyData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    
    const reservationData = monthlyData.map(item => parseInt(item.reservations));
    const revenueData = monthlyData.map(item => parseFloat(item.revenue));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Reservations',
                    data: reservationData,
                    borderColor: colors.primary,
                    backgroundColor: colors.primary + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Revenue ($)',
                    data: revenueData,
                    borderColor: colors.success,
                    backgroundColor: colors.success + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 1) {
                                return `${context.dataset.label}: $${context.parsed.y.toFixed(2)}`;
                            }
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month'
                    },
                    grid: {
                        color: '#f1f3f4'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Reservations'
                    },
                    grid: {
                        color: '#f1f3f4'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeInOutQuart'
            }
        }
    });
}

function createDhanaTypeChart() {
    const ctx = document.getElementById('dhanaTypeChart').getContext('2d');
    
    const labels = dhanaTypeData.map(item => capitalizeFirst(item.dhana_type.replace('_', ' ')));
    const data = dhanaTypeData.map(item => parseInt(item.count));
    const backgroundColors = chartColors.slice(0, dhanaTypeData.length);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderColor: '#fff',
                borderWidth: 3,
                hoverBorderWidth: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateRotate: true,
                duration: 1200
            }
        }
    });
}

function createRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    const labels = statusData.map(item => capitalizeFirst(item.status.replace('_', ' ')));
    const data = statusData.map(item => parseFloat(item.revenue || 0));
    const backgroundColors = chartColors.slice(0, statusData.length);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue ($)',
                data: data,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors.map(color => color + 'CC'),
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Revenue: $${context.parsed.y.toFixed(2)}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    },
                    grid: {
                        color: '#f1f3f4'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(0);
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutBounce'
            }
        }
    });
}

// Utility functions
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Carousel Functions
function initializeCarousel() {
    updateCarouselState();

    // Add click handlers for indicators
    document.querySelectorAll('.indicator').forEach((indicator, index) => {
        indicator.addEventListener('click', () => goToSlide(index));
    });

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            previousSlide();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
        }
    });

    // Auto-advance slides (optional)
    // setInterval(nextSlide, 10000); // Uncomment for auto-advance every 10 seconds
}

function goToSlide(slideIndex) {
    if (slideIndex < 0 || slideIndex >= totalSlides) return;

    const slides = document.querySelectorAll('.chart-slide');
    const indicators = document.querySelectorAll('.indicator');

    // Remove active classes
    slides[currentSlide].classList.remove('active');
    slides[currentSlide].classList.add('prev');
    indicators[currentSlide].classList.remove('active');

    // Update current slide
    currentSlide = slideIndex;

    // Add active classes
    slides[currentSlide].classList.remove('prev');
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');

    // Update title and icon
    updateCarouselState();

    // Trigger chart resize for the active slide
    setTimeout(() => {
        const activeSlide = slides[currentSlide];
        const canvas = activeSlide.querySelector('canvas');
        if (canvas && window.Chart) {
            const chartInstance = Chart.getChart(canvas);
            if (chartInstance) {
                chartInstance.resize();
            }
        }
    }, 100);

    // Clean up prev classes after animation
    setTimeout(() => {
        slides.forEach(slide => slide.classList.remove('prev'));
    }, 500);
}

function nextSlide() {
    const nextIndex = (currentSlide + 1) % totalSlides;
    goToSlide(nextIndex);
}

function previousSlide() {
    const prevIndex = (currentSlide - 1 + totalSlides) % totalSlides;
    goToSlide(prevIndex);
}

function updateCarouselState() {
    // Update title and icon
    const titleElement = document.getElementById('currentChartTitle');
    const iconElement = titleElement.parentElement.querySelector('i');

    if (titleElement && iconElement) {
        titleElement.textContent = slideData[currentSlide].title;
        iconElement.className = slideData[currentSlide].icon;
    }

    // Update navigation buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (prevBtn && nextBtn) {
        prevBtn.disabled = false;
        nextBtn.disabled = false;

        // Optional: Disable buttons at start/end
        // prevBtn.disabled = currentSlide === 0;
        // nextBtn.disabled = currentSlide === totalSlides - 1;
    }
}

function formatNumber(number) {
    return new Intl.NumberFormat('en-US').format(number);
}

// Export data functionality
function exportChartData(chartType) {
    let data, filename;
    
    switch(chartType) {
        case 'status':
            data = statusData;
            filename = 'status_distribution.json';
            break;
        case 'monthly':
            data = monthlyData;
            filename = 'monthly_trends.json';
            break;
        case 'dhana_type':
            data = dhanaTypeData;
            filename = 'dhana_type_distribution.json';
            break;
        default:
            return;
    }
    
    const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: 'application/json'
    });
    
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
