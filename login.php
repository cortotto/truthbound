<?php
session_start();
require_once 'db.php';
$error = '';

// If already logged in, bypass the gate
if (isset($_SESSION['user_id'])) {
    header("Location: theChamber.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 1. VERIFICATION GATEKEEPER
            if (!$user['is_verified']) {
                $_SESSION['temp_email'] = $user['email'];
                header("Location: verify.php?status=pending");
                exit;
            }

            // 2. INITIALIZE SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            header("Location: theChamber.php");
            exit;
        } else {
            $error = "Access Denied: Invalid security cipher or address.";
        }
    } catch (PDOException $e) {
        $error = "System Error: Authentication protocol offline.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticate | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at center, #0f172a 0%, #020617 100%); }
        .input-focus:focus { border-color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.2); }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex items-center justify-center p-6 font-sans">

    <div class="w-full max-w-sm">
        <div class="text-center mb-10">
            <span class="text-[10px] font-black uppercase tracking-[0.6em] text-blue-500">TruthBound Protocol</span>
            <h1 class="text-4xl font-black italic uppercase tracking-tighter mt-2">Authenticate</h1>
        </div>

        <form method="POST" id="loginForm" class="bg-slate-900/50 backdrop-blur-xl border border-slate-800 p-10 rounded-[2.5rem] shadow-2xl space-y-6">
            
            <?php if(isset($_GET['verified'])): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/50 p-4 rounded-xl text-emerald-400 text-[9px] font-bold uppercase text-center tracking-widest">
                    Identity Activated Successfully
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/50 p-4 rounded-xl text-red-400 text-[10px] font-bold uppercase text-center tracking-wider">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div>
                <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 mb-2 block">Electronic Address</label>
                <input type="email" name="email" required 
                    class="w-full bg-slate-950 border border-slate-800 p-4 rounded-2xl outline-none transition-all input-focus text-white" 
                    placeholder="identity@domain.com">
            </div>

            <div>
                <label class="text-[9px] font-black uppercase tracking-widest text-slate-500 ml-2 mb-2 block">Security Cipher</label>
                <input type="password" name="password" required 
                    class="w-full bg-slate-950 border border-slate-800 p-4 rounded-2xl outline-none transition-all input-focus text-white" 
                    placeholder="••••••••">
            </div>

            <button type="submit" id="loginBtn" class="w-full py-5 bg-white text-slate-950 font-black rounded-2xl uppercase tracking-[0.2em] text-xs transition-all flex items-center justify-center gap-3 hover:bg-blue-600 hover:text-white active:scale-95 shadow-lg">
                <span id="btnText">Enter The Chamber</span>
                <div id="loader" class="hidden">
                    <svg class="animate-spin h-5 w-5 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </button>
            
            <div class="pt-6 text-center border-t border-slate-800/50">
                <p class="text-[10px] text-slate-500 font-medium italic">
                    New identity? <a href="register.php" class="text-blue-400 hover:underline not-italic font-bold ml-1">Initialize Protocol</a>
                </p>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const loader = document.getElementById('loader');

            // Prevent multi-submission
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');

            // Trigger Animation
            btnText.innerText = 'Verifying Cipher...';
            loader.classList.remove('hidden');
        });
    </script>
</body>
</html>