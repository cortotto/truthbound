<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- 1. Filter & Sort Logic ---

// Get parameters with defaults
$active_tab  = $_GET['tab'] ?? 'OBJ';
$period      = $_GET['period'] ?? 'year';
$social_sort = $_GET['social_sort'] ?? 'newest';

// A. Time Filter Logic
switch ($period) {
    case 'week':  $interval_sql = "AND s.created_at >= NOW() - INTERVAL '1 week'"; break;
    case 'month': $interval_sql = "AND s.created_at >= NOW() - INTERVAL '1 month'"; break;
    case 'year':  $interval_sql = "AND s.created_at >= NOW() - INTERVAL '1 year'"; break;
    case 'all':   $interval_sql = ""; break; // Fallback just in case
    default:      $interval_sql = "AND s.created_at >= NOW() - INTERVAL '1 year'"; break;
}

// B. Social Sort Logic
switch ($social_sort) {
    case 'most_liked':      $order_sql = "l_count DESC, s.created_at DESC"; break;
    case 'least_liked':     $order_sql = "l_count ASC, s.created_at DESC"; break;
    case 'most_discussed':  $order_sql = "c_count DESC, s.created_at DESC"; break;
    case 'newest': default: $order_sql = "s.created_at DESC"; break;
}

// --- 2. Fetch User Stats (Sidebar) ---
$user_stmt = $pdo->prepare("SELECT accuracy_score, rank_title FROM users WHERE id = :uid::uuid");
$user_stmt->execute([':uid' => $user_id]);
$userData = $user_stmt->fetch();
$user_accuracy = ($userData['accuracy_score'] ?? 0);
$user_rank = $userData['rank_title'] ?? 'CHAMBERLAIN';

// --- 3. Archive Query ---
// We fetch counts for likes (l_count) and comments (c_count) to enable sorting
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM comments WHERE statement_id = s.id) as c_count,
          (SELECT COUNT(*) FROM statement_likes WHERE statement_id = s.id) as l_count,
          (SELECT 1 FROM statement_likes WHERE statement_id = s.id AND user_id = :uid::uuid LIMIT 1) as user_liked
          FROM statements s 
          WHERE s.status IN ('RATIFIED', 'CONTESTED') 
          AND UPPER(s.final_category::text) = UPPER(:tab) 
          $interval_sql
          ORDER BY $order_sql";

$stmt = $pdo->prepare($query);
$stmt->execute([':tab' => $active_tab, ':uid' => $user_id]);
$archive = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Archives | TruthBound</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #020617; color: #f1f5f9; font-family: sans-serif; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(51, 65, 85, 0.5); }
        #side-panel { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .panel-closed { transform: translateX(-100%); }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .modal-blur { backdrop-filter: blur(20px); }
        select { -webkit-appearance: none; appearance: none; } /* Custom dropdown style */
    </style>
