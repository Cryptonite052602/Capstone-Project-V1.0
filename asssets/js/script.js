document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', showTooltip);
        trigger.addEventListener('mouseleave', hideTooltip);
    });

    // Notification handling
    const notificationCloseButtons = document.querySelectorAll('.notification-close');
    notificationCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.notification').remove();
        });
    });

    // AJAX form submissions
    const ajaxForms = document.querySelectorAll('form.ajax-form');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', handleAjaxFormSubmit);
    });

    // Toggle mobile menu
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
});

function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'absolute z-50 bg-gray-800 text-white text-xs rounded py-1 px-2 whitespace-nowrap';
    tooltip.textContent = tooltipText;
    
    const rect = this.getBoundingClientRect();
    tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
    tooltip.style.left = `${rect.left + window.scrollX}px`;
    
    tooltip.id = 'current-tooltip';
    document.body.appendChild(tooltip);
}

function hideTooltip() {
    const tooltip = document.getElementById('current-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

function handleAjaxFormSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.textContent;
    
    // Show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
    
    fetch(form.action, {
        method: form.method,
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showAlert('success', data.message || 'Action completed successfully');
            
            // Reset form if needed
            if (form.dataset.reset === 'true') {
                form.reset();
            }
            
            // Reload or redirect if needed
            if (data.redirect) {
                window.location.href = data.redirect;
            } else if (data.reload) {
                window.location.reload();
            }
        } else {
            // Show error message
            showAlert('error', data.message || 'An error occurred');
        }
    })
    .catch(error => {
        showAlert('error', 'Network error. Please try again.');
    })
    .finally(() => {
        // Restore button state
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    });
}

function showAlert(type, message) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    const alert = document.createElement('div');
    
    alert.className = `notification alert alert-${type} p-4 mb-4 rounded-lg border ${type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'}`;
    alert.innerHTML = `
        <div class="flex justify-between items-center">
            <div>${message}</div>
            <button class="notification-close text-${type === 'success' ? 'green' : 'red'}-700 hover:text-${type === 'success' ? 'green' : 'red'}-900">
                &times;
            </button>
        </div>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.className = 'fixed top-4 right-4 w-80 z-50';
    document.body.appendChild(container);
    return container;
}

// Date picker initialization
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
}

// Initialize when DOM is loaded
initDatePickers();