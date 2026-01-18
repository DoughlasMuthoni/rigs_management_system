// Main JavaScript functions

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// Format number as currency
function formatCurrency(amount) {
    return 'Ksh ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Auto-calculate totals in forms
function calculateTotal() {
    let total = 0;
    
    // Sum all expense inputs
    document.querySelectorAll('input[type="number"]').forEach(input => {
        if (input.value && !isNaN(input.value)) {
            total += parseFloat(input.value);
        }
    });
    
    // Update total display if exists
    const totalDisplay = document.getElementById('totalExpenses');
    if (totalDisplay) {
        totalDisplay.textContent = formatCurrency(total);
    }
    
    return total;
}

// Add event listeners to number inputs
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate on input change
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calculateTotal);
    });
    
    // Initialize calculation
    calculateTotal();
    
    // Print functionality
    const printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Date validation
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('completion_date');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = this.value;
        });
        
        endDate.addEventListener('change', function() {
            if (this.value < startDate.value) {
                alert('Completion date cannot be before start date');
                this.value = startDate.value;
            }
        });
    }
});