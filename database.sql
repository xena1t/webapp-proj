CREATE DATABASE IF NOT EXISTS techmart CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE techmart;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    tagline VARCHAR(200) DEFAULT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) NOT NULL,
    spec_json JSON DEFAULT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    shipping_address TEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Processing',
    promo_code VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    preference VARCHAR(80) NOT NULL,
    budget_focus VARCHAR(40) NOT NULL,
    subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('AetherBook Pro 16', 'Laptops', 'Creator-grade performance in a 1.9kg chassis', 'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.', 2399.00, 15, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Processor', 'Intel Core Ultra 7', 'Graphics', 'NVIDIA RTX 4070 8GB', 'Display', '16" mini-LED 165Hz', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4 SSD'), 1),
('Nebula Studio Monitor', 'Peripherals', 'Calibrated for color-critical workflows', 'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.', 1299.00, 22, 'https://images.unsplash.com/photo-1516542076529-1ea3854896e1?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Resolution', '5120x2880 5K', 'Brightness', '1000 nits peak', 'Connectivity', 'Thunderbolt 4, HDMI 2.1', 'Calibration', 'Factory Delta E < 2'), 1),
('Quantum RTX 5080 GPU', 'Components', 'AI-ready graphics powerhouse', 'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.', 1599.00, 8, 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('CUDA Cores', '18,432', 'Memory', '32GB GDDR7', 'Boost Clock', '2.7GHz', 'Power', '350W TDP'), 1);


-- for creating user database for login purposes.
CREATE TABLE if not exists users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0
);


INSERT INTO users (username, email, password, is_admin) VALUES
('admin', 'admin@local.com', 'admin', 1);
