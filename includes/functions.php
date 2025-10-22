<?php
require_once __DIR__ . '/db.php';

function fetch_categories(): array
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $exception) {
        return [];
    }
}

function fetch_featured_products(int $limit = 3): array
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM products ORDER BY featured DESC, created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function fetch_products_by_category(?string $category = null): array
{
    try {
        $pdo = get_db_connection();
        if ($category) {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE category = :category ORDER BY name');
            $stmt->execute(['category' => $category]);
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query('SELECT * FROM products ORDER BY category, name');
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function fetch_product(int $id): ?array
{
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function format_price(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function get_cart(): array
{
    start_session();
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

function save_cart(array $cart): void
{
    start_session();
    $_SESSION['cart'] = $cart;
}

function add_to_cart(int $productId, int $quantity): void
{
    $cart = get_cart();
    $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
    save_cart($cart);
}

function update_cart_item(int $productId, int $quantity): void
{
    $cart = get_cart();
    if ($quantity <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = $quantity;
    }
    save_cart($cart);
}

function clear_cart(): void
{
    save_cart([]);
}

function fetch_cart_items(): array
{
    $cart = get_cart();
    if (empty($cart)) {
        return [];
    }

    try {
        $pdo = get_db_connection();
        $placeholders = implode(',', array_fill(0, count($cart), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute(array_keys($cart));
        $products = $stmt->fetchAll();

        foreach ($products as &$product) {
            $product['quantity'] = $cart[$product['id']];
            $product['line_total'] = $product['quantity'] * $product['price'];
        }

        return $products;
    } catch (Throwable $exception) {
        return [];
    }
}

function calculate_cart_totals(array $items): array
{
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }
    $discount = 0;
    $total = max($subtotal - $discount, 0);

    return [
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
    ];
}

function sanitize_string(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS));
}
