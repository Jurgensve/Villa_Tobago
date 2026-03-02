<?php
/**
 * Run pending database migrations
 */
function run_pending_migrations($pdo)
{
    $result = [
        'ran_migrations' => false,
        'success' => true,
        'messages' => []
    ];

    // 1. Ensure migrations table exists
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS system_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    try {
        $pdo->exec($createTableSql);
    }
    catch (PDOException $e) {
        error_log("Migration system error (create table): " . $e->getMessage());
        $result['success'] = false;
        $result['messages'][] = "Error creating migrations table: " . $e->getMessage();
        return $result;
    }

    // 2. Scan directory
    $migrationsDir = __DIR__ . '/../../database/migrations/';
    if (!is_dir($migrationsDir)) {
        return $result; // Nothing to do
    }

    $files = scandir($migrationsDir);
    $sqlFiles = [];
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $sqlFiles[] = $file;
        }
    }

    sort($sqlFiles); // Execute in alphabetical order

    foreach ($sqlFiles as $file) {
        // Check if already applied
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_migrations WHERE migration_name = ?");
        $stmt->execute([$file]);
        if ($stmt->fetchColumn() > 0) {
            continue; // Already applied
        }

        // Apply migration
        $sqlPath = $migrationsDir . $file;
        $sql = file_get_contents($sqlPath);
        if (empty(trim($sql))) {
            continue;
        }

        try {
            // MySQL DDL statements (CREATE, ALTER, DROP) cause implicit commits.
            // Start a transaction, but we must check if one still exists before committing
            // to avoid a "no active transaction" PDO exception.
            $pdo->beginTransaction();

            // Execute the script. 
            // Note: simple multi-query execution. With PDO default settings in PHP, exec() can run multiple statements.
            $pdo->exec($sql);

            // Record migration
            $stmt = $pdo->prepare("INSERT INTO system_migrations (migration_name) VALUES (?)");
            $stmt->execute([$file]);

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            $result['ran_migrations'] = true;
            $result['messages'][] = "Success: Applied " . $file;
            error_log("Migration applied successfully: " . $file);

        }
        catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $result['ran_migrations'] = true;
            $result['success'] = false;
            $result['messages'][] = "Failed: Could not apply " . $file . " - " . $e->getMessage();

            error_log("Failed to apply migration {$file}: " . $e->getMessage());
            // Stop on first error to prevent cascading failures
            break;
        }
    }

    return $result;
}
?>