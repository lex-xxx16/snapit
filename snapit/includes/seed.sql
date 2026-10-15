
INSERT INTO users (name, email, password, role, status) VALUES
('Admin User', 'admin@snapit.ph', '$2y$12$NpXATJzDUz.C3YBjhHy9oOIUwNyWabZM4aI0F2Y92OdcIPfDNV9Fq', 'admin', 'active'),
('Staff Member', 'staff@snapit.ph', '$2y$12$oF8ldqAS61ZTi5NPsJZSCufzZiowueIboPY63QRU8KwiHLocYYhFO', 'staff', 'active'),
('Customer Demo', 'customer@snapit.ph', '$2y$12$Phl6741oilQU70juuuwACuRgvsQUj5lSpEVXEPvLMGsaRU1anHdZu', 'customer', 'active');

INSERT INTO packages (name, description, duration_hours, softcopy_count, hardcopy_count, base_price, has_softcopy_addon, softcopy_addon_price, is_active) VALUES
('Solo Event Package', 'Perfect for intimate gatherings and small events.', 6, 500, 200, 8000.00, 1, 1500.00, 1),
('Classic Party Package', 'Our most popular option for birthdays and weddings.', 8, 1000, 500, 14500.00, 1, 2500.00, 1),
('Premium Corporate Package', 'Full-day service with unlimited prints and express delivery.', 10, 2000, 1000, 25000.00, 1, 3500.00, 1),
('Grand Wedding Package', '12-hour coverage, albums included, on-site photographer.', 12, 3000, 1500, 42000.00, 1, 5000.00, 1),
('Quick Booth (Add-on)', 'Short 2-hour rental for mini-events.', 2, 100, 50, 3500.00, 1, 800.00, 1);

INSERT INTO camera_presets (name, description, brightness, contrast, saturation, warmness, cam_filter, fx_grain, fx_vignette, fx_fade, is_active) VALUES
('Natural', 'Balanced, true-to-life colors.', 0, 0, 0, 0, NULL, 0, 0, 0, 1),
('Kodak Gold 200', 'Warm golden tones, soft grain and a gentle fade.', 0, 0, 0, 0, 'brightness(1.04) contrast(1.06) saturate(1.18) sepia(.2) hue-rotate(-8deg)', 16, 14, 8, 1),
('Kodak Portra 400', 'Creamy skin tones, pastel colors, low contrast.', 0, 0, 0, 0, 'brightness(1.06) contrast(.94) saturate(.92) sepia(.1) hue-rotate(-4deg)', 10, 8, 12, 1),
('Fuji X Classic Chrome', 'Muted colors with deep, moody contrast.', 0, 0, 0, 0, 'brightness(.98) contrast(1.14) saturate(.78) sepia(.06) hue-rotate(6deg)', 6, 14, 4, 1),
('Fuji Superia 400', 'Cool green-cyan shadows with punchy color.', 0, 0, 0, 0, 'brightness(1.03) contrast(1.1) saturate(1.2) hue-rotate(10deg)', 14, 10, 6, 1),
('Canon G7X II', 'Clean, bright vlog look with natural skin tones.', 0, 0, 0, 0, 'brightness(1.06) contrast(1.06) saturate(1.1)', 0, 4, 0, 1),
('iPhone 5S', 'Slightly warm, soft 2013 phone-camera look.', 0, 0, 0, 0, 'brightness(1.04) contrast(1.04) saturate(1.1) sepia(.07) blur(.3px)', 4, 4, 2, 1),
('iPhone 4S', 'Warmer, noisier and softer, like 2011.', 0, 0, 0, 0, 'brightness(1.02) contrast(1.1) saturate(1.05) sepia(.16) blur(.5px)', 14, 10, 4, 1),
('DV Camcorder', 'Early-2000s handycam: soft, noisy, slightly green.', 0, 0, 0, 0, 'brightness(1.05) contrast(1.18) saturate(1.2) hue-rotate(12deg) blur(.6px)', 24, 26, 6, 1),
('Disposable Flash', 'Harsh flash, hot colors and dark edges.', 0, 0, 0, 0, 'brightness(1.1) contrast(1.22) saturate(1.28) sepia(.1)', 20, 30, 2, 1),
('Polaroid Instant', 'Faded and dreamy with lifted blacks and a warm cast.', 0, 0, 0, 0, 'brightness(1.08) contrast(.92) saturate(.9) sepia(.12) hue-rotate(-4deg)', 8, 18, 16, 1),
('Y2K Digicam', 'Flash-lit compact camera: cool, shiny, slightly soft.', 0, 0, 0, 0, 'brightness(1.1) contrast(1.1) saturate(1.2) hue-rotate(6deg) blur(.3px)', 8, 12, 0, 1),
('B&W Film', 'Classic black & white with strong contrast and grain.', 0, 0, 0, 0, 'grayscale(1) contrast(1.28) brightness(1.02)', 26, 20, 4, 1);

