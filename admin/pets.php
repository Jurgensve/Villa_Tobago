<?php
$required_roles = ['admin', 'managing_agent'];
require_once 'includes/header.php';

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $pet_id = $_POST['pet_id'];
        $status = $_POST['status'];
        $trustee_comments = trim($_POST['trustee_comments'] ?? '');

        $stmtCurrent = $pdo->prepare("SELECT p.*, r.resident_type, r.resident_id FROM pets p JOIN residents r ON p.resident_id = r.id WHERE p.id = ?");
        $stmtCurrent->execute([$pet_id]);
        $currentPet = $stmtCurrent->fetch();

        // Fetch resident email
        $owner_email = '';
        $full_name = 'Resident';
        if ($currentPet) {
            if ($currentPet['resident_type'] === 'owner') {
                $r_stmt = $pdo->prepare("SELECT email, full_name FROM owners WHERE id = ?");
            }
            else {
                $r_stmt = $pdo->prepare("SELECT email, full_name FROM tenants WHERE id = ?");
            }
            $r_stmt->execute([$currentPet['resident_id']]);
            $res = $r_stmt->fetch();
            if ($res) {
                $owner_email = $res['email'];
                $full_name = $res['full_name'];
            }
        }

        $token = $currentPet['amendment_token'] ?? '';

        if ($status === 'Information Requested' || $status === 'Declined') {
            if (empty($trustee_comments)) {
                $error = "Trustee Comment is mandatory when requesting information or declining.";
            }
            else {
                if (empty($token) && $status === 'Information Requested') {
                    $token = bin2hex(random_bytes(32));
                }
                $stmt = $pdo->prepare("UPDATE pets SET status = ?, trustee_comments = ?, amendment_token = ? WHERE id = ?");
                $stmt->execute([$status, $trustee_comments, $token, $pet_id]);

                // Log action
                $log_comment = "Status changed to $status. " . ($trustee_comments ? "Comment: $trustee_comments" : "");
                $log = $pdo->prepare("INSERT INTO amendment_logs (related_type, related_id, action_type, comments) VALUES ('pet', ?, 'status_change', ?)");
                $log->execute([$pet_id, $log_comment]);

                $message = "Pet status updated and email sent.";

                if (!empty($owner_email)) {
                    $subject = "Pet Request Update - $status";
                    $body = "Dear " . h($full_name) . ",\n\n";
                    $body .= "Your pet application status is now: $status.\n\n";
                    $body .= "Trustee Comments:\n" . h($trustee_comments) . "\n\n";

                    if ($status === 'Information Requested') {
                        $link = SITE_URL . "/amend_request.php?type=pet&token=" . $token;
                        $body .= "Please update your request by clicking here: <a href='$link'>$link</a>\n\n";
                    }
                    send_notification_email($owner_email, $subject, $body);
                }
            }
        }
        else {
            $stmt = $pdo->prepare("UPDATE pets SET status = ?, trustee_comments = ? WHERE id = ?");
            $stmt->execute([$status, $trustee_comments, $pet_id]);

            // Log action
            $log = $pdo->prepare("INSERT INTO amendment_logs (related_type, related_id, action_type, comments) VALUES ('pet', ?, 'status_change', ?)");
            $log->execute([$pet_id, "Status changed to $status."]);

            $message = "Pet status updated.";
        }
    }
}
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-900">Pet Applications</h1>
</div>

<?php if ($message): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <?= h($message)?>
</div>
<?php
endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <?= h($error)?>
</div>
<?php
endif; ?>

<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pet Info</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
// We use 'status' since we added it to schema_amendments.sql
$sql = "SELECT p.*, u.unit_number 
                    FROM pets p
                    JOIN units u ON p.unit_id = u.id
                    ORDER BY CASE WHEN p.status = 'Pending Updated' THEN 1 WHEN p.status = 'Pending' THEN 2 ELSE 3 END, p.created_at DESC";
$stmt = $pdo->query($sql);
while ($row = $stmt->fetch()):
    $statusColor = 'bg-gray-100 text-gray-800';
    if ($row['status'] == 'Approved' || $row['status'] == 'approved') {
        $statusColor = 'bg-green-100 text-green-800';
    }
    elseif ($row['status'] == 'Declined' || $row['status'] == 'rejected') {
        $statusColor = 'bg-red-100 text-red-800';
    }
    elseif ($row['status'] == 'Information Requested') {
        $statusColor = 'bg-yellow-100 text-yellow-800';
    }
    elseif ($row['status'] == 'Pending Updated') {
        $statusColor = 'bg-purple-100 text-purple-800';
    }
?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    <?= h($row['unit_number'])?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500">
                    <div class="font-bold text-gray-700">
                        <?= h($row['name'])?> (
                        <?= h($row['type'])?>)
                    </div>
                    <div>Breed:
                        <?= h($row['breed'])?>
                    </div>
                    <div class="truncate max-w-xs text-xs">
                        <?= h($row['notes'])?>
                    </div>
                    <?php if (!empty($row['trustee_comments'])): ?>
                    <div class="mt-1 text-xs text-blue-600 bg-blue-50 p-1 rounded">
                        <strong>Trustee:</strong>
                        <?= h($row['trustee_comments'])?>
                    </div>
                    <?php
    endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor?>">
                        <?= ucfirst($row['status'])?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= format_date($row['created_at'])?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                        onclick="openStatusModal(<?= $row['id']?>, '<?= h($row['status'])?>', '<?= h(addslashes($row['trustee_comments'] ?? ''))?>')"
                        class="text-indigo-600 hover:text-indigo-900">Update Status</button>
                </td>
            </tr>
            <?php
endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Simple Modal for Status Update -->
<div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Update Pet Status</h3>
            <form method="POST" class="mt-2 text-left">
                <input type="hidden" name="pet_id" id="modal_pet_id">
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="modal_status"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option value="Pending">Pending</option>
                        <option value="Pending Updated">Pending Updated</option>
                        <option value="Approved">Approved</option>
                        <option value="Declined">Declined</option>
                        <option value="Information Requested">Information Requested</option>
                    </select>
                </div>
                <div class="mt-2">
                    <label class="block text-sm font-medium text-gray-700">Trustee Comments (Required for Decline/Info
                        Requested)</label>
                    <textarea name="trustee_comments" id="modal_trustee_comments"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm" rows="3"></textarea>
                </div>
                <div class="mt-2 text-xs text-gray-500 mb-2">
                    * If you select "Information Requested" or "Declined", the resident will be emailed the Trustee
                    Comments.
                </div>
                <div class="items-center px-4 py-3">
                    <button name="update_status"
                        class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700">
                        Save Status
                    </button>
                    <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')"
                        class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-200">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function openStatusModal(id, status, trustee_comments) {
        document.getElementById('modal_pet_id').value = id;

        // Auto select
        let selValue = status.charAt(0).toUpperCase() + status.slice(1);
        let sel = document.getElementById('modal_status');
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === selValue || sel.options[i].value.toLowerCase() === status.toLowerCase()) {
                sel.selectedIndex = i;
                break;
            }
        }

        document.getElementById('modal_trustee_comments').value = trustee_comments;
        document.getElementById('statusModal').classList.remove('hidden');
    }
</script>