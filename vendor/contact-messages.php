<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/notification_helper.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    csrf_require($_POST['csrf_token'] ?? null);
    
    $msgId = (int)($_POST['id'] ?? 0);
    $reply = clean($_POST['admin_reply'] ?? '');
    
    if ($msgId <= 0) {
        flash_error('Invalid message.');
    } elseif (mb_strlen($reply) < 5) {
        flash_error('Reply must be at least 5 characters.');
    } else {
        $stmt = $pdo->prepare(
            "UPDATE contact_messages 
             SET admin_reply = ?, replied_at = NOW(), replied_by = ?, status = 'replied'
             WHERE id = ?"
        );
        $stmt->execute([$reply, $user['id'], $msgId]);
        
        log_activity($pdo, 'contact_replied_by_vendor', 'contact', $msgId, 'Vendor replied');
        flash_success('Reply saved! Click "Open Email & Send" to send it.');
        redirect(BASE_URL . 'vendor/contact-messages.php?view=' . $msgId);
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_status'])) {
    csrf_require($_POST['csrf_token'] ?? null);
    $msgId = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? 'read';
    if (in_array($newStatus, ['new','read','replied','archived'])) {
        $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$newStatus, $msgId]);
        flash_success('Status updated.');
    }
    redirect(BASE_URL . 'vendor/contact-messages.php');
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$viewId = (int)($_GET['view'] ?? 0);

$sql = "SELECT cm.*, u.full_name AS replied_by_name 
        FROM contact_messages cm
        LEFT JOIN users u ON u.id = cm.replied_by
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= " AND cm.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY cm.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Counts
$counts = [];
foreach (['new','read','replied','archived'] as $s) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE status = ?");
    $c->execute([$s]);
    $counts[$s] = (int)$c->fetchColumn();
}

$pageTitle = 'Contact Messages';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
$autoOpenId = $viewId > 0 ? $viewId : 0;
?>

