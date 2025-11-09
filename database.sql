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
('AetherBook Pro 16', 'Laptops', 'Power meets portability', 'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.', 2399.00, 15, 'assets/images/laptops/aetherbook.avif', JSON_OBJECT('Processor', 'Intel Core Ultra 7', 'Graphics', 'NVIDIA RTX 4070 8GB', 'Display', '16\" mini-LED 165Hz', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4 SSD'), 0),
('Nebula Studio Monitor', 'Peripherals', 'Precision in every pixel', 'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.', 1299.00, 22, 'assets/images/peripherals/monitor.png', JSON_OBJECT('Resolution', '5120x2880 5K', 'Brightness', '1000 nits peak', 'Connectivity', 'Thunderbolt 4, HDMI 2.1', 'Calibration', 'Factory Delta E < 2'), 1),
('Quantum RTX 5080 GPU', 'Components', 'Next-gen AI graphics power', 'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.', 1599.00, 8, 'assets/images/components/GPU.png', JSON_OBJECT('CUDA Cores', '18,432', 'Memory', '32GB GDDR7', 'Boost Clock', '2.7GHz', 'Power', '350W TDP'), 1),
('Lumen Creator Tablet', 'Tablets', 'Draw anywhere with precision', 'The Lumen Creator pairs a laminated 13\" display with 8,192 pressure levels and tilt recognition, delivering a natural sketching workflow anywhere.', 699.00, 18, 'assets/images/tablets/tablet.jpg', JSON_OBJECT('Display', '13\" 2.8K laminated', 'Pen', '8192 levels + tilt', 'Connectivity', 'USB-C + Bluetooth 5.3', 'Battery', '18 hours'), 0),
('Orion Mech Keyboard', 'Peripherals', 'Compact. Custom. Clicky.', 'An aluminium-framed, gasket-mounted keyboard featuring per-key RGB, south-facing stabilisers, and tri-mode connectivity for any battlestation.', 219.00, 40, 'assets/images/peripherals/keyboard.jpg', JSON_OBJECT('Layout', '65% with arrow keys', 'Switches', 'Hot-swap linear (lubed)', 'Connectivity', 'USB-C, 2.4GHz, Bluetooth', 'Battery', '4000 mAh'), 0),
('Solace ANC Headset', 'Audio', 'Silence meets pure sound', 'Solace Active Noise Cancelling headphones deliver studio reference tuning, adaptive transparency, and 35-hour battery life for deep work sessions.', 349.00, 30, 'assets/images/audio/headset.png', JSON_OBJECT('Drivers', '42mm graphene', 'ANC', 'Hybrid 4-mic', 'Connectivity', 'Bluetooth 5.4 + USB-C', 'Battery', 'Up to 35 hours'), 0);

INSERT INTO users (username, email, password, is_admin) VALUES
('admin', 'admin@local.com', 'admin', 1),
('test', 'test@local.com', 'test', 0);

DELETE FROM products
WHERE tagline IN (
  'Creator-grade performance in a 1.9kg chassis',
  'Calibrated for color-critical workflows',
  'AI-ready graphics powerhouse',
  'Pen-ready canvas for illustrators',
  '65% layout with hot-swap switches',
  'Focus on your mix—not the noise'
);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Aurora OpenWave', 'Audio', 'Open-back studio precision', 'The Aurora OpenWave delivers spacious, lifelike sound with 50mm planar magnetic drivers, breathable ear cushions, and a lightweight magnesium frame for all-day comfort.', 499.00, 25, 'assets/images/audio/openback.png', JSON_OBJECT(
  'Driver Type', '50mm Planar Magnetic',
  'Design', 'Open-back, over-ear',
  'Impedance', '64Ω',
  'Frequency Response', '10Hz – 45kHz',
  'Connectivity', '3.5mm + 6.3mm jack',
  'Cable', 'Detachable braided 2.5m'
), 1);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Aurora Echo IEM', 'Audio', 'Precision in your pocket', 'The Aurora Echo IEM delivers reference-grade sound with dual hybrid drivers, detachable silver cables, and ergonomic titanium shells for immersive, fatigue-free listening on the go.', 279.00, 35, 'assets/images/audio/echoiem.png', JSON_OBJECT(
  'Driver Configuration', 'Hybrid dual-driver (1DD + 1BA)',
  'Shell Material', 'CNC Titanium Alloy',
  'Impedance', '18Ω',
  'Frequency Response', '15Hz – 40kHz',
  'Cable', 'Detachable 2-pin silver-plated',
  'Connectivity', '3.5mm + 4.4mm balanced'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Aurora Core X9', 'Components', 'Next-gen creator CPU power', 'The Aurora Core X9 packs 16 high-efficiency and performance cores, PCIe Gen5 support, and advanced boost algorithms to keep demanding creative and gaming workloads running smoothly.', 399.00, 20, 'assets/images/components/cpu.png', JSON_OBJECT(
  'Cores', '16 cores',
  'Threads', '32 threads',
  'Base Clock', '3.6GHz',
  'Boost Clock', '5.2GHz',
  'TDP', '125W',
  'Socket', 'LGA-Style Desktop'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Aurora Velocity DDR5', 'Components', 'Speed that fuels creation', 'The Aurora Velocity DDR5 memory delivers ultra-fast performance with low latency, advanced heat dissipation, and power efficiency for next-gen workstations and gaming rigs.', 189.00, 40, 'assets/images/components/ram.png', JSON_OBJECT(
  'Type', 'DDR5',
  'Capacity', '32GB (2x16GB)',
  'Speed', '6400MHz',
  'CAS Latency', 'CL32',
  'Voltage', '1.35V',
  'Features', 'Aluminium heat spreader, XMP 3.0 ready'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('AetherBook Air 14', 'Laptops', 'Ultra-thin creative power', 'The AetherBook Air 14 redefines portability with an all-aluminium body, Intel® Core™ Ultra performance, and a stunning 2.8K display—engineered for creators on the move.', 1799.00, 20, 'assets/images/laptops/aetherair14.png', JSON_OBJECT(
  'Processor', 'Intel Core Ultra 7',
  'Graphics', 'Intel Arc integrated',
  'Display', '14" 2.8K IPS 120Hz',
  'Memory', '16GB LPDDR5X',
  'Storage', '1TB NVMe Gen4 SSD',
  'Battery', '70Wh (up to 15 hrs)',
  'Weight', '1.2 kg'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('AetherBook Stratos 17', 'Laptops', 'Performance without limits', 'The AetherBook Stratos 17 is built for creators and gamers who demand peak performance. Featuring Intel® Core™ i9 processing, RTX™ 4080 graphics, and a 17.3" QHD+ display with 240Hz refresh, it delivers desktop-class power in a portable form.', 2799.00, 12, 'assets/images/laptops/stratos17.png', JSON_OBJECT(
  'Processor', 'Intel Core i9-14900HX',
  'Graphics', 'NVIDIA RTX 4080 12GB',
  'Display', '17.3" QHD+ 240Hz IPS',
  'Memory', '32GB DDR5 5600MHz',
  'Storage', '2TB NVMe Gen4 SSD',
  'Keyboard', 'Per-key RGB backlit',
  'Battery', '99Wh (up to 8 hrs)'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Velocity M1 Mouse', 'Peripherals', 'Precision meets control', 'The Velocity M1 Mouse delivers pixel-perfect tracking with a lightweight 58g chassis, ultra-flex cable, and PTFE feet for effortless glides during competitive gaming or long creative sessions.', 79.00, 50, 'assets/images/peripherals/velocitym1.png', JSON_OBJECT(
  'Sensor', 'PixArt 3395 Optical',
  'DPI Range', '100 – 26,000 DPI',
  'Polling Rate', '1000Hz',
  'Weight', '58g',
  'Connectivity', 'Wired USB-C (ultra-flex)',
  'Buttons', '6 programmable'
), 0);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('Lumen Studio Tab 11', 'Tablets', 'Canvas for work and play', 'The Lumen Studio Tab 11 pairs a vibrant 11-inch OLED display with a fast octa-core processor, low-latency pen support, and quad speakers—perfect for sketching, streaming, and productivity on the go.', 899.00, 18, 'assets/images/tablets/studiotab11.png', JSON_OBJECT(
  'Display', '11\" OLED 120Hz',
  'Processor', 'Octa-core 3.0GHz',
  'Memory', '8GB LPDDR5',
  'Storage', '256GB UFS 3.1',
  'Pen Support', '4096 levels, low latency',
  'Connectivity', 'Wi-Fi 6E, Bluetooth 5.3, USB-C',
  'Battery', '9000 mAh (up to 12 hrs)'
), 0);
