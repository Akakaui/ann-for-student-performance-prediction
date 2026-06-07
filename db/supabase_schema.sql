-- PostgreSQL schema for Supabase
-- Run this in the Supabase SQL Editor (Dashboard > SQL Editor)

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('student', 'lecturer', 'admin')),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Lecturers table for verification system
CREATE TABLE IF NOT EXISTS lecturers (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    institution VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    staff_id VARCHAR(100),
    verification_status VARCHAR(20) DEFAULT 'pending' CHECK (verification_status IN ('pending', 'approved', 'rejected')),
    verified_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    verified_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Lecturer groups
CREATE TABLE IF NOT EXISTS lecturer_groups (
    id SERIAL PRIMARY KEY,
    lecturer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Group students
CREATE TABLE IF NOT EXISTS group_students (
    id SERIAL PRIMARY KEY,
    group_id INTEGER NOT NULL REFERENCES lecturer_groups(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    added_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE (group_id, student_id)
);

-- Access requests
CREATE TABLE IF NOT EXISTS access_requests (
    id SERIAL PRIMARY KEY,
    lecturer_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER REFERENCES lecturer_groups(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    requested_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    responded_at TIMESTAMP WITH TIME ZONE
);

-- Predictions table
CREATE TABLE IF NOT EXISTS predictions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    exam_type VARCHAR(10) NOT NULL CHECK (exam_type IN ('WAEC', 'NECO')),
    current_cgpa DECIMAL(3,2),
    
    -- Subject grades (WAEC/NECO grading: A1=1, B2=2, B3=3, C4=4, C5=5, C6=6, D7=7, E8=8, F9=9)
    subject1_name VARCHAR(100),
    subject1_grade INTEGER,
    subject2_name VARCHAR(100),
    subject2_grade INTEGER,
    subject3_name VARCHAR(100),
    subject3_grade INTEGER,
    subject4_name VARCHAR(100),
    subject4_grade INTEGER,
    subject5_name VARCHAR(100),
    subject5_grade INTEGER,
    subject6_name VARCHAR(100),
    subject6_grade INTEGER,
    subject7_name VARCHAR(100),
    subject7_grade INTEGER,
    subject8_name VARCHAR(100),
    subject8_grade INTEGER,
    subject9_name VARCHAR(100),
    subject9_grade INTEGER,
    
    -- Socio-economic factors
    parent_education_level VARCHAR(20) NOT NULL CHECK (parent_education_level IN ('none', 'primary', 'secondary', 'tertiary')),
    study_hours_per_week INTEGER NOT NULL,
    access_to_learning_materials VARCHAR(20) NOT NULL CHECK (access_to_learning_materials IN ('poor', 'average', 'good', 'excellent')),
    school_type VARCHAR(10) NOT NULL CHECK (school_type IN ('public', 'private')),
    family_income_level VARCHAR(10) NOT NULL CHECK (family_income_level IN ('low', 'middle', 'high')),
    
    -- Prediction results
    predicted_performance DECIMAL(5,2),
    confidence_level DECIMAL(5,2),
    feature_contributions JSONB,
    recommendations_viewed BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Recommendations table for personalized study tips
CREATE TABLE IF NOT EXISTS recommendations (
    id SERIAL PRIMARY KEY,
    prediction_id INTEGER NOT NULL REFERENCES predictions(id) ON DELETE CASCADE,
    recommendations JSONB,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    action_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Prediction templates table
CREATE TABLE IF NOT EXISTS prediction_templates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    template_name VARCHAR(255) NOT NULL,
    exam_type VARCHAR(10) NOT NULL CHECK (exam_type IN ('WAEC', 'NECO')),
    subject_data JSONB NOT NULL,
    socio_economic_data JSONB NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_predictions_user_id ON predictions(user_id);
CREATE INDEX IF NOT EXISTS idx_predictions_created_at ON predictions(created_at);
CREATE INDEX IF NOT EXISTS idx_lecturers_verification ON lecturers(verification_status);
CREATE INDEX IF NOT EXISTS idx_predictions_exam_type ON predictions(exam_type);
CREATE INDEX IF NOT EXISTS idx_group_students_group_id ON group_students(group_id);
CREATE INDEX IF NOT EXISTS idx_group_students_student_id ON group_students(student_id);
CREATE INDEX IF NOT EXISTS idx_lecturers_user_id ON lecturers(user_id);
CREATE INDEX IF NOT EXISTS idx_recommendations_prediction ON recommendations(prediction_id);
CREATE INDEX IF NOT EXISTS idx_predictions_user_exam ON predictions(user_id, exam_type);
CREATE INDEX IF NOT EXISTS idx_group_students_composite ON group_students(group_id, student_id);
CREATE INDEX IF NOT EXISTS idx_lecturer_groups_lecturer ON lecturer_groups(lecturer_id);
CREATE INDEX IF NOT EXISTS idx_users_verified ON users(is_verified, role);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_date ON audit_logs(user_id, created_at);

-- Auto-update updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply triggers
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_recommendations_updated_at BEFORE UPDATE ON recommendations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_system_settings_updated_at BEFORE UPDATE ON system_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_prediction_templates_updated_at BEFORE UPDATE ON prediction_templates
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert default admin user (password: admin123)
-- IMPORTANT: Change this password in production!
INSERT INTO users (username, email, password, role, is_verified) VALUES 
('admin', 'admin@studentpredictor.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE)
ON CONFLICT (username) DO NOTHING;

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('system_version', '1.0', 'Current system version'),
('prediction_model_version', '1.0', 'AI model version'),
('min_subjects_required', '5', 'Minimum subjects required for prediction'),
('max_subjects_allowed', '9', 'Maximum subjects allowed for prediction')
ON CONFLICT (setting_key) DO NOTHING;
