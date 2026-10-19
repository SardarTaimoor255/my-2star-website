<?php
/**
 * admin/users.php
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_ADMIN);
require_once dirname(__DIR__) . '/includes/pagination.php';

// Filters
// Filters
$roleFilter   = $_GET['role']      ?? '';
$statusFilter = $_GET['status']    ?? '';
$search       = trim($_GET['q']    ?? '');
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to']   ?? '';

// ============================================
// MAIN QUERY
// ============================================
$sql = "SELECT u.*, r.name AS role_name, r.display_name AS role_label
        FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
$params = [];

if ($roleFilter !== '')   { $sql .= " AND r.name = ?";     $params[] = $roleFilter; }
if ($statusFilter !== '') { $sql .= " AND u.status = ?";   $params[] = $statusFilter; }
if ($search !== '')       { 
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%"; 
    $params[] = "%$search%"; 
}
if ($dateFrom !== '')     { $sql .= " AND DATE(u.created_at) >= ?";  $params[] = $dateFrom; }
if ($dateTo !== '')       { $sql .= " AND DATE(u.created_at) <= ?";  $params[] = $dateTo; }

// ============================================
// COUNT QUERY (for pagination)
// ============================================
$countSql = "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
$countParams = [];

if ($roleFilter !== '')   { $countSql .= " AND r.name = ?";     $countParams[] = $roleFilter; }
if ($statusFilter !== '') { $countSql .= " AND u.status = ?";   $countParams[] = $statusFilter; }
if ($search !== '')       { 
    $countSql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $countParams[] = "%$search%"; 
    $countParams[] = "%$search%"; 
}
if ($dateFrom !== '')     { $countSql .= " AND DATE(u.created_at) >= ?";  $countParams[] = $dateFrom; }
if ($dateTo !== '')       { $countSql .= " AND DATE(u.created_at) <= ?";  $countParams[] = $dateTo; }

// Count total for pagination
$countSql = preg_replace('/SELECT u\.\*, r\.name AS role_name, r\.display_name AS role_label/',
    'SELECT COUNT(*)', $sql);
// Actually simpler: build count query separately
$countSql = "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE 1=1";
$countParams = [];
if ($roleFilter !== '')   { $countSql .= " AND r.name = ?";     $countParams[] = $roleFilter; }
if ($statusFilter !== '') { $countSql .= " AND u.status = ?";   $countParams[] = $statusFilter; }
if ($search !== '')       { $countSql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
                            $countParams[] = "%$search%"; $countParams[] = "%$search%"; }

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalUsers = (int)$countStmt->fetchColumn();

// Build base URL for pagination (preserve filters)
$baseUrl = 'users.php?';
$filterParams = [];
if ($roleFilter !== '') $filterParams[] = 'role=' . urlencode($roleFilter);
if ($statusFilter !== '') $filterParams[] = 'status=' . urlencode($statusFilter);
if ($search !== '') $filterParams[] = 'q=' . urlencode($search);
$baseUrl .= implode('&', $filterParams);

// Pagination
$pg = paginate($totalUsers, current_page_num(), 20, $baseUrl);
$offset = $pg['offset'];

$sql .= " ORDER BY u.id DESC LIMIT {$pg['perPage']} OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles = $pdo->query("SELECT name, display_name FROM roles ORDER BY id")->fetchAll();

$pageTitle = 'Users';
require_once dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Users</h2>
        <p class="text-muted small mb-0">Manage all platform users</p>
    </div>
</div>

<!-- Filters -->
<div class="card-soft p-3 mb-3">
        <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Search</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   value="<?= e($search) ?>" placeholder="Name or email">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Role</label>
            <select name="role" class="form-select form-select-sm">
                <option value="">All roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['name']) ?>" <?= $roleFilter === $r['name'] ? 'selected' : '' ?>>
                        <?= e($r['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach (USER_STATUSES as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">From Date</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small">To Date</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-1">
            <button class="btn btn-primary btn-sm w-100">
                <i class="bi bi-funnel"></i>
            </button>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
            <?php else: foreach ($users as $u): ?>
                <tr>
                    <td class="small">#<?= (int)$u['id'] ?></td>
                    <td class="small fw-semibold"><?= e($u['full_name']) ?></td>
                    <td class="small text-muted"><?= e($u['email']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($u['role_label']) ?></span></td>
                    <td>
                        <?php
                        $badge = ['pending'=>'warning','active'=>'success','suspended'=>'danger','rejected'=>'dark'];
                        $cls = $badge[$u['status']] ?? 'secondary';
                        ?>
                        <span class="badge text-bg-<?= $cls ?>">
                            <?= e(USER_STATUSES[$u['status']] ?? $u['status']) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?= $u['last_login_at'] ? fmt_datetime($u['last_login_at']) : '—' ?>
                    </td>
                    <td class="text-end">
                        <?php if ($u['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="handleApprove(this,'toggle_user',<?= (int)$u['id'] ?>,{to:'suspended'})">
                                <i class="bi bi-pause-circle"></i>
                            </button>
                        <?php elseif ($u['status'] === 'suspended'): ?>
                            <button class="btn btn-sm btn-outline-success"
                                    onclick="handleApprove(this,'toggle_user',<?= (int)$u['id'] ?>,{to:'active'})">
                                <i class="bi bi-play-circle"></i>
                            </button>
                        <?php elseif ($u['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-outline-success"
                                    onclick="handleApprove(this,'approve_user',<?= (int)$u['id'] ?>)">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="handleApprove(this,'reject_user',<?= (int)$u['id'] ?>)">
                                <i class="bi bi-x"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
               </table>
    </div>
    <?= $pg['html'] ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>