INSERT INTO filters (name, css_filter, preview_color, is_active) VALUES
('None', 'none', '#ffffff', 1),
('Vintage', 'sepia(0.6) contrast(1.1)', '#d4a574', 1),
('Black & White', 'grayscale(1) contrast(1.2)', '#444444', 1),
('Pop', 'saturate(2) contrast(1.15)', '#e83e8c', 1),
('Cool', 'hue-rotate(20deg) saturate(1.3)', '#3b82f6', 1),
('Dreamy', 'brightness(1.1) contrast(0.9) saturate(1.2) blur(0.3px)', '#c4b5fd', 1),
('Film', 'sepia(0.2) contrast(1.1) brightness(0.95)', '#a16207', 1),
('Noir', 'grayscale(1) contrast(1.5) brightness(0.9)', '#111111', 1);

INSERT INTO layouts (name, photo_count, grid_cols, grid_rows, is_active) VALUES
('Solo Shot', 1, 1, 1, 1),
('4-Pic Strip', 4, 2, 2, 1),
('6-Pic Grid', 6, 3, 2, 1);

INSERT INTO frame_designs (name, image_path, border_color, border_width, is_active) VALUES
('Elegant White', NULL, '#ffffff', 8, 1);

INSERT INTO inventory_items (sku, name, description, category, quantity_on_hand, reorder_level, unit_measure, last_restocked_at) VALUES
('PAP-A4-GLOSS', 'Glossy Photo Paper 4R', 'Premium glossy 4R photo paper for prints.', 'paper', 1500, 300, 'sheets', NOW() - INTERVAL 2 DAY),
('PAP-A4-MATTE', 'Matte Photo Paper 4R', 'Anti-glare matte 4R paper.', 'paper', 800, 200, 'sheets', NOW() - INTERVAL 5 DAY),
('INK-CYAN-100', 'Cyan Ink Cartridge 100ml', 'Cyan dye ink for photo printers.', 'ink', 18, 5, 'bottle', NOW() - INTERVAL 1 DAY),
('INK-MAGENTA-100', 'Magenta Ink Cartridge 100ml', 'Magenta dye ink for photo printers.', 'ink', 15, 5, 'bottle', NOW() - INTERVAL 1 DAY),
('INK-YELLOW-100', 'Yellow Ink Cartridge 100ml', 'Yellow dye ink for photo printers.', 'ink', 12, 5, 'bottle', NOW() - INTERVAL 1 DAY),
('INK-BLACK-100', 'Black Ink Cartridge 100ml', 'Photo-black dye ink.', 'ink', 22, 5, 'bottle', NOW() - INTERVAL 1 DAY),
('PRN-CLEAN', 'Printer Cleaning Kit', 'Printer head cleaning wipes.', 'other', 40, 10, 'kit', NOW() - INTERVAL 7 DAY),
('CABLE-USB', 'USB Type-B Cable 3m', 'Printer USB cable.', 'other', 20, 5, 'pcs', NOW() - INTERVAL 10 DAY);

INSERT INTO layouts (name, photo_count, grid_cols, grid_rows, print_size, is_active) VALUES
('Strip · 3 Photos', 3, 1, 3, '2x6', 1),
('Strip · 4 Photos', 4, 1, 4, '2x6', 1),
('Duo Strip · 2 Photos', 2, 1, 2, '2x6', 1),
('Wide Strip · 4 Photos', 4, 2, 2, '4x6', 1),
('Big Strip · 6 Photos', 6, 2, 3, '4x6', 1);

INSERT INTO frame_designs (name, border_color, border_width, bg_color, text_color, photo_gap, photo_radius, pattern, is_active) VALUES
('Classic White', '#111111', 1, '#ffffff', '#111111', 8, 0, NULL, 1),
('Midnight Black', '#111111', 2, '#111111', '#ffffff', 8, 2, NULL, 1),
('Blush Pink', '#f8a5c2', 4, '#fde4ec', '#c2185b', 8, 10, NULL, 1),
('Vintage Cream', '#8b6b3d', 2, '#f5ecd7', '#5b4527', 6, 2, 'dots', 1),
('Burgundy Rose', '#7a0c1e', 3, '#7a0c1e', '#f7d6dc', 8, 3, NULL, 1),
('Holiday Noir', '#c0392b', 3, '#14201a', '#f1e4c3', 8, 4, 'dots', 1),
('Ocean Breeze', '#4bb3d9', 4, '#e3f4fb', '#0b5d7a', 8, 12, NULL, 1),
('Film Reel', '#0d0d0d', 2, '#0d0d0d', '#e8d9a8', 12, 0, NULL, 1);

INSERT INTO packages (name, description, duration_hours, softcopy_count, hardcopy_count, base_price, has_softcopy_addon, softcopy_addon_price, package_type, is_active) VALUES
('Walk-in · Single Strip', '1 printed photo strip, plus a digital copy sent to your email.', 1, 1, 1, 120.00, 0, 0.00, 'walkin', 1),
('Walk-in · Duo Pack', '2 printed photo strips, plus a digital copy sent to your email.', 1, 1, 2, 200.00, 0, 0.00, 'walkin', 1),
('Walk-in · Friends Pack', '4 printed photo strips, plus a digital copy sent to your email.', 1, 1, 4, 350.00, 0, 0.00, 'walkin', 1),
('Walk-in · Squad Pack', '6 printed photo strips, plus a digital copy sent to your email.', 1, 1, 6, 480.00, 0, 0.00, 'walkin', 1);
