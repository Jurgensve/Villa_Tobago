<?php
// manage_pets.php
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

// Handle Pet Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_pet') {
    $pet_id = (int) $_POST['pet_id'];
    $reason = trim($_POST['removal_reason']);

    if (empty($reason)) {
        $error = "A reason for removal is required.";
    } else {
        // Ensure the pet belongs to the unit
        $stmt_check = $pdo->prepare("SELECT id FROM pets WHERE id = ? AND unit_id = ?");
        $stmt_check->execute([$pet_id, $res['unit_id']]);
        if ($stmt_check->fetch()) {
            $stmt_remove = $pdo->prepare("UPDATE pets SET status = 'Removed', removal_reason = ?, removed_at = NOW() WHERE id = ?");
            if ($stmt_remove->execute([$reason, $pet_id])) {
                $message = "Pet successfully removed from your record.";
            } else {
                $error = "Error updating pet record.";
            }
        } else {
            $error = "Invalid pet selected.";
        }
    }
}

// Handle Agree Condition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'agree_condition') {
    $pet_id = (int) $_POST['pet_id'];
    $stmt_check = $pdo->prepare("SELECT id FROM pets WHERE id = ? AND unit_id = ? AND status = 'Conditional Approval'");
    $stmt_check->execute([$pet_id, $res['unit_id']]);
    if ($stmt_check->fetch()) {
        $stmt_agree = $pdo->prepare("UPDATE pets SET status = 'Approved' WHERE id = ?");
        if ($stmt_agree->execute([$pet_id])) {
            $message = "Condition agreed to. The pet is now Approved.";
        } else {
            $error = "Error updating pet record.";
        }
    } else {
        $error = "Invalid pet selected or already approved.";
    }
}

// Handle Acknowledge Decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acknowledge_decline') {
    $pet_id = (int) $_POST['pet_id'];
    $stmt_check = $pdo->prepare("SELECT id FROM pets WHERE id = ? AND unit_id = ? AND status = 'Declined'");
    $stmt_check->execute([$pet_id, $res['unit_id']]);
    if ($stmt_check->fetch()) {
        $stmt_ack = $pdo->prepare("UPDATE pets SET status = 'Declined (Acknowledged)' WHERE id = ?");
        if ($stmt_ack->execute([$pet_id])) {
            $message = "You have acknowledged the decline of this pet.";
        } else {
            $error = "Error updating pet record.";
        }
    } else {
        $error = "Invalid pet selected.";
    }
}

// Handle Appeal Decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'appeal_decline') {
    $pet_id = (int) $_POST['pet_id'];
    $motivation = trim($_POST['appeal_motivation']);
    if (empty($motivation)) {
        $error = "A motivation note is required to appeal the decision.";
    } else {
        $stmt_check = $pdo->prepare("SELECT id, motivation_note FROM pets WHERE id = ? AND unit_id = ? AND status IN ('Declined', 'Declined (Acknowledged)')");
        $stmt_check->execute([$pet_id, $res['unit_id']]);
        $pet_data = $stmt_check->fetch();
        if ($pet_data) {
            $new_motivation = $pet_data['motivation_note'] . "\n\n--- APPEAL MOTIVATION ---\n" . $motivation;
            $stmt_appeal = $pdo->prepare("UPDATE pets SET status = 'Pending', motivation_note = ?, trustee_comments = NULL WHERE id = ?");
            if ($stmt_appeal->execute([$new_motivation, $pet_id])) {
                $message = "Appeal submitted successfully. The pet is back under review.";
            } else {
                $error = "Error updating pet record.";
            }
        } else {
            $error = "Invalid pet selected.";
        }
    }
}

