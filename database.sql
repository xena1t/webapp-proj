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
('AetherBook Pro 16', 'Laptops', 'Creator-grade performance in a 1.9kg chassis', 'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.', 2399.00, 15, 'assets/images/products/aetherbook-pro-16.svg', JSON_OBJECT('Processor', 'Intel Core Ultra 7', 'Graphics', 'NVIDIA RTX 4070 8GB', 'Display', '16" mini-LED 165Hz', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4 SSD'), 1),
('Nebula Studio Monitor', 'Peripherals', 'Calibrated for color-critical workflows', 'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.', 1299.00, 22, 'assets/images/products/nebula-studio-monitor.svg', JSON_OBJECT('Resolution', '5120x2880 5K', 'Brightness', '1000 nits peak', 'Connectivity', 'Thunderbolt 4, HDMI 2.1', 'Calibration', 'Factory Delta E < 2'), 1),
('Quantum RTX 5080 GPU', 'Components', 'AI-ready graphics powerhouse', 'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.', 1599.00, 8, 'assets/images/products/quantum-rtx-5080.svg', JSON_OBJECT('CUDA Cores', '18,432', 'Memory', '32GB GDDR7', 'Boost Clock', '2.7GHz', 'Power', '350W TDP'), 1),
('Lumen Creator Tablet', 'Tablets', 'Pen-ready canvas for illustrators', 'The Lumen Creator pairs a laminated 13" display with 8,192 pressure levels and tilt recognition, delivering a natural sketching workflow anywhere.', 699.00, 18, 'assets/images/products/lumen-creator-tablet.svg', JSON_OBJECT('Display', '13" 2.8K laminated', 'Pen', '8192 levels + tilt', 'Connectivity', 'USB-C + Bluetooth 5.3', 'Battery', '18 hours'), 0),
('Orion Mech Keyboard', 'Peripherals', '65% layout with hot-swap switches', 'An aluminium-framed, gasket-mounted keyboard featuring per-key RGB, south-facing stabilisers, and tri-mode connectivity for any battlestation.', 219.00, 40, 'assets/images/products/orion-mechanical-keyboard.svg', JSON_OBJECT('Layout', '65% with arrow keys', 'Switches', 'Hot-swap linear (lubed)', 'Connectivity', 'USB-C, 2.4GHz, Bluetooth', 'Battery', '4000 mAh'), 0),
('Solace ANC Headset', 'Audio', 'Focus on your mix—not the noise', 'Solace Active Noise Cancelling headphones deliver studio reference tuning, adaptive transparency, and 35-hour battery life for deep work sessions.', 349.00, 30, 'assets/images/products/solace-noise-cancelling-headset.svg', JSON_OBJECT('Drivers', '42mm graphene', 'ANC', 'Hybrid 4-mic', 'Connectivity', 'Bluetooth 5.4 + USB-C', 'Battery', 'Up to 35 hours'), 0);

INSERT INTO users (username, email, password, is_admin) VALUES
('admin', 'admin@local.com', 'admin', 1),
('test', 'test@local.com', 'test', 0);
