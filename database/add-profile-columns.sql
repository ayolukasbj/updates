-- Add missing profile columns to users table if they don't exist
-- Run this SQL in phpMyAdmin or execute via PHP script

-- Check and add bio column
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL;

-- Check and add social media columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS facebook VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS twitter VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS instagram VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS youtube VARCHAR(255) DEFAULT NULL;

-- Check and add avatar column
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL;

-- Verify columns were added
SELECT 'Users table columns:' as info;
DESCRIBE users;

