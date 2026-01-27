<?php
require_once 'db.php';

try {
    // 1. Get your test user ID
    $userQuery = $pdo->query("SELECT id FROM users LIMIT 1");
    $userId = $userQuery->fetchColumn();

    if (!$userId) {
        die("No user found. Please seed the database first.");
    }

    // 2. Insert a dummy notification
    $sql = "INSERT INTO notifications (user_id, notif_type, message) 
            VALUES (:uid, :type, :msg)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'  => $userId,
        ':type' => 'SYSTEM_TEST',
        ':msg'  => 'The Nexus is active. Connection to PostgreSQL established.'
    ]);

    echo "Notification injected! <a href='theChamber.php'>Return to The Chamber</a> to see it.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>