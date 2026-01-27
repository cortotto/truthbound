<?php
session_start();
require_once 'db.php';
require_once 'garbage_collector.php'; // Keep your TTL cleaner running

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 1. Handle Sorting Logic
$sort = $_GET['sort'] ?? 'newest';
switch ($sort) {
    case 'oldest': $order_sql = "s.created_at ASC"; break;
    case 'hot': $order_sql = "s.vote_count DESC, s.created_at DESC"; break;
    case 'cold': $order_sql = "s.vote_count ASC, s.created_at DESC"; break;
    default: $order_sql = "s.created_at DESC"; break;
}

// 2. Fetch User Stats for HUD
$user_stmt = $pdo->prepare("SELECT accuracy_score, rank_title FROM users WHERE id = :uid::uuid");
$user_stmt->execute([':uid' => $user_id]);
$userData = $user_stmt->fetch();
$user_accuracy = $userData['accuracy_score'] ?? 0;
$user_rank = $userData['rank_title'] ?? 'CHAMBERLAIN';

// 3. Fetch latest ratified statement for Sidebar
$latest_stmt = $pdo->query("SELECT content FROM statements WHERE status = 'RATIFIED' ORDER BY created_at DESC LIMIT 1");
$latest_truth = $latest_stmt->fetchColumn();

// 4. Fetch Active statements (Threshold is 3)
$query = "SELECT s.* FROM statements s WHERE s.status = 'ACTIVE' AND s.vote_count < 3 ORDER BY $order_sql";
$feed_stmt = $pdo->prepare($query);
$feed_stmt->execute();
$feed = $feed_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Chamber | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #020617; color: #f1f5f9; font-family: sans-serif; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(51, 65, 85, 0.5); transition: all 0.5s ease; }
        #side-panel { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .panel-closed { transform: translateX(-100%); }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .voted-card { opacity: 0.4; pointer-events: none; filter: grayscale(0.5); }
        .progress-aqua { background-color: #00ffff; box-shadow: 0 0 10px #00ffff, 0 0 20px #00ffff; }
        .modal-blur { backdrop-filter: blur(20px); }
        
        @keyframes vanish {
            0% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(0.9) translateY(-50px); }
        }
        .animate-vanish { animation: vanish 0.8s forwards ease-in-out; }
    </style>
