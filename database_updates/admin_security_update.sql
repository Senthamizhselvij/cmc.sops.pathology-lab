USE pathology_lab;

-- Add roles to existing login accounts without overwriting passwords.
SET @has_role := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@has_role = 0,
    'ALTER TABLE admins ADD COLUMN role ENUM(''admin'',''hod'',''staff'') NOT NULL DEFAULT ''staff'' AFTER password',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add HOD as a supported role for existing installations too.
ALTER TABLE admins MODIFY role ENUM('admin','hod','staff') NOT NULL DEFAULT 'staff';

-- Repair legacy/invalid empty role values (often caused by creating HOD before the enum was updated).
UPDATE admins SET role = 'staff' WHERE role IS NULL OR role = '';

-- Make the existing admin accounts administrators.
UPDATE admins SET role = 'admin' WHERE username IN ('admin', '5001');

-- Append-only activity history. Do not delete rows from this table through the app.
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_username (username),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
