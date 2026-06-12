-- The Brighten Stars Academy - Database Schema
-- Version 1.0

-- Database: tbsacademy (already created in Hostinger panel)

-- Users Table (admin, student, parent)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','student','parent') NOT NULL DEFAULT 'student',
    phone VARCHAR(20),
    status ENUM('active','inactive') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses Table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    duration VARCHAR(50),
    instructor VARCHAR(100),
    description TEXT,
    fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students Table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    roll_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    father_name VARCHAR(100),
    cnic VARCHAR(20),
    dob DATE,
    gender ENUM('male','female','other'),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(150),
    course_id INT,
    batch VARCHAR(50),
    enrollment_date DATE,
    profile_photo VARCHAR(255),
    status ENUM('active','inactive','completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Parents Table
CREATE TABLE parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    relation ENUM('father','mother','guardian') DEFAULT 'father',
    phone VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20),
    email VARCHAR(150),
    cnic VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Lectures Table
CREATE TABLE lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_type VARCHAR(50),
    file_size INT,
    allow_download TINYINT(1) DEFAULT 1,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Course Files Table
CREATE TABLE course_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_type VARCHAR(50),
    file_size INT,
    allow_download TINYINT(1) DEFAULT 1,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Attendance Table
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','leave') NOT NULL DEFAULT 'absent',
    marked_by INT,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, course_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tests Table
CREATE TABLE tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    subject VARCHAR(100),
    test_type ENUM('weekly','monthly','final') DEFAULT 'weekly',
    total_marks INT NOT NULL DEFAULT 100,
    date DATE NOT NULL,
    duration_minutes INT DEFAULT 60,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Test Results Table
CREATE TABLE test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    student_id INT NOT NULL,
    obtained_marks DECIMAL(5,2) DEFAULT 0,
    grade CHAR(3),
    percentage DECIMAL(5,2),
    position INT,
    teacher_remarks TEXT,
    entered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_result (test_id, student_id),
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Monthly Reports Table
CREATE TABLE monthly_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    month TINYINT NOT NULL,
    year YEAR NOT NULL,
    total_classes INT DEFAULT 0,
    present_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    leave_days INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2),
    tests_taken INT DEFAULT 0,
    average_marks DECIMAL(5,2),
    overall_grade CHAR(3),
    teacher_remarks TEXT,
    generated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_report (student_id, course_id, month, year),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Announcements Table
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('holiday','course','test','event','notice','general') DEFAULT 'general',
    target_audience ENUM('all','students','parents') DEFAULT 'all',
    course_id INT,
    is_pinned TINYINT(1) DEFAULT 0,
    published_by INT,
    expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (published_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings Table
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit Logs Table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- WhatsApp Logs Table
CREATE TABLE whatsapp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sent_by INT,
    recipient_name VARCHAR(100),
    phone VARCHAR(20),
    message TEXT,
    message_type ENUM('result','attendance','report','announcement','custom') DEFAULT 'custom',
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Allowed IPs Table (for file access restriction)
CREATE TABLE allowed_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    label VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('institute_name', 'The Brighten Stars Academy'),
('institute_address', 'Your Address Here'),
('institute_phone', '+92-XXX-XXXXXXX'),
('institute_email', 'info@brightenstarss.com'),
('institute_logo', ''),
('whatsapp_api_key', ''),
('whatsapp_instance_id', ''),
('allow_student_download_outside', '0'),
('session_year', '2025'),
('grading_a_plus', '90'),
('grading_a', '80'),
('grading_b', '70'),
('grading_c', '60'),
('grading_d', '50');

-- Default Admin User (password: Admin@123)
INSERT INTO users (name, email, password, role, phone) VALUES
('Administrator', 'admin@brightenstars.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+92-300-0000000');

-- Sample Allowed IP (localhost)
INSERT INTO allowed_ips (ip_address, label) VALUES
('127.0.0.1', 'Localhost'),
('::1', 'Localhost IPv6');

-- Sample Courses
INSERT INTO courses (name, code, duration, instructor, description) VALUES
('Certificate in Information Technology', 'CIT', '6 Months', 'Mr. Ali Hassan', 'Comprehensive IT course covering basics to advanced computer skills'),
('Graphic Design', 'GD', '3 Months', 'Ms. Fatima', 'Learn professional graphic design with Photoshop and Illustrator'),
('Web Development', 'WD', '6 Months', 'Mr. Ahmed', 'Full stack web development with HTML, CSS, JavaScript and PHP'),
('English Language', 'EL', '4 Months', 'Ms. Sara', 'Complete English language course for beginners to advanced'),
('Spoken English', 'SE', '2 Months', 'Mr. Usman', 'Practical spoken English communication skills'),
('MS Office', 'MSO', '2 Months', 'Mrs. Nadia', 'Microsoft Office Suite - Word, Excel, PowerPoint');
