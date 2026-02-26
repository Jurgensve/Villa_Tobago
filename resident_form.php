<?php
// resident_form.php
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $type = $_POST['resident_type']; // 'owner' or 'tenant'
    $unit_id = (int)$_POST['unit_id'];
    $id_number = trim($_POST['id_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    try {
        $pdo->beginTransaction();

        if ($type === 'owner') {
            // Verify against existing owner records
            $stmt = $pdo->prepare(
                "SELECT o.id, o.full_name, o.email FROM owners o
                 JOIN ownership_history oh ON o.id = oh.owner_id
                 WHERE oh.unit_id = ? AND o.id_number = ? AND oh.is_current = 1 LIMIT 1"
            );
            $stmt->execute([$unit_id, $id_number]);
            $owner = $stmt->fetch();

            if ($owner) {
                $owner_id = $owner['id'];

                // Update contact details
                $pdo->prepare("UPDATE owners SET email = ?, phone = ?, status = 'Pending', portal_access_granted = 0 WHERE id = ?")
                    ->execute([$email, $phone, $owner_id]);

                // Register as resident
                $pdo->prepare(
                    "INSERT INTO residents (unit_id, resident_type, resident_id) VALUES (?, 'owner', ?)
                     ON DUPLICATE KEY UPDATE resident_type = 'owner', resident_id = VALUES(resident_id)"
                )->execute([$unit_id, $owner_id]);

                // Handle secondary owner note (informational only at this stage)
                $secondary_note = trim($_POST['secondary_owner_name'] ?? '');
                // If there's a secondary owner noted, we store it in notes for the agent to action
                if ($secondary_note) {
                // Stored as a notation in the owner record for now via trustee/agent review
                // The admin can add them via the Manage Owners flow
                }

                // Notify the managing agent that an owner has applied
                $agent_email_setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'security_email'")->fetchColumn();
                if ($agent_email_setting) {
                    $subject = "Owner Access Application: Unit " . $unit_id;
                    $body = "Dear Managing Agent,<br><br>";
                    $body .= "An owner has applied for resident portal access.<br><br>";
                    $body .= "<strong>Name:</strong> " . h($full_name) . "<br>";
                    $body .= "<strong>Email:</strong> " . h($email) . "<br>";
                    $body .= "<strong>Phone:</strong> " . h($phone) . "<br>";
                    if ($secondary_note) {
                        $body .= "<strong>Co-owner noted:</strong> " . h($secondary_note) . "<br>";
                    }
                    $body .= "<br>Please review and grant portal access via the Admin Panel → Pending Approvals.<br><br>";
                    $body .= "Regards,<br>Villa Tobago System";
                    send_notification_email($agent_email_setting, $subject, $body);
                }

                $message = "Your owner application has been received and is awaiting approval by the Managing Agent. You will receive an email once access has been granted.";
                $success = true;
            }
            else {
                $error = "Verification failed. We could not find a registered owner for this unit with the provided ID Number. Please contact the Managing Agent.";
            }
        }
        elseif ($type === 'tenant') {
            // Create a pending tenant record
            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare(
                "INSERT INTO tenants (unit_id, full_name, id_number, email, phone, status, owner_approval, pet_approval, portal_access_granted, amendment_token)
                 VALUES (?, ?, ?, ?, ?, 'Pending', 0, 1, 0, ?)"
            );
            $stmt->execute([$unit_id, $full_name, $id_number, $email, $phone, $token]);
            $tenant_id = $pdo->lastInsertId();

            // Find the owner to send approval
            $ownerStmt = $pdo->prepare(
                "SELECT o.id, o.full_name, o.email FROM owners o
                 JOIN ownership_history oh ON o.id = oh.owner_id
                 WHERE oh.unit_id = ? AND oh.is_current = 1 LIMIT 1"
            );
            $ownerStmt->execute([$unit_id]);
            $owner = $ownerStmt->fetch();

            if ($owner && !empty($owner['email'])) {
                $approval_link = SITE_URL . "/owner_approve_tenant.php?token=" . $token;
                $subject = "Action Required: Tenant Application for Your Unit";

                $body = "Dear " . h($owner['full_name']) . ",<br><br>";
                $body .= "A tenant has applied to reside in your unit at Villa Tobago.<br><br>";
                $body .= "<strong>Tenant Details:</strong><br>";
                $body .= "Name: " . h($full_name) . "<br>";
                $body .= "Email: " . h($email) . "<br>";
                $body .= "Phone: " . h($phone) . "<br><br>";
                $body .= "Please review and approve or decline this application using the link below:<br>";
                $body .= "<a href='{$approval_link}'>{$approval_link}</a><br><br>";
                $body .= "Regards,<br>Villa Tobago Management";

                send_notification_email($owner['email'], $subject, $body);
            }

            $message = "Your tenant application has been submitted successfully! An approval request has been sent to the registered unit owner. Once approved, you will receive an email with instructions to complete your profile.";
            $success = true;
        }

        $pdo->commit();
    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $error = "System Error: " . $e->getMessage();
    }
}

