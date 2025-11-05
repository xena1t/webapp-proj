<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Product Administration';
start_session();

/* ----------------------------
|  AuthN / AuthZ
| ---------------------------- */
if (!is_user_logged_in()) {
    $_SESSION['flash_error'] = 'Please log in to access the admin panel.';
    header('Location: login.php');
    exit;
}
if (!is_user_admin()) {
    http_response_code(403);
    require_once __DIR__ . '/includes/header.php'; ?>
    <section class="container">
        <h1 class="section-title">Access denied</h1>
        <p>You do not have permission to view this page.</p>
    </section>
<?php require_once __DIR__ . '/includes/footer.php';
    exit;
}

/* ----------------------------
|  State
| ---------------------------- */
$errors = [];
$successMessage = null;
$editFormOverrides = [];
$categorySuccess = null;
$maxCategoryLength = 22;
$maxProductNameLength = 22;
$addFormData = [
    'name' => '',
    'category' => '',
    'tagline' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
    'spec_json' => '',
    'featured' => 0,
    'image_url' => '',
];

/* ----------------------------
|  POST actions
| ---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---------- ADD CATEGORY ---------- */
    if ($action === 'add_category') {
        $newCategory = trim($_POST['new_category'] ?? '');

        if ($newCategory === '') {
            $errors[] = 'Category name cannot be empty.';
        } elseif ((function_exists('mb_strlen') ? mb_strlen($newCategory) : strlen($newCategory)) > $maxCategoryLength) {
            $errors[] = 'Category name must be 22 characters or fewer.';
        } else {
            try {
                $pdo = get_db_connection();
                ensure_categories_table($pdo);

                $stmt = $pdo->prepare('INSERT IGNORE INTO categories (name) VALUES (:name)');
                $stmt->execute(['name' => $newCategory]);

                if ($stmt->rowCount() === 0) {
                    $errors[] = 'That category already exists.';
                } else {
                    $categorySuccess = 'Category "' . htmlspecialchars($newCategory) . '" added successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to add category: ' . $e->getMessage();
            }
        }

        // Skip further processing for this request.
    }

    /* ---------- RENAME CATEGORY ---------- */
    elseif ($action === 'rename_category') {
        $currentName = trim($_POST['current_name'] ?? '');
        $newName = trim($_POST['updated_name'] ?? '');

        if ($currentName === '' || $newName === '') {
            $errors[] = 'Both the current and new category names are required.';
        } elseif ((function_exists('mb_strlen') ? mb_strlen($newName) : strlen($newName)) > $maxCategoryLength) {
            $errors[] = 'Category name must be 22 characters or fewer.';
        } else {
            try {
                $pdo = get_db_connection();
                ensure_categories_table($pdo);
                rename_category($pdo, $currentName, $newName);
                $categorySuccess = 'Category "' . htmlspecialchars($currentName) . '" renamed to "' . htmlspecialchars($newName) . '".';
            } catch (Throwable $e) {
                $errors[] = 'Failed to rename category: ' . $e->getMessage();
            }
        }
    }

    /* ---------- DELETE CATEGORY ---------- */
    elseif ($action === 'delete_category') {
        $categoryName = trim($_POST['category_name'] ?? '');
        $reassignTarget = trim($_POST['reassign_to'] ?? '');
        $reassignTarget = $reassignTarget !== '' ? $reassignTarget : null;

        if ($categoryName === '') {
            $errors[] = 'Select a category to delete.';
        } else {
            try {
                $pdo = get_db_connection();
                ensure_categories_table($pdo);
                delete_category($pdo, $categoryName, $reassignTarget);

                if ($reassignTarget !== null) {
                    $categorySuccess = 'Category "' . htmlspecialchars($categoryName) . '" deleted. Products were reassigned to "' . htmlspecialchars($reassignTarget) . '".';
                } else {
                    $categorySuccess = 'Category "' . htmlspecialchars($categoryName) . '" deleted.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to delete category: ' . $e->getMessage();
            }
        }
    }

    /* ---------- ADD ---------- */
    elseif ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $tagline     = trim($_POST['tagline'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $stock       = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
        $featured    = isset($_POST['featured']) ? 1 : 0;
        $specJsonRaw = trim($_POST['spec_json'] ?? '');
        $imageUrlInput = trim($_POST['image_url'] ?? '');
        $imageUrl = '';
        $uploadInfo = null;

        // keep form values on validation failure
        $addFormData = [
            'name' => $name,
            'category' => $category,
            'tagline' => $tagline,
            'description' => $description,
            'price' => $price !== false ? $price : ($_POST['price'] ?? ''),
            'stock' => $stock !== false ? $stock : ($_POST['stock'] ?? ''),
            'spec_json' => $specJsonRaw,
            'featured' => $featured,
            'image_url' => $imageUrlInput,
        ];

        // validate
        if ($name === '')            $errors[] = 'Product name is required.';
        if ($name !== '' && (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > $maxProductNameLength) {
            $errors[] = 'Product name must be 22 characters or fewer.';
        }
        if ($category === '')        $errors[] = 'Category is required.';
        if ($description === '')     $errors[] = 'A description is required.';
        if ($tagline !== '' && (function_exists('mb_strlen') ? mb_strlen($tagline) : strlen($tagline)) > 30) {
            $errors[] = 'Tagline must be 30 characters or fewer.';
        }
        if ($price === false || $price < 0)  $errors[] = 'Price must be a positive number.';
        if ($stock === false || $stock < 0)  $errors[] = 'Stock must be zero or a positive integer.';

        // image: file upload OR external URL
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload image. Please try again.';
            } else {
                $tmpPath  = $_FILES['image']['tmp_name'];
                $fileSize = (int)($_FILES['image']['size'] ?? 0);
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : null;
                if ($finfo) finfo_close($finfo);

                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                if (!isset($allowedTypes[$mimeType])) {
                    $errors[] = 'Product images must be JPG, PNG, GIF, or WEBP files.';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $errors[] = 'Product images must be 5MB or smaller.';
                } else {
                    $uploadDir = __DIR__ . '/assets/uploads';
                    $filename  = uniqid('product_', true) . '.' . $allowedTypes[$mimeType];
                    $uploadInfo = [
                        'tmp_name'    => $tmpPath,
                        'destination' => $uploadDir . '/' . $filename,
                        'relative'    => 'assets/uploads/' . $filename,
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

        // specs JSON
        $specJsonValue = null;
        if ($specJsonRaw !== '') {
            $decoded = json_decode($specJsonRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) $errors[] = 'Specifications must be valid JSON.';
            else $specJsonValue = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        // move uploaded file
        if (empty($errors) && $uploadInfo !== null) {
            $uploadDir = dirname($uploadInfo['destination']);
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $errors[] = 'Unable to create the uploads directory.';
            } elseif (!move_uploaded_file($uploadInfo['tmp_name'], $uploadInfo['destination'])) {
                $errors[] = 'Failed to save the uploaded image.';
            } else {
                $imageUrl = $uploadInfo['relative'];
            }
        }

        // insert
        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare(
                    'INSERT INTO products
                        (name, category, tagline, description, price, stock, image_url, spec_json, featured)
                     VALUES (:name, :category, :tagline, :description, :price, :stock, :image_url, :spec_json, :featured)'
                );
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
                $addFormData = ['name' => '', 'category' => '', 'tagline' => '', 'description' => '', 'price' => '', 'stock' => '', 'spec_json' => '', 'featured' => 0, 'image_url' => ''];
            } catch (Throwable $e) {
                $errors[] = 'Failed to add product: ' . $e->getMessage();
            }
        }

        /* ---------- ARCHIVE (soft delete) ---------- */
    } elseif ($action === 'delete') { // keep "delete" from the form, but archive in DB
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$productId) {
            $errors[] = 'A valid product ID is required to archive a product.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id');
                $stmt->execute(['id' => $productId]);
                if (!$stmt->fetch()) {
                    $errors[] = 'Product not found or already archived.';
                } else {
                    $upd = $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = :id');
                    $upd->execute(['id' => $productId]);
                    if ($upd->rowCount() === 0) $errors[] = 'Unable to archive the product. Please try again.';
                    else $successMessage = 'Product archived successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to archive product: ' . $e->getMessage();
            }
        }

        /* ---------- RESTORE (un-archive) ---------- */
    } elseif ($action === 'restore') {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$productId) {
            $errors[] = 'A valid product ID is required to restore a product.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('SELECT id, is_active FROM products WHERE id = :id');
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if (!$product) {
                    $errors[] = 'Product not found.';
                } elseif ((int)$product['is_active'] === 1) {
                    $errors[] = 'Product is already active.';
                } else {
                    $upd = $pdo->prepare('UPDATE products SET is_active = 1 WHERE id = :id');
                    $upd->execute(['id' => $productId]);
                    if ($upd->rowCount() === 0) {
                        $errors[] = 'Unable to restore the product. Please try again.';
                    } else {
                        $successMessage = 'Product restored successfully.';
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Failed to restore product: ' . $e->getMessage();
            }
        }

        /* ---------- EDIT ---------- */
    } elseif ($action === 'edit') {
        $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if (!$productId) {
            $errors[] = 'A valid product ID is required to edit a product.';
        } else {
            $existingProduct = fetch_product($productId, /* onlyActive = */ false);
            if (!$existingProduct) {
                $errors[] = 'Product not found or already deleted.';
            } else {
                $name        = trim($_POST['name'] ?? ($existingProduct['name'] ?? ''));
                $category    = trim($_POST['category'] ?? ($existingProduct['category'] ?? ''));
                $tagline     = trim($_POST['tagline'] ?? ($existingProduct['tagline'] ?? ''));
                $description = trim($_POST['description'] ?? ($existingProduct['description'] ?? ''));
                $price       = filter_var($_POST['price'] ?? ($existingProduct['price'] ?? ''), FILTER_VALIDATE_FLOAT);
                $stock       = filter_var($_POST['stock'] ?? ($existingProduct['stock'] ?? ''), FILTER_VALIDATE_INT);
                $featured    = isset($_POST['featured']) ? 1 : 0;
                $specJsonRaw = trim($_POST['spec_json'] ?? ($existingProduct['spec_json'] ?? ''));
                $imageUrlInput = trim($_POST['image_url'] ?? '');

                $previousImageUrl = is_string($existingProduct['image_url'] ?? null) ? $existingProduct['image_url'] : '';
                $imageUrl = $previousImageUrl;
                $uploadInfo = null;
                $removePreviousImage = false;

                if ($name === '')            $errors[] = 'Product name is required.';
                if ($name !== '' && (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > $maxProductNameLength) {
                    $errors[] = 'Product name must be 22 characters or fewer.';
                }
                if ($category === '')        $errors[] = 'Category is required.';
                if ($description === '')     $errors[] = 'A description is required.';
                if ($tagline !== '' && (function_exists('mb_strlen') ? mb_strlen($tagline) : strlen($tagline)) > 30) {
                    $errors[] = 'Tagline must be 30 characters or fewer.';
                }
                if ($price === false || $price < 0)  $errors[] = 'Price must be a positive number.';
                if ($stock === false || $stock < 0)  $errors[] = 'Stock must be zero or a positive integer.';

                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        $errors[] = 'Failed to upload image. Please try again.';
                    } else {
                        $tmpPath  = $_FILES['image']['tmp_name'];
                        $fileSize = (int)($_FILES['image']['size'] ?? 0);
                        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : null;
                        if ($finfo) finfo_close($finfo);

                        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                        if (!isset($allowedTypes[$mimeType])) {
                            $errors[] = 'Product images must be JPG, PNG, GIF, or WEBP files.';
                        } elseif ($fileSize > 5 * 1024 * 1024) {
                            $errors[] = 'Product images must be 5MB or smaller.';
                        } else {
                            $uploadDir = __DIR__ . '/assets/uploads';
                            $filename  = uniqid('product_', true) . '.' . $allowedTypes[$mimeType];
                            $uploadInfo = [
                                'tmp_name'    => $tmpPath,
                                'destination' => $uploadDir . '/' . $filename,
                                'relative'    => 'assets/uploads/' . $filename,
                            ];
                            $removePreviousImage = true;
                        }
                    }
                }

                if ($imageUrlInput !== '') {
                    if (!filter_var($imageUrlInput, FILTER_VALIDATE_URL)) {
                        $errors[] = 'The external image URL must be a valid URL.';
                    } else {
                        $imageUrl = $imageUrlInput;
                        $removePreviousImage = true;
                    }
                }

                if ($imageUrl === '' && $uploadInfo === null && $imageUrlInput === '') {
                    $errors[] = 'Upload an image or provide an image URL for this product.';
                }

                $specJsonValue = null;
                if ($specJsonRaw !== '') {
                    $decoded = json_decode($specJsonRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) $errors[] = 'Specifications must be valid JSON.';
                    else $specJsonValue = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }

                if (empty($errors) && $uploadInfo !== null) {
                    $uploadDir = dirname($uploadInfo['destination']);
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        $errors[] = 'Unable to create the uploads directory.';
                    } elseif (!move_uploaded_file($uploadInfo['tmp_name'], $uploadInfo['destination'])) {
                        $errors[] = 'Failed to save the uploaded image.';
                    } else {
                        $imageUrl = $uploadInfo['relative'];
                    }
                }

                if (empty($errors)) {
                    try {
                        $pdo = get_db_connection();
                        $stmt = $pdo->prepare(
                            'UPDATE products
                                SET name=:name, category=:category, tagline=:tagline, description=:description,
                                    price=:price, stock=:stock, image_url=:image_url, spec_json=:spec_json, featured=:featured
                              WHERE id = :id'
                        );
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
                            'id' => $productId,
                        ]);

                        if (
                            $removePreviousImage
                            && $previousImageUrl !== ''
                            && $previousImageUrl !== $imageUrl
                            && strpos($previousImageUrl, 'assets/uploads/') === 0
                        ) {
                            $previousPath = __DIR__ . '/' . $previousImageUrl;
                            if (is_file($previousPath)) {
                                @unlink($previousPath);
                            }
                        }

                        $successMessage = 'Product "' . htmlspecialchars($name) . '" was updated successfully.';
                    } catch (Throwable $e) {
                        $errors[] = 'Failed to update product: ' . $e->getMessage();
                    }
                }

                if (!empty($errors)) {
                    $editFormOverrides[$productId] = [
                        'name' => $name,
                        'category' => $category,
                        'tagline' => $tagline,
                        'description' => $description,
                        'price' => $price !== false ? $price : ($_POST['price'] ?? ''),
                        'stock' => $stock !== false ? $stock : ($_POST['stock'] ?? ''),
                        'spec_json' => $specJsonRaw,
                        'featured' => $featured,
                        'image_url_input' => $imageUrlInput,
                    ];
                }
            }
        }
    } else {
        $errors[] = 'Unknown action requested.';
    }
}