</head>
<body class="overflow-x-hidden hide-scrollbar pb-40">

    <nav class="sticky top-0 z-50 w-full glass border-b border-slate-800 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidePanel()" class="p-2 hover:bg-slate-800 rounded-lg transition-colors">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                </button>
                <span class="text-xs font-black uppercase tracking-[0.4em] text-white">TruthBound</span>
            </div>
            <span onclick="openProfile()" class="text-[10px] font-bold text-blue-500 tracking-widest cursor-pointer hover:text-white transition-all uppercase"><?= htmlspecialchars($username) ?></span>
        </div>
    </nav>

    <aside id="side-panel" class="fixed top-0 left-0 h-full w-80 z-[60] glass border-r border-slate-800 p-6 panel-closed">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-blue-500">Protocol_Menu</h3>
            <button onclick="toggleSidePanel()" class="text-slate-500 hover:text-white transition-colors">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-8">
            <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-center">
                <p class="text-[8px] font-black uppercase text-slate-500 mb-1 tracking-tighter">Accuracy</p>
                <p id="side-accuracy" class="text-xl font-black text-white"><?= $user_accuracy ?></p>
            </div>
            <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-center">
                <p class="text-[8px] font-black uppercase text-slate-500 mb-1 tracking-tighter">Status</p>
                <p class="text-[10px] font-black text-blue-400 uppercase mt-1"><?= $user_rank ?></p>
            </div>
        </div>
        <nav class="space-y-3">
            <a href="theChamber.php" class="block p-4 bg-blue-600/10 border border-blue-500/20 rounded-2xl text-[10px] font-black uppercase text-blue-400">The Chamber</a>
            <a href="leaderboards.php" class="block p-4 border border-slate-800 rounded-2xl text-[10px] font-black uppercase text-slate-400 hover:text-white transition-all">The Archives</a>
        </nav>
        <div class="absolute bottom-10 left-6 right-6">
            <a href="logout.php" class="block text-center p-4 border border-red-500/20 text-red-500 text-[10px] font-black uppercase rounded-2xl hover:bg-red-500/10 transition-all">Terminate Session</a>
        </div>
    </aside>

    <main class="max-w-2xl mx-auto py-12 px-6">
        <div class="flex items-center justify-between mb-10 px-4">
            <h2 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500">Live_Consensus_Feed</h2>
            <select onchange="window.location.href='?sort=' + this.value" class="bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 text-[10px] font-black uppercase tracking-widest text-blue-400 outline-none cursor-pointer">
                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="hot" <?= $sort == 'hot' ? 'selected' : '' ?>>Hot</option>
            </select>
        </div>

        <div id="claims-container" class="space-y-8">
            <?php foreach ($feed as $claim): 
                // Check local vote status
                $votedCheck = $pdo->prepare("SELECT 1 FROM votes WHERE user_id = :uid::uuid AND statement_id = :sid::uuid");
                $votedCheck->execute([':uid' => $user_id, ':sid' => $claim['id']]);
                $hasVoted = $votedCheck->fetch();
                $progress = ($claim['vote_count'] / 3) * 100;
            ?>
                <div id="card-<?= $claim['id'] ?>" class="glass p-8 rounded-[2.5rem] space-y-6 relative overflow-hidden <?= $hasVoted ? 'voted-card' : '' ?>">
                    <div class="absolute top-0 left-0 w-full h-1 bg-slate-800">
                        <div id="bar-<?= $claim['id'] ?>" class="h-full progress-aqua transition-all duration-700" style="width: <?= $progress ?>%"></div>
                    </div>

                    <div class="flex justify-between items-start">
                        <p id="content-<?= $claim['id'] ?>" class="text-lg font-medium italic text-slate-200">"<?= htmlspecialchars($claim['content']) ?>"</p>
                        
                        <div class="flex flex-col items-end ml-4 min-w-fit">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Consensus</span>
                            <span id="count-<?= $claim['id'] ?>" class="text-xs font-mono font-bold text-cyan-400"><?= $claim['vote_count'] ?>/3</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <button onclick="initiateVote('<?= $claim['id'] ?>', 'OBJ')" class="py-4 rounded-2xl bg-slate-950 border border-slate-800 text-blue-400 font-black text-[9px] uppercase hover:border-blue-500 transition-all">Objective</button>
                        <button onclick="initiateVote('<?= $claim['id'] ?>', 'INT')" class="py-4 rounded-2xl bg-slate-950 border border-slate-800 text-purple-400 font-black text-[9px] uppercase hover:border-purple-500 transition-all">Inter-Sub</button>
                        <button onclick="initiateVote('<?= $claim['id'] ?>', 'SUB')" class="py-4 rounded-2xl bg-slate-950 border border-slate-800 text-amber-400 font-black text-[9px] uppercase hover:border-amber-500 transition-all">Subjective</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 w-auto min-w-[320px] z-40">
        <div class="glass rounded-full p-2 flex items-center justify-between shadow-2xl border-slate-700/50">
            <div class="flex items-center gap-6 ml-6 mr-8">
                <div class="flex flex-col">
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-tighter">Accuracy</span>
                    <span id="hud-accuracy" class="text-xs font-mono font-bold text-blue-400"><?= $user_accuracy ?></span>
                </div>
                <div class="flex flex-col border-l border-slate-800 pl-6">
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-tighter">Rank</span>
                    <span class="text-[10px] font-bold text-white uppercase tracking-widest"><?= htmlspecialchars($user_rank) ?></span>
                </div>
            </div>
            <button onclick="toggleCreateModal()" class="bg-white text-slate-950 h-12 w-12 rounded-full font-black hover:bg-blue-600 hover:text-white transition-all flex items-center justify-center group shadow-lg">
                <svg class="w-6 h-6 transform group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6v12m6-6H6" stroke-width="3" stroke-linecap="round"/></svg>
            </button>
        </div>
    </div>

    <div id="vote-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-6 bg-slate-950/90 modal-blur">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-[2.5rem] p-10 shadow-2xl relative">
            <div class="mb-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-4">Confirm_Protocol</h3>
                <p id="modal-claim-text" class="text-lg font-medium italic text-slate-200 mb-6">"..."</p>
                <div id="modal-category-badge" class="inline-block px-3 py-1 rounded-md border text-[9px] font-black uppercase tracking-widest mb-4">CATEGORY</div>
                <p id="modal-desc" class="text-xs text-slate-400 leading-relaxed">Explanation text goes here.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <button onclick="closeVoteModal()" class="py-3 rounded-xl border border-slate-700 text-slate-400 font-bold text-xs uppercase hover:bg-slate-800">Cancel</button>
                <button id="confirm-vote-btn" class="py-3 rounded-xl bg-white text-slate-950 font-black text-xs uppercase hover:bg-blue-500 hover:text-white transition-colors">Confirm Vote</button>
            </div>
        </div>
    </div>

    <div id="create-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-6 bg-slate-950/90 modal-blur">
        <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-[2.5rem] p-10 shadow-2xl relative">
            <button onclick="toggleCreateModal()" class="absolute top-8 right-8 text-slate-500 hover:text-white">✕</button>
            <form action="post_statement.php" method="POST" class="space-y-8">
                <h2 class="text-2xl font-black uppercase tracking-tight">Propose_Claim</h2>
                <textarea name="content" required maxlength="140" class="w-full bg-slate-950 border border-slate-800 rounded-2xl p-6 text-lg focus:ring-2 focus:ring-blue-500 outline-none text-white" placeholder="What is the truth?"></textarea>
                <div class="grid grid-cols-3 gap-3">
                    <label class="cursor-pointer"><input type="radio" name="intended_category" value="OBJ" class="peer hidden" required><div class="p-3 text-center rounded-xl border border-slate-800 bg-slate-950 text-slate-600 peer-checked:text-blue-400 peer-checked:border-blue-500 text-[10px] font-black">OBJ</div></label>
                    <label class="cursor-pointer"><input type="radio" name="intended_category" value="INT" class="peer hidden"><div class="p-3 text-center rounded-xl border border-slate-800 bg-slate-950 text-slate-600 peer-checked:text-purple-400 peer-checked:border-purple-500 text-[10px] font-black">INT</div></label>
                    <label class="cursor-pointer"><input type="radio" name="intended_category" value="SUB" class="peer hidden"><div class="p-3 text-center rounded-xl border border-slate-800 bg-slate-950 text-slate-600 peer-checked:text-amber-400 peer-checked:border-amber-500 text-[10px] font-black">SUB</div></label>
                </div>
                <button type="submit" class="w-full py-4 bg-white text-slate-950 font-black rounded-2xl uppercase text-[10px] tracking-widest hover:bg-blue-500 hover:text-white transition-all">Submit to Chamber</button>
            </form>
        </div>
    </div>

    <div id="profile-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-slate-950/98 modal-blur">
        <div class="max-w-4xl w-full p-8 relative">
            <button onclick="closeProfile()" class="absolute top-0 right-0 p-12 text-slate-500 hover:text-white text-3xl transition-colors">✕</button>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                <div class="space-y-6">
                    <div>
                        <h2 id="prof-username" class="text-6xl font-black tracking-tighter uppercase text-white leading-none">---</h2>
                        <p id="prof-rank" class="text-sm font-black uppercase tracking-[0.5em] text-blue-500 mt-4">---</p>
                    </div>
                    <div class="pt-8 border-t border-slate-800">
                        <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Global Calibration</span>
                        <div class="text-7xl font-black mt-2 text-white"><span id="prof-accuracy">0</span><span class="text-blue-500">pts</span></div>
                    </div>
                </div>
                <div class="glass p-10 rounded-[3.5rem] aspect-square flex flex-col items-center justify-center">
                    <h4 class="text-[10px] font-black uppercase text-slate-500 mb-6 tracking-widest">Consensus Distribution</h4>
                    <div class="w-full h-full relative">
                        <canvas id="truthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let truthChart = null;

        // --- Panel & Modal Toggles ---
        function toggleSidePanel() { document.getElementById('side-panel').classList.toggle('panel-closed'); }
        function toggleCreateModal() { document.getElementById('create-modal').classList.toggle('hidden'); }
        function closeProfile() { document.getElementById('profile-modal').classList.add('hidden'); }
        function closeVoteModal() { document.getElementById('vote-modal').classList.add('hidden'); }

        async function openProfile() {
            try {
                const resp = await fetch('get_profile.php');
                const data = await resp.json();
                document.getElementById('prof-username').innerText = data.user.username; 
                document.getElementById('prof-rank').innerText = data.user.rank_title;
                document.getElementById('prof-accuracy').innerText = data.user.accuracy_score;
                document.getElementById('profile-modal').classList.remove('hidden');
                renderChart(data.chart);
            } catch (err) { console.error(err); }
        }

        function renderChart(chartData) {
            const ctx = document.getElementById('truthChart').getContext('2d');
            if(truthChart) truthChart.destroy();
            truthChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['OBJ', 'INT', 'SUB', 'CNT'],
                    datasets: [{ 
                        data: chartData, 
                        backgroundColor: ['#3b82f6', '#a855f7', '#f59e0b', '#ef4444'], 
                        borderWidth: 0 
                    }]
                },
                options: { cutout: '70%', plugins: { legend: { position: 'bottom', labels: { color: '#64748b', font: { weight: '900', size: 10 }, padding: 20 } } } }
            });
        }

        // --- Vote Confirmation Logic ---
        let pendingVote = { id: null, type: null };

        function initiateVote(sId, type) {
            // 1. Get content to display in confirmation
            const contentText = document.getElementById(`content-${sId}`).innerText;
            
            // 2. Set descriptions based on type
            const meta = {
                'OBJ': { label: 'Objective Truth', color: 'text-blue-400 border-blue-500', desc: 'Verified by hard data or physical reality. Independent of observation.' },
                'INT': { label: 'Inter-Subjective', color: 'text-purple-400 border-purple-500', desc: 'True because we collectively agree it is true (e.g., Money, Borders, Language).' },
                'SUB': { label: 'Subjective', color: 'text-amber-400 border-amber-500', desc: 'Dependent on personal experience or feeling. True for the observer.' }
            }[type];

            // 3. Update Modal UI
            document.getElementById('modal-claim-text').innerText = contentText;
            const badge = document.getElementById('modal-category-badge');
            badge.innerText = meta.label;
            badge.className = `inline-block px-3 py-1 rounded-md border text-[9px] font-black uppercase tracking-widest mb-4 ${meta.color}`;
            document.getElementById('modal-desc').innerText = meta.desc;

            // 4. Store state and show
            pendingVote = { id: sId, type: type };
            document.getElementById('vote-modal').classList.remove('hidden');
        }

        // Bind confirm button
        document.getElementById('confirm-vote-btn').addEventListener('click', async () => {
            if (pendingVote.id && pendingVote.type) {
                await castVote(pendingVote.id, pendingVote.type);
                closeVoteModal();
            }
        });

        // --- AJAX Voting Logic ---
        async function castVote(sId, type) {
            try {
                const response = await fetch('cast_vote.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `statement_id=${sId}&vote_type=${type}`
                });
                
                const result = await response.json();

                if (result.success) {
                    const bar = document.getElementById(`bar-${sId}`);
                    const countText = document.getElementById(`count-${sId}`);
                    const card = document.getElementById(`card-${sId}`);

                    // 1. Calculate new values
                    let currentCount = parseInt(countText.innerText.split('/')[0]);
                    let newCount = currentCount + 1;
                    
                    // 2. Update DOM elements immediately
                    countText.innerText = `${newCount}/3`;
                    bar.style.width = `${(newCount / 3) * 100}%`;
                    
                    // 3. Mark card as voted (gray out)
                    card.classList.add('voted-card');

                    // 4. If Ratified (Hit 3), trigger Vanish Animation
                    if (newCount >= 3) {
                        // Change text to show status change
                        countText.innerText = "RATIFIED";
                        countText.classList.add('text-green-400'); // Optional: change color to green
                        
                        setTimeout(() => {
                            // Add the animation class defined in CSS
                            card.classList.add('animate-vanish');
                            
                            // Remove from DOM after animation completes (0.8s)
                            setTimeout(() => {
                                card.remove();
                                // Optional: Check if feed is empty and show "All Clear" message
                                const container = document.getElementById('claims-container');
                                if (container.children.length === 0) {
                                    container.innerHTML = '<div class="glass p-10 text-center"><p class="text-slate-500 uppercase tracking-widest text-xs">All claims ratified.</p></div>';
                                }
                            }, 800);
                        }, 1000); // 1-second pause to admire the full bar
                    }
                } else {
                    alert(result.error);
                }
            } catch (e) {
                console.error("AJAX Error:", e);
            }
        }
    </script>
</body>
</html>