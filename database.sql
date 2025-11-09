SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS techmart CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE techmart;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS wishlist_items;
DROP TABLE IF EXISTS discount_codes;
DROP TABLE IF EXISTS order_reviews;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS newsletter_subscribers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(22) NOT NULL,
    category VARCHAR(80) NOT NULL,
    tagline VARCHAR(200) DEFAULT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) NOT NULL,
    spec_json JSON DEFAULT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_products_category (category),
    KEY idx_products_featured (featured),
    KEY idx_products_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(22) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    customer_order_number INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    shipping_address TEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'Processing',
    promo_code VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_email (customer_email),
    KEY idx_orders_created_at (created_at),
    KEY idx_orders_user (user_id),
    UNIQUE KEY uniq_orders_user_number (user_id, customer_order_number),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    reviewer_name VARCHAR(150) NOT NULL,
    reviewer_email VARCHAR(150) NOT NULL,
    rating TINYINT NOT NULL,
    comments TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_order_reviewer (order_id, reviewer_email),
    KEY idx_order_reviews_rating (rating),
    KEY idx_order_reviews_email (reviewer_email),
    CONSTRAINT fk_order_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    KEY idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    preference VARCHAR(80) NOT NULL,
    budget_focus VARCHAR(40) NOT NULL,
    subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    newsletter_subscriber_id INT DEFAULT NULL,
    code VARCHAR(40) NOT NULL,
    email VARCHAR(150) NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    max_uses INT NOT NULL DEFAULT 1,
    redeemed_at TIMESTAMP NULL DEFAULT NULL,
    redeemed_by_user_id INT DEFAULT NULL,
    redeemed_order_id INT DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_discount_code (code),
    KEY idx_discount_email (email),
    KEY idx_discount_subscriber (newsletter_subscriber_id),
    CONSTRAINT fk_discount_subscriber FOREIGN KEY (newsletter_subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE SET NULL,
    CONSTRAINT fk_discount_user FOREIGN KEY (redeemed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_discount_order FOREIGN KEY (redeemed_order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    KEY idx_cart_items_product (product_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist_user_product (user_id, product_id),
    KEY idx_wishlist_product (product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('AetherBook Pro 16', 'Laptops', 'Creator-grade performance in a 1.9kg chassis', 'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.', 2399.00, 15, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Processor', 'Intel Core Ultra 7', 'Graphics', 'NVIDIA RTX 4070 8GB', 'Display', '16" mini-LED 165Hz', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4 SSD'), 1),
('Aurora Air 14', 'Laptops', 'Featherweight OLED powerhouse', 'Aurora Air 14 keeps creative rigs ultra-mobile with a 1.2kg chassis, diamond-cut cooling vents, and rapid-charge support that hits 60% in 30 minutes.', 1899.00, 20, 'assets/images/products/aurora-air-14.svg', JSON_OBJECT('Processor', 'Intel Core Ultra 5', 'Graphics', 'Intel Arc integrated', 'Display', '14" OLED 120Hz', 'Memory', '16GB LPDDR5X', 'Storage', '1TB NVMe Gen4', 'Weight', '1.2kg'), 1),
('Nebula Flex 2-in-1', 'Laptops', 'Adaptive dual-mode performance', 'Nebula Flex 2-in-1 flips between tablet sketching and full keyboard productivity with a reinforced 360° hinge and bundled precision pen.', 1799.00, 18, 'assets/images/products/nebula-flex-2in1.svg', JSON_OBJECT('Processor', 'AMD Ryzen 7 8840U', 'Graphics', 'Radeon 780M', 'Display', '14" QHD+ touch', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4', 'Battery', '18-hour rated'), 0),
('Vertex Creator 17', 'Laptops', '17-inch mini-LED studio rig', 'Vertex Creator 17 drives high-refresh color critical work with factory calibration, vapor-chamber cooling, and four Thunderbolt™ 4 ports.', 2799.00, 10, 'assets/images/products/vertex-creator-17.svg', JSON_OBJECT('Processor', 'Intel Core i9 HX', 'Graphics', 'NVIDIA RTX 4080 12GB', 'Display', '17" mini-LED 240Hz', 'Memory', '64GB DDR5', 'Storage', '2TB NVMe Gen4', 'Color', 'Delta E < 1.5'), 0),
('Helio Edge 13', 'Laptops', 'Portable AI editing companion', 'Helio Edge 13 is tuned for mobile creators with on-device AI acceleration, Wi-Fi 7 networking, and a 3K panel that hits 600 nits.', 1399.00, 25, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=800&q=60', JSON_OBJECT('Processor', 'Qualcomm X Elite', 'Graphics', 'Adreno integrated', 'Display', '13" 3K IPS', 'Memory', '16GB LPDDR5X', 'Storage', '512GB NVMe', 'Connectivity', 'Wi-Fi 7'), 0),
('Summit XR Workstation', 'Laptops', 'Dual-screen executive mobile', 'Summit XR Workstation deploys a dual-OLED array with glass haptic touchpad, enterprise TPM 2.0, and magnesium alloy construction.', 3299.00, 6, 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Processor', 'AMD Ryzen 9 7950HX', 'Graphics', 'NVIDIA RTX 4090 16GB', 'Display', 'Dual 14" 4K OLED', 'Memory', '64GB DDR5', 'Storage', '2TB NVMe RAID', 'Battery', '95Wh fast charge'), 1),
('Nebula Studio Monitor', 'Peripherals', 'Calibrated for color-critical workflows', 'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.', 1299.00, 22, 'https://images.unsplash.com/photo-1516542076529-1ea3854896e1?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Resolution', '5120x2880 5K', 'Brightness', '1000 nits peak', 'Connectivity', 'Thunderbolt 4, HDMI 2.1', 'Calibration', 'Factory Delta E < 2'), 1),
('Ion Arc Wireless Mouse', 'Peripherals', 'Ergonomic 26k DPI precision', 'Ion Arc Wireless Mouse glides effortlessly with PTFE skates, tri-mode connectivity, and dynamic lift-off tuning for esports-grade aim.', 159.00, 60, 'assets/images/products/ion-arc-mouse.svg', JSON_OBJECT('Sensor', 'Focus Pro 26K DPI', 'Connectivity', '2.4GHz + Bluetooth', 'Battery', '120 hours RGB off', 'Buttons', '8 programmable', 'Weight', '64g'), 0),
('Lumen Mech Pro', 'Peripherals', 'Hot-swappable RGB controls', 'Lumen Mech Pro ships with factory-lubed switches, double-shot PBT caps, and per-key diffusion for dreamlike lighting presets.', 219.00, 45, 'assets/images/products/lumen-mech-pro.svg', JSON_OBJECT('Switches', 'Hot-swap linear', 'Layout', '75% compact', 'Lighting', 'Per-key RGB', 'Connectivity', 'USB-C detachable', 'Firmware', 'QMK/VIA ready'), 0),
('PulseStream USB Mic', 'Peripherals', 'Broadcast-grade condenser mic', 'PulseStream USB Mic captures studio warmth with a 34mm capsule, on-board DSP, and LED gain metering for instant monitoring.', 249.00, 30, 'assets/images/products/pulse-stream-mic.svg', JSON_OBJECT('Capsule', '34mm condenser', 'Polar Pattern', 'Cardioid + Omni', 'Connectivity', 'USB-C', 'Sample Rate', '24-bit/192kHz', 'Monitoring', 'Latency-free headphone'), 0),
('Horizon Thunder Dock', 'Peripherals', '11-port Thunderbolt expansion', 'Horizon Thunder Dock fuels minimalist setups with dual 8K support, 90W passthrough power, and front-facing quick access ports.', 329.00, 35, 'assets/images/products/horizon-docking-hub.svg', JSON_OBJECT('Ports', '2x TB4, 2x HDMI 2.1', 'USB', '4x USB-A 10Gbps', 'Ethernet', '2.5GbE', 'Power Delivery', '90W passthrough', 'Card Reader', 'UHS-II SD'), 0),
('Spectra VR Headset', 'Peripherals', 'Immersive dual-4K optics', 'Spectra VR Headset unlocks next-gen simulations with dual 4K micro-OLED panels, precise inside-out tracking, and spatial audio.', 799.00, 14, 'https://images.unsplash.com/photo-1549923746-c502d488b3ea?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Displays', 'Dual 4K micro-OLED', 'Refresh Rate', '120Hz', 'Tracking', 'Inside-out 6DoF', 'Field of View', '112°', 'Audio', 'Integrated spatial'), 0),
('Quantum RTX 5080 GPU', 'Components', 'AI-ready graphics powerhouse', 'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.', 1599.00, 8, 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('CUDA Cores', '18,432', 'Memory', '32GB GDDR7', 'Boost Clock', '2.7GHz', 'Power', '350W TDP'), 1),
('Solstice NVMe 2TB', 'Components', 'Gen5 7400MB/s throughput', 'Solstice NVMe 2TB keeps render queues flying with graphene cooling, SLC caching, and firmware tuned for sustained transfers.', 329.00, 50, 'assets/images/products/solstice-nvme-2tb.svg', JSON_OBJECT('Interface', 'PCIe Gen5 x4', 'Read', '7400MB/s', 'Write', '6500MB/s', 'Endurance', '1400 TBW', 'Heatsink', 'Graphene nano'), 0),
('QuantumWave DDR5 Kit', 'Components', '64GB 7200MT/s low-latency', 'QuantumWave DDR5 Kit pairs matched 32GB sticks with on-die ECC, dual-zone diffusion, and pre-tested XMP 3.0 profiles.', 399.00, 40, 'https://images.unsplash.com/photo-1618005198919-d3d4b5a92eee?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Capacity', '64GB (2x32GB)', 'Speed', '7200MT/s CL34', 'Voltage', '1.35V', 'RGB', 'Diffused dual-zone', 'ECC', 'On-die ECC'), 0),
('Atlas Server Chassis', 'Components', '4U modular enterprise frame', 'Atlas Server Chassis accelerates lab builds with tool-less rails, hot-swap backplane support, and redundant PSU mounting.', 599.00, 12, 'assets/images/products/atlas-server-chassis.svg', JSON_OBJECT('Form Factor', '4U', 'Backplane', '8x hot-swap bays', 'Power', 'Dual 1200W PSU ready', 'Cooling', 'Six 140mm PWM', 'Management', 'Front LCD monitor'), 0),
('Stratus Cooling Loop', 'Components', 'Soft tubing + D-RGB block', 'Stratus Cooling Loop arrives pre-filled with UV-reactive coolant, a silent PWM pump, and mod-friendly distro-plate reservoir.', 269.00, 28, 'assets/images/products/stratus-cooling-loop.svg', JSON_OBJECT('Radiator', '360mm slim copper', 'Pump', 'PWM ceramic', 'Reservoir', 'Modular distro plate', 'Coolant', 'Pre-mixed UV blue', 'Warranty', '3 years'), 0),
('Helios Core i9 15C', 'Components', '15-core boost to 5.6GHz', 'Helios Core i9 15C is a drop-in workstation CPU with AVX-512 acceleration, PCIe Gen5 lanes, and advanced AI inference engines.', 699.00, 22, 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Cores', '15 performance', 'Threads', '30', 'Base Clock', '3.5GHz', 'Boost Clock', '5.6GHz', 'Cache', '36MB L3', 'Socket', 'LGA1851'), 0),
('Nova PSU 850 Titanium', 'Components', 'Fanless 80+ Titanium rated', 'Nova PSU 850 Titanium maintains rock-solid rails with fully modular cabling, 0 RPM operation under 40% load, and comprehensive protections.', 299.00, 38, 'assets/images/products/quantum-psu-850.svg', JSON_OBJECT('Efficiency', '80+ Titanium', 'Design', 'Fully modular', 'Fan', '0 RPM until 40% load', 'Cables', 'Individually sleeved', 'Protection', 'OVP/OPP/SCP'), 0);

INSERT INTO users (username, email, password, is_admin) VALUES
('admin', 'admin@local.com', 'admin', 1),
('test', 'test@local.com', 'test', 0);
