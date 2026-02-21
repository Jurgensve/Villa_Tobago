-- Add Attachment Support for Modifications

CREATE TABLE IF NOT EXISTS modification_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modification_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (modification_id) REFERENCES modifications(id) ON DELETE CASCADE
);

-- Add category support if not exists (we'll reuse the description field or add category)
-- For now, let's add a category column to make filtering easier
ALTER TABLE modifications ADD COLUMN IF NOT EXISTS category VARCHAR(50) AFTER owner_id;
