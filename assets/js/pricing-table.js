/**
 * Pricing Table JavaScript
 * Handles modal interactions and dynamic updates
 */

// Edit Price Modal
function editPrice(pricingId, currentPrice, notes) {
    document.getElementById('edit_pricing_id').value = pricingId;
    document.getElementById('edit_price').value = currentPrice;
    document.getElementById('edit_notes').value = notes || '';
    showModal('editPriceModal');
}

// Show Bulk Update Modal
function showBulkUpdateModal() {
    showModal('bulkUpdateModal');
}

// Show Add Month Modal
function showAddMonthModal() {
    showModal('addMonthModal');
}

// Show Remove Month Modal
function showRemoveMonthModal() {
    showModal('removeMonthModal');
}

// Show Pricing History
function showPricingHistory() {
    window.location.href = 'pricing-history.php';
}

// Show Modal
let pricingModalTrigger = null;
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        pricingModalTrigger = document.activeElement;
        modal.classList.add('show');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.querySelector('.modal-content')?.focus());
    }
}

// Close Modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        pricingModalTrigger?.focus?.();
    }
}

document.addEventListener('click', event => {
    if (event.target.classList?.contains('modal') && event.target.classList.contains('show')) {
        closeModal(event.target.id);
    }
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const openModal = document.querySelector('.modal.show');
    if (openModal) closeModal(openModal.id);
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
        event.target.style.display = 'none';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            modal.classList.remove('show');
            modal.style.display = 'none';
        });
    }
});

// Update bulk update label based on type
function updateBulkLabel() {
    const updateType = document.getElementById('bulk_update_type').value;
    const label = document.getElementById('bulk_value_label');
    
    if (updateType === 'percentage') {
        label.textContent = 'Percentage (%)';
    } else {
        label.textContent = 'Fixed Amount (LKR)';
    }
}

// Change display months
function changeDisplayMonths(months) {
    const url = new URL(window.location.href);
    url.searchParams.set('display_months', months);
    url.searchParams.set('start_offset', '0'); // Reset to current month
    window.location.href = url.toString();
}

// Navigate months
function navigateMonths(offset) {
    const url = new URL(window.location.href);
    const currentOffset = parseInt(url.searchParams.get('start_offset') || '0');
    const newOffset = currentOffset + offset;
    
    if (newOffset >= 0) {
        url.searchParams.set('start_offset', newOffset);
        window.location.href = url.toString();
    }
}

// Confirm bulk update
document.addEventListener('DOMContentLoaded', function() {
    const bulkForm = document.querySelector('form[name="bulk_update"]');
    if (bulkForm) {
        bulkForm.addEventListener('submit', async function(e) {
            const dhanaType = document.getElementById('bulk_dhana_type').selectedOptions[0].text;
            const updateType = document.getElementById('bulk_update_type').value;
            const updateValue = document.getElementById('bulk_update_value').value;
            
            const message = `Are you sure you want to apply a ${updateType} ${updateType === 'percentage' ? 'of' : 'change of'} ${updateValue}${updateType === 'percentage' ? '%' : ' LKR'} to ${dhanaType}?`;
            
            if (bulkForm.dataset.confirmed === '1') return;
            e.preventDefault();
            if (await window.adminConfirm(message, { title: 'Apply bulk price update?', confirmText: 'Apply update' })) {
                bulkForm.dataset.confirmed = '1';
                bulkForm.requestSubmit(e.submitter);
            }
        });
    }
});

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

// Highlight current month column
document.addEventListener('DOMContentLoaded', function() {
    const currentMonthCells = document.querySelectorAll('.current-month');
    currentMonthCells.forEach(cell => {
        cell.style.animation = 'pulse 2s ease-in-out infinite';
    });
});

// Add pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(33, 150, 243, 0.4);
        }
        50% {
            box-shadow: 0 0 0 5px rgba(33, 150, 243, 0.1);
        }
    }
`;
document.head.appendChild(style);
