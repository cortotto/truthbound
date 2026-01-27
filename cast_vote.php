<?php
session_start();
require_once 'db.php';

// Ensure we always return JSON for the AJAX call
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized session.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$statement_id = $_POST['statement_id'] ?? null;
$vote_type = $_POST['vote_type'] ?? null;

if (!$statement_id || !$vote_type) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
    exit();
}

try {
    // 1. Set PDO to throw exceptions so we catch database errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // 2. Check if this user has already voted on this statement
    // This prevents the "vote_count" from incrementing multiple times by one person
    $checkVote = $pdo->prepare("SELECT 1 FROM votes WHERE user_id = :uid::uuid AND statement_id = :sid::uuid");
    $checkVote->execute([':uid' => $user_id, ':sid' => $statement_id]);
    
    if ($checkVote->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Protocol error: Vote already recorded in your sector.']);
        exit();
    }

    // 3. Insert the new vote
    $insertVote = $pdo->prepare("
        INSERT INTO votes (id, user_id, statement_id, vote_type) 
        VALUES (gen_random_uuid(), :uid::uuid, :sid::uuid, :type)
    ");
    $insertVote->execute([
        ':uid' => $user_id,
        ':sid' => $statement_id,
        ':type' => $vote_type
    ]);

    // 4. Increment the vote_count in the statements table
    // The ::uuid cast ensures PostgreSQL matches the types correctly
    $updateStmt = $pdo->prepare("
        UPDATE statements 
        SET vote_count = vote_count + 1 
        WHERE id = :sid::uuid
    ");
    $updateStmt->execute([':sid' => $statement_id]);

    // 5. Commit the transaction
    // This is the moment the "vote_count" actually changes in the DB
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Consensus recorded.'
    ]);

} catch (Exception $e) {
    // Rollback ensures that if the update fails, the vote insert is also undone
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error for the developer and inform the frontend
    error_log("Vote Failure: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database Sync Error: ' . $e->getMessage()
    ]);
}