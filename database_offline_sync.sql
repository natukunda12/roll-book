-- ============================================================
-- Run this in phpMyAdmin (run database_update.sql first)
-- ============================================================
USE rollbook;

-- Offline sync queue: stores pending actions made while offline
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    user_id INT NOT NULL,
    client_key VARCHAR(64) NOT NULL UNIQUE, -- unique ID generated on device
    action_type ENUM(
        'transaction_add','transaction_edit','transaction_delete',
        'book_add','book_edit','book_delete'
    ) NOT NULL,
    payload JSON NOT NULL,
    synced TINYINT(1) DEFAULT 0,
    conflict TINYINT(1) DEFAULT 0,
    conflict_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    synced_at TIMESTAMP NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sync_user (user_id, synced),
    INDEX idx_sync_key (client_key)
);
