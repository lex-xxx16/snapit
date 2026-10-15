-- Run ONCE on an existing db_snapit database (phpMyAdmin > SQL tab).
ALTER TABLE packages ADD COLUMN package_type ENUM('event','walkin') NOT NULL DEFAULT 'event';
ALTER TABLE bookings ADD COLUMN booking_type ENUM('event','walkin') NOT NULL DEFAULT 'event';

INSERT INTO packages (name, description, duration_hours, softcopy_count, hardcopy_count, base_price, has_softcopy_addon, softcopy_addon_price, package_type, is_active) VALUES
('Walk-in · Single Strip', '1 printed photo strip, plus a digital copy sent to your email.', 1, 1, 1, 120.00, 0, 0.00, 'walkin', 1),
('Walk-in · Duo Pack', '2 printed photo strips, plus a digital copy sent to your email.', 1, 1, 2, 200.00, 0, 0.00, 'walkin', 1),
('Walk-in · Friends Pack', '4 printed photo strips, plus a digital copy sent to your email.', 1, 1, 4, 350.00, 0, 0.00, 'walkin', 1),
('Walk-in · Squad Pack', '6 printed photo strips, plus a digital copy sent to your email.', 1, 1, 6, 480.00, 0, 0.00, 'walkin', 1);
