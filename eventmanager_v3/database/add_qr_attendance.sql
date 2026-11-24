-- Run this once in phpMyAdmin if you already have the CampusConnect database.
USE school_event_db;

-- Each event registration gets its own unique, random QR token.
ALTER TABLE registrations
ADD COLUMN qr_token CHAR(64) NULL UNIQUE AFTER attendance;
