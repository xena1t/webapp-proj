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

function fetch_products_by_category(?string $category = null, ?int $limit = null): array
{
    try {
        $pdo = get_db_connection();
        if ($category) {
            $sql = 'SELECT * FROM products WHERE category = :category ORDER BY name';
            if ($limit !== null) {
                $sql .= ' LIMIT :limit';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $sql = 'SELECT * FROM products ORDER BY category, name';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
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

function get_authenticated_user(): ?array
{
    start_session();

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function get_authenticated_user_id(): ?int
{
    $user = get_authenticated_user();

    if ($user === null || !isset($user['id'])) {
        return null;
    }

    return (int) $user['id'];
}

function is_user_logged_in(): bool
{
    return get_authenticated_user_id() !== null;
}

function is_user_admin(): bool
{
    $user = get_authenticated_user();

    if ($user === null) {
        return false;
    }

    return isset($user['is_admin']) && (int) $user['is_admin'] === 1;
}

function get_cart(): array
{
    $userId = get_authenticated_user_id();

    if ($userId === null) {
        return [];
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        $cart = [];
        foreach ($stmt->fetchAll() as $row) {
            $cart[(int) $row['product_id']] = (int) $row['quantity'];
        }

        return $cart;
    } catch (Throwable $exception) {
        return [];
    }
}

function add_to_cart(int $productId, int $quantity): void
{
    if ($quantity <= 0) {
        return;
    }

    $userId = get_authenticated_user_id();
    if ($userId === null) {
        throw new RuntimeException('You must be logged in to add items to your cart.');
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'product_id' => $productId,
        'quantity' => $quantity,
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
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'product_id' => $productId,
        'quantity' => $quantity,
    ]);
}

function clear_cart(): void
{
    $userId = get_authenticated_user_id();
    if ($userId === null) {
        return;
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    } catch (Throwable $exception) {
        // Ignore failures when clearing the cart; checkout flow will report if necessary.
    }
}

function fetch_cart_items(): array
{
    $userId = get_authenticated_user_id();

    if ($userId === null) {
        return [];
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, ci.quantity FROM cart_items ci
            INNER JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = :user_id
            ORDER BY ci.added_at ASC, p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $products = $stmt->fetchAll();

        foreach ($products as &$product) {
            $product['quantity'] = (int) $product['quantity'];
            $product['line_total'] = $product['quantity'] * (float) $product['price'];
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