/* ----------------------------
|  Fetch (include inactive in admin)
| ---------------------------- */
// Show filter: active (default) | all | archived
$show = isset($_GET['show']) ? strtolower(trim($_GET['show'])) : 'active';
if (!in_array($show, ['active', 'all', 'archived'], true)) {
    $show = 'active';
}

// Grab everything once (admin view needs the full set), then filter in PHP.
$allProductsRaw = fetch_products_by_category(null, null, false); // false = include archived too
$categoryOptions = fetch_categories(false);
$categoryUsage = fetch_category_product_counts(false);

$allProducts = array_values(array_filter($allProductsRaw, function ($p) use ($show) {
    $active = (int)($p['is_active'] ?? 1) === 1;
    if ($show === 'active')   return $active;
    if ($show === 'archived') return !$active;
    return true; // 'all'
}));


require_once __DIR__ . '/includes/header.php';
?>
<section class="container">
    <h1 class="section-title">Product administration</h1>

    <?php if (!empty($errors)): ?>
        <div class="notice" role="alert" style="margin-bottom: 1.5rem;">
            <strong>We ran into some issues:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="notice" style="margin-bottom: 1.5rem; background:#e6ffed; border-color:#34c759;">
            <?= $successMessage ?>
        </div>
    <?php endif; ?>

    <?php if ($categorySuccess): ?>
        <div class="notice" style="margin-bottom: 1.5rem; background:#e6ffed; border-color:#34c759;">
            <?= $categorySuccess ?>
        </div>
    <?php endif; ?>

    <datalist id="category-options">
        <?php foreach ($categoryOptions as $categoryOption): ?>
            <option value="<?= htmlspecialchars($categoryOption) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <section style="margin-bottom: 2rem;">
        <h2>Catalog categories</h2>
        <form method="post" class="form-grid" style="gap: 1rem; max-width: 32rem;">
            <input type="hidden" name="action" value="add_category">
            <div style="grid-column: 1 / -1;">
                <label for="new_category">Add a new category</label>
                <input type="text" id="new_category" name="new_category" required placeholder="e.g., Tablets" maxlength="22">
            </div>
            <div>
                <button type="submit" class="btn-primary">Add category</button>
            </div>
        </form>
        <?php if ($categoryOptions): ?>
            <div class="table-responsive" style="margin-top:1.5rem;">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col">Products</th>
                            <th scope="col">Rename</th>
                            <th scope="col">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryOptions as $index => $categoryOption): ?>
                            <?php
                            $usage = $categoryUsage[$categoryOption] ?? 0;
                            $requiresReassign = $usage > 0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($categoryOption) ?></td>
                                <td><?= $usage ?></td>
                                <td>
                                    <form method="post" class="category-inline-form">
                                        <input type="hidden" name="action" value="rename_category">
                                        <input type="hidden" name="current_name" value="<?= htmlspecialchars($categoryOption) ?>">
                                        <label class="sr-only" for="rename-category-<?= $index ?>">Rename <?= htmlspecialchars($categoryOption) ?></label>
                                        <input type="text" id="rename-category-<?= $index ?>" name="updated_name" required value="<?= htmlspecialchars($categoryOption) ?>" maxlength="22">
                                        <button type="submit" class="btn-secondary btn-sm">Rename</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" class="category-inline-form">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_name" value="<?= htmlspecialchars($categoryOption) ?>">
                                        <?php if ($requiresReassign): ?>
                                            <label class="sr-only" for="reassign-category-<?= $index ?>">Reassign products from <?= htmlspecialchars($categoryOption) ?></label>
                                            <input type="text" id="reassign-category-<?= $index ?>" name="reassign_to" list="category-options" placeholder="Select destination" required>
                                            <p class="category-note">Move products before deleting.</p>
                                        <?php else: ?>
                                            <input type="hidden" name="reassign_to" value="">
                                            <p class="category-note text-muted">No products assigned.</p>
                                        <?php endif; ?>
                                        <button type="submit" class="btn-secondary btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-top:1rem;" class="text-muted">No categories yet.</p>
        <?php endif; ?>
    </section>

    <section style="margin-bottom: 2rem;">
        <h2>Add a new product</h2>
        <form method="post" enctype="multipart/form-data" class="form-grid" style="gap: 1rem;">
            <input type="hidden" name="action" value="add">
            <div><label for="name">Product name</label>
                <input type="text" id="name" name="name" required maxlength="22"
                    value="<?= htmlspecialchars($addFormData['name']) ?>">
            </div>
            <div><label for="category">Category</label>
                <input type="text" id="category" name="category" list="category-options" required value="<?= htmlspecialchars($addFormData['category']) ?>">
            </div>
            <div><label for="tagline">Tagline</label>
                <input type="text" id="tagline" name="tagline" maxlength="30" value="<?= htmlspecialchars($addFormData['tagline']) ?>">
            </div>
            <div><label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= htmlspecialchars($addFormData['price']) ?>">
            </div>
            <div><label for="stock">Stock</label>
                <input type="number" id="stock" name="stock" min="0" required value="<?= htmlspecialchars($addFormData['stock']) ?>">
            </div>
            <div style="grid-column:1 / -1;"><label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required><?= htmlspecialchars($addFormData['description']) ?></textarea>
            </div>
            <div><label for="image">Upload image</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            <div><label for="image_url">Or provide external image URL</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($addFormData['image_url']) ?>">
            </div>
            <div style="grid-column:1 / -1;"><label for="spec_json">Specifications (JSON)</label>
                <textarea id="spec_json" name="spec_json" rows="4" placeholder='{"Processor":"Intel", "RAM":"16GB"}'><?= htmlspecialchars($addFormData['spec_json']) ?></textarea>
            </div>
            <div class="checkbox"><label><input type="checkbox" name="featured" value="1" <?= ((int)$addFormData['featured']) === 1 ? 'checked' : '' ?>> Featured product</label></div>
            <div><button type="submit" class="btn-primary">Add product</button></div>
        </form>
    </section>

    <section>
        <h2>Existing products</h2>
        <!-- Filter: Active | All | Archived -->
        <div class="admin-filter" style="margin: .5rem 0 1rem;">
            <a class="btn-secondary btn-sm" href="admin.php?show=active" <?= $show === 'active'   ? 'aria-current="true"' : '' ?>>Active</a>
            <a class="btn-secondary btn-sm" href="admin.php?show=all" <?= $show === 'all'      ? 'aria-current="true"' : '' ?>>All</a>
            <a class="btn-secondary btn-sm" href="admin.php?show=archived" <?= $show === 'archived' ? 'aria-current="true"' : '' ?>>Archived</a>
        </div>
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
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProducts as $product): ?>
                            <?php
                            $productId = (int)$product['id'];
                            $formData = $editFormOverrides[$productId] ?? [
                                'name' => $product['name'] ?? '',
                                'category' => $product['category'] ?? '',
                                'tagline' => $product['tagline'] ?? '',
                                'description' => $product['description'] ?? '',
                                'price' => $product['price'] ?? '',
                                'stock' => $product['stock'] ?? '',
                                'spec_json' => $product['spec_json'] ?? '',
                                'featured' => $product['featured'] ?? 0,
                                'image_url_input' => '',
                            ];
                            $isActive = isset($product['is_active']) ? (int)$product['is_active'] : 1;
                            ?>
                            <tr<?= $isActive ? '' : ' class="row-archived"' ?>>
                                <td>#<?= $productId ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category']) ?></td>
                                <td><?= format_price((float)$product['price']) ?></td>
                                <td><?= (int)$product['stock'] ?></td>
                                <td><?= $isActive ? 'Active' : 'Archived' ?></td>
                                <?php
                                $manageRowId = 'manage-panel-' . $productId;
                                $isExpanded = isset($editFormOverrides[$productId]);
                                ?>
                                <td class="actions-cell">
                                    <button type="button"
                                        class="manage-product-toggle"
                                        data-target="<?= $manageRowId ?>"
                                        aria-controls="<?= $manageRowId ?>"
                                        aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                                        data-label-open="Hide editor"
                                        data-label-closed="Manage product">
                                        <span class="toggle-label"><?= $isExpanded ? 'Hide editor' : 'Manage product' ?></span>
                                        <span class="toggle-icon" aria-hidden="true">▾</span>
                                    </button>
                                </td>
                            </tr>
                            <tr id="<?= $manageRowId ?>" class="manage-product-row<?= $isExpanded ? ' is-open' : '' ?><?= $isActive ? '' : ' row-archived' ?>">
                                <td colspan="7">
                                    <div class="manage-product-panel">
                                        <form method="post" enctype="multipart/form-data" class="form-grid manage-product-form">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="product_id" value="<?= $productId ?>">
                                            <div>
                                                <label for="edit-name-<?= $productId ?>">Product name</label>
                                                <input type="text" id="edit-name-<?= $productId ?>" name="name" required maxlength="22" value="<?= htmlspecialchars($formData['name']) ?>">
                                            </div>
                                            <div>
                                                <label for="edit-category-<?= $productId ?>">Category</label>
                                                <input type="text" id="edit-category-<?= $productId ?>" name="category" list="category-options" required value="<?= htmlspecialchars($formData['category']) ?>">
                                            </div>
                                            <div>
                                                <label for="edit-tagline-<?= $productId ?>">Tagline</label>
                                                <input type="text" id="edit-tagline-<?= $productId ?>" name="tagline" maxlength="30" value="<?= htmlspecialchars($formData['tagline']) ?>">
                                            </div>
                                            <div>
                                                <label for="edit-price-<?= $productId ?>">Price</label>
                                                <input type="number" step="0.01" min="0" id="edit-price-<?= $productId ?>" name="price" required value="<?= htmlspecialchars($formData['price']) ?>">
                                            </div>
                                            <div>
                                                <label for="edit-stock-<?= $productId ?>">Stock</label>
                                                <input type="number" min="0" id="edit-stock-<?= $productId ?>" name="stock" required value="<?= htmlspecialchars($formData['stock']) ?>">
                                            </div>
                                            <div class="manage-product-field-full">
                                                <label for="edit-description-<?= $productId ?>">Description</label>
                                                <textarea id="edit-description-<?= $productId ?>" name="description" rows="4" required><?= htmlspecialchars($formData['description']) ?></textarea>
                                            </div>
                                            <div>
                                                <label for="edit-image-<?= $productId ?>">Upload new image</label>
                                                <input type="file" id="edit-image-<?= $productId ?>" name="image" accept="image/*">
                                            </div>
                                            <div>
                                                <label for="edit-image-url-<?= $productId ?>">Or provide external image URL</label>
                                                <input type="url" id="edit-image-url-<?= $productId ?>" name="image_url" placeholder="https://example.com/image.jpg" value="<?= htmlspecialchars($formData['image_url_input']) ?>">
                                                <small class="current-image">Current image: <?= htmlspecialchars($product['image_url'] ?? 'None') ?></small>
                                            </div>
                                            <div class="manage-product-field-full">
                                                <label for="edit-spec-json-<?= $productId ?>">Specifications (JSON)</label>
                                                <textarea id="edit-spec-json-<?= $productId ?>" name="spec_json" rows="3" placeholder='{"Processor":"Intel", "RAM":"16GB"}'><?= htmlspecialchars($formData['spec_json']) ?></textarea>
                                            </div>
                                            <div class="checkbox"><label><input type="checkbox" name="featured" value="1" <?= ((int)$formData['featured']) === 1 ? 'checked' : '' ?>> Featured product</label></div>
                                            <div>
                                                <button type="submit" class="btn-primary">Save changes</button>
                                            </div>
                                        </form>

                                        <div class="manage-product-actions">
                                            <?php if ($isActive): ?>
                                                <form method="post" class="manage-product-action" onsubmit="return confirm('Archive this product? It will disappear from the storefront but stay in orders.');">
                                                    <input type="hidden" name="action" value="delete"><!-- same action, soft delete -->
                                                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                                                    <button type="submit" class="btn-secondary" style="background:#c92a2a;border-color:#c92a2a;">
                                                        Archive product
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" class="manage-product-action">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                                                    <button type="submit" class="btn-secondary" style="background:#2b8a3e;border-color:#2b8a3e;">
                                                        Restore product
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
