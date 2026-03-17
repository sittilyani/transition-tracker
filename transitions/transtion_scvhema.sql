-- transitions/transition_schema.sql


-- Sections table
CREATE TABLE transition_sections (
    section_id INT AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(20) NOT NULL,
    section_name VARCHAR(255) NOT NULL,
    section_category VARCHAR(50) NOT NULL, -- 'Leadership', 'Clinical', 'Management', 'Institutional'
    display_order INT NOT NULL,
    has_ip_component TINYINT DEFAULT 1, -- Whether section has IP involvement component
    has_cdoh_component TINYINT DEFAULT 1, -- Whether section has CDOH autonomy component
    max_score_ip INT DEFAULT 0,
    max_score_cdoh INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indicators table (for sub-questions)
CREATE TABLE transition_indicators (
    indicator_id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    indicator_code VARCHAR(20) NOT NULL, -- e.g., 'T1.1', 'T2.1'
    indicator_text TEXT NOT NULL,
    verification_guidance TEXT,
    max_score INT DEFAULT 4, -- Most indicators are 0-4
    display_order INT NOT NULL,
    FOREIGN KEY (section_id) REFERENCES transition_sections(section_id) ON DELETE CASCADE
);

-- Main assessments table
CREATE TABLE transition_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    county_id INT NOT NULL,
    assessment_period VARCHAR(50) NOT NULL,
    assessment_date DATE NOT NULL,
    assessed_by VARCHAR(100),
    assessment_status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft',

    -- Overall scores will be calculated from child tables
    overall_cdoh_score INT DEFAULT 0,
    overall_ip_score INT DEFAULT 0,
    overall_gap_score INT DEFAULT 0,
    overall_overlap_score INT DEFAULT 0,

    -- Readiness level (calculated)
    readiness_level ENUM('Not Ready', 'Support and Monitor', 'Transition') DEFAULT 'Not Ready',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (county_id) REFERENCES counties(county_id) ON DELETE CASCADE,
    INDEX idx_county_period (county_id, assessment_period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Individual scores table (stores each indicator score)
CREATE TABLE transition_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    indicator_id INT NOT NULL,
    cdoh_score INT DEFAULT 0, -- Score for CDOH (0-4)
    ip_score INT DEFAULT 0,    -- Score for IP involvement (0-4)
    comments TEXT,

    -- Calculated fields for dashboard
    cdoh_percentage DECIMAL(5,2) GENERATED ALWAYS AS ((cdoh_score / 4) * 100) STORED,
    ip_percentage DECIMAL(5,2) GENERATED ALWAYS AS ((ip_score / 4) * 100) STORED,
    gap_score INT GENERATED ALWAYS AS (GREATEST(0, ip_score - cdoh_score)) STORED,
    overlap_score INT GENERATED ALWAYS AS (LEAST(cdoh_score, ip_score)) STORED,

    FOREIGN KEY (assessment_id) REFERENCES transition_assessments(assessment_id) ON DELETE CASCADE,
    FOREIGN KEY (indicator_id) REFERENCES transition_indicators(indicator_id),
    UNIQUE KEY unique_assessment_indicator (assessment_id, indicator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments table for qualitative feedback
CREATE TABLE transition_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    section_id INT,
    indicator_id INT,
    comment_type ENUM('general', 'section', 'indicator') DEFAULT 'general',
    comment_text TEXT NOT NULL,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES transition_assessments(assessment_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES transition_sections(section_id),
    FOREIGN KEY (indicator_id) REFERENCES transition_indicators(indicator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- transitions/seed_data.sql

-- Insert sections based on your document
INSERT INTO transition_sections (section_code, section_name, section_category, display_order, max_score_ip, max_score_cdoh) VALUES
-- COUNTY LEVEL LEADERSHIP AND GOVERNANCE
('T1', 'County Legislature Health Leadership and Governance', 'Leadership', 1, 44, 44),
('T2', 'County Executive (CHMT) in Health Leadership and Governance', 'Leadership', 2, 44, 44),
('T3', 'County Health Planning', 'Leadership', 3, 44, 44),

-- COUNTY LEVEL ROUTINE SUPERVISION AND MENTORSHIP
('T4A', 'HIV/TB Routine Supervision and Mentorship - IP Involvement', 'Supervision', 4, 48, 0),
('T4B', 'HIV/TB Routine Supervision and Mentorship - CDOH Autonomy', 'Supervision', 5, 0, 48),

-- COUNTY LEVEL HIV/TB PROGRAM SPECIAL INITIATIVES
('T5A', 'HIV/TB Program Special Initiatives (RRI, Leap, Surge SIMS) - IP Involvement', 'Special Initiatives', 6, 32, 0),
('T5B', 'HIV/TB Program Special Initiatives - CDOH Autonomy', 'Special Initiatives', 7, 0, 32),

-- COUNTY LEVEL QUALITY IMPROVEMENT
('T6A', 'HIV/TB Quality Improvement (QI) - IP Involvement', 'Quality Improvement', 8, 28, 0),
('T6B', 'HIV/TB Quality Improvement - CDOH Autonomy', 'Quality Improvement', 9, 0, 28),

-- COUNTY LEVEL PATIENT IDENTIFICATION AND LINKAGE
('T7A', 'HIV/TB Patient identification and linkage - IP Involvement', 'Patient Services', 10, 44, 0),
('T7B', 'HIV/TB Patient identification and linkage - CDOH Autonomy', 'Patient Services', 11, 0, 44),

-- COUNTY LEVEL PATIENT RETENTION AND VIRAL SUPPRESSION
('T8A', 'Patient retention, adherence and Viral suppression - IP Involvement', 'Patient Services', 12, 56, 0),
('T8B', 'Patient retention, adherence and Viral suppression - CDOH Autonomy', 'Patient Services', 13, 0, 56),

-- COUNTY LEVEL HIV PREVENTION AND KEY POPULATION SERVICES
('T9A', 'HIV/TB prevention and Key population services - IP Involvement', 'Prevention', 14, 36, 0),
('T9B', 'HIV/TB prevention and Key population services - CDOH Autonomy', 'Prevention', 15, 0, 36),

-- COUNTY LEVEL FINANCE MANAGEMENT
('T10A', 'HIV/TB Financial Management - IP Involvement', 'Finance', 16, 36, 0),
('T10B', 'HIV/TB Financial Management - CDOH Autonomy', 'Finance', 17, 0, 36),

-- COUNTY LEVEL MANAGING SUB-GRANTS
('T11A', 'HIV/TB Managing Sub-Grants - IP Involvement', 'Finance', 18, 24, 0),
('T11B', 'HIV/TB Managing Sub-Grants - CDOH Autonomy', 'Finance', 19, 0, 24),

-- COUNTY LEVEL COMMODITIES MANAGEMENT
('T12A', 'HIV/TB Commodities Management - IP Involvement', 'Commodities', 20, 28, 0),
('T12B', 'HIV/TB Commodities Management - CDOH Autonomy', 'Commodities', 21, 0, 28),

-- COUNTY LEVEL EQUIPMENT MANAGEMENT
('T13A', 'HIV/TB Equipment Management - IP Involvement', 'Equipment', 22, 24, 0),
('T13B', 'HIV/TB Equipment Management - CDOH Autonomy', 'Equipment', 23, 0, 24),

-- COUNTY LEVEL LABORATORY SERVICES
('T14A', 'HIV/TB Laboratory Services - IP Involvement', 'Laboratory', 24, 48, 0),
('T14B', 'HIV/TB Laboratory Services - CDOH Autonomy', 'Laboratory', 25, 0, 48),

-- COUNTY LEVEL INVENTORY MANAGEMENT
('T15A', 'HIV/TB Inventory Management - IP Involvement', 'Inventory', 26, 24, 0),
('T15B', 'HIV/TB Inventory Management - CDOH Autonomy', 'Inventory', 27, 0, 24),

-- COUNTY LEVEL IN-SERVICE TRAINING
('T16A', 'HIV/TB In-service Training - IP Involvement', 'Training', 28, 28, 0),
('T16B', 'HIV/TB In-service Training - CDOH Autonomy', 'Training', 29, 0, 28),

-- COUNTY LEVEL HUMAN RESOURCE MANAGEMENT
('T17A', 'HIV/TB Human Resource Management - IP Involvement', 'HR', 30, 40, 0),
('T17B', 'HIV/TB Human Resource Management - CDOH Autonomy', 'HR', 31, 0, 40),

-- COUNTY LEVEL PROGRAM DATA MANAGEMENT
('T18A', 'HIV/TB Program Data Management - IP Involvement', 'Data', 32, 36, 0),
('T18B', 'HIV/TB Program Data Management - CDOH Autonomy', 'Data', 33, 0, 36),

-- COUNTY LEVEL PATIENT MONITORING SYSTEM
('T19A', 'HIV/TB Patient Monitoring System - IP Involvement', 'Data', 34, 28, 0),
('T19B', 'HIV/TB Patient Monitoring System - CDOH Autonomy', 'Data', 35, 0, 28),

-- INSTITUTIONAL OWNERSHIP INDICATORS
('IO1', 'Operationalization of national HIV/TB plan at institutional level', 'Institutional', 36, 20, 20),
('IO2', 'Institutional coordination of HIV prevention, care and treatment', 'Institutional', 37, 16, 16),
('IO3', 'Congruence of expectations between levels of the health system', 'Institutional', 38, 24, 24);