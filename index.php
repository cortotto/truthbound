<?php
// Check for the calibration cookie before rendering any HTML
if (isset($_COOKIE['is_calibrated']) && $_COOKIE['is_calibrated'] === 'TRUE') {
    header("Location: theChamber.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass { background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(16px); }
        .fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .cookie-blur { backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-6">

    <a href="https://getsongbpm.com">BPM Data provided by GetSongBPM</a>
</body>
</html>
