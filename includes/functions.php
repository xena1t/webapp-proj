<?php
require_once __DIR__ . '/db.php';

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
function fetch_categories(bool $onlyActive = true, bool $suppressErrors = true): array
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
        error_log('Failed to fetch categories: ' . $exception->getMessage());
        if (!$suppressErrors) {
            throw $exception;
        }
        return [];
    }
}

function fetch_featured_products(int $limit = 3, bool $onlyActive = true, bool $suppressErrors = true): array
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
        error_log('Failed to fetch featured products: ' . $exception->getMessage());
        if (!$suppressErrors) {
            throw $exception;
        }
        return [];
    }
}

function fetch_products_by_category(
    ?string $category = null,
    ?int $limit = null,
    bool $onlyActive = true,
    bool $suppressErrors = true,
    ?string $searchTerm = null
): array
{
    try {
        $pdo = get_db_connection();

        $conditions = [];

        $category = $category !== null ? trim($category) : null;
        if ($category === '') {
            $category = null;
        }

        if ($onlyActive) {
            $conditions[] = 'is_active = 1';
        }

        if ($category !== null) {
            $conditions[] = 'category = :category';
        }

        $searchTerm = $searchTerm !== null ? trim($searchTerm) : null;
        $useSearch = $searchTerm !== null && $searchTerm !== '';
        if ($useSearch) {
            $conditions[] = '(name LIKE :search OR tagline LIKE :search OR description LIKE :search)';
        }

        $sql = 'SELECT * FROM products';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($category !== null || $useSearch) {
            $sql .= ' ORDER BY name';
        } else {
            $sql .= ' ORDER BY category, name';
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $pdo->prepare($sql);
        if ($category !== null) {
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        }
        if ($useSearch) {
            $stmt->bindValue(':search', '%' . $searchTerm . '%', PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Failed to fetch products: ' . $exception->getMessage());
        if (!$suppressErrors) {
            throw $exception;
        }
        return [];
    }
}

/** Public fetch (hides archived by default). Use $onlyActive=false in admin. */
function fetch_product(int $id, bool $onlyActive = true, bool $suppressErrors = true): ?array
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
        error_log('Failed to fetch product #' . $id . ': ' . $exception->getMessage());
        if (!$suppressErrors) {
            throw $exception;
        }
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
    $stmt = $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity)
         VALUES (:user_id, :product_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
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

    $stmt = $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity)
         VALUES (:user_id, :product_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );
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
        error_log('Failed to clear cart for user ' . $userId . ': ' . $exception->getMessage());
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
            $product['line_total'] = $product['quantity'] * (float)$product['price'];
        }
        return $products;
    } catch (Throwable $exception) {
        error_log('Failed to fetch cart items: ' . $exception->getMessage());
        return [];
    }
}

/* ----------------------------
|  Wishlist
| ---------------------------- */
function get_wishlist_product_ids(): array
{
    $userId = get_authenticated_user_id();
    if ($userId === null) return [];

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT product_id FROM wishlist_items WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids ?: []);
    } catch (Throwable $exception) {
        error_log('Failed to fetch wishlist product IDs: ' . $exception->getMessage());
        return [];
    }
}

function is_product_in_wishlist(int $productId, ?array $wishlistProductIds = null): bool
{
    if ($wishlistProductIds === null) {
        $wishlistProductIds = get_wishlist_product_ids();
    }

    return in_array($productId, $wishlistProductIds, true);
}

function add_to_wishlist(int $productId): void
{
    $userId = get_authenticated_user_id();
    if ($userId === null) {
        throw new RuntimeException('You must be logged in to modify your wishlist.');
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'INSERT INTO wishlist_items (user_id, product_id) VALUES (:user_id, :product_id)
         ON DUPLICATE KEY UPDATE added_at = VALUES(added_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'product_id' => $productId,
    ]);
}

function remove_from_wishlist(int $productId): void
{
    $userId = get_authenticated_user_id();
    if ($userId === null) {
        throw new RuntimeException('You must be logged in to modify your wishlist.');
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('DELETE FROM wishlist_items WHERE user_id = :user_id AND product_id = :product_id');
    $stmt->execute([
        'user_id' => $userId,
        'product_id' => $productId,
    ]);
}

function fetch_wishlist_items(): array
{
    $userId = get_authenticated_user_id();
    if ($userId === null) return [];

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, wi.added_at
               FROM wishlist_items wi
               INNER JOIN products p ON p.id = wi.product_id
              WHERE wi.user_id = :user_id AND p.is_active = 1
              ORDER BY wi.added_at DESC, p.name ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Failed to fetch wishlist items: ' . $exception->getMessage());
        return [];
    }
}

/* ----------------------------
|  Misc
| ---------------------------- */
function calculate_cart_totals(array $items, ?array $discount = null): array
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }
    $discountAmount = 0.0;
    if ($discount && isset($discount['percent'])) {
        $discountAmount = calculate_discount_amount($discount, $subtotal);
    }
    $total    = max($subtotal - $discountAmount, 0.0);

    return ['subtotal' => $subtotal, 'discount' => $discountAmount, 'total' => $total];
}

