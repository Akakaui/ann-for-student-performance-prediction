// Student Performance Predictor - JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Grade selection functionality
    initializeGradeSelection();
    
    // Form validation enhancements
    initializeFormValidation();
});

// Grade Selection System
function initializeGradeSelection() {
    const gradeBadges = document.querySelectorAll('.grade-badge');
    
    gradeBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            const select = this.closest('.input-group').querySelector('select');
            const gradeValue = this.getAttribute('data-grade');
            
            // Update select value
            select.value = gradeValue;
            
            // Update visual selection
            gradeBadges.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Update badge appearance based on grade
            updateGradeBadgeAppearance(this, gradeValue);
        });
    });
}

function updateGradeBadgeAppearance(badge, grade) {
    // Remove all grade color classes
    badge.classList.remove('grade-A1', 'grade-B2', 'grade-B3', 'grade-C4', 'grade-C5', 
                          'grade-C6', 'grade-D7', 'grade-E8', 'grade-F9');
    
    // Add appropriate grade color class
    const gradeClass = getGradeColorClass(grade);
    if (gradeClass) {
        badge.classList.add(gradeClass);
    }
}

function getGradeColorClass(grade) {
    const gradeMap = {
        '1': 'grade-A1',
        '2': 'grade-B2',
        '3': 'grade-B3',
        '4': 'grade-C4',
        '5': 'grade-C5',
        '6': 'grade-C6',
        '7': 'grade-D7',
        '8': 'grade-E8',
        '9': 'grade-F9'
    };
    return gradeMap[grade] || '';
}

// Form Validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            this.classList.add('was-validated');
        });
    });
}

// Subject Management
function addSubject() {
    const subjectContainer = document.getElementById('subjectContainer');
    const subjectCount = subjectContainer.querySelectorAll('.subject-row').length;
    
    if (subjectCount >= 9) {
        alert('Maximum of 9 subjects allowed');
        return;
    }
    
    const newSubjectRow = document.createElement('div');
    newSubjectRow.className = 'subject-row';
    newSubjectRow.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <label class="form-label">Subject Name</label>
                <input type="text" class="form-control" name="subjects[${subjectCount}][name]" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Grade</label>
                <div class="input-group">
                    <select class="form-select" name="subjects[${subjectCount}][grade]" required>
                        <option value="">Select Grade</option>
                        <option value="1">A1 (Excellent)</option>
                        <option value="2">B2 (Very Good)</option>
                        <option value="3">B3 (Good)</option>
                        <option value="4">C4 (Credit)</option>
                        <option value="5">C5 (Credit)</option>
                        <option value="6">C6 (Credit)</option>
                        <option value="7">D7 (Pass)</option>
                        <option value="8">E8 (Pass)</option>
                        <option value="9">F9 (Fail)</option>
                    </select>
                    <div class="input-group-text p-0">
                        <div class="btn-group btn-group-sm" role="group">
                            <span class="grade-badge btn btn-outline-secondary" data-grade="1">A1</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="2">B2</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="3">B3</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="4">C4</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="5">C5</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="6">C6</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="7">D7</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="8">E8</span>
                            <span class="grade-badge btn btn-outline-secondary" data-grade="9">F9</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-danger remove-subject" onclick="removeSubject(this)">
                <i class="bi bi-trash"></i> Remove Subject
            </button>
        </div>
    `;
    
    subjectContainer.appendChild(newSubjectRow);
    initializeGradeSelection(); // Re-initialize for new badges
}

function removeSubject(button) {
    const subjectContainer = document.getElementById('subjectContainer');
    const subjectRows = subjectContainer.querySelectorAll('.subject-row');
    
    if (subjectRows.length <= 5) {
        alert('Minimum of 5 subjects required');
        return;
    }
    
    button.closest('.subject-row').remove();
    
    // Re-index remaining subjects
    const remainingRows = subjectContainer.querySelectorAll('.subject-row');
    remainingRows.forEach((row, index) => {
        const nameInput = row.querySelector('input[name^="subjects"]');
        const gradeSelect = row.querySelector('select[name^="subjects"]');
        
        nameInput.name = `subjects[${index}][name]`;
        gradeSelect.name = `subjects[${index}][grade]`;
    });
}

// Prediction Form Handling
function handlePredictionFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
    submitBtn.disabled = true;
    
    // Re-enable after 3 seconds (for demo)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
    
    return true;
}

// Utility Functions
function formatPercentage(value) {
    return Math.round(value) + '%';
}

function getPerformanceColor(percentage) {
    if (percentage >= 70) return 'success';
    if (percentage >= 50) return 'warning';
    return 'danger';
}

// Export functions for global use
window.addSubject = addSubject;
window.removeSubject = removeSubject;
window.handlePredictionFormSubmit = handlePredictionFormSubmit;