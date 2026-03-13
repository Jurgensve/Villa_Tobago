<?php
// modification_form.php
session_start();
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

if (!isset($_SESSION['auth_resident'])) {
    header("Location: resident_portal.php");
    exit;
}

$res = $_SESSION['auth_resident'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    // Determine IDs
    $unit_id = $res['unit_id'];
    $resident_type = $res['type'];
    $tenant_id = ($resident_type === 'tenant') ? $res['id'] : null;

    // Fetch the current owner for this unit.
    $stmtOwnerId = $pdo->prepare("SELECT owner_id, full_name, email FROM ownership_history oh JOIN owners o ON oh.owner_id = o.id WHERE oh.unit_id = ? AND oh.is_current = 1 LIMIT 1");
    $stmtOwnerId->execute([$unit_id]);
    $owner_row = $stmtOwnerId->fetch();
    $owner_id = $owner_row ? $owner_row['owner_id'] : 0;

    // Determine Status and Token
    $status = 'Pending';
    $amendment_token = null;
    if ($resident_type === 'tenant') {
        $status = 'Pending Owner Approval';
        $amendment_token = bin2hex(random_bytes(16));
    }

    if (empty($category) || empty($description)) {
        $error = "Category and Description are required.";
    }
    else {
        try {
            $policy_accepted = isset($_POST['policy_agreement']) && $_POST['policy_agreement'] == '1' ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO modifications (unit_id, owner_id, tenant_id, category, description, status, request_date, amendment_token, policy_accepted) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$unit_id, $owner_id, $tenant_id, $category, $description, $status, $amendment_token, $policy_accepted]);
            $mod_id = $pdo->lastInsertId();

            if ($policy_accepted) {
                $ip_addr = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $pdo->prepare("INSERT INTO digital_signatures (resident_type, resident_id, unit_id, document_type, record_id, ip_address, user_agent) VALUES (?, ?, ?, 'Modification Policy', ?, ?, ?)")
                    ->execute([$resident_type, ($resident_type === 'tenant' ? $tenant_id : $owner_id), $unit_id, $mod_id, $ip_addr, $user_agent]);
            }

            // Handle Multiple Attachments
            if (isset($_FILES['attachments'])) {
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] == 0) {
                        $filename = $_FILES['attachments']['name'][$key];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $display_name = trim($_POST['attachment_names'][$key] ?? 'Attachment ' . ($key + 1));

                        $new_filename = uniqid() . '_' . $key . '.' . $ext;
                        $target_dir = UPLOAD_DIR . 'modifications/';
                        if (!file_exists($target_dir))
                            mkdir($target_dir, 0755, true);

                        $target_path = $target_dir . $new_filename;
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            $relative_path = 'uploads/modifications/' . $new_filename;
                            $att_stmt = $pdo->prepare("INSERT INTO modification_attachments (modification_id, file_path, display_name) VALUES (?, ?, ?)");
                            $att_stmt->execute([$mod_id, $relative_path, $display_name]);
                        }
                    }
                }
            }
            if ($resident_type === 'tenant' && $owner_row && !empty($owner_row['email'])) {
                // Send email to owner
                $approval_url = SITE_URL . "/owner_approve_modification.php?token=" . $amendment_token;

                // Fetch unit number, we don't have it in $res directly since login only stores unit_id usually, but let's query it
                $unit_num = $pdo->query("SELECT unit_number FROM units WHERE id = " . (int)$unit_id)->fetchColumn();

                $subject = "Tenant Modification Request - Unit " . h($unit_num);
                $body = "<p>Dear " . h($owner_row['full_name']) . ",</p>";
                $body .= "<p>Your tenant (<b>" . h($res['full_name']) . "</b>) has submitted a Modification Request (<b>" . h($category) . "</b>) for Unit " . h($unit_num) . ".</p>";
                $body .= "<p>Description: <br>" . nl2br(h($description)) . "</p>";
                $body .= "<p>Please review and approve or decline this request by clicking the secure link below. If approved, it will be forwarded to the Managing Agent for final review.</p>";
                $body .= "<p style='margin: 20px 0;'><a href='{$approval_url}' style='background-color:#4F46E5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;font-weight:bold;'>Review Modification Request</a></p>";
                $body .= "<p>Warm regards,<br>Villa Tobago Management</p>";
                send_notification_email($owner_row['email'], $subject, $body);

                $message = "Modification request submitted successfully. It is now Pending Owner Approval.";
            }
            else {
                $message = "Modification request submitted successfully. It is now Pending review by Trustees.";
            }
        }
        catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Modification Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">New Unit Modification</h1>
            <a href="resident_portal.php" class="text-blue-600 hover:text-blue-800">Back to Portal</a>
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

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Category *</label>
                <select id="categorySelect" name="category" class="w-full border p-2 rounded" required onchange="handleCategoryChange(this)">
                    <option value="" data-policy="">-- Select Category --</option>
                    <?php 
                    $mod_categories = $pdo->query("SELECT * FROM modification_categories ORDER BY name ASC")->fetchAll();
                    foreach ($mod_categories as $cat) {
                        $policyPath = $cat['policy_document_path'] ? SITE_URL . '/' . h($cat['policy_document_path']) : '';
                        echo '<option value="' . h($cat['name']) . '" data-policy="' . $policyPath . '">' . h($cat['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div id="policyAgreementSection" class="mb-4 hidden">
                <div class="bg-blue-50 border border-blue-200 p-4 rounded text-sm text-blue-800">
                    <p class="mb-2"><strong>Policy Document Required:</strong> This modification category has a specific policy you must read.</p>
                    <a id="policyLink" href="#" target="_blank" class="inline-block bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 font-bold mb-3"><i class="fas fa-file-pdf"></i> View Policy Document</a>
                    <label class="flex items-start gap-2 cursor-pointer font-bold">
                        <input type="checkbox" id="policyAgreementCheckbox" name="policy_agreement" value="1" class="mt-1">
                        I have read, understood and agreed to the terms and requirements laid out in the policy document.
                    </label>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-bold mb-2">Detailed Description *</label>
                <textarea name="description" class="w-full border p-2 rounded" rows="5"
                    placeholder="Specify brands, contractors, sizes, colors, etc." required></textarea>
            </div>

            <div class="bg-gray-50 border p-4 rounded mb-6">
                <h3 class="font-bold border-b pb-2 mb-2 flex justify-between">
                    Attachments
                    <button type="button" onclick="addAttachmentRow()" class="text-blue-600 text-sm hover:underline"><i
                            class="fas fa-plus"></i> Add file</button>
                </h3>
                <div id="attachments-container" class="space-y-4">
                    <div class="attachment-row border p-2 bg-white rounded flex gap-4">
                        <div class="flex-1">
                            <input type="text" name="attachment_names[]" class="w-full border px-2 text-sm"
                                placeholder="e.g. Quote / Plan">
                        </div>
                        <div class="flex-1">
                            <input type="file" name="attachments[]" class="w-full text-sm">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded font-bold w-full hover:bg-blue-700">Submit Modification
                Request</button>
        </form>
    </div>

    <script>
        function addAttachmentRow() {
            const container = document.getElementById('attachments-container');
            const clone = container.querySelector('.attachment-row').cloneNode(true);
            clone.querySelector('input[type="text"]').value = '';
            clone.querySelector('input[type="file"]').value = '';
            container.appendChild(clone);
        }

        function handleCategoryChange(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const policyPath = selectedOption.getAttribute('data-policy');
            const section = document.getElementById('policyAgreementSection');
            const link = document.getElementById('policyLink');
            const checkbox = document.getElementById('policyAgreementCheckbox');

            if (policyPath) {
                link.href = policyPath;
                section.classList.remove('hidden');
                checkbox.setAttribute('required', 'required');
            } else {
                section.classList.add('hidden');
                checkbox.removeAttribute('required');
                checkbox.checked = false;
            }
        }
    </script>
</body>

</html>