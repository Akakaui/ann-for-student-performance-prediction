document.addEventListener('DOMContentLoaded', function() {
    initFormValidation();
    initGradeSelection();
    initAlertDismiss();
});

function initAlertDismiss() {
    const alerts = document.querySelectorAll('.auth-alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

function initGradeSelection() {
    const selects = document.querySelectorAll('.grade-select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            const item = this.closest('.grade-item');
            if (!item) return;
            const val = this.value;
            item.style.borderColor = val ? getGradeColor(val) : '';
        });
    });
}

function getGradeColor(grade) {
    const colors = {
        'A1': '#10b981', 'B2': '#14b8a6', 'B3': '#06b6d4',
        'C4': '#f59e0b', 'C5': '#f97316', 'C6': '#ef4444',
        'D7': '#dc2626', 'E8': '#b91c1c', 'F9': '#7f1d1d'
    };
    return colors[grade] || 'rgba(255,255,255,0.06)';
}

function initFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('.form-input[required], .form-select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });
            input.addEventListener('input', function() {
                if (this.value.trim()) this.classList.remove('error');
            });
        });
    });
}

function formatPercentage(value) {
    return Math.round(value) + '%';
}

function getPerformanceColor(percentage) {
    if (percentage >= 70) return '#10b981';
    if (percentage >= 50) return '#f59e0b';
    return '#ef4444';
}
