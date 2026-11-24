-- Create an administrator directly in the database.
-- Run this query in phpMyAdmin after importing database.sql or applying migrations.
-- Demo login:
-- Email: admin@campusconnect.local
-- Password: Admin@12345
-- Change this demo password before using the system outside a classroom/demo.

USE school_event_db;

INSERT INTO users (full_name, email, password, role, year_level, block)
VALUES (
    'CampusConnect Administrator',
    'admin@campusconnect.local',
    '$2y$12$Xziqt4qud.KtrtIFeUCC7us3TA1gZqpuhqKNcN0WTUtDHvv6DIgNm',
    'admin',
    NULL,
    NULL
);
