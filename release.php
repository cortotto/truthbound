<?php
session_start();
require_once 'db.php';

// Guard: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $content = trim($_POST['statement']);
    $intent = $_POST['intent'] ?? null;

    // Basic validation
    if (empty($content) || !$intent) {
        die("Error: Statement content and category intent are required.");
    }

    try {
        $sql = "INSERT INTO statements (user_id, content, intended_category, status, vote_count) 
                VALUES (:user_id, :content, :intent, 'PENDING', 0)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':content' => $content,
            ':intent'  => $intent
        ]);

        // Success: Redirect back to the feed
        header("Location: theChamber.php?success=released");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}