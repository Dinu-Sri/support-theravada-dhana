/**
 * Payment Page JavaScript for Dhana Booking System
 */

document.addEventListener('DOMContentLoaded', function() {
    // File upload functionality
    const fileInput = document.getElementById('receipt');
    const fileUploadArea = document.querySelector('.file-upload-area');
    const fileUploadText = document.querySelector('.file-upload-text');

    if (fileInput && fileUploadArea) {
        // Click to select file - only trigger on the text area, not the input itself
        fileUploadText.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event bubbling
            fileInput.click();
        });

        // File selection change
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                // Manually set the files to the input
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(files[0]);
                fileInput.files = dataTransfer.files;
                handleFileSelection(files[0]);
            }
        });
    }
    
    // Form validation
    const uploadForm = document.querySelector('.upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            if (!validateUploadForm()) {
                e.preventDefault();
            } else {
                // Show loading state
                const submitButton = this.querySelector('button[type="submit"]');
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                submitButton.disabled = true;
                
                // If form validation passes, it will submit normally
                // Restore button state if there's an error
                setTimeout(() => {
                    if (submitButton.disabled) {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }
                }, 10000);
            }
        });
    }
});

// Handle file selection
function handleFileSelection(file) {
    const fileUploadText = document.querySelector('.file-upload-text');
    const fileUploadArea = document.querySelector('.file-upload-area');
    
    if (!file) {
        resetFileUploadArea();
        return;
    }
    
    // Validate file
    const validation = validateFile(file);
    if (!validation.valid) {
        showFileError(validation.error);
        resetFileUploadArea();
        return;
    }
    
    // Show selected file info
    const fileSize = formatFileSize(file.size);
    const fileName = file.name;
    
    fileUploadText.innerHTML = `
        <i class="fas fa-file-check" style="color: #28a745;"></i>
        <p><strong>${fileName}</strong></p>
        <small>Size: ${fileSize} | Ready to upload</small>
    `;
    
    fileUploadArea.style.borderColor = '#28a745';
    fileUploadArea.style.background = 'rgba(40, 167, 69, 0.05)';
}

// Reset file upload area
function resetFileUploadArea() {
    const fileUploadText = document.querySelector('.file-upload-text');
    const fileUploadArea = document.querySelector('.file-upload-area');
    
    fileUploadText.innerHTML = `
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Click to select receipt file or drag and drop</p>
        <small>Supported formats: JPG, PNG, PDF (Max 5MB)</small>
    `;
    
    fileUploadArea.style.borderColor = '#e1e5e9';
    fileUploadArea.style.background = 'transparent';
}

// Validate file
function validateFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    
    if (file.size > maxSize) {
        return {
            valid: false,
            error: 'File size must be less than 5MB'
        };
    }
    
    if (!allowedTypes.includes(file.type)) {
        return {
            valid: false,
            error: 'Only JPG, PNG, and PDF files are allowed'
        };
    }
    
    return { valid: true };
}

// Show file error
function showFileError(error) {
    const fileUploadText = document.querySelector('.file-upload-text');
    const fileUploadArea = document.querySelector('.file-upload-area');
    
    fileUploadText.innerHTML = `
        <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
        <p style="color: #dc3545;"><strong>Error: ${error}</strong></p>
        <small>Please select a valid file</small>
    `;
    
    fileUploadArea.style.borderColor = '#dc3545';
    fileUploadArea.style.background = 'rgba(220, 53, 69, 0.05)';
    
    // Reset after 3 seconds
    setTimeout(resetFileUploadArea, 3000);
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Validate upload form
function validateUploadForm() {
    const errors = [];
    
    // Check payment method
    const paymentMethod = document.getElementById('payment_method').value;
    if (!paymentMethod) {
        errors.push('Please select a payment method');
        highlightField('payment_method', false);
    } else {
        highlightField('payment_method', true);
    }
    
    // Check payment reference
    const paymentReference = document.getElementById('payment_reference').value.trim();
    if (!paymentReference) {
        errors.push('Please enter payment reference/transaction ID');
        highlightField('payment_reference', false);
    } else {
        highlightField('payment_reference', true);
    }
    
    // Check file selection
    const fileInput = document.getElementById('receipt');
    if (!fileInput.files || fileInput.files.length === 0) {
        errors.push('Please select a receipt file to upload');
    } else {
        const file = fileInput.files[0];
        const validation = validateFile(file);
        if (!validation.valid) {
            errors.push(validation.error);
        }
    }
    
    if (errors.length > 0) {
        showValidationErrors(errors);
        return false;
    }
    
    return true;
}

// Highlight form field
function highlightField(fieldId, isValid) {
    const field = document.getElementById(fieldId);
    const formGroup = field.closest('.form-group');
    
    // Remove existing validation classes
    formGroup.classList.remove('error', 'success');
    
    // Add appropriate class
    if (isValid) {
        formGroup.classList.add('success');
    } else {
        formGroup.classList.add('error');
    }
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
    const form = document.querySelector('.upload-form');
    form.insertBefore(errorDiv, form.firstChild);
    
    // Scroll to error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.remove();
        }
    }, 10000);
}

// Add real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const paymentReferenceInput = document.getElementById('payment_reference');
    
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            if (this.value) {
                highlightField('payment_method', true);
            }
        });
    }
    
    if (paymentReferenceInput) {
        paymentReferenceInput.addEventListener('input', function() {
            if (this.value.trim()) {
                highlightField('payment_reference', true);
            }
        });
        
        paymentReferenceInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                highlightField('payment_reference', false);
            }
        });
    }
});





// DISABLED: Print functionality moved to payment.php inline script
// This was creating a duplicate print button
/*
// Add print functionality for booking summary
document.addEventListener('DOMContentLoaded', function() {
    const bookingSummary = document.querySelector('.booking-summary-card');
    if (bookingSummary) {
        const printButton = document.createElement('button');
        printButton.type = 'button';
        printButton.className = 'btn btn-secondary';
        printButton.innerHTML = '<i class="fas fa-print"></i> Print Summary';
        printButton.style.marginTop = '20px';

        printButton.addEventListener('click', function() {
            printBookingSummary();
        });

        bookingSummary.appendChild(printButton);
    }
});

// Print booking summary
function printBookingSummary() {
    const summaryContent = document.querySelector('.booking-summary-card').cloneNode(true);

    // Remove the print button from the cloned content
    const printBtn = summaryContent.querySelector('button');
    if (printBtn) {
        printBtn.remove();
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Booking Summary</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .booking-summary-card { border: 1px solid #ddd; padding: 20px; }
                .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .summary-item { margin-bottom: 10px; }
                .summary-item label { font-weight: bold; }
                .summary-item.total { grid-column: 1 / -1; border-top: 2px solid #333; padding-top: 10px; margin-top: 15px; }
                .special-requests { margin-top: 15px; padding: 10px; background: #f5f5f5; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>Dhana Booking Summary</h1>
            ${summaryContent.outerHTML}
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
*/
