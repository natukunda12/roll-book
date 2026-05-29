-- ============================================================
-- Run this SQL in phpMyAdmin to add notifications support
-- ============================================================
use rollbook;
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    user_id INT NOT NULL,           -- who triggered the action
    admin_id INT NOT NULL,          -- which admin to notify
    type ENUM('transaction_add','transaction_edit','transaction_delete','book_add','book_edit','book_delete') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_notif_admin_read ON notifications(admin_id, is_read);
CREATE INDEX idx_notif_business ON notifications(business_id, created_at);