function sanitize_string(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS));
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function find_active_discount_code_for_email(string $email, ?PDO $pdo = null): ?array
{
    $pdo = $pdo ?: get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT * FROM discount_codes
          WHERE email = :email AND redeemed_at IS NULL
          ORDER BY created_at DESC
          LIMIT 1'
    );
    $stmt->execute(['email' => normalize_email($email)]);
    $code = $stmt->fetch();
    return $code ?: null;
}

function generate_unique_discount_code(int $length = 10, ?PDO $pdo = null): string
{
    $pdo = $pdo ?: get_db_connection();
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxAttempts = 20;

    while ($maxAttempts-- > 0) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM discount_codes WHERE code = :code');
        $stmt->execute(['code' => $code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to generate a unique discount code.');
}

function issue_newsletter_discount_code(int $subscriberId, string $email, float $percent = 10.0, ?PDO $pdo = null): array
{
    $pdo = $pdo ?: get_db_connection();

    $existing = find_active_discount_code_for_email($email, $pdo);
    if ($existing) {
        return [
            'id' => (int)$existing['id'],
            'code' => $existing['code'],
            'percent' => (float)$existing['discount_percent'],
            'email' => $existing['email'],
        ];
    }

    $code = generate_unique_discount_code(10, $pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO discount_codes (newsletter_subscriber_id, code, email, discount_percent, max_uses)
         VALUES (:subscriber_id, :code, :email, :percent, :max_uses)'
    );
    $stmt->execute([
        'subscriber_id' => $subscriberId,
        'code' => $code,
        'email' => normalize_email($email),
        'percent' => $percent,
        'max_uses' => 1,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'code' => $code,
        'percent' => $percent,
        'email' => normalize_email($email),
    ];
}

function calculate_discount_amount(array $discount, float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }

    $percent = isset($discount['percent']) ? (float)$discount['percent'] : 0.0;
    if ($percent <= 0) {
        return 0.0;
    }

    return round($subtotal * ($percent / 100), 2);
}

function validate_discount_code(string $code, string $email, ?int $userId = null): array
{
    $code = strtoupper(trim($code));
    $email = normalize_email($email);

    if ($code === '') {
        return ['valid' => false, 'message' => 'Enter a discount code to apply it.'];
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM discount_codes WHERE code = :code LIMIT 1');
    $stmt->execute(['code' => $code]);
    $discount = $stmt->fetch();

    if (!$discount) {
        return ['valid' => false, 'message' => 'That discount code could not be found.'];
    }

    if ($discount['email'] !== $email) {
        return ['valid' => false, 'message' => 'This discount code is tied to a different email address.'];
    }

    if (!empty($discount['redeemed_at'])) {
        return ['valid' => false, 'message' => 'That discount code has already been used.'];
    }

    if (!empty($discount['expires_at']) && strtotime($discount['expires_at']) < time()) {
        return ['valid' => false, 'message' => 'That discount code has expired.'];
    }

    return [
        'valid' => true,
        'discount' => [
            'id' => (int)$discount['id'],
            'code' => $discount['code'],
            'email' => $discount['email'],
            'percent' => (float)$discount['discount_percent'],
            'max_uses' => (int)$discount['max_uses'],
            'user_id' => $userId,
        ],
        'message' => 'Discount code applied successfully.',
    ];
}

function mark_discount_code_redeemed(int $discountId, int $orderId, ?int $userId = null, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: get_db_connection();
    $stmt = $pdo->prepare(
        'UPDATE discount_codes
            SET redeemed_at = NOW(),
                redeemed_order_id = :order_id,
                redeemed_by_user_id = :user_id
          WHERE id = :id AND redeemed_at IS NULL'
    );
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    if ($userId !== null) {
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':id', $discountId, PDO::PARAM_INT);
    $stmt->execute();
}

function fetch_orders_for_user(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $orders = $stmt->fetchAll();

        if (!$orders) {
            return [];
        }

        $orderIds = array_map('intval', array_column($orders, 'id'));
        $itemsByOrder = [];

        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsStmt = $pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.unit_price, p.name\n                   FROM order_items oi\n                   INNER JOIN products p ON p.id = oi.product_id\n                  WHERE oi.order_id IN ($placeholders)\n               ORDER BY oi.order_id DESC, p.name ASC"
            );
            $itemsStmt->execute($orderIds);

            foreach ($itemsStmt->fetchAll() as $item) {
                $orderId = (int) $item['order_id'];
                if (!isset($itemsByOrder[$orderId])) {
                    $itemsByOrder[$orderId] = [];
                }
                $itemsByOrder[$orderId][] = [
                    'name' => $item['name'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'line_total' => (float) $item['unit_price'] * (int) $item['quantity'],
                ];
            }
        }

        foreach ($orders as &$order) {
            $orderId = (int) $order['id'];
            $order['customer_order_number'] = isset($order['customer_order_number']) && (int) $order['customer_order_number'] > 0
                ? (int) $order['customer_order_number']
                : $orderId;
            $order['items'] = $itemsByOrder[$orderId] ?? [];
        }
        unset($order);

        return $orders;
    } catch (Throwable $exception) {
        error_log('Failed to fetch orders for user ' . $userId . ': ' . $exception->getMessage());
        return [];
    }
}
