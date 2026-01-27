<?php
// garbage_collector.php
// This script runs silently to prune expired statements based on the TTL rules.

try {
    // 1. Identify IDs that violate the time constraints
    // Rule A: Older than 1 week with less than 2 votes (Stale Start)
    // Rule B: Older than 1 month and still ACTIVE (Failed Ratification)
    $findQuery = "
        SELECT id FROM statements 
        WHERE status = 'ACTIVE' 
        AND (
            (vote_count < 2 AND created_at < NOW() - INTERVAL '1 week')
            OR 
            (created_at < NOW() - INTERVAL '1 month')
        )
    ";
    
    $stmt = $pdo->query($findQuery);
    $ids_to_purge = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ids_to_purge)) {
        // Prepare a comma-separated string of IDs for the IN clause
        // UUIDs are safe to implode directly if fetched from DB, but we wrap in quotes
        $idList = "'" . implode("','", $ids_to_purge) . "'";

        $pdo->beginTransaction();

        // 2. Delete Dependencies (Child Records) first
        $pdo->exec("DELETE FROM votes WHERE statement_id IN ($idList)");
        $pdo->exec("DELETE FROM statement_likes WHERE statement_id IN ($idList)");
        $pdo->exec("DELETE FROM comments WHERE statement_id IN ($idList)");

        // 3. Delete the Statements themselves
        $deleted = $pdo->exec("DELETE FROM statements WHERE id IN ($idList)");

        $pdo->commit();
        
        // Optional: Log this to a file if you want to track cleanups
        // error_log("Garbage Collector: Purged $deleted expired statements.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // We fail silently here so we don't crash the user's page load
    error_log("Garbage Collector Error: " . $e->getMessage());
}
?>