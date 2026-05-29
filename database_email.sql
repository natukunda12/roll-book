-- Run this in phpMyAdmin
USE rollbook;

-- Store email settings per business
ALTER TABLE businesses
  ADD COLUMN smtp_host     VARCHAR(255) DEFAULT '' AFTER currency,
  ADD COLUMN smtp_port     INT DEFAULT 587 AFTER smtp_host,
  ADD COLUMN smtp_user     VARCHAR(255) DEFAULT '' AFTER smtp_port,
  ADD COLUMN smtp_pass     VARCHAR(255) DEFAULT '' AFTER smtp_user,
  ADD COLUMN smtp_from     VARCHAR(255) DEFAULT '' AFTER smtp_pass,
  ADD COLUMN smtp_from_name VARCHAR(255) DEFAULT 'Cashbook Pro' AFTER smtp_from,
  ADD COLUMN smtp_secure   ENUM('tls','ssl','none') DEFAULT 'tls' AFTER smtp_from_name;

-- Email log
CREATE TABLE IF NOT EXISTS email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_id INT NOT NULL,
  to_email VARCHAR(255) NOT NULL,
  subject VARCHAR(500) NOT NULL,
  status ENUM('sent','failed') DEFAULT 'sent',
  error_msg TEXT,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);
