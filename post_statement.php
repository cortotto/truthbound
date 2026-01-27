<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$content = trim($_POST['content']);
// Match the name attribute from the HTML form
$intended_category = $_POST['intended_category'] ?? null; 

if (strlen($content) > 140 || !$intended_category) {
    die("Validation Error: Missing category or content too long.");
}

try {
    $pdo->beginTransaction();

    // 1. Insert into statements including the intended_category column
    $stmtSql = "INSERT INTO statements (user_id, content, intended_category, vote_count, status) 
                VALUES (:uid::uuid, :content, :icat::category_type, 1, 'ACTIVE'::text::status_type) 
                RETURNING id";
    
    $stmt = $pdo->prepare($stmtSql);
    $stmt->execute([
        ':uid' => $user_id,
        ':content' => $content,
        ':icat' => $intended_category // This fills the column that was null
    ]);
    $statement_id = $stmt->fetchColumn();

    // 2. Record the first vote in the votes table
    $voteSql = "INSERT INTO votes (user_id, statement_id, vote_type) 
                VALUES (:uid::uuid, :sid::uuid, :vtype::category_type)";
    $voteStmt = $pdo->prepare($voteSql);
    $voteStmt->execute([
        ':uid' => $user_id,
        ':sid' => $statement_id,
        ':vtype' => $intended_category
    ]);

    $pdo->commit();
    header("Location: theChamber.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    die("Database Error: " . $e->getMessage());
}