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
Chart.defaults.font.family = 'Inter, "Noto Sans Sinhala", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#666';

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
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
                    label: 'Revenue (LKR)',
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
                                return `${context.dataset.label}: LKR ${context.parsed.y.toFixed(2)}`;
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
                        text: 'Revenue (LKR)'
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
                label: 'Revenue (LKR)',
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
                            return `Revenue: LKR ${context.parsed.y.toFixed(2)}`;
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
                        text: 'Revenue (LKR)'
                    },
                    grid: {
                        color: '#f1f3f4'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'LKR ' + value.toFixed(0);
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
    return new Intl.NumberFormat('en-LK', {
        style: 'currency',
        currency: 'LKR'
    }).format(amount);
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
