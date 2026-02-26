<?php
// admin/run_migrations.php — One-click SQL migration runner
require_once 'includes/header.php';

// ─── Migration files registered here ──────────────────────────────────────────
// Each migration has a key (used as POST identifier), label, and file path.
$migrations = [
    'db_schema' => [
        'label'       => 'Core Schema (db_schema.sql)',
        'description' => 'Creates the base tables: units, owners, tenants, modifications, pets.',
        'file'        => __DIR__ . '/../db_schema.sql',
        'icon'        => 'fa-database',
        'color'       => 'blue',
    ],
    'residents_pets' => [
        'label'       => 'Residents & Pets Schema (schema_residents_pets.sql)',
        'description' => 'Adds the residents and pets tables.',
        'file'        => __DIR__ . '/../schema_residents_pets.sql',
        'icon'        => 'fa-paw',
        'color'       => 'green',
    ],
    'attachments' => [
        'label'       => 'Attachments Schema (update_schema_attachments.sql)',
        'description' => 'Adds attachment columns for lease documents and modification files.',
        'file'        => __DIR__ . '/../update_schema_attachments.sql',
        'icon'        => 'fa-paperclip',
        'color'       => 'purple',
    ],
    'move_logistics' => [
        'label'       => 'Move Logistics Schema (move_logistics_schema.sql)',
        'description' => 'Creates move_logistics table, system_settings, and adds move_in_token and intercom columns.',
        'file'        => __DIR__ . '/../move_logistics_schema.sql',
        'icon'        => 'fa-truck-moving',
        'color'       => 'orange',
    ],
    'user_management' => [
        'label'       => 'User Management Schema (user_management_schema.sql)',
        'description' => 'Adds full_name, email, phone, role, and is_active columns to the users table. Promotes the first user to super_admin.',
        'file'        => __DIR__ . '/../user_management_schema.sql',
        'icon'        => 'fa-users-cog',
        'color'       => 'purple',
    ],
];

// ─── Execute a migration ───────────────────────────────────────────────────────
$run_results = null;
$run_key     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $run_key = $_POST['run_migration'];

    if (!isset($migrations[$run_key])) {
        die('Invalid migration key.');
    }

    $file = $migrations[$run_key]['file'];

    if (!file_exists($file)) {
        $run_results = [['sql' => '', 'ok' => false, 'error' => "File not found: {$file}"]];
    } else {
        $sql_raw = file_get_contents($file);

        // Strip single-line comments (-- ...) but keep multi-line intact,
        // then split on statement-ending semicolons.
        $sql_clean = preg_replace('/--[^\n]*/', '', $sql_raw);
        $statements = array_filter(
            array_map('trim', explode(';', $sql_clean)),
            fn($s) => $s !== ''
        );

        $run_results = [];
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $run_results[] = ['sql' => $stmt, 'ok' => true,  'error' => null];
            } catch (PDOException $e) {
                $run_results[] = ['sql' => $stmt, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
    }
}

$ok_count  = $run_results ? count(array_filter($run_results, fn($r) => $r['ok']))  : 0;
$err_count = $run_results ? count(array_filter($run_results, fn($r) => !$r['ok'])) : 0;
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Database Migrations</h1>
    <p class="text-gray-500 text-sm mt-1">Run schema migration scripts directly from the admin panel. Safe to re-run — all scripts use <code>IF NOT EXISTS</code> and <code>INSERT IGNORE</code>.</p>
</div>

