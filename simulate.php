<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) exit('Unauthorized');

$user_id = $_SESSION['user_id'];
$content = $_POST['content'];
$intended = $_POST['intended_category']; // Your vote
$sim_cat = $_POST['sim_category'];      // Consensus vote

try {
    $pdo->beginTransaction();

    // 1. Create the Statement
    $stmtId = bin2hex(random_bytes(16)); // Generate a UUID-like string
    $stmt = $pdo->prepare("INSERT INTO statements (id, user_id, content, intended_category, status, vote_count) 
                           VALUES (:id::uuid, :uid::uuid, :content, :intended, 'ACTIVE', 0)");
    $stmt->execute(['id' => $stmtId, 'uid' => $user_id, 'content' => $content, 'intended' => $intended]);

    // 2. Record YOUR vote
    $vote = $pdo->prepare("INSERT INTO votes (id, user_id, statement_id, vote_type) VALUES (gen_random_uuid(), :uid::uuid, :sid::uuid, :type)");
    $vote->execute(['uid' => $user_id, 'sid' => $stmtId, 'type' => $intended]);

    // 3. Simulate 99 Other Votes
    // We use a loop to insert votes from a "System" or random UUIDs
    $simVote = $pdo->prepare("INSERT INTO votes (id, user_id, statement_id, vote_type) VALUES (gen_random_uuid(), gen_random_uuid(), :sid::uuid, :type)");
    
    for ($i = 0; $i < 99; $i++) {
        $simVote->execute(['sid' => $stmtId, 'type' => $sim_cat]);
    }

    // 4. Update the final count to 100
    // This will trigger your AFTER trigger to ratify the statement immediately!
    $update = $pdo->prepare("UPDATE statements SET vote_count = 100 WHERE id = :sid::uuid");
    $update->execute(['sid' => $stmtId]);

    $pdo->commit();
    header("Location: theChamber.php?success=simulated");
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Simulation Failed: " . $e->getMessage();
}