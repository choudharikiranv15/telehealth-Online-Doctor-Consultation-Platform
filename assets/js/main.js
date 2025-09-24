// TeleHealth Platform Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initTooltips();
    initFormValidation();
    initTableSorting();
    initNotifications();
    initAnimations();
    initDropdownFix();
});

/**
 * Fix dropdown positioning and z-index issues
 */
function initDropdownFix() {
    // Get all dropdown elements
    const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');

    dropdownElements.forEach(dropdown => {
        dropdown.addEventListener('shown.bs.dropdown', function() {
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                // Force positioning
                menu.style.position = 'fixed';
                menu.style.zIndex = '99999';

                // Calculate position relative to trigger button
                const rect = this.getBoundingClientRect();
                menu.style.top = (rect.bottom + 5) + 'px';
                menu.style.left = (rect.right - menu.offsetWidth) + 'px';

                // Ensure menu doesn't go off-screen
                if (menu.getBoundingClientRect().right > window.innerWidth) {
                    menu.style.left = (window.innerWidth - menu.offsetWidth - 10) + 'px';
                }
            }
        });

        dropdown.addEventListener('hidden.bs.dropdown', function() {
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                // Reset styles
                menu.style.position = '';
                menu.style.zIndex = '';
                menu.style.top = '';
                menu.style.left = '';
            }
        });
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
}

/**
 * Initialize table sorting
 */
function initTableSorting() {
    const sortableTables = document.querySelectorAll('.table-sortable');
    
    sortableTables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.sort;
                const order = this.dataset.order === 'asc' ? 'desc' : 'asc';
                
                // Update all headers
                headers.forEach(h => h.dataset.order = '');
                this.dataset.order = order;
                
                // Sort table
                sortTable(table, column, order);
            });
        });
    });
}

/**
 * Sort table by column
 */
function sortTable(table, column, order) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.querySelector(`td[data-${column}]`).textContent.trim();
        const bValue = b.querySelector(`td[data-${column}]`).textContent.trim();
        
        if (order === 'asc') {
            return aValue.localeCompare(bValue);
        } else {
            return bValue.localeCompare(aValue);
        }
    });
    
    // Clear and re-append rows
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Initialize notifications
 */
function initNotifications() {
    // Check for URL parameters to show notifications
    const urlParams = new URLSearchParams(window.location.search);
    const message = urlParams.get('message');
    const type = urlParams.get('type') || 'info';
    
    if (message) {
        showNotification(message, type);
        // Remove from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the main content
    const main = document.querySelector('main');
    if (main) {
        main.insertBefore(alertDiv, main.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

/**
 * Initialize animations
 */
function initAnimations() {
    // Add animation classes to elements as they come into view
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe all cards and sections
    document.querySelectorAll('.card, section').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format time for display
 */
function formatTime(timeString) {
    const time = new Date(`2000-01-01T${timeString}`);
    return time.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Confirm action with custom message
 */
function confirmAction(message = 'Are you sure you want to proceed?') {
    return confirm(message);
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = '<div class="spinner"></div> Loading...';
        element.disabled = true;
    }
}

/**
 * Hide loading spinner
 */
function hideLoading(element, originalText) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = originalText;
        element.disabled = false;
    }
}

/**
 * Make AJAX request
 */
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin'
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    return fetch(url, finalOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            throw error;
        });
}

/**
 * Update appointment status
 */
function updateAppointmentStatus(appointmentId, status) {
    if (!confirmAction('Are you sure you want to update this appointment status?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('action', status);
    
    fetch('update_appointment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Reload page or update UI
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating the appointment.', 'danger');
    });
}

/**
 * Search functionality for tables
 */
function initTableSearch() {
    const searchInputs = document.querySelectorAll('.table-search');
    
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.card').querySelector('table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableSelector, filename = 'export.csv') {
    const table = document.querySelector(tableSelector);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        cols.forEach(col => {
            rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

/**
 * Print element
 */
function printElement(elementSelector) {
    const element = document.querySelector(elementSelector);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <link href="assets/css/style.css" rel="stylesheet">
                <style>
                    @media print {
                        body { font-size: 12pt; }
                        .no-print { display: none !important; }
                    }
                </style>
            </head>
            <body>
                ${element.outerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}

/**
 * Initialize all search functionality
 */
function initSearch() {
    initTableSearch();
}

// Initialize search when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initSearch();
});

// Export functions to global scope
window.TeleHealth = {
    showNotification,
    confirmAction,
    showLoading,
    hideLoading,
    makeRequest,
    updateAppointmentStatus,
    exportTableToCSV,
    printElement,
    formatDate,
    formatTime,
    formatCurrency
};