<!-- ═══ RESULTS ═══════════════════════════════════════════════════════════════ -->
<?php if ($run_results !== null): ?>
<?php $migration_label = $migrations[$run_key]['label']; ?>
<div class="mb-8 bg-white shadow rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b flex items-center gap-3
        <?= $err_count === 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
        <i class="fas <?= $err_count === 0 ? 'fa-check-circle text-green-500' : 'fa-exclamation-triangle text-red-500' ?> text-xl"></i>
        <div>
            <h2 class="font-bold text-gray-800"><?= h($migration_label) ?> — Run Complete</h2>
            <p class="text-sm mt-0.5">
                <span class="text-green-700 font-bold"><?= $ok_count ?> statement(s) OK</span>
                <?php if ($err_count > 0): ?>
                &nbsp;·&nbsp;<span class="text-red-600 font-bold"><?= $err_count ?> error(s)</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="p-4 space-y-2 max-h-96 overflow-y-auto">
        <?php foreach ($run_results as $i => $r): ?>
        <div class="rounded border <?= $r['ok'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?> px-3 py-2 text-xs font-mono">
            <div class="flex items-start gap-2">
                <i class="fas <?= $r['ok'] ? 'fa-check text-green-600' : 'fa-times text-red-600' ?> mt-0.5 flex-shrink-0"></i>
                <pre class="whitespace-pre-wrap break-all text-gray-700 flex-1"><?= h(mb_strimwidth($r['sql'], 0, 300, '…')) ?></pre>
            </div>
            <?php if (!$r['ok'] && $r['error']): ?>
            <p class="mt-1 ml-5 text-red-700 font-sans font-bold"><?= h($r['error']) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ MIGRATION LIST ════════════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <?php foreach ($migrations as $key => $m): ?>
    <?php
        $file_exists = file_exists($m['file']);
        $colorMap = [
            'blue'   => ['card' => 'border-blue-200',   'icon' => 'text-blue-500',   'btn' => 'bg-blue-600 hover:bg-blue-700'],
            'green'  => ['card' => 'border-green-200',  'icon' => 'text-green-500',  'btn' => 'bg-green-600 hover:bg-green-700'],
            'purple' => ['card' => 'border-purple-200', 'icon' => 'text-purple-500', 'btn' => 'bg-purple-600 hover:bg-purple-700'],
            'orange' => ['card' => 'border-orange-200', 'icon' => 'text-orange-500', 'btn' => 'bg-orange-600 hover:bg-orange-700'],
        ];
        $c = $colorMap[$m['color']] ?? $colorMap['blue'];
        $just_ran = ($run_key === $key);
    ?>
    <div class="bg-white shadow rounded-lg border <?= $c['card'] ?> overflow-hidden
        <?= $just_ran ? 'ring-2 ring-offset-2 ring-indigo-400' : '' ?>">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="text-2xl <?= $c['icon'] ?> mt-1 w-8 text-center flex-shrink-0">
                    <i class="fas <?= $m['icon'] ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-900 text-sm leading-snug"><?= h($m['label']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1"><?= h($m['description']) ?></p>
                    <?php if (!$file_exists): ?>
                    <p class="text-xs text-red-500 mt-2 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i>SQL file not found on server</p>
                    <?php else: ?>
                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-file-alt mr-1"></i>
                        <?= h(basename($m['file'])) ?>
                        &nbsp;·&nbsp;
                        <?= round(filesize($m['file']) / 1024, 1) ?> KB
                    </p>
                    <?php endif; ?>

                    <?php if ($just_ran && $run_results !== null): ?>
                    <p class="mt-2 text-xs font-bold <?= $err_count === 0 ? 'text-green-700' : 'text-red-600' ?>">
                        <i class="fas <?= $err_count === 0 ? 'fa-check-circle' : 'fa-times-circle' ?> mr-1"></i>
                        Last run: <?= $ok_count ?> OK<?= $err_count > 0 ? ", {$err_count} error(s)" : '' ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="px-6 pb-5">
            <form method="POST"
                onsubmit="return confirm('Run migration: <?= h(addslashes($m['label'])) ?>?\n\nThis is safe to re-run.')">
                <input type="hidden" name="run_migration" value="<?= h($key) ?>">
                <button type="submit"
                    <?= !$file_exists ? 'disabled' : '' ?>
                    class="w-full <?= $c['btn'] ?> text-white text-sm font-bold py-2 px-4 rounded shadow
                        disabled:opacity-40 disabled:cursor-not-allowed transition duration-150 flex items-center justify-center gap-2">
                    <i class="fas fa-play"></i>
                    Run Migration
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ RUN ALL ══════════════════════════════════════════════════════════════ -->
<div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6">
    <h2 class="font-bold text-gray-700 mb-1"><i class="fas fa-layer-group mr-2 text-gray-400"></i>Run All Migrations in Order</h2>
    <p class="text-sm text-gray-500 mb-4">Executes all four scripts sequentially. Recommended for a fresh database setup. Safe to re-run on an existing database.</p>
    <form method="POST" id="runAllForm">
        <input type="hidden" name="run_all" value="1">
        <button type="button"
            onclick="runAllSequentially()"
            class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2 px-6 rounded shadow flex items-center gap-2">
            <i class="fas fa-forward"></i> Run All Migrations
        </button>
    </form>
</div>

<script>
// "Run All" triggers each migration form sequentially by chaining form submissions
function runAllSequentially() {
    if (!confirm('Run ALL migrations in order?\n\nThis is safe on an existing database — all scripts use IF NOT EXISTS / INSERT IGNORE.')) return;

    const keys = <?= json_encode(array_keys($migrations)) ?>;
    let idx = 0;

    function next() {
        if (idx >= keys.length) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'run_migration';
        input.value = keys[idx++];
        form.appendChild(input);
        document.body.appendChild(form);
        // Store pending index in sessionStorage and submit
        sessionStorage.setItem('migration_pending_idx', idx);
        form.submit();
    }

    // Check if we were mid-sequence on page load
    const pending = parseInt(sessionStorage.getItem('migration_pending_idx') || '0');
    if (pending > 0 && pending < keys.length) {
        // Resume sequence
        idx = pending;
        next();
    } else {
        sessionStorage.removeItem('migration_pending_idx');
        idx = 0;
        next();
    }
}

// Auto-resume "Run All" sequence if we were mid-way
document.addEventListener('DOMContentLoaded', function () {
    const pending = parseInt(sessionStorage.getItem('migration_pending_idx') || '0');
    const keys = <?= json_encode(array_keys($migrations)) ?>;
    if (pending > 0 && pending < keys.length) {
        // We just came back from a sequential run, continue with the next one
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'run_migration';
        input.value = keys[pending];
        form.appendChild(input);
        document.body.appendChild(form);
        sessionStorage.setItem('migration_pending_idx', pending + 1);
        if (pending + 1 >= keys.length) sessionStorage.removeItem('migration_pending_idx');
        form.submit();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
