<?php
session_start();
require_once 'db.php';
$error = '';

$blockedDomains = ['mailinator.com', 'yopmail.com', 'tempmail.com', '10minutemail.com'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $domain = substr(strrchr($email, "@"), 1);

    try {
        $cleanup = $pdo->prepare("DELETE FROM users WHERE is_verified = FALSE AND created_at < NOW() - INTERVAL '24 hours'");
        $cleanup->execute();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid electromagnetic address format.";
        } 
        // SPACE PREVENTION CHECK
        elseif (preg_match('/\s/', $username)) {
            $error = "Username cannot contain spaces.";
        }
        elseif (in_array($domain, $blockedDomains) || !checkdnsrr($domain, "MX")) {
            $error = "Identity domain verification failed.";
        } elseif ($password !== $confirmPassword) {
            $error = "Security ciphers do not match.";
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[\W]/', $password)) {
            $error = "Cipher too weak. 8+ chars, 1 Uppercase, 1 Number, 1 Symbol required.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->rowCount() > 0) {
                $error = "Identity collision detected. Name or Address already claimed.";
            } else {
                $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO users (username, email, password_hash, verification_code, is_verified, rank_title) 
                        VALUES (?, ?, ?, ?, FALSE, 'Chamberlain')";
                $pdo->prepare($sql)->execute([$username, $email, $hashed, $verificationCode]);
                
                $subject = "TruthBound: Identity Activation Required";
                $message = "Your Protocol Activation Cipher is: " . $verificationCode;
                $headers = "From: protocol@truthbound.com";
                mail($email, $subject, $message, $headers);
                
                $_SESSION['temp_email'] = $email;
                header("Location: verify.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "System Error: Protocol failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Identity | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at center, #0f172a 0%, #020617 100%); }
        .input-focus:focus { border-color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.2); }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex items-center justify-center p-6 font-sans">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <span class="text-[10px] font-black uppercase tracking-[0.6em] text-blue-500">TruthBound Protocol</span>
            <h1 class="text-4xl font-black italic uppercase tracking-tighter mt-2">Initialize</h1>
        </div>

        <form method="POST" class="bg-slate-900/50 backdrop-blur-xl border border-slate-800 p-8 rounded-[2.5rem] shadow-2xl space-y-4">
            
            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 p-4 rounded-xl flex items-center gap-3">
                    <p class="text-[10px] font-bold text-red-400 uppercase tracking-wider"><?= $error ?></p>
                </div>
            <?php endif; ?>

            <div>
                <div class="flex justify-between items-center mb-1 pr-2">
                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 block">Username</label>
                    <span id="username-status" class="text-[8px] font-bold uppercase tracking-tighter"></span>
                </div>
                <input type="text" id="username" name="username" onkeyup="checkIdentity('username')" required 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    class="w-full bg-slate-950 border border-slate-800 p-4 rounded-2xl outline-none transition-all input-focus text-white" placeholder="Subject_Name">
            </div>

            <div>
                <div class="flex justify-between items-center mb-1 pr-2">
                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 block">Address</label>
                    <span id="email-status" class="text-[8px] font-bold uppercase tracking-tighter"></span>
                </div>
                <input type="email" id="email" name="email" onkeyup="checkIdentity('email')" required 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    class="w-full bg-slate-950 border border-slate-800 p-4 rounded-2xl outline-none transition-all input-focus text-white" placeholder="identity@domain.com">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 mb-1 block">Cipher</label>
                    <input type="password" name="password" required 
                        class="w-full bg-slate-950 border border-slate-800 p-4 rounded-xl outline-none transition-all input-focus text-white" placeholder="••••••••">
                </div>
                <div>
                    <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 mb-1 block">Verify</label>
                    <input type="password" name="confirm_password" required 
                        class="w-full bg-slate-950 border border-slate-800 p-4 rounded-xl outline-none transition-all input-focus text-white" placeholder="••••••••">
                </div>
            </div>

            <div class="bg-slate-950/50 p-4 rounded-xl border border-slate-800/50 text-center">
                <p class="text-[8px] text-slate-500 uppercase tracking-[0.2em] leading-relaxed">
                    8+ Chars | 1 Uppercase | 1 Digit | 1 Symbol | No Spaces
                </p>
            </div>

            <button type="submit" class="w-full py-5 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-2xl uppercase tracking-[0.2em] text-xs transition-all active:scale-95 shadow-lg mt-4">
                Register Identity
            </button>
            
            <p class="text-center text-[10px] text-slate-500 font-medium italic">
                Authenticated? <a href="login.php" class="text-blue-400 font-bold not-italic ml-1">Login</a>
            </p>
        </form>
    </div>

    <script>
        // UX: Prevent spaces in real-time
        document.getElementById('username').addEventListener('keydown', function(e) {
            if (e.which === 32) e.preventDefault();
        });
        document.getElementById('username').addEventListener('input', function() {
            this.value = this.value.replace(/\s/g, '');
        });

        let typingTimer;
        const doneTypingInterval = 600;

        function checkIdentity(type) {
            clearTimeout(typingTimer);
            const value = document.getElementById(type).value;
            const statusEl = document.getElementById(type + '-status');

            if (value.length < 3) {
                statusEl.innerText = '';
                return;
            }

            statusEl.innerText = 'Checking...';
            statusEl.className = 'text-[8px] font-bold uppercase text-slate-600';

            typingTimer = setTimeout(async () => {
                try {
                    const response = await fetch(`check_identity.php?${type}=${encodeURIComponent(value)}`);
                    const data = await response.json();
                    
                    if (data.available) {
                        statusEl.innerText = 'Available';
                        statusEl.className = 'text-[8px] font-bold uppercase text-emerald-500';
                    } else {
                        statusEl.innerText = 'Claimed';
                        statusEl.className = 'text-[8px] font-bold uppercase text-red-500';
                    }
                } catch (e) {
                    statusEl.innerText = 'Error';
                }
            }, doneTypingInterval);
        }
    </script>
</body>
</html>