<style>
.msg-card { transition: all .2s; }
.msg-card.unread { border-left: 4px solid #0b3d91; }
.reply-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 15px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">Contact Messages</h2>
        <p class="text-muted small mb-0">Messages from website visitors — please reply</p>
    </div>
</div>

<!-- Info Alert -->
<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i>
    <strong>Order-Related Messages:</strong> Agar koi order karne ke liye contact kare, 
    toh usko <a href="<?= BASE_URL ?>auth/register.php" target="_blank" class="alert-link">registration link</a> bhej dein.
</div>

<!-- Filter Buttons -->
<div class="card-soft p-2 mb-3">
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="small text-muted ms-2">Filter:</span>
        <a href="?" class="btn btn-sm <?= $statusFilter===''?'btn-primary':'btn-outline-secondary' ?>">
            All (<?= array_sum($counts) ?>)
        </a>
        <a href="?status=new" class="btn btn-sm <?= $statusFilter==='new'?'btn-primary':'btn-outline-secondary' ?>">
            🔵 New (<?= $counts['new'] ?>)
        </a>
        <a href="?status=read" class="btn btn-sm <?= $statusFilter==='read'?'btn-primary':'btn-outline-secondary' ?>">
            Read (<?= $counts['read'] ?>)
        </a>
        <a href="?status=replied" class="btn btn-sm <?= $statusFilter==='replied'?'btn-primary':'btn-outline-secondary' ?>">
            ✅ Replied (<?= $counts['replied'] ?>)
        </a>
        <a href="?status=archived" class="btn btn-sm <?= $statusFilter==='archived'?'btn-primary':'btn-outline-secondary' ?>">
            Archived (<?= $counts['archived'] ?>)
        </a>
    </div>
</div>

<?php if (empty($messages)): ?>
    <div class="card-soft p-5 text-center text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        <h5 class="fw-bold">No messages yet</h5>
        <p class="small mb-0">When visitors contact through the website, their messages will appear here.</p>
    </div>
<?php else: ?>

    <?php foreach ($messages as $m): 
        $statusColors = ['new'=>'primary','read'=>'info','replied'=>'success','archived'=>'secondary'];
        $sc = $statusColors[$m['status']] ?? 'secondary';
        $isUnread = $m['status'] === 'new';
    ?>
        <div class="card-soft p-3 mb-3 msg-card <?= $isUnread ? 'unread' : '' ?>">
            
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-person-circle text-primary"></i>
                        <?= e($m['name']) ?>
                        <span class="badge text-bg-<?= $sc ?> ms-2"><?= e(ucfirst($m['status'])) ?></span>
                    </h5>
                    <div class="small text-muted">
                        <i class="bi bi-envelope"></i> <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                        <?php if ($m['phone']): ?>
                            · <i class="bi bi-telephone"></i> <?= e($m['phone']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end small text-muted">
                    <div><i class="bi bi-clock"></i> <?= fmt_datetime($m['created_at']) ?></div>
                </div>
            </div>

            <?php if ($m['subject']): ?>
                <div class="mb-2">
                    <strong>Subject:</strong> <?= e($m['subject']) ?>
                </div>
            <?php endif; ?>

            <div class="border rounded p-3 mb-3" style="background:#f8fafc;">
                <div class="small text-muted mb-1"><strong>Message:</strong></div>
                <div><?= nl2br(e($m['message'])) ?></div>
            </div>

            <?php if ($m['admin_reply']): ?>
                <div class="reply-box mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong class="text-primary"><i class="bi bi-reply-fill"></i> Your Reply:</strong>
                        </div>
                        <div class="small text-muted">
                            <?= fmt_datetime($m['replied_at']) ?>
                            <?php if ($m['replied_by_name']): ?>
                                · by <?= e($m['replied_by_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div><?= nl2br(e($m['admin_reply'])) ?></div>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <?php if (!$m['admin_reply']): ?>
                    <button class="btn btn-sm btn-primary" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#replyForm<?= (int)$m['id'] ?>">
                        <i class="bi bi-reply"></i> Write Reply
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-primary" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#replyForm<?= (int)$m['id'] ?>">
                        <i class="bi bi-pencil"></i> Edit Reply
                    </button>
                <?php endif; ?>

                <?php if ($m['admin_reply']): 
                    $emailSubject = 'Re: ' . ($m['subject'] ?: 'Your message to ' . SITE_NAME);
                    $emailBody = $m['admin_reply'] . "\n\n---\n" . SITE_NAME . "\n" . SITE_PHONE . "\n" . SITE_EMAIL;
                    $mailto = 'mailto:' . $m['email'] . '?subject=' . rawurlencode($emailSubject) . '&body=' . rawurlencode($emailBody);
                ?>
                    <a href="<?= e($mailto) ?>" class="btn btn-sm btn-success ms-2">
                        <i class="bi bi-send"></i> Open Email & Send
                    </a>
                <?php endif; ?>

                <div class="collapse mt-3 <?= $autoOpenId === (int)$m['id'] ? 'show' : '' ?>" id="replyForm<?= (int)$m['id'] ?>">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="send_reply" value="1">
                        
                        <label class="form-label small fw-bold">Your Reply:</label>
                        <textarea name="admin_reply" class="form-control mb-2" rows="5" required
                                  placeholder="Reply likhein..."><?= e($m['admin_reply'] ?? '') ?></textarea>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-save"></i> Save Reply
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    data-bs-toggle="collapse" data-bs-target="#replyForm<?= (int)$m['id'] ?>">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <select name="new_status" class="form-select form-select-sm d-inline-block" 
                            style="width:auto;" onchange="this.form.submit()">
                        <option value="">Change status...</option>
                        <option value="new">New</option>
                        <option value="read">Read</option>
                        <option value="replied">Replied</option>
                        <option value="archived">Archived</option>
                    </select>
                    <input type="hidden" name="mark_status" value="1">
                </form>
            </div>

        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>