// Fetch active pets (not removed)
$stmt_pets = $pdo->prepare("SELECT * FROM pets WHERE unit_id = ? AND status != 'Removed' ORDER BY created_at DESC");
$stmt_pets->execute([$res['unit_id']]);
$pets = $stmt_pets->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pets — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gradient-to-br from-slate-100 to-yellow-50 min-h-screen font-sans">
    <div class="max-w-4xl mx-auto py-10 px-4">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-yellow-500 mb-3">
                <i class="fas fa-paw text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900">Manage Pets</h1>
            <p class="text-gray-500 mt-1">Unit <?= h($res['unit_number']) ?> — <?= h($res['full_name']) ?></p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-xl mb-6 shadow-sm flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                <p class="font-bold text-green-800"><?= h($message) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl mb-6 shadow-sm flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                <p class="font-bold text-red-800"><?= h($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Controls -->
        <div class="flex justify-between items-center mb-6">
            <a href="resident_portal.php"
                class="bg-gray-100 text-gray-700 font-bold py-2.5 px-6 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="pet_form.php"
                class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2.5 px-6 rounded-xl shadow transition flex items-center justify-center gap-2">
                <i class="fas fa-plus"></i> Register New Pet
            </a>
        </div>

        <!-- Pet List -->
        <?php if (empty($pets)): ?>
            <div class="bg-white rounded-2xl shadow p-10 text-center border-t-4 border-yellow-400">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-50 mb-4">
                    <i class="fas fa-cat text-gray-300 text-3xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-700 mb-2">No Registered Pets</h2>
                <p class="text-gray-500">You currently have no active pets registered for this unit.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($pets as $pet):
                    $sc = 'bg-gray-100 text-gray-600';
                    $icon = 'fa-clock';
                    if ($pet['status'] == 'Approved') {
                        $sc = 'bg-green-100 text-green-800';
                        $icon = 'fa-check-circle';
                    } elseif ($pet['status'] == 'Conditional Approval') {
                        $sc = 'bg-orange-100 text-orange-800';
                        $icon = 'fa-handshake';
                    } elseif (strpos($pet['status'], 'Declined') !== false) {
                        $sc = 'bg-red-100 text-red-800';
                        $icon = 'fa-times-circle';
                    }
                    ?>
                    <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden flex flex-col">
                        <div class="p-5 flex-1 relative">
                            <span
                                class="absolute top-4 right-4 text-xs px-2 py-1 rounded-full font-bold <?= $sc ?> flex items-center gap-1 shadow-sm">
                                <i class="fas <?= $icon ?>"></i> <?= h($pet['status']) ?>
                            </span>
                            <div class="flex items-center gap-4 mb-4">
                                <?php if (!empty($pet['photo_path']) && file_exists($pet['photo_path'])): ?>
                                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-gray-100 shadow-sm shrink-0">
                                        <img src="<?= SITE_URL ?>/<?= h($pet['photo_path']) ?>" alt="Pet Photo"
                                            class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="w-16 h-16 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-300 text-2xl border-2 border-yellow-100 shrink-0">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900"><?= h($pet['name']) ?></h3>
                                    <p class="text-gray-500 text-sm font-medium"><?= h($pet['breed'] ?: $pet['type']) ?></p>
                                </div>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-x-2 gap-y-1 text-sm text-gray-600 mb-3 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <p><span class="font-bold text-gray-800">Type:</span> <?= h($pet['type']) ?></p>
                                <p><span class="font-bold text-gray-800">Size:</span> <?= h($pet['adult_size'] ?: 'N/A') ?></p>
                                <?php if ($pet['reg_number']): ?>
                                    <p class="col-span-2"><span class="font-bold text-gray-800">Chip/Reg:</span>
                                        <?= h($pet['reg_number']) ?></p>
                                <?php endif; ?>

                                <p class="col-span-2 mt-2 pt-2 border-t border-gray-200">
                                    <span class="text-xs font-bold uppercase tracking-wide text-gray-400">Health Docs</span>
                                </p>
                                <p class="flex items-center gap-1">
                                    <i
                                        class="fas <?= $pet['is_sterilized'] ? 'fa-check text-green-500' : 'fa-times text-red-400' ?>"></i>
                                    Sterilized
                                </p>
                                <p class="flex items-center gap-1">
                                    <i
                                        class="fas <?= $pet['is_vaccinated'] ? 'fa-check text-green-500' : 'fa-times text-red-400' ?>"></i>
                                    Vaccinated
                                </p>
                            </div>
                        </div>

                        <?php if ($pet['status'] == 'Conditional Approval'): ?>
                            <div class="bg-orange-50 border-t border-b border-orange-100 p-4">
                                <h4 class="text-orange-900 font-bold mb-1"><i class="fas fa-handshake mr-1.5 text-orange-500"></i>
                                    Conditional Approval</h4>
                                <p class="text-sm text-orange-800 mb-3 italic">"<?= nl2br(h($pet['trustee_comments'])) ?>"</p>
                                <button
                                    onclick="openConditionModal(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>', '<?= htmlspecialchars(addslashes(str_replace(array("\r", "\n"), '', nl2br(h($pet['trustee_comments']))))) ?>')"
                                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition text-sm flex items-center justify-center gap-2">
                                    <i class="fas fa-check-circle"></i> Review & Agree to Condition
                                </button>
                            </div>
                        <?php elseif ($pet['status'] == 'Declined' && !empty($pet['trustee_comments'])): ?>
                            <div class="bg-red-50 border-t border-b border-red-100 p-4">
                                <h4 class="text-red-900 font-bold mb-1"><i class="fas fa-exclamation-circle mr-1 text-red-500"></i>
                                    Reason for Decline</h4>
                                <p class="text-sm text-red-800 italic mb-4">"<?= nl2br(h($pet['trustee_comments'])) ?>"</p>

                                <div class="flex flex-col sm:flex-row gap-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="acknowledge_decline">
                                        <input type="hidden" name="pet_id" value="<?= $pet['id'] ?>">
                                        <button type="submit"
                                            class="w-full bg-red-100 hover:bg-red-200 text-red-700 border border-red-300 font-bold py-2 px-4 rounded-lg shadow-sm transition text-sm flex items-center justify-center gap-2">
                                            <i class="fas fa-check"></i> I Accept the Decision
                                        </button>
                                    </form>
                                    <button
                                        onclick="openAppealModal(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>')"
                                        class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition text-sm flex items-center justify-center gap-2">
                                        <i class="fas fa-undo"></i> Appeal & Resubmit
                                    </button>
                                </div>
                            </div>
                        <?php elseif ($pet['status'] == 'Declined (Acknowledged)'): ?>
                            <div class="bg-gray-100 border-t border-b border-gray-200 p-4">
                                <h4 class="text-gray-700 font-bold mb-1"><i class="fas fa-info-circle mr-1 text-gray-500"></i>
                                    Decline Acknowledged</h4>
                                <p class="text-sm text-gray-600 mb-3">You have accepted the administration's decision regarding this
                                    pet.</p>
                                <button
                                    onclick="openAppealModal(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>')"
                                    class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition text-sm flex items-center justify-center gap-2">
                                    <i class="fas fa-undo"></i> Appeal Decision
                                </button>
                            </div>
                        <?php endif; ?>

                        <div
                            class="bg-gray-50 flex-1 p-3 flex <?= ($pet['status'] == 'Conditional Approval' || strpos($pet['status'], 'Declined') !== false) ? '' : 'border-t border-gray-100' ?> justify-end items-end">
                            <button
                                onclick="openRemoveModal(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>')"
                                class="text-sm bg-white border border-red-200 text-red-600 hover:bg-red-50 font-bold py-1.5 px-4 rounded-lg shadow-sm transition flex items-center gap-1">
                                <i class="fas fa-trash-alt"></i> Remove Pet
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Removal Modal -->
    <div id="removeModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <form method="POST"
            class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transform transition-all">
            <input type="hidden" name="action" value="remove_pet">
            <input type="hidden" name="pet_id" id="modal_pet_id" value="">

            <div class="p-6">
                <div
                    class="w-12 h-12 rounded-full bg-red-100 text-red-500 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Remove Record: <span id="modal_pet_name"
                        class="text-red-600"></span></h3>
                <p class="text-gray-600 text-sm mb-5">Are you sure you want to remove this pet? If this pet has passed
                    away or no longer lives in the unit, please provide the reason below. This helps the Trustees keep
                    accurate records.</p>

                <label class="block text-gray-700 text-sm font-bold mb-2">Reason for Removal <span
                        class="text-red-500">*</span></label>
                <textarea name="removal_reason" id="removal_reason" rows="3" required
                    class="w-full border-2 border-gray-300 rounded-lg p-3 outline-none focus:border-red-400 transition"
                    placeholder="e.g. Pet passed away, or Pet moved to the farm..."></textarea>
            </div>

            <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeRemoveModal()"
                    class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 font-bold transition">Cancel</button>
                <button type="submit"
                    class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl shadow font-bold transition">Confirm
                    Removal</button>
            </div>
        </form>
    </div>

    <!-- Condition Modal -->
    <div id="conditionModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <form method="POST"
            class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transform transition-all">
            <input type="hidden" name="action" value="agree_condition">
            <input type="hidden" name="pet_id" id="condition_pet_id" value="">

            <div class="p-6">
                <div
                    class="w-12 h-12 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Condition for: <span id="condition_pet_name"
                        class="text-orange-600"></span></h3>
                <div class="bg-orange-50 p-4 border border-orange-100 rounded-xl text-orange-800 text-sm italic mb-5 leading-relaxed"
                    id="condition_text">
                </div>
                <p class="text-sm text-gray-600 border-l-4 border-gray-300 pl-3">By clicking agree, you formally
                    acknowledge and agree to abide by the condition above. Upon agreement, your pet's status will
                    instantly change to Approved.</p>
            </div>

            <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeConditionModal()"
                    class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 font-bold transition">Cancel</button>
                <button type="submit"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow font-bold transition">I
                    Agree to Condition</button>
            </div>
        </form>
    </div>

    <!-- Appeal Modal -->
    <div id="appealModal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
        <form method="POST"
            class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden transform transition-all">
            <input type="hidden" name="action" value="appeal_decline">
            <input type="hidden" name="pet_id" id="appeal_pet_id" value="">

            <div class="p-6">
                <div
                    class="w-12 h-12 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-undo"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Appeal Decision: <span id="appeal_pet_name"
                        class="text-gray-600"></span></h3>
                <p class="text-sm text-gray-600 mb-5">Please provide a motivation for exception. Once submitted, the pet
                    application will go back into review under "Pending" status.</p>

                <label class="block text-gray-700 text-sm font-bold mb-2">Motivation for Exception <span
                        class="text-red-500">*</span></label>
                <textarea name="appeal_motivation" id="appeal_motivation" rows="4" required
                    class="w-full border-2 border-gray-300 rounded-lg p-3 outline-none focus:border-gray-500 transition"
                    placeholder="Give details about why you are appealing..."></textarea>
            </div>

            <div class="bg-gray-50 p-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="closeAppealModal()"
                    class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 font-bold transition">Cancel</button>
                <button type="submit"
                    class="px-5 py-2.5 bg-gray-800 hover:bg-black text-white rounded-xl shadow font-bold transition">Submit
                    Appeal</button>
            </div>
        </form>
    </div>

    <script>
        function openConditionModal(id, name, text) {
            document.getElementById('condition_pet_id').value = id;
            document.getElementById('condition_pet_name').innerText = name;
            document.getElementById('condition_text').innerHTML = `"` + text + `"`;
            document.getElementById('conditionModal').classList.remove('hidden');
        }
        function closeConditionModal() {
            document.getElementById('conditionModal').classList.add('hidden');
        }

        function openAppealModal(id, name) {
            document.getElementById('appeal_pet_id').value = id;
            document.getElementById('appeal_pet_name').innerText = name;
            document.getElementById('appeal_motivation').value = '';
            document.getElementById('appealModal').classList.remove('hidden');
        }
        function closeAppealModal() {
            document.getElementById('appealModal').classList.add('hidden');
        }

        function openRemoveModal(id, name) {
            document.getElementById('modal_pet_id').value = id;
            document.getElementById('modal_pet_name').innerText = name;
            document.getElementById('removal_reason').value = '';
            document.getElementById('removeModal').classList.remove('hidden');
        }
        function closeRemoveModal() {
            document.getElementById('removeModal').classList.add('hidden');
        }
    </script>
</body>

</html>