-- Create statutory table (NHIF, NSSF, KRA PIN, etc.)
CREATE TABLE IF NOT EXISTS employee_statutory (
    statutory_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    kra_pin VARCHAR(20),
    nhif_number VARCHAR(20),
    nssf_number VARCHAR(20),
    huduma_number VARCHAR(20),
    passport_number VARCHAR(20),
    alien_number VARCHAR(20),
    birth_cert_number VARCHAR(20),
    disability ENUM('Yes', 'No') DEFAULT 'No',
    disability_description TEXT,
    disability_cert_number VARCHAR(50),
    nok_name VARCHAR(100),
    nok_relationship VARCHAR(50),
    nok_phone VARCHAR(15),
    nok_email VARCHAR(100),
    nok_alternate_phone VARCHAR(15),
    nok_postal_address VARCHAR(255),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(15),
    emergency_contact_relationship VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create academics table (Kenyan education system)
CREATE TABLE IF NOT EXISTS employee_academics (
    academic_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    qualification_type ENUM('KCPE', 'KCSE', 'Certificate', 'Diploma', 'Higher Diploma', 'Degree', 'Masters', 'PhD', 'Post Graduate Diploma', 'Other') NOT NULL,
    qualification_name VARCHAR(255),
    institution_name VARCHAR(255),
    course_name VARCHAR(255),
    specialization VARCHAR(255),
    grade VARCHAR(50),
    award_year YEAR,
    start_date DATE,
    end_date DATE,
    certificate_number VARCHAR(100),
    completion_status ENUM('Completed', 'In Progress', 'Discontinued') DEFAULT 'Completed',
    document_path VARCHAR(500),
    verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    verified_by VARCHAR(100),
    verification_date DATETIME,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create work experience table
CREATE TABLE IF NOT EXISTS employee_work_experience (
    experience_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    employer_name VARCHAR(255),
    employer_type ENUM('Government', 'Private', 'NGO', 'International Organization', 'Self-Employed', 'Other') NOT NULL,
    job_title VARCHAR(255),
    job_grade VARCHAR(100),
    department VARCHAR(255),
    start_date DATE,
    end_date DATE,
    is_current ENUM('Yes', 'No') DEFAULT 'No',
    responsibilities TEXT,
    achievements TEXT,
    supervising_role ENUM('Yes', 'No') DEFAULT 'No',
    supervised_count INT DEFAULT 0,
    leaving_reason TEXT,
    employer_contact_person VARCHAR(255),
    employer_phone VARCHAR(15),
    employer_email VARCHAR(100),
    verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    document_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create professional registrations table (Kenyan regulatory bodies)
CREATE TABLE IF NOT EXISTS employee_professional_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    regulatory_body VARCHAR(255),
    registration_number VARCHAR(100),
    registration_date DATE,
    expiry_date DATE,
    license_number VARCHAR(100),
    license_grade VARCHAR(100),
    specialization VARCHAR(255),
    verification_status ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    document_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create trainings and certifications table
CREATE TABLE IF NOT EXISTS employee_trainings (
    training_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    training_name VARCHAR(255),
    training_provider VARCHAR(255),
    training_type ENUM('In-house', 'External', 'Online', 'International') NOT NULL,
    start_date DATE,
    end_date DATE,
    certificate_number VARCHAR(100),
    certificate_issue_date DATE,
    certificate_expiry_date DATE,
    skills_acquired TEXT,
    funding_source VARCHAR(255),
    document_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create languages table
CREATE TABLE IF NOT EXISTS employee_languages (
    language_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    language_name VARCHAR(100),
    proficiency ENUM('Native', 'Fluent', 'Working Knowledge', 'Basic') NOT NULL,
    speaking ENUM('Excellent', 'Good', 'Fair', 'Poor') NOT NULL,
    writing ENUM('Excellent', 'Good', 'Fair', 'Poor') NOT NULL,
    reading ENUM('Excellent', 'Good', 'Fair', 'Poor') NOT NULL,
    certification VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create referees table
CREATE TABLE IF NOT EXISTS employee_referees (
    referee_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    referee_name VARCHAR(255),
    referee_title VARCHAR(100),
    referee_organization VARCHAR(255),
    referee_position VARCHAR(255),
    referee_phone VARCHAR(15),
    referee_email VARCHAR(100),
    referee_relationship VARCHAR(100),
    years_known INT,
    referee_address VARCHAR(255),
    can_contact ENUM('Yes', 'No') DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create next of kin table
CREATE TABLE IF NOT EXISTS employee_next_of_kin (
    kin_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    kin_name VARCHAR(255),
    kin_relationship VARCHAR(100),
    kin_phone VARCHAR(15),
    kin_alternate_phone VARCHAR(15),
    kin_email VARCHAR(100),
    kin_address VARCHAR(255),
    kin_city_town VARCHAR(100),
    kin_county VARCHAR(100),
    is_emergency_contact ENUM('Yes', 'No') DEFAULT 'Yes',
    priority_order INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create disciplinary records table
CREATE TABLE IF NOT EXISTS employee_disciplinary (
    disciplinary_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    case_number VARCHAR(100),
    case_type VARCHAR(255),
    incident_date DATE,
    report_date DATE,
    description TEXT,
    action_taken TEXT,
    action_date DATE,
    penalty VARCHAR(255),
    status ENUM('Open', 'Closed', 'Under Investigation', 'Appealed') DEFAULT 'Open',
    closed_date DATE,
    document_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create performance appraisals table
CREATE TABLE IF NOT EXISTS employee_appraisals (
    appraisal_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    appraisal_period VARCHAR(50),
    appraisal_year YEAR,
    appraisal_date DATE,
    supervisor_name VARCHAR(255),
    supervisor_id VARCHAR(20),
    overall_rating DECIMAL(3,2),
    comments TEXT,
    next_appraisal_date DATE,
    document_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);

-- Create leave records table
CREATE TABLE IF NOT EXISTS employee_leave (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(20) NOT NULL,
    leave_type ENUM('Annual', 'Sick', 'Maternity', 'Paternity', 'Compassionate', 'Study', 'Unpaid', 'Other') NOT NULL,
    start_date DATE,
    end_date DATE,
    days_requested INT,
    days_approved INT,
    reason TEXT,
    approver_name VARCHAR(255),
    approval_date DATE,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    FOREIGN KEY (id_number) REFERENCES county_staff(id_number) ON DELETE CASCADE
);