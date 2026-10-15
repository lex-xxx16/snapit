-- Run ONCE on an existing db_snapit database (phpMyAdmin > SQL tab, or the mysql CLI).
-- Accounts keep their name, email, password, role and status; only phone and address are dropped.
-- Fresh installs do not need this file: schema.sql already has the new users table.
ALTER TABLE users
    DROP COLUMN phone,
    DROP COLUMN address;
