-- Run this ONCE on an existing pathology_lab database.
USE pathology_lab;

ALTER TABLE patients
    ADD COLUMN report_status ENUM('ACTIVE', 'CANCELLED') NOT NULL DEFAULT 'ACTIVE' AFTER submitted;

ALTER TABLE patients
    ADD COLUMN cancelled_at DATETIME NULL AFTER report_status;

ALTER TABLE patients
    ADD COLUMN cancelled_by VARCHAR(100) NULL AFTER cancelled_at;

ALTER TABLE patients
    ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by;
