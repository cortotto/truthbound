<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['temp_email'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

$email = $_SESSION['temp_email'];

try {
    // Fetch the existing code
    $stmt = $pdo->prepare("SELECT verification_code FROM users WHERE email = ? AND is_verified = FALSE");
    $stmt->execute([$email]);
    $code = $stmt->fetchColumn();

    if ($code) {
        $subject = "TruthBound: Identity Activation Cipher (Resend)";
        $message = "Your requested activation cipher is: " . $code;
        $headers = "From: protocol@truthbound.com";
        
        if (mail($email, $subject, $message, $headers)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transmission failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Identity already verified.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}