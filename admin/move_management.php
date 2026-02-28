<?php
// admin/move_management.php  — Management queue for all move-in/out logistics
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// ─── POST ACTIONS ──────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Admin direct log move
    if (isset($_POST['admin_log_move'])) {
        $unit_owner_json = trim($_POST['unit_owner_json'] ?? '');
        $move_type = trim($_POST['move_type'] ?? '');
        $preferred_date = trim($_POST['preferred_date'] ?? '');
        $truck_reg = trim($_POST['truck_reg'] ?? '');
        $truck_gwm = !empty($_POST['truck_gwm']) ? (int)$_POST['truck_gwm'] : null;
        $moving_company = trim($_POST['moving_company'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($unit_owner_json && $move_type) {
            $data = json_decode($unit_owner_json, true);
            $unit_id = $data['unit_id'] ?? 0;
            $resident_id = $data['resident_id'] ?? 0;
            $resident_type = $data['resident_type'] ?? '';

            if ($unit_id && $resident_type && $resident_id) {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO move_logistics (unit_id, resident_type, resident_id, move_type, preferred_date, truck_reg, truck_gwm, moving_company, notes, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved')"
                    );
                    $stmt->execute([
                        $unit_id, $resident_type, $resident_id, $move_type,
                        $preferred_date, $truck_reg, $truck_gwm, $moving_company, $notes
                    ]);
                    $message = "Move successfully logged and approved.";
                    $action = 'list';
                }
                catch (PDOException $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
            else {
                $error = "Invalid resident details.";
            }
        }
        else {
            $error = "Unit, Resident, and Move Type are required.";
        }
    }

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

                // Fetch max GWM for the email warning
                $max_gwm = 3500;
                try {
                    $gwm_setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_truck_gwm'")->fetchColumn();
                    if (is_numeric($gwm_setting))
                        $max_gwm = (int)$gwm_setting;
                }
                catch (Exception $e) {
                }

                // Send confirmation to Resident
                $move_str = $move['move_type'] === 'move_in' ? 'Move-In' : 'Move-Out';
                $subject = "{$move_str} Request Approved – Villa Tobago";
                $body = "<p>Dear " . h($move['resident_name']) . ",</p>";
                $body .= "<p>Your {$move_str} request for Unit " . h($move['unit_number']) . " has been <strong>approved</strong> by the Managing Agent.</p>";
                $body .= "<p><strong>Date:</strong> " . ($move['preferred_date'] ? format_date($move['preferred_date']) : 'Not specified') . "</p>";
                $body .= "<p>Security has been notified and will expect your arrival.</p>";
                if ($move['truck_gwm'] > $max_gwm) {
                    $body .= "<p style='color:red;'><strong>Important:</strong> Your truck exceeds the max allowed weight limit. It must park outside the complex.</p>";
                }
                $body .= "<p>Regards,<br>Villa Tobago Management</p>";
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

    if (isset($_POST['redirect_to_unit']) && !empty($_POST['redirect_to_unit'])) {
        $redir_unit_id = (int)$_POST['redirect_to_unit'];
        $msg_param = $message ? "&msg=" . urlencode($message) : ($error ? "&err=" . urlencode($error) : "");
        header("Location: units.php?action=view&id={$redir_unit_id}{$msg_param}");
        exit;
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
    <?php if ($action !== 'add'): ?>
    <div class="flex gap-2">
        <a href="move_management.php?action=add"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm transition shadow-sm">
            <i class="fas fa-plus mr-1"></i> Log Move
        </a>
    </div>
    <?php
else: ?>
    <a href="move_management.php"
        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition">
        <i class="fas fa-arrow-left mr-2"></i> Back to List
    </a>
    <?php
endif; ?>
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

<?php if ($action === 'add'): ?>
<?php
    // Fetch all current units and their occupants
    $sql_units = "
        SELECT u.id as unit_id, u.unit_number, 
            CASE WHEN r.resident_type = 'owner' THEN o.id ELSE t.id END AS resident_id,
            CASE WHEN r.resident_type = 'owner' THEN 'owner' ELSE 'tenant' END AS resident_type,
            CASE WHEN r.resident_type = 'owner' THEN o.full_name ELSE t.full_name END AS resident_name
        FROM units u 
        LEFT JOIN residents r ON u.id = r.unit_id AND r.is_current = 1
        LEFT JOIN owners o ON r.resident_type = 'owner' AND r.resident_id = o.id
        LEFT JOIN tenants t ON r.resident_type = 'tenant' AND r.resident_id = t.id
        ORDER BY u.unit_number ASC
    ";

    // Graceful fallback for resident associations
    try {
        $units = $pdo->query($sql_units)->fetchAll();
    }
    catch (Exception $e) {
        $units = $pdo->query("
            SELECT u.id as unit_id, u.unit_number, o.id as resident_id, 'owner' as resident_type, o.full_name as resident_name 
            FROM units u 
            JOIN ownership_history oh ON u.id = oh.unit_id AND oh.is_current = 1
            JOIN owners o ON oh.owner_id = o.id
            ORDER BY u.unit_number ASC
        ")->fetchAll();
    }
?>
<div class="bg-white shadow rounded-lg p-6 max-w-4xl">
    <h2 class="text-xl font-semibold mb-6">Log Move-In / Move-Out (Auto-Approved)</h2>
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Unit & Resident</label>
                <select id="unit_resident_select" name="unit_owner_json"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    required>
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($units as $u): ?>
                    <option value='<?= json_encode([' unit_id'=> $u['unit_id'], 'resident_id' => $u['resident_id'],
                        'resident_type' => $u['resident_type']])?>'>
                        <?= h($u['unit_number'])?> -
                        <?= h($u['resident_name'])?> (
                        <?= ucfirst($u['resident_type'])?>)
                    </option>
                    <?php
    endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Move Type</label>
                <select name="move_type" class="shadow border rounded w-full py-2 px-3 text-gray-700 bg-white" required>
                    <option value="">-- Select Type --</option>
                    <option value="move_in">Move-In</option>
                    <option value="move_out">Move-Out</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Preferred Date</label>
                <input type="date" name="preferred_date"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700" required>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Moving Company</label>
                <input type="text" name="moving_company"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Truck Registration</label>
                <input type="text" name="truck_reg"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 uppercase"
                    placeholder="e.g. CA 12345">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Truck GWM (kg)</label>
                <input type="number" name="truck_gwm"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700"
                    placeholder="e.g. 3500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 text-sm font-bold mb-2">Notes</label>
                <textarea name="notes" rows="2"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700"></textarea>
            </div>
        </div>
        <div class="mt-8">
            <button type="submit" name="admin_log_move"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded focus:outline-none focus:shadow-outline w-full md:w-auto">
                Save & Approve Move
            </button>
        </div>
    </form>
    <script>
        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedUnitId = urlParams.get('unit_id');
            if (preselectedUnitId) {
                const select = document.getElementById('unit_resident_select');
                for (let i = 0; i < select.options.length; i++) {
                    const opt = select.options[i];
                    if (opt.value) {
                        try {
                            const data = JSON.parse(opt.value);
                            if (data.unit_id == preselectedUnitId) {
                                select.selectedIndex = i;
                                select.classList.add('bg-gray-100', 'pointer-events-none');
                                select.tabIndex = -1;
                                break;
                            }
                        } catch (e) { }
                    }
                }
            }
        };
    </script>
</div>
<?php
else: ?>

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

<?php
endif; ?>
<?php require_once 'includes/footer.php'; ?>