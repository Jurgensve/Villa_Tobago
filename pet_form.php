<?php
// pet_form.php
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

// Fetch pet settings
$pet_settings = $pdo->query("SELECT setting_key, setting_value FROM pet_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$max_pets = (int)($pet_settings['max_pets_per_unit'] ?? 2);
$allowed_types = array_map('trim', explode(',', $pet_settings['allowed_pet_types'] ?? 'Dog, Cat, Bird, Fish'));

// Check current pet count for this unit
$existing_count = (int)$pdo->prepare("SELECT COUNT(*) FROM pets WHERE unit_id = ?")->execute([$res['unit_id']]) ? 0 : 0;
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM pets WHERE unit_id = ?");
$stmt_count->execute([$res['unit_id']]);
$existing_count = (int)$stmt_count->fetchColumn();

if ($max_pets > 0 && $existing_count >= $max_pets) {
    $error = "You have reached the maximum number of pets allowed per unit ({$max_pets}).";
}

// ── Handle Form Submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $breed = trim($_POST['breed'] ?? '');
    $adult_size = trim($_POST['adult_size'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '') ?: null;
    $is_sterilized = isset($_POST['is_sterilized']) ? 1 : 0;
    $is_vaccinated = isset($_POST['is_vaccinated']) ? 1 : 0;
    $is_microchipped = isset($_POST['is_microchipped']) ? 1 : 0;
    $wears_id_tag = isset($_POST['wears_id_tag']) ? 1 : 0;
    $motivation_note = trim($_POST['motivation_note'] ?? '');
    $house_rules_accepted = isset($_POST['house_rules_accepted']) ? 1 : 0;
    $reg_number = trim($_POST['reg_number'] ?? '');

    if (empty($name) || empty($type)) {
        $error = "Pet Name and Type are required.";
    }
    elseif (!$house_rules_accepted) {
        $error = "You must agree to the Villa Tobago House Rules regarding the keeping of pets.";
    }
    else {
        try {
            // Ensure resident record exists
            $stmt = $pdo->prepare("SELECT id FROM residents WHERE unit_id = ? AND resident_type = ? AND resident_id = ?");
            $stmt->execute([$res['unit_id'], $res['type'], $res['id']]);
            $resident_id_record = $stmt->fetchColumn();

            if (!$resident_id_record) {
                $ins = $pdo->prepare("INSERT INTO residents (unit_id, resident_type, resident_id) VALUES (?, ?, ?)");
                $ins->execute([$res['unit_id'], $res['type'], $res['id']]);
                $resident_id_record = $pdo->lastInsertId();
            }

            // Insert pet record (files uploaded after we have the pet ID)
            $stmt = $pdo->prepare(
                "INSERT INTO pets (unit_id, resident_id, name, type, breed, reg_number, adult_size, birth_date,
                    is_sterilized, is_vaccinated, is_microchipped, wears_id_tag,
                    motivation_note, house_rules_accepted, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())"
            );
            $stmt->execute([
                $res['unit_id'], $resident_id_record, $name, $type, $breed, $reg_number,
                $adult_size ?: null, $birth_date,
                $is_sterilized, $is_vaccinated, $is_microchipped, $wears_id_tag,
                $motivation_note ?: null, $house_rules_accepted
            ]);
            $pet_id = $pdo->lastInsertId();

            // Handle file uploads
            $upload_dir = __DIR__ . "/uploads/pets/{$pet_id}/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

            function save_pet_upload($file_key, $upload_dir, $allowed_types)
            {
                if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK)
                    return null;
                $file = $_FILES[$file_key];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed_types))
                    return null;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $dest = $upload_dir . $file_key . '.' . strtolower($ext);
                move_uploaded_file($file['tmp_name'], $dest);
                return "uploads/pets/{$GLOBALS['pet_id_global']}/{$file_key}." . strtolower($ext);
            }

            // Simple upload helper
            $updates = [];
            $upload_fields = [
                'photo' => 'photo_path',
                'sterilized_proof' => 'sterilized_proof_path',
                'vaccination_proof' => 'vaccination_proof_path',
            ];

            foreach ($upload_fields as $file_key => $col) {
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$file_key];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    if (in_array($mime, $allowed_img_types)) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $dest_rel = "uploads/pets/{$pet_id}/{$file_key}.{$ext}";
                        $dest_abs = __DIR__ . "/" . $dest_rel;
                        if (move_uploaded_file($file['tmp_name'], $dest_abs)) {
                            $updates[$col] = $dest_rel;
                        }
                    }
                }
            }

            if (!empty($updates)) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($updates)));
                $vals = array_values($updates);
                $vals[] = $pet_id;
                $pdo->prepare("UPDATE pets SET {$set} WHERE id = ?")->execute($vals);
            }

            $message = "Pet application submitted successfully! It is now pending review by the Trustees.";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Application — Villa Tobago</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gradient-to-br from-slate-100 to-yellow-50 min-h-screen font-sans">
    <div class="max-w-2xl mx-auto py-10 px-4">

        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-yellow-500 mb-3">
                <i class="fas fa-paw text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900">Pet Registration Application</h1>
            <p class="text-gray-500 mt-1">Unit
                <?= h($res['unit_number'])?> —
                <?= h($res['full_name'])?>
            </p>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-xl mb-6 shadow">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                <div>
                    <p class="font-bold text-green-800">Application Submitted</p>
                    <p class="text-green-700 text-sm">
                        <?= h($message)?>
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <a href="resident_portal.php"
                    class="inline-flex items-center gap-2 text-green-700 font-bold hover:underline">
                    <i class="fas fa-arrow-left"></i> Back to Resident Portal
                </a>
            </div>
        </div>
        <?php
endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl mb-6">
            <p class="font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Notice</p>
            <p>
                <?= h($error)?>
            </p>
        </div>
        <?php
endif; ?>

        <?php if (!$message): ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <!-- ── 1. Basic Pet Details ──────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow p-6 border-t-4 border-yellow-400">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-yellow-500"></i> Pet Details
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Pet Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="name" required
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition"
                            placeholder="e.g. Buddy">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Type / Species <span
                                class="text-red-500">*</span></label>
                        <select name="type" required
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition bg-white">
                            <option value="">— Select Type —</option>
                            <?php foreach ($allowed_types as $t): ?>
                            <option value="<?= h($t)?>">
                                <?= h($t)?>
                            </option>
                            <?php
    endforeach; ?>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Breed</label>
                        <input type="text" name="breed"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition"
                            placeholder="e.g. Labrador">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Adult Size</label>
                        <select name="adult_size"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition bg-white">
                            <option value="">— Select Size —</option>
                            <option value="Small">Small (under 10kg)</option>
                            <option value="Medium">Medium (10 – 25kg)</option>
                            <option value="Large">Large (over 25kg)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Date of Birth</label>
                        <input type="date" name="birth_date"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-1">Microchip / Registration #</label>
                        <input type="text" name="reg_number"
                            class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-yellow-400 outline-none transition"
                            placeholder="Optional">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-1">Recent Photo of Pet <span
                                class="text-gray-400 font-normal">(recommended)</span></label>
                        <div
                            class="flex items-center gap-3 p-3 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50 hover:border-yellow-400 transition">
                            <i class="fas fa-camera text-gray-400 text-xl"></i>
                            <input type="file" name="photo" accept="image/*"
                                class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 2. Health & Compliance Requirements ───────────────── -->
            <div class="bg-white rounded-2xl shadow p-6 border-t-4 border-blue-500">
                <h2 class="text-lg font-bold text-gray-800 mb-2 flex items-center gap-2">
                    <i class="fas fa-shield-alt text-blue-500"></i> Health & Compliance Requirements
                </h2>
                <p class="text-gray-500 text-sm mb-5">Please confirm your pet's status for each requirement. Upload
                    proof where indicated.</p>

                <!-- Sterilized -->
                <div class="border border-gray-100 rounded-xl p-4 mb-4 bg-gray-50" id="sterilized-block">
                    <div class="flex items-start gap-3 mb-3">
                        <input type="checkbox" name="is_sterilized" id="is_sterilized" value="1"
                            class="h-5 w-5 mt-0.5 text-blue-600 rounded border-gray-300 cursor-pointer"
                            onchange="toggleMotivation()">
                        <div>
                            <label for="is_sterilized" class="font-bold text-gray-900 cursor-pointer">
                                Sterilized or Neutered <span
                                    class="ml-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Proof
                                    Required</span>
                            </label>
                            <p class="text-gray-500 text-xs mt-0.5">A vet certificate or letter is required as proof.
                            </p>
                        </div>
                    </div>
                    <div class="ml-8">
                        <label class="block text-gray-600 text-xs font-bold mb-1">Upload Proof (image or PDF)</label>
                        <div
                            class="flex items-center gap-2 p-2 border border-dashed border-gray-200 rounded-lg bg-white hover:border-blue-300 transition">
                            <i class="fas fa-upload text-gray-300 text-sm"></i>
                            <input type="file" name="sterilized_proof" accept="image/*,application/pdf"
                                class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Vaccinated -->
                <div class="border border-gray-100 rounded-xl p-4 mb-4 bg-gray-50">
                    <div class="flex items-start gap-3 mb-3">
                        <input type="checkbox" name="is_vaccinated" id="is_vaccinated" value="1"
                            class="h-5 w-5 mt-0.5 text-blue-600 rounded border-gray-300 cursor-pointer"
                            onchange="toggleMotivation()">
                        <div>
                            <label for="is_vaccinated" class="font-bold text-gray-900 cursor-pointer">
                                Up-to-Date Vaccination <span
                                    class="ml-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">Proof
                                    Required</span>
                            </label>
                            <p class="text-gray-500 text-xs mt-0.5">Upload your latest vaccination card or vet record.
                            </p>
                        </div>
                    </div>
                    <div class="ml-8">
                        <label class="block text-gray-600 text-xs font-bold mb-1">Upload Proof (image or PDF)</label>
                        <div
                            class="flex items-center gap-2 p-2 border border-dashed border-gray-200 rounded-lg bg-white hover:border-blue-300 transition">
                            <i class="fas fa-upload text-gray-300 text-sm"></i>
                            <input type="file" name="vaccination_proof" accept="image/*,application/pdf"
                                class="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        </div>
                    </div>
                </div>

                <!-- Microchipped -->
                <div class="border border-gray-100 rounded-xl p-4 mb-4 bg-gray-50">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="is_microchipped" id="is_microchipped" value="1"
                            class="h-5 w-5 mt-0.5 text-blue-600 rounded border-gray-300 cursor-pointer">
                        <div>
                            <label for="is_microchipped" class="font-bold text-gray-900 cursor-pointer">
                                Microchipped <span
                                    class="ml-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-bold">Informational</span>
                            </label>
                            <p class="text-gray-500 text-xs mt-0.5">Not a hard requirement, but important for
                                identification. No proof needed.</p>
                        </div>
                    </div>
                </div>

                <!-- Wears Identity Tag -->
                <div class="border border-gray-100 rounded-xl p-4 bg-gray-50">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="wears_id_tag" id="wears_id_tag" value="1"
                            class="h-5 w-5 mt-0.5 text-blue-600 rounded border-gray-300 cursor-pointer">
                        <div>
                            <label for="wears_id_tag" class="font-bold text-gray-900 cursor-pointer">
                                Wears an Identity Tag with Contact Details <span
                                    class="ml-1 text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-bold">Required</span>
                            </label>
                            <p class="text-gray-500 text-xs mt-0.5">Your pet must wear a tag with your contact number at
                                all times. No upload needed.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 3. Motivation (dynamic) ────────────────────────────── -->
            <div id="motivation-section" class="hidden bg-orange-50 border-2 border-orange-200 rounded-2xl p-6">
                <h2 class="text-lg font-bold text-orange-900 mb-1 flex items-center gap-2">
                    <i class="fas fa-comment-alt text-orange-500"></i> Written Motivation
                </h2>
                <p class="text-orange-700 text-sm mb-4">Your pet does not currently meet one or more of the required
                    health standards. You may still submit this application with a written motivation explaining your
                    circumstances. The Trustees will consider this during their review.</p>
                <label class="block text-orange-800 text-sm font-bold mb-2">Personal Motivation <span
                        class="text-gray-500 font-normal">(optional but encouraged)</span></label>
                <textarea name="motivation_note" rows="4"
                    class="w-full border-2 border-orange-200 rounded-lg px-4 py-3 bg-white focus:border-orange-400 outline-none transition text-sm"
                    placeholder="Please explain why your pet does not currently meet the requirements, and any plans you have to address this..."></textarea>
            </div>

            <!-- ── 4. House Rules Agreement ───────────────────────────── -->
            <div class="bg-white rounded-2xl shadow p-6 border-t-4 border-green-500">
                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-file-signature text-green-500"></i> Agreement
                </h2>
                <label
                    class="flex items-start gap-3 cursor-pointer p-4 bg-green-50 rounded-xl border-2 border-green-100 hover:border-green-400 transition">
                    <input type="checkbox" name="house_rules_accepted" value="1"
                        class="h-5 w-5 mt-0.5 text-green-600 rounded border-gray-300 cursor-pointer" required>
                    <div>
                        <span class="font-bold text-green-900 block">I agree to the Villa Tobago House Rules with
                            Regards to the Keeping of Pets</span>
                        <span class="text-green-700 text-sm block mt-1">By checking this box, I confirm that I have read
                            and understood the body corporate rules relating to pets, and agree to abide by them at all
                            times.</span>
                        <?php
    $rules_pdf = '';
    try {
        $rules_pdf = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'complex_rules_pdf'")->fetchColumn();
    }
    catch (Exception $e) {
    }
    if ($rules_pdf): ?>
                        <a href="<?= SITE_URL?>/<?= h($rules_pdf)?>" target="_blank"
                            class="inline-flex items-center gap-1 text-green-600 hover:text-green-800 text-xs font-bold mt-2 underline">
                            <i class="fas fa-file-pdf"></i> View Pet Rules (PDF)
                        </a>
                        <?php
    endif; ?>
                    </div>
                </label>
            </div>

            <!-- Submit -->
            <div class="flex gap-4">
                <a href="resident_portal.php"
                    class="flex-none bg-gray-100 text-gray-700 font-bold py-3 px-6 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button type="submit"
                    class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-lg">
                    <i class="fas fa-paw"></i> Submit Pet Application
                </button>
            </div>
        </form>
        <?php
endif; ?>
    </div>

    <script>
        function toggleMotivation() {
            const sterilized = document.getElementById('is_sterilized').checked;
            const vaccinated = document.getElementById('is_vaccinated').checked;
            const motivSection = document.getElementById('motivation-section');
            // Show motivation if either required item is NOT checked
            if (!sterilized || !vaccinated) {
                motivSection.classList.remove('hidden');
            } else {
                motivSection.classList.add('hidden');
            }
        }
    </script>
</body>

</html>