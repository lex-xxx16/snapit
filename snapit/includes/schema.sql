
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','staff','admin') NOT NULL DEFAULT 'customer',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS packages (
    package_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    duration_hours INT NOT NULL DEFAULT 6,
    softcopy_count INT NOT NULL DEFAULT 500,
    hardcopy_count INT NOT NULL DEFAULT 200,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    has_softcopy_addon TINYINT(1) NOT NULL DEFAULT 1,
    softcopy_addon_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    package_type ENUM('event','walkin') NOT NULL DEFAULT 'event',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    booking_type ENUM('event','walkin') NOT NULL DEFAULT 'event',
    event_name VARCHAR(200) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    duration_hours INT NOT NULL,
    venue TEXT NOT NULL,
    estimated_softcopies INT NOT NULL DEFAULT 0,
    estimated_hardcopies INT NOT NULL DEFAULT 0,
    softcopy_addon TINYINT(1) NOT NULL DEFAULT 0,
    hardcopy_delivery_name VARCHAR(150),
    hardcopy_delivery_address TEXT,
    hardcopy_delivery_contact VARCHAR(30),
    package_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    addon_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','confirmed','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS camera_presets (
    preset_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    brightness TINYINT NOT NULL DEFAULT 0,
    contrast TINYINT NOT NULL DEFAULT 0,
    saturation TINYINT NOT NULL DEFAULT 0,
    warmness TINYINT NOT NULL DEFAULT 0,
    cam_filter VARCHAR(255) NULL,
    fx_grain TINYINT NOT NULL DEFAULT 0,
    fx_vignette TINYINT NOT NULL DEFAULT 0,
    fx_fade TINYINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS filters (
    filter_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    css_filter VARCHAR(255) NOT NULL DEFAULT 'none',
    preview_color VARCHAR(50) DEFAULT '#a78bfa',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS layouts (
    layout_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    photo_count TINYINT NOT NULL DEFAULT 1,
    grid_cols TINYINT NOT NULL DEFAULT 1,
    grid_rows TINYINT NOT NULL DEFAULT 1,
    print_size VARCHAR(10) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS frame_designs (
    design_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    image_path VARCHAR(255),
    border_color VARCHAR(20) DEFAULT '#6f42c1',
    border_width TINYINT NOT NULL DEFAULT 6,
    bg_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    text_color VARCHAR(20) NOT NULL DEFAULT '#6f42c1',
    photo_gap TINYINT NOT NULL DEFAULT 10,
    photo_radius TINYINT NOT NULL DEFAULT 8,
    pattern VARCHAR(20) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guest_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    guest_name VARCHAR(150),
    session_owner_user_id INT NULL,
    camera_preset_id INT,
    filter_id INT,
    layout_id INT,
    frame_design_id INT,
    status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    idle_timeout_at DATETIME NULL,
    countdown_seconds TINYINT NOT NULL DEFAULT 3,
    mirror_preview TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_gs_owner (session_owner_user_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (camera_preset_id) REFERENCES camera_presets(preset_id),
    FOREIGN KEY (filter_id) REFERENCES filters(filter_id),
    FOREIGN KEY (layout_id) REFERENCES layouts(layout_id),
    FOREIGN KEY (frame_design_id) REFERENCES frame_designs(design_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS session_photos (
    photo_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    order_index TINYINT NOT NULL DEFAULT 0,
    is_kept TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES guest_sessions(session_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delivery_recipients (
    recipient_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    session_id INT,
    email VARCHAR(150) NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES guest_sessions(session_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash') NOT NULL DEFAULT 'cash',
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','completed','captured','failed','refunded_pending') NOT NULL DEFAULT 'completed',
    collected_by INT,
    or_number VARCHAR(50),
    gateway_reference VARCHAR(255) NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    sku VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    category ENUM('paper','ink','other') NOT NULL DEFAULT 'other',
    quantity_on_hand INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    unit_measure VARCHAR(30) NOT NULL DEFAULT 'pcs',
    last_restocked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    quantity_delta INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    reference_id INT,
    done_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (done_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS print_jobs (
    job_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    booking_id INT NOT NULL,
    photo_id INT,
    copies INT NOT NULL DEFAULT 1,
    status ENUM('pending','printed','error') NOT NULL DEFAULT 'pending',
    printed_at DATETIME NULL,
    paper_used INT NOT NULL DEFAULT 0,
    ink_used_ml INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES guest_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (photo_id) REFERENCES session_photos(photo_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    user_id INT,
    type ENUM('booking_created','booking_confirmed','softcopy_sent','event_reminder','payment_received','booking_cancelled') NOT NULL,
    recipient_email VARCHAR(150),
    subject VARCHAR(255),
    message_body TEXT,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_usage (
    usage_id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES print_jobs(job_id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
