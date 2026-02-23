<?php
// resident_form.php
require_once 'admin/config/db.php';
require_once 'admin/includes/functions.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $type = $_POST['resident_type']; // 'owner' or 'tenant'
    $unit_id = $_POST['unit_id'];
    $id_number = trim($_POST['id_number']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $has_pets = isset($_POST['has_pets']) ? 1 : 0;

    try {
        $pdo->beginTransaction();

        if ($type === 'owner') {
            // Verify against existing owner records
            $stmt = $pdo->prepare("SELECT o.id, o.full_name, o.email FROM owners o 
                                   JOIN ownership_history oh ON o.id = oh.owner_id 
                                   WHERE oh.unit_id = ? AND o.id_number = ? AND oh.is_current = 1 LIMIT 1");
            $stmt->execute([$unit_id, $id_number]);
            $owner = $stmt->fetch();

            if ($owner) {
                // Owner verified!
                $owner_id = $owner['id'];

                // Update their email/phone if provided
                $update = $pdo->prepare("UPDATE owners SET email = ?, phone = ? WHERE id = ?");
                $update->execute([$email, $phone, $owner_id]);

                // Register as the resident
                $pdo->prepare("INSERT INTO residents (unit_id, resident_type, resident_id) VALUES (?, 'owner', ?) 
                               ON DUPLICATE KEY UPDATE resident_type = 'owner', resident_id = VALUES(resident_id)")
                    ->execute([$unit_id, $owner_id]);

                // If pets are requested, initialize the pet process
                if ($has_pets) {
                    $pdo->prepare("UPDATE owners SET pet_approval = 0 WHERE id = ?")->execute([$owner_id]);
                    $message = "Your owner profile has been successfully verified! Since you indicated having pets, a Pet Application process has been initiated which requires Trustee approval. Please log into the Resident Portal to complete your Pet form.";
                }
                else {
                    $pdo->prepare("UPDATE owners SET pet_approval = 1, status = 'Approved' WHERE id = ?")->execute([$owner_id]);
                    $message = "Your owner profile has been successfully verified and approved! You can now log into the Resident Portal.";
                }
                $success = true;
            }
            else {
                $error = "Verification failed. We could not find a registered owner for this unit with the provided ID Number. Please contact the Managing Agent.";
            }
        }
        else if ($type === 'tenant') {
            // Create a new tenant record pending approval
            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare("INSERT INTO tenants (unit_id, full_name, id_number, email, phone, status, owner_approval, pet_approval, amendment_token) 
                                   VALUES (?, ?, ?, ?, ?, 'Pending', 0, ?, ?)");
            $pet_approval_status = $has_pets ? 0 : 1; // 1 if no pets (pre-approved for pets)
            $stmt->execute([$unit_id, $full_name, $id_number, $email, $phone, $pet_approval_status, $token]);
            $tenant_id = $pdo->lastInsertId();

            // Find the owner to send approval
            $ownerStmt = $pdo->prepare("SELECT o.id, o.full_name, o.email FROM owners o 
                                        JOIN ownership_history oh ON o.id = oh.owner_id 
                                        WHERE oh.unit_id = ? AND oh.is_current = 1 LIMIT 1");
            $ownerStmt->execute([$unit_id]);
            $owner = $ownerStmt->fetch();

            if ($owner && !empty($owner['email'])) {
                $approval_link = SITE_URL . "/owner_approve_tenant.php?token=" . $token;
                $subject = "Action Required: Tenant Application Approval";

                $body = "Dear " . h($owner['full_name']) . ",<br><br>";
                $body .= "A new tenant has applied to reside in your unit.<br><br>";
                $body .= "<strong>Tenant Details:</strong><br>";
                $body .= "Name: " . h($full_name) . "<br>";
                $body .= "Email: " . h($email) . "<br>";
                $body .= "Phone: " . h($phone) . "<br>";
                $body .= "Has Pets: " . ($has_pets ? "Yes" : "No") . "<br><br>";

                $body .= "Please click the link below to review and Approve or Decline this tenant.<br>";
                $body .= "<a href='{$approval_link}'>{$approval_link}</a><br><br>";
                $body .= "Regards,<br>Villa Tobago Management";

                send_notification_email($owner['email'], $subject, $body);
            }

            if ($has_pets) {
                $message = "Your tenant application has been submitted! An approval request has been sent to the unit owner. Additionally, because you indicated having pets, this application will also be held for Trustee approval regarding the pets.";
            }
            else {
                $message = "Your tenant application has been submitted successfully! An approval request has been sent to the registered unit owner.";
            }
            $success = true;
        }

        $pdo->commit();
    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $error = "System Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Resident Application Form - Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans">
    <div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-blue-900 border-b-4 border-yellow-400 inline-block pb-2">Resident
                Application</h1>
            <p class="mt-4 text-gray-600">Start your residency process by submitting your details below.</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-white rounded-lg shadow-xl p-8 text-center border-t-4 border-green-500">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
                <i class="fas fa-check text-2xl text-green-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Application Submitted</h2>
            <p class="text-gray-700 mb-6 leading-relaxed">
                <?= h($message)?>
            </p>
            <div class="space-x-4">
                <a href="resident_portal.php"
                    class="inline-block bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700 transition">Go
                    to Resident Portal</a>
                <a href="index.html"
                    class="inline-block bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded hover:bg-gray-300 transition">Return
                    to Homepage</a>
            </div>
        </div>
        <?php
else: ?>

        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="p-8">
                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Notice</p>
                    <p>
                        <?= h($error)?>
                    </p>
                </div>
                <?php
    endif; ?>

                <form method="POST" class="space-y-6">

                    <div class="bg-blue-50 p-6 rounded-lg mb-6 border border-blue-100">
                        <label class="block text-gray-800 text-lg font-bold mb-4">I am applying as a:</label>
                        <div class="flex space-x-6">
                            <label
                                class="flex items-center space-x-3 cursor-pointer p-4 bg-white border border-gray-300 rounded focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-200 w-1/2 justify-center transition">
                                <input type="radio" name="resident_type" value="owner" class="h-5 w-5 text-blue-600"
                                    required>
                                <span class="text-gray-900 font-semibold"><i
                                        class="fas fa-key text-blue-500 mr-2"></i>Unit Owner</span>
                            </label>
                            <label
                                class="flex items-center space-x-3 cursor-pointer p-4 bg-white border border-gray-300 rounded focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-200 w-1/2 justify-center transition">
                                <input type="radio" name="resident_type" value="tenant" class="h-5 w-5 text-blue-600"
                                    required>
                                <span class="text-gray-900 font-semibold"><i
                                        class="fas fa-home text-blue-500 mr-2"></i>Tenant</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Unit Number <span
                                    class="text-red-500">*</span></label>
                            <select name="unit_id"
                                class="w-full border rounded px-4 py-3 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition"
                                required>
                                <option value="">-- Select Unit --</option>
                                <?php
    $units = $pdo->query("SELECT id, unit_number FROM units ORDER BY unit_number ASC")->fetchAll();
    foreach ($units as $u) {
        echo "<option value='{$u['id']}'>{$u['unit_number']}</option>";
    }
?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">ID Number / Passport <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="id_number"
                                class="w-full border rounded px-4 py-3 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition"
                                required placeholder="For verification purposes">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="full_name"
                                class="w-full border rounded px-4 py-3 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email Address <span
                                    class="text-red-500">*</span></label>
                            <input type="email" name="email"
                                class="w-full border rounded px-4 py-3 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition"
                                required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="phone"
                                class="w-full border rounded px-4 py-3 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition"
                                required>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <label
                            class="flex items-start space-x-3 cursor-pointer p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <input type="checkbox" name="has_pets" value="1" class="h-5 w-5 text-blue-600 mt-1">
                            <div>
                                <span class="text-yellow-900 font-bold block">I plan to keep pets at the unit</span>
                                <span class="text-yellow-700 text-sm block mt-1">Checking this box initiates the Pet
                                    Application process which requires Trustee approval according to the Body Corporate
                                    rules.</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-6">
                        <button type="submit" name="submit_application"
                            class="w-full bg-blue-600 text-white font-bold py-4 px-4 rounded-lg hover:bg-blue-700 shadow-lg hover:shadow-xl transition flex justify-center items-center text-lg">
                            Submit Application <i class="fas fa-arrow-right ml-3"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="resident_portal.php" class="text-blue-600 hover:text-blue-800 font-bold text-sm">Already approved?
                Log in to the Resident Portal</a>
        </div>
        <?php
endif; ?>
    </div>
</body>

</html>