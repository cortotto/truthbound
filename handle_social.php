<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? null;
$statement_id = $_POST['statement_id'] ?? null;

try {
    if ($action === 'like') {
        // Check if already liked
        $check = $pdo->prepare("SELECT 1 FROM statement_likes WHERE user_id = :uid::uuid AND statement_id = :sid::uuid");
        $check->execute([':uid' => $user_id, ':sid' => $statement_id]);
        
        if ($check->fetch()) {
            // Unlike: Remove the record
            $stmt = $pdo->prepare("DELETE FROM statement_likes WHERE user_id = :uid::uuid AND statement_id = :sid::uuid");
            $stmt->execute([':uid' => $user_id, ':sid' => $statement_id]);
            echo json_encode(['success' => true, 'status' => 'unliked']);
        } else {
            // Like: Add the record
            $stmt = $pdo->prepare("INSERT INTO statement_likes (id, user_id, statement_id) VALUES (gen_random_uuid(), :uid::uuid, :sid::uuid)");
            $stmt->execute([':uid' => $user_id, ':sid' => $statement_id]);
            echo json_encode(['success' => true, 'status' => 'liked']);
        }
    } 
    
    else if ($action === 'comment') {
        $text = trim($_POST['text'] ?? '');
        if (empty($text)) {
            echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO comments (id, user_id, statement_id, content) VALUES (gen_random_uuid(), :uid::uuid, :sid::uuid, :content)");
        $stmt->execute([
            ':uid' => $user_id,
            ':sid' => $statement_id,
            ':content' => $text
        ]);
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}