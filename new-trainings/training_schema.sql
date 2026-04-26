-- ============================================================
--  Vuqa Training Module — Full SQL Schema
--  Run this AFTER your existing schema is in place.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- 1. PLANNED TRAININGS
--    Created by coordinators; each row gets a
--    unique QR token linking to the public form.
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS planned_trainings (
    training_id        INT            AUTO_INCREMENT PRIMARY KEY,
    training_code      VARCHAR(30)    NOT NULL UNIQUE COMMENT 'e.g. TRN-20250601-AB3F9C',
    qr_token           VARCHAR(64)    NOT NULL UNIQUE COMMENT 'Hex token embedded in QR URL',

    -- Training details (mirrors training_sessions FK pattern)
    course_id          INT            DEFAULT NULL,
    duration_id        INT            DEFAULT NULL,
    trainingtype_id    INT            DEFAULT NULL,
    location_id        INT            DEFAULT NULL,
    fac_level_id       INT            DEFAULT NULL,
    county_id          INT            DEFAULT NULL,
    subcounty_id       INT            DEFAULT NULL,

    -- Free-text overrides / extras
    venue_details      VARCHAR(500)   DEFAULT NULL COMMENT 'Room / hall / GPS description',
    facilitator_name   VARCHAR(200)   DEFAULT NULL,
    start_date         DATE           NOT NULL,
    end_date           DATE           NOT NULL,
    training_objectives TEXT          DEFAULT NULL,
    materials_provided  TEXT          DEFAULT NULL,
    max_participants   SMALLINT       DEFAULT 50,

    -- Lifecycle
    status             ENUM('planned','active','completed','cancelled') DEFAULT 'planned',
    created_by         VARCHAR(200)   NOT NULL,
    created_at         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status       (status),
    INDEX idx_start_date   (start_date),
    INDEX idx_created_by   (created_by),
    INDEX idx_qr_token     (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ─────────────────────────────────────────────
-- 2. TRAINING REGISTRATIONS
--    Written by participants via the public QR form.
--    No login required.
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS training_registrations (
    registration_id    INT            AUTO_INCREMENT PRIMARY KEY,
    training_id        INT            NOT NULL,

    -- Personal details
    first_name         VARCHAR(100)   NOT NULL,
    last_name          VARCHAR(100)   NOT NULL,
    gender             ENUM('Male','Female','Prefer not to say') NOT NULL,
    date_of_birth      DATE           DEFAULT NULL,
    id_number          VARCHAR(50)    DEFAULT NULL,
    phone              VARCHAR(20)    DEFAULT NULL,
    email              VARCHAR(200)   DEFAULT NULL,

    -- Professional details
    facility_name      VARCHAR(300)   DEFAULT NULL,
    department         VARCHAR(200)   DEFAULT NULL,
    cadre              VARCHAR(200)   DEFAULT NULL,
    employment_type    ENUM('Permanent','Contract','Volunteer','Other') DEFAULT NULL,
    highest_education  ENUM(
        'Certificate','Diploma','Bachelor''s Degree',
        'Master''s Degree','PhD','Other'
    ) DEFAULT NULL,

    -- Location
    county             VARCHAR(100)   DEFAULT NULL,
    subcounty          VARCHAR(100)   DEFAULT NULL,

    -- Inclusion data
    disability_status  ENUM('No','Yes') DEFAULT 'No',
    disability_type    VARCHAR(200)   DEFAULT NULL,

    -- Consent & meta
    consent_given      TINYINT(1)     DEFAULT 0,
    submitted_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    ip_address         VARCHAR(45)    DEFAULT NULL,
    device_info        VARCHAR(500)   DEFAULT NULL,

    FOREIGN KEY (training_id) REFERENCES planned_trainings(training_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_training     (training_id),
    INDEX idx_id_number    (id_number),
    INDEX idx_submitted    (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ─────────────────────────────────────────────
-- 3. TRAINING DRAFTS  (already exists in some installs;
--    included here in case it is missing)
-- ─────────────────────────────────────────────
drop table training_drafts;
CREATE TABLE IF NOT EXISTS training_drafts (
    draft_id    INT           AUTO_INCREMENT PRIMARY KEY,
    user_id     VARCHAR(200)  NOT NULL,
    draft_data  LONGTEXT      NOT NULL COMMENT 'JSON blob of form state',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ─────────────────────────────────────────────
-- 4. USEFUL VIEWS
-- ─────────────────────────────────────────────

-- 4a. Training summary with lookup names + registration counts
CREATE OR REPLACE VIEW vw_planned_trainings AS
SELECT
    pt.training_id,
    pt.training_code,
    pt.qr_token,
    pt.start_date,
    pt.end_date,
    pt.status,
    pt.max_participants,
    pt.facilitator_name,
    pt.venue_details,
    pt.created_by,
    pt.created_at,

    c.course_name,
    tt.trainingtype_name,
    cd.duration_name,
    tl.location_name,
    fl.facilitator_level_name,
    co.county_name,
    sc.sub_county_name,

    COUNT(tr.registration_id) AS registered_count

FROM planned_trainings pt
LEFT JOIN courses            c  ON pt.course_id       = c.course_id
LEFT JOIN trainingtypes      tt ON pt.trainingtype_id  = tt.trainingtype_id
LEFT JOIN course_durations   cd ON pt.duration_id      = cd.duration_id
LEFT JOIN training_locations tl ON pt.location_id      = tl.location_id
LEFT JOIN facilitator_levels fl ON pt.fac_level_id     = fl.fac_level_id
LEFT JOIN counties           co ON pt.county_id        = co.county_id
LEFT JOIN sub_counties       sc ON pt.subcounty_id     = sc.sub_county_id
LEFT JOIN training_registrations tr ON pt.training_id  = tr.training_id

GROUP BY pt.training_id;


-- 4b. Full participant list with training info
CREATE OR REPLACE VIEW vw_training_participants AS
SELECT
    tr.registration_id,
    tr.training_id,
    pt.training_code,
    pt.start_date,
    pt.end_date,
    c.course_name,
    tt.trainingtype_name,
    tr.first_name,
    tr.last_name,
    tr.gender,
    tr.id_number,
    tr.phone,
    tr.email,
    tr.facility_name,
    tr.department,
    tr.cadre,
    tr.county,
    tr.subcounty,
    tr.disability_status,
    tr.submitted_at
FROM training_registrations tr
JOIN planned_trainings  pt ON tr.training_id    = pt.training_id
LEFT JOIN courses       c  ON pt.course_id      = c.course_id
LEFT JOIN trainingtypes tt ON pt.trainingtype_id = tt.trainingtype_id;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────
-- END OF SCHEMA
-- ─────────────────────────────────────────────
