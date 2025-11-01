<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

if (!is_user_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to manage your wishlist.',
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
$action = isset($payload['action']) ? trim((string) $payload['action']) : 'toggle';

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product selection.',
    ]);
    exit;
}

$userId = get_authenticated_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to manage your wishlist.',
    ]);
    exit;
}

$product = fetch_product($productId);
if (!$product) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Product not found.',
    ]);
    exit;
}

try {
    $isInWishlist = is_product_in_wishlist($userId, $productId);

    if ($action === 'remove') {
        if ($isInWishlist) {
            remove_wishlist_item($userId, $productId);
        }
        $isInWishlist = false;
    } elseif ($action === 'add') {
        if (!$isInWishlist) {
            add_wishlist_item($userId, $productId);
            $isInWishlist = true;
        }
    } else { // toggle
        if ($isInWishlist) {
            remove_wishlist_item($userId, $productId);
            $isInWishlist = false;
        } else {
            add_wishlist_item($userId, $productId);
            $isInWishlist = true;
        }
    }

    echo json_encode([
        'success' => true,
        'inWishlist' => $isInWishlist,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Failed to update wishlist: ' . $exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update wishlist right now. Please try again later.',
    ]);
}
