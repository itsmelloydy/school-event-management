-- Run this ONCE in phpMyAdmin for an existing CampusConnect database.
USE school_event_db;

ALTER TABLE users
    ADD COLUMN year_level VARCHAR(30) NULL AFTER role,
    ADD COLUMN block VARCHAR(50) NULL AFTER year_level;

-- Existing accounts will show as 'Year not specified' / 'Block not specified'
-- in the admin participant grouping until their year and block are updated.