// Fetch units for dropdown
$units = $pdo->query("SELECT id, unit_number FROM units ORDER BY CAST(unit_number AS UNSIGNED) ASC, unit_number ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Application — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .step-card {
            transition: all 0.3s ease;
        }

        .radio-card input[type="radio"]:checked+.radio-label {
            border-color: #2563EB;
            background-color: #EFF6FF;
            box-shadow: 0 0 0 3px #BFDBFE;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-100 to-blue-50 min-h-screen font-sans">
    <div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-900 mb-4">
                <i class="fas fa-building text-yellow-400 text-2xl"></i>
            </div>
            <h1 class="text-4xl font-extrabold text-blue-900">Resident Application</h1>
            <p class="mt-3 text-gray-500 text-lg">Begin your residency process at Villa Tobago</p>
        </div>

        <?php if ($success): ?>
        <!-- Success Card -->
        <div class="bg-white rounded-2xl shadow-xl p-10 text-center border-t-4 border-green-500">
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                <i class="fas fa-check-circle text-4xl text-green-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Application Submitted!</h2>
            <p class="text-gray-600 leading-relaxed mb-8 max-w-md mx-auto">
                <?= h($message)?>
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="resident_portal.php"
                    class="inline-flex items-center justify-center bg-blue-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-sign-in-alt mr-2"></i> Go to Resident Portal
                </a>
                <a href="index.html"
                    class="inline-flex items-center justify-center bg-gray-100 text-gray-700 font-bold py-3 px-8 rounded-lg hover:bg-gray-200 transition">
                    Return to Homepage
                </a>
            </div>
        </div>

        <?php
else: ?>
        <!-- Application Form -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Progress hint -->
            <div class="bg-blue-900 text-white px-8 py-5">
                <h2 class="font-bold text-lg">Step 1 of 5 — Basic Information</h2>
                <p class="text-blue-300 text-sm mt-1">Provide your details to begin. You'll complete the remaining steps
                    inside the Resident Portal after approval.</p>
            </div>

            <div class="p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Notice</p>
                    <p>
                        <?= h($error)?>
                    </p>
                </div>
                <?php
    endif; ?>

                <form method="POST" class="space-y-8">

                    <!-- Resident Type -->
                    <div>
                        <label class="block text-gray-800 text-sm font-bold mb-3 uppercase tracking-wide">I am applying
                            as a</label>
                        <div class="grid grid-cols-2 gap-4" id="type-selector">
                            <label class="radio-card cursor-pointer" onclick="handleTypeChange('owner')">
                                <input type="radio" name="resident_type" value="owner" class="sr-only" required>
                                <div
                                    class="radio-label border-2 border-gray-200 rounded-xl p-5 text-center hover:border-blue-400 transition">
                                    <i class="fas fa-key text-3xl text-blue-500 mb-2 block"></i>
                                    <span class="font-bold text-gray-800 text-lg block">Unit Owner</span>
                                    <span class="text-gray-400 text-xs">I own this unit</span>
                                </div>
                            </label>
                            <label class="radio-card cursor-pointer" onclick="handleTypeChange('tenant')">
                                <input type="radio" name="resident_type" value="tenant" class="sr-only" required>
                                <div
                                    class="radio-label border-2 border-gray-200 rounded-xl p-5 text-center hover:border-blue-400 transition">
                                    <i class="fas fa-home text-3xl text-green-500 mb-2 block"></i>
                                    <span class="font-bold text-gray-800 text-lg block">Tenant</span>
                                    <span class="text-gray-400 text-xs">I am renting this unit</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Core Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Unit Number <span
                                    class="text-red-500">*</span></label>
                            <select name="unit_id"
                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 focus:bg-white focus:border-blue-500 outline-none transition"
                                required>
                                <option value="">— Select Unit —</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id']?>">
                                    <?= h($u['unit_number'])?>
                                </option>
                                <?php
    endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">RSA ID / Passport Number <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="id_number"
                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 focus:bg-white focus:border-blue-500 outline-none transition"
                                required placeholder="For verification purposes">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="full_name"
                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 focus:bg-white focus:border-blue-500 outline-none transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email Address <span
                                    class="text-red-500">*</span></label>
                            <input type="email" name="email"
                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 focus:bg-white focus:border-blue-500 outline-none transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Cellphone Number <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="phone"
                                class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 focus:bg-white focus:border-blue-500 outline-none transition"
                                required>
                        </div>
                    </div>

                    <!-- Secondary Owner Section (Owner only) -->
                    <div id="secondary-owner-section" class="hidden">
                        <div class="border-2 border-blue-100 rounded-xl p-6 bg-blue-50">
                            <h3 class="font-bold text-blue-900 mb-1"><i class="fas fa-user-plus mr-2"></i>Co-Owner
                                Details</h3>
                            <p class="text-blue-600 text-sm mb-4">If there is a second owner of this unit, please
                                provide their details. The Managing Agent will link them to the unit.</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Co-Owner Full Name</label>
                                    <input type="text" name="secondary_owner_name"
                                        class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-white focus:border-blue-500 outline-none transition"
                                        placeholder="Full name">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Co-Owner Email</label>
                                    <input type="email" name="secondary_owner_email"
                                        class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-white focus:border-blue-500 outline-none transition"
                                        placeholder="Email address">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- What happens next info box -->
                    <div id="info-tenant" class="hidden bg-green-50 border border-green-200 rounded-xl p-5">
                        <h4 class="font-bold text-green-800 mb-2"><i class="fas fa-info-circle mr-2"></i>What Happens
                            Next</h4>
                        <ol class="text-green-700 text-sm space-y-1 list-decimal list-inside">
                            <li>Your application is sent to the registered unit owner for approval.</li>
                            <li>Once the owner approves, you'll receive an email with portal access.</li>
                            <li>You'll complete your full profile (intercom details, vehicles, pets, etc.) in the
                                Resident Portal.</li>
                            <li>The Managing Agent performs a final review before you receive your Move-In Form.</li>
                        </ol>
                    </div>
                    <div id="info-owner" class="hidden bg-blue-50 border border-blue-200 rounded-xl p-5">
                        <h4 class="font-bold text-blue-800 mb-2"><i class="fas fa-info-circle mr-2"></i>What Happens
                            Next</h4>
                        <ol class="text-blue-700 text-sm space-y-1 list-decimal list-inside">
                            <li>Your identity is verified against our owner records.</li>
                            <li>The Managing Agent reviews and grants you portal access.</li>
                            <li>You'll complete your full profile (intercom details, vehicles, pets, etc.) in the
                                Resident Portal.</li>
                            <li>After final agent approval, you receive your Move-In Form.</li>
                        </ol>
                    </div>

                    <!-- Submit -->
                    <div class="pt-2">
                        <button type="submit" name="submit_application"
                            class="w-full bg-blue-900 text-white font-bold py-4 px-4 rounded-xl hover:bg-blue-800 shadow-lg hover:shadow-xl transition text-lg flex justify-center items-center gap-3">
                            Submit Application <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Portal Login Link -->
        <div class="text-center mt-6">
            <a href="resident_portal.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                <i class="fas fa-sign-in-alt mr-1"></i> Already approved? Log in to the Resident Portal
            </a>
        </div>
        <?php
endif; ?>
    </div>

    <script>
        function handleTypeChange(type) {
            const secondarySection = document.getElementById('secondary-owner-section');
            const infoTenant = document.getElementById('info-tenant');
            const infoOwner = document.getElementById('info-owner');

            if (type === 'owner') {
                secondarySection.classList.remove('hidden');
                infoOwner.classList.remove('hidden');
                infoTenant.classList.add('hidden');
            } else {
                secondarySection.classList.add('hidden');
                infoTenant.classList.remove('hidden');
                infoOwner.classList.add('hidden');
            }

            // Visual selection state for radio cards
            document.querySelectorAll('.radio-label').forEach(el => {
                el.style.borderColor = '#E5E7EB';
                el.style.background = '#FFFFFF';
                el.style.boxShadow = '';
            });
            const selected = document.querySelector(`input[value="${type}"]`).nextElementSibling;
            selected.style.borderColor = '#2563EB';
            selected.style.background = '#EFF6FF';
            selected.style.boxShadow = '0 0 0 3px #BFDBFE';
        }
    </script>
</body>

</html>