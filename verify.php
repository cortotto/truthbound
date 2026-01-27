<?php
session_start();
require_once 'db.php';
$error = '';

if (!isset($_SESSION['temp_email'])) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'];
    $email = $_SESSION['temp_email'];

    $stmt = $pdo->prepare("SELECT verification_code FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $storedCode = $stmt->fetchColumn();

    if ($code === $storedCode) {
        $update = $pdo->prepare("UPDATE users SET is_verified = TRUE, verification_code = NULL WHERE email = ?");
        $update->execute([$email]);
        unset($_SESSION['temp_email']);
        header("Location: login.php?verified=1");
        exit;
    } else {
        $error = "Invalid activation cipher.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Identity | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-sm">
        <form method="POST" class="bg-slate-900 border border-slate-800 p-8 rounded-[2.5rem] text-center shadow-2xl">
            <h2 class="text-2xl font-black italic uppercase mb-2">Activate Identity</h2>
            <p class="text-[10px] text-slate-500 uppercase tracking-widest mb-8">Cipher sent to <?= htmlspecialchars($_SESSION['temp_email']) ?></p>
            
            <?php if($error): ?>
                <p class="text-red-500 text-[10px] font-bold mb-4 uppercase tracking-wider"><?= $error ?></p>
            <?php endif; ?>
            
            <input type="text" name="code" maxlength="6" placeholder="000000" required 
                class="w-full bg-slate-950 border border-slate-800 p-4 rounded-2xl text-center text-3xl font-mono tracking-[0.4em] focus:border-blue-500 outline-none mb-6 text-white">
            
            <button type="submit" class="w-full py-4 bg-blue-600 rounded-xl font-black uppercase tracking-widest hover:bg-blue-500 transition-all mb-6">
                Verify Protocol
            </button>

            <div class="pt-4 border-t border-slate-800/50">
                <button type="button" id="resendBtn" onclick="resendCipher()" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest hover:text-blue-400 transition-colors">
                    Resend Cipher
                </button>
                <p id="timerMsg" class="text-[9px] text-slate-700 mt-2 uppercase hidden">Wait <span id="seconds">60</span>s to retry</p>
            </div>
        </form>
    </div>

    <script>
        async function resendCipher() {
            const btn = document.getElementById('resendBtn');
            const timerMsg = document.getElementById('timerMsg');
            const secondsSpan = document.getElementById('seconds');
            
            btn.disabled = true;
            btn.classList.add('opacity-30', 'cursor-not-allowed');
            timerMsg.classList.remove('hidden');

            try {
                const response = await fetch('resend_code.php');
                const data = await response.json();
                
                if (data.success) {
                    alert("Cipher re-transmitted successfully.");
                } else {
                    alert("Error: " + data.message);
                }
            } catch (e) {
                alert("Core Logic Error.");
            }

            // Start 60s Countdown
            let timeLeft = 60;
            const timer = setInterval(() => {
                timeLeft--;
                secondsSpan.innerText = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.classList.remove('opacity-30', 'cursor-not-allowed');
                    timerMsg.classList.add('hidden');
                    secondsSpan.innerText = 60;
                }
            }, 1000);
        }
    </script>
</body>
</html>