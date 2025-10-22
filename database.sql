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

INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES
('AetherBook Pro 16', 'Laptops', 'Creator-grade performance in a 1.9kg chassis', 'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.', 2399.00, 15, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Processor', 'Intel Core Ultra 7', 'Graphics', 'NVIDIA RTX 4070 8GB', 'Display', '16" mini-LED 165Hz', 'Memory', '32GB LPDDR5X', 'Storage', '1TB NVMe Gen4 SSD'), 1),
('Nebula Studio Monitor', 'Peripherals', 'Calibrated for color-critical workflows', 'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.', 1299.00, 22, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=60', JSON_OBJECT('Resolution', '5120x2880 5K', 'Brightness', '1000 nits peak', 'Connectivity', 'Thunderbolt 4, HDMI 2.1', 'Calibration', 'Factory Delta E < 2'), 1),
('Pulse Mechanical Keyboard', 'Peripherals', 'Hot-swappable switches with smart layer macros', 'Experience responsive typing with gasket-mounted acoustics, per-key RGB, and on-board macro layers controllable via web app—no drivers needed.', 189.00, 40, 'https://images.unsplash.com/photo-1516382799247-94bc6e6c62de?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Layout', '75% compact', 'Switches', 'Hot-swap linear', 'Connectivity', 'USB-C, Bluetooth 5.1', 'Battery', '4000mAh'), 1),
('Quantum RTX 5080 GPU', 'Components', 'AI-ready graphics powerhouse', 'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.', 1599.00, 8, 'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('CUDA Cores', '18,432', 'Memory', '32GB GDDR7', 'Boost Clock', '2.7GHz', 'Power', '350W TDP'), 1),
('Flux Smart Lighting Kit', 'Smart Home', 'Adaptive lighting for immersive focus', 'Transform any room with adaptive color temperatures, voice assistant support, and synchronised lighting scenes for work and play.', 249.00, 60, 'https://images.unsplash.com/photo-1505692794403-55b39e00c3df?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Connectivity', 'Wi-Fi 6 + Matter', 'Color Range', '16M colors', 'Integrations', 'Alexa, Google, HomeKit', 'Power', '18W per panel'), 0),
('Vortex Gaming Mouse', 'Peripherals', '65g lightweight, 4K polling precision', 'Engineered for esports reflexes with custom sensor tuning, textured side grips, and adaptive lift-off calibration.', 129.00, 75, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=40', JSON_OBJECT('Sensor', 'Focus+ 39K DPI', 'Polling', '4K wireless', 'Battery', '80 hours', 'Buttons', '8 programmable'), 0),
('Atlas Workstation Tower', 'Components', 'Modular power for studios and labs', 'Pre-configured workstation featuring Threadripper™ Pro, ECC memory, and hot-swap NVMe bays for uncompromised throughput.', 3899.00, 5, 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('CPU', 'AMD Threadripper Pro 7985WX', 'Memory', '128GB ECC DDR5', 'Storage', '2TB NVMe + 4TB HDD', 'Graphics', 'RTX 5000 Ada 32GB'), 1),
('Nimbus Noise-Cancelling Headphones', 'Peripherals', 'Studio-quality ANC with spatial audio', 'Hybrid active noise cancelling headphones tuned for clarity, with 35-hour battery life and wireless lossless streaming.', 349.00, 28, 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=900&q=80', JSON_OBJECT('Drivers', '40mm Beryllium', 'Battery', '35 hours', 'Connectivity', 'Bluetooth 5.3 + USB-C', 'Audio', 'Spatial audio, LDAC'), 0);
