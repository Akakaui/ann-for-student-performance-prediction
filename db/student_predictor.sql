-- Create database
CREATE DATABASE IF NOT EXISTS student_predictor;
USE student_predictor;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'lecturer', 'admin') NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Lecturers table for verification system
CREATE TABLE IF NOT EXISTS lecturers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    institution VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    staff_id VARCHAR(100),
    verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Lecturer groups
CREATE TABLE IF NOT EXISTS lecturer_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lecturer_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Group students
CREATE TABLE IF NOT EXISTS group_students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES lecturer_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_student (group_id, student_id)
);

-- Access requests
CREATE TABLE IF NOT EXISTS access_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lecturer_id INT NOT NULL,
    student_id INT NOT NULL,
    group_id INT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES lecturer_groups(id) ON DELETE SET NULL
);

-- Predictions table
CREATE TABLE IF NOT EXISTS predictions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    exam_type ENUM('WAEC', 'NECO') NOT NULL,
    current_cgpa DECIMAL(3,2),
    
    -- Subject grades (WAEC/NECO grading: A1=1, B2=2, B3=3, C4=4, C5=5, C6=6, D7=7, E8=8, F9=9)
    subject1_name VARCHAR(100),
    subject1_grade INT,
    subject2_name VARCHAR(100),
    subject2_grade INT,
    subject3_name VARCHAR(100),
    subject3_grade INT,
    subject4_name VARCHAR(100),
    subject4_grade INT,
    subject5_name VARCHAR(100),
    subject5_grade INT,
    subject6_name VARCHAR(100),
    subject6_grade INT,
    subject7_name VARCHAR(100),
    subject7_grade INT,
    subject8_name VARCHAR(100),
    subject8_grade INT,
    subject9_name VARCHAR(100),
    subject9_grade INT,
    
    -- Socio-economic factors
    parent_education_level ENUM('none', 'primary', 'secondary', 'tertiary') NOT NULL,
    study_hours_per_week INT NOT NULL,
    access_to_learning_materials ENUM('poor', 'average', 'good', 'excellent') NOT NULL,
    school_type ENUM('public', 'private') NOT NULL,
    family_income_level ENUM('low', 'middle', 'high') NOT NULL,
    
    -- Prediction results
    predicted_performance DECIMAL(5,2), -- Percentage score prediction
    confidence_level DECIMAL(5,2), -- Confidence percentage
    feature_contributions JSON, -- Store feature importance analysis
    recommendations_viewed BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recommendations table for personalized study tips
CREATE TABLE IF NOT EXISTS recommendations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prediction_id INT NOT NULL,
    recommendations JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prediction_id) REFERENCES predictions(id) ON DELETE CASCADE,
    INDEX idx_prediction_id (prediction_id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password, role, is_verified) VALUES 
('admin', 'admin@studentpredictor.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_predictions_user_id ON predictions(user_id);
CREATE INDEX idx_predictions_created_at ON predictions(created_at);
CREATE INDEX idx_lecturers_verification ON lecturers(verification_status);
CREATE INDEX idx_predictions_exam_type ON predictions(exam_type);
CREATE INDEX idx_group_students_group_id ON group_students(group_id);
CREATE INDEX idx_group_students_student_id ON group_students(student_id);
CREATE INDEX idx_lecturers_user_id ON lecturers(user_id);
CREATE INDEX idx_recommendations_prediction ON recommendations(prediction_id);

-- Add settings table for system configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('system_version', '1.0', 'Current system version'),
('prediction_model_version', '1.0', 'AI model version'),
('min_subjects_required', '5', 'Minimum subjects required for prediction'),
('max_subjects_allowed', '9', 'Maximum subjects allowed for prediction');

-- Create audit log table for important actions
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create prediction templates table
CREATE TABLE IF NOT EXISTS prediction_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    template_name VARCHAR(255) NOT NULL,
    exam_type ENUM('WAEC', 'NECO') NOT NULL,
    subject_data JSON NOT NULL,
    socio_economic_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Additional indexes for query optimization
CREATE INDEX idx_predictions_user_exam ON predictions(user_id, exam_type);
CREATE INDEX idx_group_students_composite ON group_students(group_id, student_id);
CREATE INDEX idx_lecturer_groups_lecturer ON lecturer_groups(lecturer_id);
CREATE INDEX idx_users_verified ON users(is_verified, role);
CREATE INDEX idx_audit_logs_user_date ON audit_logs(user_id, created_at);