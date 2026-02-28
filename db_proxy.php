<?php
// Secure Database Interface for AI Agent
header('Content-Type: application/json');

// Only allow requests with the correct secret key
$secret_key = 'ai_admin_token_2026';

if (!isset($_POST['token']) || $_POST['token'] !== $secret_key) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized Access']);
    exit;
}

require_once 'admin/config/db.php';

$query = $_POST['query'] ?? '';

if (empty($query)) {
    echo json_encode(['error' => 'No query provided']);
    exit;
}

try {
    // Basic security block: prevent structural destructive commands just in case
    $query_upper = strtoupper($query);
    if (strpos($query_upper, 'DROP TABLE') !== false || strpos($query_upper, 'DROP DATABASE') !== false) {
        throw new Exception("Destructive DROP commands are blocked for safety.");
    }

    $stmt = $pdo->query($query);

    // If it's a SELECT query, fetch results
    if (strpos($query_upper, 'SELECT') === 0 || strpos($query_upper, 'SHOW') === 0 || strpos($query_upper, 'DESCRIBE') === 0) {
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'rows' => count($results), 'data' => $results]);
    }
    else {
        // For UPDATE/INSERT/DELETE
        echo json_encode(['success' => true, 'affected_rows' => $stmt->rowCount()]);
    }
}
catch (PDOException $e) {
    echo json_encode(['error' => 'SQL Error: ' . $e->getMessage()]);
}
catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>