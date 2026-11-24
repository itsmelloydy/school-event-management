-- Run this ONLY if school_event_db already exists with the older attendance enum:
-- ENUM('registered','attended','absent')
USE school_event_db;

-- First allow both old and new labels so existing records can be converted safely.
ALTER TABLE registrations
MODIFY attendance ENUM('registered','attended','present','absent') NOT NULL DEFAULT 'registered';

-- Convert the old value to the new label used by this version of CampusConnect.
UPDATE registrations SET attendance = 'present' WHERE attendance = 'attended';

-- Remove the old label after all existing records have been converted.
ALTER TABLE registrations
MODIFY attendance ENUM('registered','present','absent') NOT NULL DEFAULT 'registered';
