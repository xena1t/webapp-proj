<?php
require_once __DIR__ . '/db.php';

const NEWSLETTER_PROMO_CODE = 'WELCOME10';
const NEWSLETTER_PROMO_RATE = 0.10;

/* ----------------------------
|  URL + path helpers
| ---------------------------- */
function is_absolute_url(string $u): bool
{
    return (bool)preg_match('~^https?://~i', $u);
}

function app_base_url(): string
{
    // e.g. "/ie4727/webapp-proj-yh-branch"
    return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
}

/** Turn a DB-stored path like "assets/uploads/..." into a web URL under your app. */
function asset_url(string $path): string
{
    if ($path === '') return '';
    if (is_absolute_url($path) || str_starts_with($path, '/')) return $path;
    return app_base_url() . '/' . ltrim($path, '/');
}

/* ----------------------------
|  Products (public by default)
|  Pass $onlyActive=false in admin when you need archived rows too.
| ---------------------------- */
function fetch_categories(bool $onlyActive = true): array
{
    try {
        $pdo = get_db_connection();
        if ($onlyActive) {
            $stmt = $pdo->query('SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category');
        } else {
            $stmt = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category');
        }
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $exception) {
        return [];
    }
}

function fetch_featured_products(int $limit = 3, bool $onlyActive = true): array
{
    try {
        $pdo = get_db_connection();
        $sql = 'SELECT * FROM products';
        if ($onlyActive) $sql .= ' WHERE is_active = 1';
        $sql .= ' ORDER BY featured DESC, created_at DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function fetch_products_by_category(?string $category = null, ?int $limit = null, bool $onlyActive = true): array
{
    try {
        $pdo = get_db_connection();

        if ($category) {
            $sql = 'SELECT * FROM products WHERE category = :category';
            if ($onlyActive) $sql .= ' AND is_active = 1';
            $sql .= ' ORDER BY name';
            if ($limit !== null) $sql .= ' LIMIT :limit';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $sql = 'SELECT * FROM products';
        if ($onlyActive) $sql .= ' WHERE is_active = 1';
        $sql .= ' ORDER BY category, name';
        if ($limit !== null) $sql .= ' LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        if ($limit !== null) $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

/** Public fetch (hides archived by default). Use $onlyActive=false in admin. */
function fetch_product(int $id, bool $onlyActive = true): ?array
{
    try {
        $pdo = get_db_connection();
        $sql = 'SELECT * FROM products WHERE id = :id';
        if ($onlyActive) $sql .= ' AND is_active = 1';
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

/* ----------------------------
|  Formatting
| ---------------------------- */
function format_price(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/* ----------------------------
|  Session + Auth
| ---------------------------- */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function get_authenticated_user(): ?array
{
    start_session();
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) return null;
    return $_SESSION['user'];
}

function get_authenticated_user_id(): ?int
{
    $user = get_authenticated_user();
    if ($user === null || !isset($user['id'])) return null;
    return (int)$user['id'];
}

function is_user_logged_in(): bool
{
    return get_authenticated_user_id() !== null;
}

function is_user_admin(): bool
{
    $user = get_authenticated_user();
    return $user !== null && isset($user['is_admin']) && (int)$user['is_admin'] === 1;
}

/* ----------------------------
|  Cart
| ---------------------------- */
function get_cart(): array
{
    $userId = get_authenticated_user_id();
    if ($userId === null) return [];
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        $cart = [];
        foreach ($stmt->fetchAll() as $row) {
            $cart[(int)$row['product_id']] = (int)$row['quantity'];
        }
        return $cart;
    } catch (Throwable $exception) {
        return [];
    }
}

function add_to_cart(int $productId, int $quantity): void
{
    if ($quantity <= 0) return;

    $userId = get_authenticated_user_id();
    if ($userId === null) {
        throw new RuntimeException('You must be logged in to add items to your cart.');
    }

    $pdo = get_db_connection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $sql = 'INSERT INTO cart_items (user_id, product_id, quantity)
                 VALUES (:user_id, :product_id, :quantity)
                 ON CONFLICT(user_id, product_id)
                 DO UPDATE SET quantity = cart_items.quantity + excluded.quantity,
                                updated_at = CURRENT_TIMESTAMP';
    } else {
        $sql = 'INSERT INTO cart_items (user_id, product_id, quantity)
                 VALUES (:user_id, :product_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity),
                                         updated_at = CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id'    => $userId,
        'product_id' => $productId,
        'quantity'   => $quantity,
    ]);
}

function update_cart_item(int $productId, int $quantity): void
{
    $userId = get_authenticated_user_id();
    if ($userId === null) {
        throw new RuntimeException('You must be logged in to update your cart.');
    }

    $pdo = get_db_connection();

    if ($quantity <= 0) {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = :user_id AND product_id = :product_id');
        $stmt->execute(['user_id' => $userId, 'product_id' => $productId]);
        return;
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $sql = 'INSERT INTO cart_items (user_id, product_id, quantity)
                 VALUES (:user_id, :product_id, :quantity)
                 ON CONFLICT(user_id, product_id)
                 DO UPDATE SET quantity = excluded.quantity,
                                updated_at = CURRENT_TIMESTAMP';
    } else {
        $sql = 'INSERT INTO cart_items (user_id, product_id, quantity)
                 VALUES (:user_id, :product_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity),
                                         updated_at = CURRENT_TIMESTAMP';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id'    => $userId,
        'product_id' => $productId,
        'quantity'   => $quantity,
    ]);
}

function clear_cart(): void
{
    $userId = get_authenticated_user_id();
    if ($userId === null) return;

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    } catch (Throwable $exception) {
        // Ignore; checkout flow will report if necessary.
    }
}

function fetch_cart_items(): array
{
    $userId = get_authenticated_user_id();
    if ($userId === null) return [];

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, ci.quantity
               FROM cart_items ci
               INNER JOIN products p ON p.id = ci.product_id
              WHERE ci.user_id = :user_id
              ORDER BY ci.added_at ASC, p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $products = $stmt->fetchAll();

        foreach ($products as &$product) {
            $product['quantity']  = (int)$product['quantity'];
            $product['price']     = (float)$product['price'];
            $product['line_total'] = $product['quantity'] * $product['price'];
        }
        return $products;
    } catch (Throwable $exception) {
        return [];
    }
}

/* ----------------------------
|  Misc
| ---------------------------- */
function calculate_cart_totals(array $items, float $discountRate = 0.0): array
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) ($item['line_total'] ?? 0);
    }

    $discountRate = max(0.0, min($discountRate, 1.0));
    $discount = round($subtotal * $discountRate, 2);
    $total    = max($subtotal - $discount, 0.0);

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'discount_rate' => $discountRate,
        'total' => $total,
    ];
}

function validate_newsletter_promo(string $code, string $email): array
{
    $normalized = strtoupper(trim($code));
    if ($normalized === '') {
        return ['valid' => false, 'message' => '', 'code' => '', 'rate' => 0.0];
    }

    if ($normalized !== NEWSLETTER_PROMO_CODE) {
        return [
            'valid' => false,
            'message' => 'That promo code is not recognized.',
            'code' => $normalized,
            'rate' => 0.0,
        ];
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_subscribers WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $isSubscriber = (int) $stmt->fetchColumn() > 0;
        if (!$isSubscriber) {
            return [
                'valid' => false,
                'message' => 'Join the newsletter to unlock the WELCOME10 discount.',
                'code' => $normalized,
                'rate' => 0.0,
            ];
        }
    } catch (Throwable $exception) {
        return [
            'valid' => false,
            'message' => 'We could not verify your promo code right now. Please try again.',
            'code' => $normalized,
            'rate' => 0.0,
        ];
    }

    return [
        'valid' => true,
        'message' => 'Newsletter code applied! Enjoy 10% off your order.',
        'code' => $normalized,
        'rate' => NEWSLETTER_PROMO_RATE,
    ];
}

function sanitize_string(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS));
}
