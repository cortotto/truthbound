<?php
session_start();
require_once 'db.php';

$statement_id = $_GET['statement_id'] ?? '';

if (!$statement_id) exit("<p class='text-slate-500 italic text-center'>No discussion found.</p>");

$query = "SELECT c.*, u.username, u.rank_title 
          FROM comments c 
          JOIN users u ON c.user_id = u.id 
          WHERE c.statement_id = :sid::uuid 
          ORDER BY c.created_at ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([':sid' => $statement_id]);
$comments = $stmt->fetchAll();

if (empty($comments)) {
    echo "<div class='text-center py-8'>
            <p class='text-slate-600 italic text-sm'>Silence in the archives. Be the first to provide insight.</p>
          </div>";
} else {
    foreach ($comments as $c) {
        echo "
        <div class='bg-slate-950/50 border border-slate-800 p-5 rounded-2xl'>
            <div class='flex justify-between items-center mb-2'>
                <span class='text-[10px] font-black text-blue-400 uppercase tracking-widest'>" . htmlspecialchars($c['username']) . "</span>
                <span class='text-[8px] font-bold text-slate-600 uppercase'>" . htmlspecialchars($c['rank_title']) . "</span>
            </div>
            <p class='text-sm text-slate-300 leading-relaxed'>" . htmlspecialchars($c['comment_text']) . "</p>
            <div class='mt-3 text-[8px] text-slate-700 font-bold uppercase'>" . date('M d | H:i', strtotime($c['created_at'])) . "</div>
        </div>";
    }
}