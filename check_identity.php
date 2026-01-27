<?php
require_once 'db.php';

$username = $_GET['username'] ?? null;
$email = $_GET['email'] ?? null;

$response = ['available' => true];

if ($username) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) $response['available'] = false;
}

if ($email) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $response['available'] = false;
}

header('Content-Type: application/json');
echo json_encode($response);