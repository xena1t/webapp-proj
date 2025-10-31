<?php
$configPath = __DIR__ . '/../config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    require_once __DIR__ . '/../config.sample.php';
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

function get_db_connection(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = defined('DB_DSN') ? DB_DSN : sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    if (str_starts_with($dsn, 'sqlite:')) {
        $path = substr($dsn, strlen('sqlite:'));
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create SQLite data directory: ' . $directory);
            }
        }
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO(
            $dsn,
            defined('DB_USER') ? DB_USER : null,
            defined('DB_PASS') ? DB_PASS : null,
            $options
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<h1>Database Connection Error</h1>';
        echo '<p>Please check your database configuration.</p>';
        exit;
    }

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
        initialize_sqlite_schema($pdo);
    }

    return $pdo;
}

function initialize_sqlite_schema(PDO $pdo): void
{
    static $initialised = false;
    if ($initialised) {
        return;
    }
    $initialised = true;

    $schemaStatements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL,
            tagline TEXT,
            description TEXT NOT NULL,
            price REAL NOT NULL,
            stock INTEGER NOT NULL DEFAULT 0,
            image_url TEXT NOT NULL,
            spec_json TEXT,
            featured INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            shipping_address TEXT NOT NULL,
            total REAL NOT NULL,
            status TEXT NOT NULL DEFAULT "Processing",
            promo_code TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
        )',
        'CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            preference TEXT NOT NULL,
            budget_focus TEXT NOT NULL,
            subscribed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS cart_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 1,
            added_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, product_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
        )',
    ];

    foreach ($schemaStatements as $statement) {
        $pdo->exec($statement);
    }

    seed_sqlite_data($pdo);
}

function seed_sqlite_data(PDO $pdo): void
{
    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($productCount === 0) {
        $products = [
            [
                'AetherBook Pro 16',
                'Laptops',
                'Creator-grade performance in a 1.9kg chassis',
                'The AetherBook Pro 16 combines Intel® Core™ Ultra processing with RTX™ 4070 graphics, 32GB LPDDR5X memory, and a mini-LED display tailored for creative pros.',
                2399.00,
                15,
                'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80',
                json_encode([
                    'Processor' => 'Intel Core Ultra 7',
                    'Graphics' => 'NVIDIA RTX 4070 8GB',
                    'Display' => '16" mini-LED 165Hz',
                    'Memory' => '32GB LPDDR5X',
                    'Storage' => '1TB NVMe Gen4 SSD',
                ]),
                1,
            ],
            [
                'Nebula Studio Monitor',
                'Peripherals',
                'Calibrated for color-critical workflows',
                'A 32-inch 5K HDR monitor with 99% DCI-P3 coverage, Thunderbolt™ 4 connectivity, and ambient light adaptive brightness for marathon sessions.',
                1299.00,
                22,
                'https://images.unsplash.com/photo-1516542076529-1ea3854896e1?auto=format&fit=crop&w=900&q=80',
                json_encode([
                    'Resolution' => '5120x2880 5K',
                    'Brightness' => '1000 nits peak',
                    'Connectivity' => 'Thunderbolt 4, HDMI 2.1',
                    'Calibration' => 'Factory Delta E < 2',
                ]),
                1,
            ],
            [
                'Quantum RTX 5080 GPU',
                'Components',
                'AI-ready graphics powerhouse',
                'The Quantum RTX 5080 delivers 18,000 CUDA cores, 32GB GDDR7 memory, and fourth-gen tensor acceleration optimized for creative AI workloads.',
                1599.00,
                8,
                'https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?auto=format&fit=crop&w=900&q=80',
                json_encode([
                    'CUDA Cores' => '18,432',
                    'Memory' => '32GB GDDR7',
                    'Boost Clock' => '2.7GHz',
                    'Power' => '350W TDP',
                ]),
                1,
            ],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured)
             VALUES (:name, :category, :tagline, :description, :price, :stock, :image_url, :spec_json, :featured)'
        );

        foreach ($products as $product) {
            $stmt->execute([
                'name' => $product[0],
                'category' => $product[1],
                'tagline' => $product[2],
                'description' => $product[3],
                'price' => $product[4],
                'stock' => $product[5],
                'image_url' => $product[6],
                'spec_json' => $product[7],
                'featured' => $product[8],
            ]);
        }
    }

    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, is_admin) VALUES (:username, :email, :password, :is_admin)');
        $stmt->execute([
            'username' => 'admin',
            'email' => 'admin@local.com',
            'password' => 'admin',
            'is_admin' => 1,
        ]);
    }
}
