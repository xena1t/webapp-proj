<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Product Administration';

start_session();

if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to access the admin panel.';
    header('Location: login.php');
    exit;
}

if (!is_user_admin()) {
    http_response_code(403);
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="container">
        <h1 class="section-title">Access denied</h1>
        <p>You do not have permission to view this page.</p>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $tagline = trim($_POST['tagline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $specJsonRaw = trim($_POST['spec_json'] ?? '');
        $imageUrlInput = trim($_POST['image_url'] ?? '');
        $imageUrl = '';
        $uploadInfo = null;

        if ($name === '') {
            $errors[] = 'Product name is required.';
        }
        if ($category === '') {
            $errors[] = 'Category is required.';
        }
        if ($description === '') {
            $errors[] = 'A description is required.';
        }
        if ($price === false || $price < 0) {
            $errors[] = 'Price must be a positive number.';
        }
        if ($stock === false || $stock < 0) {
            $errors[] = 'Stock must be zero or a positive integer.';
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload image. Please try again.';
            } else {
                $tmpPath = $_FILES['image']['tmp_name'];
                $fileSize = (int) ($_FILES['image']['size'] ?? 0);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : null;
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedTypes[$mimeType])) {
                    $errors[] = 'Product images must be JPG, PNG, GIF, or WEBP files.';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $errors[] = 'Product images must be 5MB or smaller.';
                } else {
                    $uploadDir = __DIR__ . '/assets/uploads';
                    $filename = uniqid('product_', true) . '.' . $allowedTypes[$mimeType];
                    $uploadInfo = [
                        'tmp_name' => $tmpPath,
                        'destination' => $uploadDir . '/' . $filename,
                        'relative' => 'assets/uploads/' . $filename,
                    ];
                }
            }
        } elseif ($imageUrlInput !== '') {
            if (!filter_var($imageUrlInput, FILTER_VALIDATE_URL)) {
                $errors[] = 'The external image URL must be a valid URL.';
            } else {
                $imageUrl = $imageUrlInput;
            }
        } else {
            $errors[] = 'Upload an image or provide an image URL.';
        }

        $specJsonValue = null;
        if ($specJsonRaw !== '') {
            $decodedSpec = json_decode($specJsonRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Specifications must be valid JSON.';
            } else {
                $specJsonValue = json_encode($decodedSpec, JSON_UNESCAPED_UNICODE);
            }
        }

        if (empty($errors) && $uploadInfo !== null) {
            $uploadDir = dirname($uploadInfo['destination']);
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $errors[] = 'Unable to create the uploads directory.';
                }
            }

            if (empty($errors)) {
                if (!move_uploaded_file($uploadInfo['tmp_name'], $uploadInfo['destination'])) {
                    $errors[] = 'Failed to save the uploaded image.';
                } else {
                    $imageUrl = $uploadInfo['relative'];
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('INSERT INTO products (name, category, tagline, description, price, stock, image_url, spec_json, featured) VALUES (:name, :category, :tagline, :description, :price, :stock, :image_url, :spec_json, :featured)');
                $stmt->execute([
                    'name' => $name,
                    'category' => $category,
                    'tagline' => $tagline !== '' ? $tagline : null,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'image_url' => $imageUrl,
                    'spec_json' => $specJsonValue,
                    'featured' => $featured,
                ]);

                $successMessage = 'Product "' . htmlspecialchars($name) . '" was added successfully.';
                $_POST = [];
            } catch (Throwable $exception) {
                $errors[] = 'Failed to add product: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$productId) {
            $errors[] = 'A valid product ID is required to delete a product.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = :id');
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    $errors[] = 'Product not found or already deleted.';
                } else {
                    $deleteStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
                    $deleteStmt->execute(['id' => $productId]);

                    if ($deleteStmt->rowCount() === 0) {
                        $errors[] = 'Unable to delete the product. Please try again.';
                    } else {
                        $imageUrl = $product['image_url'] ?? '';
                        if (is_string($imageUrl) && strpos($imageUrl, 'assets/uploads/') === 0) {
                            $filePath = __DIR__ . '/' . $imageUrl;
                            if (is_file($filePath)) {
                                @unlink($filePath);
                            }
                        }
                        $successMessage = 'Product removed successfully.';
                    }
                }
            } catch (Throwable $exception) {
                $errors[] = 'Failed to delete product: ' . $exception->getMessage();
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

$allProducts = fetch_products_by_category();

require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Product administration</h1>

    <?php if (!empty($errors)): ?>
        <div class="notice" role="alert" style="margin-bottom: 1.5rem;">
            <strong>We ran into some issues:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="notice" style="margin-bottom: 1.5rem; background: #e6ffed; border-color: #34c759;">
            <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <section style="margin-bottom: 2rem;">
        <h2>Add a new product</h2>
        <form method="post" enctype="multipart/form-data" class="form-grid" style="gap: 1rem;">
            <input type="hidden" name="action" value="add">
            <div>
                <label for="name">Product name</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div>
                <label for="category">Category</label>
                <input type="text" id="category" name="category" required value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
            </div>
            <div>
                <label for="tagline">Tagline</label>
                <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($_POST['tagline'] ?? '') ?>">
            </div>
            <div>
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
            </div>
            <div>
                <label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" required value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>">
            </div>
            <div>
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="image">Upload image</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            <div>
                <label for="image_url">Or provide external image URL</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>">
            </div>
            <div>
                <label for="spec_json">Specifications (JSON)</label>
                <textarea id="spec_json" name="spec_json" rows="4" placeholder='{"Processor":"Intel", "RAM":"16GB"}'><?= htmlspecialchars($_POST['spec_json'] ?? '') ?></textarea>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="featured" value="1" <?= isset($_POST['featured']) ? 'checked' : '' ?>> Featured product
                </label>
            </div>
            <div>
                <button type="submit" class="btn-primary">Add product</button>
            </div>
        </form>
    </section>

    <section>
        <h2>Existing products</h2>
        <?php if (empty($allProducts)): ?>
            <p>No products found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProducts as $product): ?>
                            <tr>
                                <td>#<?= (int) $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category']) ?></td>
                                <td><?= format_price((float) $product['price']) ?></td>
                                <td><?= (int) $product['stock'] ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="btn-secondary">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require_once __DIR__ . '/includes/footer.php';
