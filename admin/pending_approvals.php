<?php
// admin/pending_approvals.php — Unified queue of all pending resident applications
require_once 'includes/header.php';

// ── Fetch all pending / in-review TENANTS ────────────────────────────────────
$tenants = $pdo->query(
    "SELECT t.*, u.unit_number,
            o.full_name AS owner_name, o.email AS owner_email
     FROM tenants t
     JOIN units u ON t.unit_id = u.id
     LEFT JOIN ownership_history oh ON oh.unit_id = u.id AND oh.is_current = 1
     LEFT JOIN owners o ON oh.owner_id = o.id
     WHERE t.status NOT IN ('Approved','Declined','Completed')
     ORDER BY t.created_at ASC"
)->fetchAll();

// ── Fetch all pending / in-review OWNERS ─────────────────────────────────────
$owners = $pdo->query(
    "SELECT o.*, u.unit_number
     FROM owners o
     JOIN ownership_history oh ON o.id = oh.owner_id AND oh.is_current = 1
     JOIN units u ON oh.unit_id = u.id
     WHERE COALESCE(o.status, 'Pending') NOT IN ('Approved','Declined','Completed')
     ORDER BY o.created_at ASC"
)->fetchAll();

// ── Fetch all pending MOVE LOGISTICS ─────────────────────────────────────────
$moves = $pdo->query(
    "SELECT ml.*, u.unit_number,
            COALESCE(t.full_name, o.full_name) AS resident_name,
            COALESCE(t.email, o.email) AS resident_email
     FROM move_logistics ml
     JOIN units u ON ml.unit_id = u.id
     LEFT JOIN tenants t ON ml.resident_type = 'tenant' AND ml.resident_id = t.id
     LEFT JOIN owners o ON ml.resident_type = 'owner' AND ml.resident_id = o.id
     WHERE ml.status = 'Pending'
       AND (ml.owner_approval IS NULL OR ml.owner_approval = 1)
     ORDER BY ml.created_at ASC"
)->fetchAll();

// ── Helper badge ─────────────────────────────────────────────────────────────
function approval_badge($val, $label)
{
    $cls = $val ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    $icon = $val ? 'fa-check' : 'fa-times';
    return "<span class='inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold {$cls}'><i class='fas {$icon}'></i>{$label}</span>";
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Pending Approvals</h1>
    <p class="text-gray-500 text-sm mt-1">All resident applications awaiting review or action.</p>
</div>

<!-- ═══ PENDING TENANTS ══════════════════════════════════════════════════════ -->
<div class="mb-10">
    <h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-user-clock text-green-500"></i>
        Tenant Applications
        <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded-full">
            <?= count($tenants)?>
        </span>
    </h2>

    <?php if (empty($tenants)): ?>
    <div class="bg-white shadow rounded-lg p-8 text-center text-gray-400 italic border border-dashed border-gray-200">
        <i class="fas fa-check-circle text-3xl mb-3 block text-gray-300"></i>
        No pending tenant applications.
    </div>
    <?php
else: ?>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="tenantsApprovalTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Owner</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approvals</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php foreach ($tenants as $t): ?>
                <?php
        $statusColors = [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Information Requested' => 'bg-orange-100 text-orange-800',
            'Pending Updated' => 'bg-purple-100 text-purple-800',
        ];
        $sc = $statusColors[$t['status']] ?? 'bg-gray-100 text-gray-800';
        $both_ok = $t['owner_approval'] && $t['pet_approval'];
?>
                <tr class="hover:bg-gray-50 <?= $both_ok ? 'bg-green-50' : ''?>">
                    <td class="px-4 py-3 text-sm font-bold text-gray-900">
                        <?= h($t['unit_number'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-900">
                            <?= h($t['full_name'])?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?= h($t['email'])?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?= $t['owner_name'] ? h($t['owner_name']) : '<span class="text-gray-300 italic">N/A</span>'?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= format_date($t['created_at'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $sc?>">
                            <?= h($t['status'])?>
                        </span>
                        <?php if ($both_ok): ?>
                        <div class="mt-1 text-xs text-green-700 font-bold"><i class="fas fa-unlock mr-1"></i>Ready to
                            Approve!</div>
                        <?php
        endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm space-y-1">
                        <?= approval_badge($t['owner_approval'], 'Owner')?>
                        <?= approval_badge($t['pet_approval'], 'Pets')?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <a href="tenants.php?action=view&id=<?= $t['id']?>"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700">
                            <i class="fas fa-eye mr-1"></i> Review
                        </a>
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

<!-- ═══ PENDING OWNERS ═══════════════════════════════════════════════════════ -->
<div>
    <h2 class="text-lg font-bold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-key text-blue-500"></i>
        Owner Applications
        <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">
            <?= count($owners)?>
        </span>
    </h2>

    <?php if (empty($owners)): ?>
    <div class="bg-white shadow rounded-lg p-8 text-center text-gray-400 italic border border-dashed border-gray-200">
        <i class="fas fa-check-circle text-3xl mb-3 block text-gray-300"></i>
        No pending owner applications.
    </div>
    <?php
else: ?>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200" id="ownersApprovalTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Approvals</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php foreach ($owners as $o): ?>
                <?php
        $o_status = $o['status'] ?? 'Pending';
        $sc = $statusColors[$o_status] ?? 'bg-gray-100 text-gray-800';
        $both_ok = $o['agent_approval'] && $o['pet_approval'];
?>
                <tr class="hover:bg-gray-50 <?= $both_ok ? 'bg-green-50' : ''?>">
                    <td class="px-4 py-3 text-sm font-bold text-gray-900">
                        <?= h($o['unit_number'])?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-gray-900">
                            <?= h($o['full_name'])?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?= h($o['email'])?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?= format_date($o['created_at'] ?? null)?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $sc?>">
                            <?= h($o_status)?>
                        </span>
                        <?php if ($both_ok): ?>
                        <div class="mt-1 text-xs text-green-700 font-bold"><i class="fas fa-unlock mr-1"></i>Ready to
                            Approve!</div>
                        <?php
        endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm space-y-1">
                        <?= approval_badge($o['agent_approval'] ?? 0, 'Agent')?>
                        <?= approval_badge($o['pet_approval'] ?? 0, 'Pets')?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <a href="owners.php?action=edit&id=<?= $o['id']?>"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700">
                            <i class="fas fa-eye mr-1"></i> Review
                        </a>
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
        $('#tenantsApprovalTable').DataTable({ pageLength: 25, order: [[3, 'asc']], searching: false });
        $('#ownersApprovalTable').DataTable({ pageLength: 25, order: [[2, 'asc']], searching: false });
        $('#movesApprovalTable').DataTable({ pageLength: 25, order: [[3, 'asc']], searching: false });
    });
</script>

<?php require_once 'includes/footer.php'; ?>