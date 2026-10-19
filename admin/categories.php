<?php
/**
 * admin/categories.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);
    $id   = (int)($_POST['id'] ?? 0);
    $name = clean($_POST['name'] ?? '');
    $desc = clean($_POST['description'] ?? '');

    if ($name === '') {
        flash_error('Category name is required.');
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=? WHERE id=?");
            $stmt->execute([$name, $slug, $desc, $id]);
            flash_success('Category updated.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $desc]);
            flash_success('Category created.');
        }
    }
    redirect(BASE_URL . 'admin/categories.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Check if products use it
    $used = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
    $used->execute([$delId]);
    if ((int)$used->fetchColumn() > 0) {
        flash_error('Cannot delete: products are using this category.');
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$delId]);
        flash_success('Category deleted.');
    }
    redirect(BASE_URL . 'admin/categories.php');
}

// Handle edit form load
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$categories = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
     FROM categories c ORDER BY c.sort_order, c.name"
)->fetchAll();

$pageTitle = 'Categories';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<h2 class="fw-bold mb-1">Categories</h2>
<p class="text-muted small mb-4">Manage product categories</p>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-soft p-3">
            <h6 class="fw-bold mb-3"><?= $editing ? 'Edit Category' : 'Add New Category' ?></h6>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">
                <div class="mb-2">
                    <label class="form-label small">Name *</label>
                    <input type="text" name="name" class="form-control form-control-sm" required
                           value="<?= e($editing['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Description</label>
                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= e($editing['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-save"></i> <?= $editing ? 'Update' : 'Add' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>admin/categories.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-soft">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Name</th><th>Slug</th>
                            <th class="text-center">Products</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="small"><?= (int)$c['id'] ?></td>
                            <td class="small fw-semibold"><?= e($c['name']) ?></td>
                            <td class="small text-muted"><code><?= e($c['slug']) ?></code></td>
                            <td class="text-center small"><?= (int)$c['product_count'] ?></td>
                            <td class="text-end">
                                <a href="?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?delete=<?= (int)$c['id'] ?>"
                                   onclick="return confirm('Delete this category?')"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>