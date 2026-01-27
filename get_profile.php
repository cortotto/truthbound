<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch User Data
    $user_stmt = $pdo->prepare("SELECT username, accuracy_score, rank_title FROM users WHERE id = :uid::uuid");
    $user_stmt->execute([':uid' => $user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Consensus Distribution (Using final_category)
    // We count statements the user voted on that are no longer ACTIVE
    $chart_query = "
        SELECT s.final_category, COUNT(*) as total 
        FROM statements s
        JOIN votes v ON s.id = v.statement_id
        WHERE v.user_id = :uid::uuid 
        AND s.status IN ('RATIFIED', 'CONTESTED')
        GROUP BY s.final_category
    ";
    $chart_stmt = $pdo->prepare($chart_query);
    $chart_stmt->execute([':uid' => $user_id]);
    $results = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Map results to Chart.js order: [OBJ, INT, SUB, CNT]
    $chart_map = ['OBJ' => 0, 'INT' => 0, 'SUB' => 0, 'CNT' => 0];
    foreach ($results as $row) {
        if (isset($chart_map[$row['final_category']])) {
            $chart_map[$row['final_category']] = (int)$row['total'];
        }
    }

    echo json_encode([
        'user' => $user,
        'chart' => array_values($chart_map)
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}