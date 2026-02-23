<?php
// move_in_form.php
require_once 'admin/config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = "Thank you! Your Move-In Logistics details have been submitted to the management team. We will coordinate your move-in date.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Move-In Logistics Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>

<body class="bg-gray-100 min-h-screen font-sans flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-lg shadow-md p-8">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900 border-b border-gray-200 pb-4"><i
                    class="fas fa-truck-moving text-blue-600 mr-2"></i> Move-In Logistics</h1>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-500 text-green-700 px-4 py-3 rounded text-center">
            <strong><i class="fas fa-check-circle"></i>
                <?= h($message)?>
            </strong>
        </div>
        <?php
else: ?>
        <p class="text-gray-600 mb-6 text-center">Congratulations on your Resident Approval! Please fill out the form
            below to schedule your move-in and arrange gate access for your movers.</p>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Preferred Move-In Date</label>
                <input type="date" name="move_in_date"
                    class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Moving Company Name (If applicable)</label>
                <input type="text" name="moving_company"
                    class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none"
                    placeholder="e.g. Master Movers">
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Vehicle Configuration / Setup Needs</label>
                <textarea name="notes" rows="3"
                    class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none"
                    placeholder="Let us know if you need extended gate-open times etc."></textarea>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded hover:bg-blue-700 transition">Submit
                Move-In Request</button>
        </form>
        <?php
endif; ?>
    </div>
</body>

</html>