</head>
<body class="overflow-x-hidden hide-scrollbar pb-20">

    <nav class="sticky top-0 z-50 w-full glass border-b border-slate-800 p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidePanel()" class="p-2 hover:bg-slate-800 rounded-lg transition-colors">
                    <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16m-7 6h7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <span class="text-xs font-black uppercase tracking-[0.4em] text-white">TruthBound</span>
            </div>
            <span onclick="openProfile()" class="text-[10px] font-bold text-blue-500 tracking-widest cursor-pointer hover:text-white transition-all uppercase">
                <?= htmlspecialchars($username) ?>
            </span>
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
                <p class="text-xl font-black text-white"><?= $user_accuracy ?></p>
            </div>
            <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl text-center">
                <p class="text-[8px] font-black uppercase text-slate-500 mb-1 tracking-tighter">Status</p>
                <p class="text-[10px] font-black text-blue-400 uppercase mt-1"><?= $user_rank ?></p>
            </div>
        </div>
        <nav class="space-y-3">
            <a href="theChamber.php" class="block p-4 border border-slate-800 rounded-2xl text-[10px] font-black uppercase text-slate-400 hover:text-white transition-all">The Chamber</a>
            <a href="leaderboards.php" class="block p-4 bg-blue-600/10 border border-blue-500/20 rounded-2xl text-[10px] font-black uppercase text-blue-400">The Archives</a>
        </nav>
        <div class="absolute bottom-10 left-6 right-6">
            <a href="logout.php" class="block text-center p-4 border border-red-500/20 text-red-500 text-[10px] font-black uppercase rounded-2xl hover:bg-red-500/10 transition-all">Terminate Session</a>
        </div>
    </aside>

    <div id="side-overlay" onclick="toggleSidePanel()" class="fixed inset-0 bg-black/60 z-[55] hidden backdrop-blur-sm"></div>

    <main class="max-w-5xl mx-auto py-12 px-6">
        
        <div class="mb-8 text-center">
            <div class="flex flex-wrap gap-6 border-b border-slate-800 justify-center mb-8">
                <?php 
                $tabs = [['OBJ', 'Objective', 'border-blue-500'], ['INT', 'Intersubjective', 'border-purple-500'], ['SUB', 'Subjective', 'border-amber-500'], ['CNT', 'Contested', 'border-red-500']];
                foreach ($tabs as $t): 
                    $isActive = ($active_tab == $t[0]);
                ?>
                    <a href="?tab=<?= $t[0] ?>&period=<?= $period ?>&social_sort=<?= $social_sort ?>" class="pb-4 text-[10px] font-black uppercase tracking-widest transition-all <?= $isActive ? 'text-white border-b-2 ' . $t[2] : 'text-slate-500 hover:text-slate-300' ?>"><?= $t[1] ?></a>
                <?php endforeach; ?>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                
                <div class="relative group">
                    <select onchange="updateSort(this.value)" class="bg-slate-900 border border-slate-800 rounded-xl px-5 py-3 text-[9px] font-black uppercase tracking-widest text-blue-400 outline-none cursor-pointer hover:border-blue-500 transition-all appearance-none pr-10">
                        <option value="newest" <?= $social_sort == 'newest' ? 'selected' : '' ?>>Recent Ratifications</option>
                        <option value="most_liked" <?= $social_sort == 'most_liked' ? 'selected' : '' ?>>Most Loved</option>
                        <option value="least_liked" <?= $social_sort == 'least_liked' ? 'selected' : '' ?>>Undiscovered</option>
                        <option value="most_discussed" <?= $social_sort == 'most_discussed' ? 'selected' : '' ?>>Most Discussed</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-blue-400">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>

                <div class="flex bg-slate-900 p-1 rounded-xl border border-slate-800">
                    <?php foreach (['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $val => $label): 
                        $isPeriodActive = ($period == $val);
                    ?>
                        <a href="?tab=<?= $active_tab ?>&period=<?= $val ?>&social_sort=<?= $social_sort ?>" 
                           class="px-6 py-2 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all <?= $isPeriodActive ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/50' : 'text-slate-500 hover:text-slate-200' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (empty($archive)): ?>
                <div class="md:col-span-2 glass p-20 text-center rounded-[3rem]">
                    <p class="text-[10px] font-black uppercase text-slate-600 tracking-widest">No data archived for this category in the selected period.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($archive as $item): 
                $theme_color = match($item['final_category']) { 'OBJ'=>'text-blue-500', 'INT'=>'text-purple-500', 'SUB'=>'text-amber-500', 'CNT'=>'text-red-500', default=>'text-slate-400' };
            ?>
                <div onclick="openDiscussion('<?= $item['id'] ?>', '<?= addslashes($item['content']) ?>')" class="glass p-8 rounded-[2.5rem] border-l-4 <?= str_replace('text', 'border', $theme_color) ?> flex flex-col justify-between hover:scale-[1.01] transition-all group cursor-pointer shadow-lg hover:shadow-2xl">
                    <p class="text-lg font-medium text-slate-200 mb-8 italic leading-relaxed group-hover:text-white transition-colors">"<?= htmlspecialchars($item['content']) ?>"</p>
                    <div class="flex justify-between items-center pt-6 border-t border-slate-800/50">
                        <div class="flex gap-4">
                            <button onclick="event.stopPropagation(); toggleLike('<?= $item['id'] ?>')" class="flex items-center gap-1.5 <?= $theme_color ?> hover:scale-110 transition-transform">
                                <svg class="w-4 h-4 <?= $item['user_liked'] ? 'fill-current' : 'fill-none' ?> stroke-current" viewBox="0 0 24 24" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" /></svg>
                                <span class="text-[10px] font-bold"><?= $item['l_count'] ?></span>
                            </button>
                            <div class="flex items-center gap-1.5 text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                <span class="text-[10px] font-bold"><?= $item['c_count'] ?></span>
                            </div>
                        </div>
                        <span class="text-[9px] font-black text-slate-600 uppercase tracking-tighter"><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="profile-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-slate-950/98 modal-blur">
        <div class="max-w-4xl w-full p-8 relative">
            <button onclick="closeProfile()" class="absolute top-0 right-0 p-8 text-slate-500 hover:text-white">✕</button>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <h2 id="prof-username" class="text-5xl font-black text-white uppercase tracking-tighter">---</h2>
                    <p id="prof-rank" class="text-xs font-black uppercase text-blue-500 tracking-[0.4em]">---</p>
                    <div class="pt-8 border-t border-slate-800">
                        <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Calibration</span>
                        <div class="text-6xl font-black text-white"><span id="prof-accuracy">0</span><span class="text-blue-500">pts</span></div>
                    </div>
                </div>
                <div class="glass p-8 rounded-[3rem]">
                    <canvas id="truthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div id="discussion-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 bg-slate-950/90 modal-blur">
        <div class="max-w-2xl w-full bg-slate-900 border border-slate-800 rounded-[2.5rem] flex flex-col max-h-[80vh]">
            <div class="p-8 border-b border-slate-800 flex justify-between">
                <p id="modal-content" class="text-xl italic font-medium text-slate-200"></p>
                <button onclick="closeDiscussion()" class="text-slate-500">✕</button>
            </div>
            <div id="comments-list" class="flex-1 overflow-y-auto p-8 space-y-4 hide-scrollbar"></div>
            <div class="p-8 border-t border-slate-800">
                <div class="flex gap-4">
                    <input type="text" id="comment-input" placeholder="Protocol input..." class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-6 py-4 text-white outline-none">
                    <button onclick="submitComment()" class="bg-blue-600 px-8 rounded-xl font-black text-[10px] uppercase">Post</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentId = null;
        let truthChart = null;

        function toggleSidePanel() { 
            document.getElementById('side-panel').classList.toggle('panel-closed');
            document.getElementById('side-overlay').classList.toggle('hidden'); 
        }

        // Helper to update URL params for the dropdown
        function updateSort(val) {
            const url = new URL(window.location);
            url.searchParams.set('social_sort', val);
            window.location.href = url.href;
        }

        async function openProfile() {
            const resp = await fetch('get_profile.php');
            const data = await resp.json();
            document.getElementById('prof-username').innerText = data.user.username;
            document.getElementById('prof-rank').innerText = data.user.rank_title;
            document.getElementById('prof-accuracy').innerText = data.user.accuracy_score;
            document.getElementById('profile-modal').classList.remove('hidden');
            renderChart(data.chart);
        }

        function closeProfile() { document.getElementById('profile-modal').classList.add('hidden'); }

        function renderChart(chartData) {
            const ctx = document.getElementById('truthChart').getContext('2d');
            if(truthChart) truthChart.destroy();
            truthChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['OBJ', 'INT', 'SUB', 'CNT'],
                    datasets: [{ data: chartData, backgroundColor: ['#3b82f6', '#a855f7', '#f59e0b', '#ef4444'], borderWidth: 0 }]
                },
                options: { plugins: { legend: { position: 'bottom', labels: { color: '#64748b', font: { weight: '900', size: 9 } } } } }
            });
        }

        async function toggleLike(id) {
            const fd = new FormData(); fd.append('action', 'like'); fd.append('statement_id', id);
            await fetch('handle_social.php', { method: 'POST', body: fd });
            location.reload(); // Refresh to re-sort if we are on 'Most Loved'
        }

        function openDiscussion(id, content) {
            currentId = id;
            document.getElementById('modal-content').innerText = `"${content}"`;
            document.getElementById('discussion-modal').classList.remove('hidden');
            loadComments(id);
        }

        function closeDiscussion() { document.getElementById('discussion-modal').classList.add('hidden'); }

        async function loadComments(id) {
            const resp = await fetch(`get_comments.php?statement_id=${id}`);
            document.getElementById('comments-list').innerHTML = await resp.text();
        }

        async function submitComment() {
            const input = document.getElementById('comment-input');
            if(!input.value.trim()) return;
            const fd = new FormData(); fd.append('action', 'comment'); fd.append('statement_id', currentId); fd.append('text', input.value);
            await fetch('handle_social.php', { method: 'POST', body: fd });
            input.value = ''; loadComments(currentId);
        }
    </script>
</body>
</html>