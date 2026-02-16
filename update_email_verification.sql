-- SQL Script to Add Email Verification Columns and Set Existing Users as Verified
-- Run this script in your MySQL database

-- Step 1: Add new columns to users table if they don't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL AFTER is_verified,
ADD COLUMN IF NOT EXISTS verification_token_expires_at DATETIME NULL AFTER verification_token;

-- Step 2: Set all existing users as verified (since they already exist in the database)
UPDATE users 
SET is_verified = 1 
WHERE is_verified = 0 OR is_verified IS NULL;

-- Step 3: Verify the changes
SELECT 
    id,
    email,
    username,
    first_name,
    last_name,
    is_active,
    is_verified,
    verification_token,
    verification_token_expires_at,
    created_at
FROM users
LIMIT 10;

-- Optional: Check how many users were updated
SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
    SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users
FROM users;
