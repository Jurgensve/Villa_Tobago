<?php
// admin/move_management.php  — Management queue for all move-in/out logistics
require_once 'includes/header.php';
require_role(['admin', 'managing_agent']); // Trustees: approvals only

$message = '';
$error = '';

// ─── POST ACTIONS ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $logistics_id = (int)($_POST['logistics_id'] ?? 0);

    // Approve a move (mainly used for owner move-outs after they've been owner-approved)
    if (isset($_POST['action_approve']) && $logistics_id) {
        try {
            $stmt = $pdo->prepare(
                "SELECT ml.*, u.unit_number,
                    CASE WHEN ml.resident_type = 'tenant' THEN t.full_name ELSE o.full_name END AS resident_name,
                    CASE WHEN ml.resident_type = 'tenant' THEN t.email ELSE o.email END AS resident_email
                 FROM move_logistics ml
                 JOIN units u ON ml.unit_id = u.id
                 LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
                 LEFT JOIN owners  o ON ml.resident_type = 'owner'  AND ml.resident_id = o.id
                 WHERE ml.id = ?"
            );
            $stmt->execute([$logistics_id]);
            $move = $stmt->fetch();

            if ($move) {
                $pdo->prepare("UPDATE move_logistics SET status = 'Approved' WHERE id = ?")->execute([$logistics_id]);

                // Send security notification if not already sent
                if (!$move['security_notified']) {
                    $move['resident_name'] = $move['resident_name'] ?? 'Resident';
                    send_security_notification($pdo, $move);
                    $pdo->prepare("UPDATE move_logistics SET security_notified = 1, security_notified_at = NOW() WHERE id = ?")->execute([$logistics_id]);
                }
                $message = "Move approved and security notified.";
            }
        }
        catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }

    // Mark complete
    if (isset($_POST['action_complete']) && $logistics_id) {
        $pdo->prepare("UPDATE move_logistics SET status = 'Completed' WHERE id = ?")->execute([$logistics_id]);
        $message = "Move marked as completed.";
    }

    // Cancel
    if (isset($_POST['action_cancel']) && $logistics_id) {
        $pdo->prepare("UPDATE move_logistics SET status = 'Cancelled' WHERE id = ?")->execute([$logistics_id]);
        $message = "Move request cancelled.";
    }

    // Resend security notification
    if (isset($_POST['action_resend_security']) && $logistics_id) {
        try {
            $stmt = $pdo->prepare(
                "SELECT ml.*, u.unit_number,
                    CASE WHEN ml.resident_type = 'tenant' THEN t.full_name ELSE o.full_name END AS resident_name
                 FROM move_logistics ml
                 JOIN units u ON ml.unit_id = u.id
                 LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
                 LEFT JOIN owners  o ON ml.resident_type = 'owner'  AND ml.resident_id = o.id
                 WHERE ml.id = ?"
            );
            $stmt->execute([$logistics_id]);
            $move = $stmt->fetch();
            if ($move) {
                send_security_notification($pdo, $move);
                $pdo->prepare("UPDATE move_logistics SET security_notified = 1, security_notified_at = NOW() WHERE id = ?")->execute([$logistics_id]);
                $message = "Security notification re-sent.";
            }
        }
        catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ─── DATA FETCH ────────────────────────────────────────────────────────────────

$sql = "SELECT ml.*,
            u.unit_number,
            CASE WHEN ml.resident_type = 'tenant' THEN t.full_name  ELSE o.full_name  END AS resident_name,
            CASE WHEN ml.resident_type = 'tenant' THEN t.email      ELSE o.email      END AS resident_email
        FROM move_logistics ml
        JOIN units u ON ml.unit_id = u.id
        LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
        LEFT JOIN owners  o ON ml.resident_type = 'owner'  AND ml.resident_id = o.id
        ORDER BY
            FIELD(ml.status, 'Approved', 'Pending', 'Completed', 'Cancelled'),
            ml.preferred_date ASC,
            ml.created_at DESC";

$moves = $pdo->query($sql)->fetchAll();

$upcoming = array_filter($moves, fn($m) => in_array($m['status'], ['Pending', 'Approved']));
$past = array_filter($moves, fn($m) => in_array($m['status'], ['Completed', 'Cancelled']));

// ─── Helper: status badge ──────────────────────────────────────────────────────
function status_badge($status)
{
    $map = [
        'Pending' => 'bg-yellow-100 text-yellow-800',
        'Approved' => 'bg-green-100  text-green-800',
        'Completed' => 'bg-blue-100   text-blue-800',
        'Cancelled' => 'bg-red-100    text-red-800',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class='px-2 py-0.5 text-xs font-bold rounded-full {$cls}'>" . htmlspecialchars($status) . "</span>";
}

function move_type_badge($type)
{
    if ($type === 'move_in') {
        return "<span class='px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800'><i class=\"fas fa-sign-in-alt mr-1\"></i>Move-In</span>";
    }
    return "<span class='px-2 py-0.5 text-xs font-bold rounded-full bg-orange-100 text-orange-800'><i class=\"fas fa-sign-out-alt mr-1\"></i>Move-Out</span>";
}
?>

<div class="mb-6 flex flex-wrap justify-between items-center gap-3">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Move Management</h1>
        <p class="text-gray-500 text-sm mt-1">All scheduled move-ins and move-outs for the complex.</p>
    </div>
    <a href="../move_out_form.php"
        class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded text-sm">
        <i class="fas fa-sign-out-alt mr-2"></i> Resident Move-Out Form
    </a>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-check-circle mr-2"></i>
    <?= h($message)?>
</div>
<?php
endif; ?>
<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    <?= h($error)?>
</div>
<?php
endif; ?>

<!-- ═══ UPCOMING MOVES ════════════════════════════════════════════════════════ -->
<div class="mb-8">
    <h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center">
        <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
        Upcoming Moves
        <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">
            <?= count($upcoming)?>
        </span>
    </h2>

    <?php if (empty($upcoming)): ?>
    <div class="bg-white shadow rounded-lg p-8 text-center text-gray-400 italic border border-dashed border-gray-200">
        <i class="fas fa-truck text-3xl mb-3 block text-gray-300"></i>
        No upcoming moves scheduled.
    </div>
    <?php
else: ?>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="upcomingTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resident</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Truck Reg</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Security</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Intercom</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php foreach ($upcoming as $m): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-bold text-gray-900">
                        <?= h($m['unit_number'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= move_type_badge($m['move_type'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-900">
                            <?= h($m['resident_name'])?>
                        </div>
                        <div class="text-xs text-gray-400 capitalize">
                            <?= h($m['resident_type'])?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <?= $m['preferred_date'] ? format_date($m['preferred_date']) : '<span class="text-gray-400">TBC</span>'?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <?= $m['truck_reg'] ? h($m['truck_reg']) : '<span class="text-gray-400">–</span>'?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= status_badge($m['status'])?>
                    </td>
                    <td class="px-4 py-3 text-sm text-center">
                        <?php if ($m['security_notified']): ?>
                        <span class="text-green-600"
                            title="Notified at <?= format_datetime($m['security_notified_at'])?>">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <?php
        else: ?>
                        <span class="text-gray-300"><i class="fas fa-minus-circle"></i></span>
                        <?php
        endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap gap-1">
                            <?php if ($m['status'] === 'Pending'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="logistics_id" value="<?= $m['id']?>">
                                <button name="action_approve"
                                    class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700"
                                    onclick="return confirm('Approve this move and notify security?')">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="logistics_id" value="<?= $m['id']?>">
                                <button name="action_cancel"
                                    class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200"
                                    onclick="return confirm('Cancel this move request?')">
                                    <i class="fas fa-times mr-1"></i>Cancel
                                </button>
                            </form>
                            <?php
        endif; ?>

                            <?php if ($m['status'] === 'Approved'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="logistics_id" value="<?= $m['id']?>">
                                <button name="action_complete"
                                    class="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">
                                    <i class="fas fa-flag-checkered mr-1"></i>Complete
                                </button>
                            </form>
                            <?php
        endif; ?>

                            <form method="POST" class="inline" title="Re-send security email">
                                <input type="hidden" name="logistics_id" value="<?= $m['id']?>">
                                <button name="action_resend_security"
                                    class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded hover:bg-gray-200">
                                    <i class="fas fa-envelope mr-1"></i>
                                    <?= $m['security_notified'] ? 'Resend' : 'Notify Security'?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
endif; ?>
</div>

<!-- ═══ PAST MOVES ══════════════════════════════════════════════════════════════ -->
<div>
    <h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center">
        <i class="fas fa-history text-gray-400 mr-2"></i>
        Past Moves
        <span class="ml-2 bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">
            <?= count($past)?>
        </span>
    </h2>

    <?php if (empty($past)): ?>
    <div
        class="bg-white shadow rounded-lg p-6 text-center text-gray-400 italic border border-dashed border-gray-200 text-sm">
        No past moves on record yet.
    </div>
    <?php
else: ?>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="pastTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resident</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Truck Reg</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Security</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php foreach ($past as $m): ?>
                <tr class="text-gray-500">
                    <td class="px-4 py-3 text-sm font-medium">
                        <?= h($m['unit_number'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= move_type_badge($m['move_type'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium">
                            <?= h($m['resident_name'])?>
                        </div>
                        <div class="text-xs text-gray-400 capitalize">
                            <?= h($m['resident_type'])?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= $m['preferred_date'] ? format_date($m['preferred_date']) : '–'?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= $m['truck_reg'] ? h($m['truck_reg']) : '–'?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= status_badge($m['status'])?>
                    </td>
                    <td class="px-4 py-3 text-sm text-center">
                        <?= $m['security_notified']
            ? "<span class='text-green-500' title='" . format_datetime($m['security_notified_at']) . "'><i class='fas fa-check-circle'></i></span>"
            : "<span class='text-gray-300'><i class='fas fa-minus-circle'></i></span>"?>
                    </td>
                </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
endif; ?>
</div>

<script>
    $(document).ready(function () {
        $('#upcomingTable').DataTable({ pageLength: 25, order: [[3, 'asc']], searching: false });
        $('#pastTable').DataTable({ pageLength: 10, order: [[3, 'desc']] });
    });
</script>

<?php require_once 'includes/footer.php'; ?>