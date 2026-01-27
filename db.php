<?php
// db.php - Supabase Production Config
$host = "db.ttkxaxoobfypqzcuoeia.supabase.co"; // Example host
$port = "5432"; // Use 6543 for transaction pooling
$dbname = "postgres";
$user = "postgres";
$password = "pineFresh@13";

try {
    // Supabase requires SSL for remote connections
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // In production, don't echo the full error to users
    error_log("Database Connection Error: " . $e->getMessage());
    die("The Nexus is currently undergoing maintenance. Please standby.");
}
?>