<?php
require_once 'db.php';

function ratifyStatement($statement_id, $pdo) {
    try {
        $pdo->beginTransaction();

        // 1. Determine the Winning Category and Vote Distribution
        // We get the counts for all categories to check if it was a "Contested" win
        $voteQuery = "SELECT vote_type, COUNT(*) as count 
                      FROM votes WHERE statement_id = :sid::uuid 
                      GROUP BY vote_type ORDER BY count DESC";
        $stmt = $pdo->prepare($voteQuery);
        $stmt->execute([':sid' => $statement_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            $pdo->rollBack();
            return false;
        }

        $winner = $results[0];
        $final_category = $winner['vote_type'];
        $winning_votes = $winner['count'];

        // 2. Determine if the Statement is "Contested"
        // Logic: If the winner has less than 55% of the total votes (55/100), 
        // it is considered a 'Contested Truth'.
        $is_contested = ($winning_votes < 55) ? 'TRUE' : 'FALSE';

        // 3. Update the Statement Table
        // Set status to RATIFIED, archive the winner, and mark contested status
        $updateStmt = $pdo->prepare("
            UPDATE statements 
            SET status = 'RATIFIED'::text::status_type, 
                ratified_category = :winner::category_type,
                is_contested = :contested
            WHERE id = :sid::uuid
        ");
        $updateStmt->execute([
            ':winner'    => $final_category,
            ':contested' => $is_contested,
            ':sid'       => $statement_id
        ]);

        // 4. Update Voter Accuracy Scores
        $votersQuery = "SELECT user_id, vote_type FROM votes WHERE statement_id = :sid::uuid";
        $votersStmt = $pdo->prepare($votersQuery);
        $votersStmt->execute([':sid' => $statement_id]);
        $voters = $votersStmt->fetchAll();

        foreach ($voters as $voter) {
            $uid = $voter['user_id'];
            
            // Mark the individual vote as correct or incorrect
            $is_correct = ($voter['vote_type'] === $final_category);
            $markVote = $pdo->prepare("
                UPDATE votes 
                SET is_correct = :correct 
                WHERE user_id = :uid::uuid AND statement_id = :sid::uuid
            ");
            $markVote->execute([
                ':correct' => $is_correct ? 'TRUE' : 'FALSE', 
                ':uid' => $uid, 
                ':sid' => $statement_id
            ]);

            // Calculate updated accuracy for this specific user
            $accStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) FILTER (WHERE is_correct = TRUE) as correct,
                    COUNT(*) as total
                FROM votes WHERE user_id = :uid::uuid
            ");
            $accStmt->execute([':uid' => $uid]);
            $stats = $accStmt->fetch();

            $new_score = ($stats['total'] > 0) ? round(($stats['correct'] / $stats['total']) * 100) : 50;

            // Determine Rank based on new accuracy score
            $rank = 'Initiate';
            if ($new_score >= 90) $rank = 'Oracle';
            elseif ($new_score >= 75) $rank = 'Acolyte';
            elseif ($new_score >= 60) $rank = 'Disciple';

            $updateUser = $pdo->prepare("
                UPDATE users 
                SET accuracy_score = :score, rank_title = :rank 
                WHERE id = :uid::uuid
            ");
            $updateUser->execute([
                ':score' => $new_score, 
                ':rank'  => $rank, 
                ':uid'   => $uid
            ]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Ratification Engine Error: " . $e->getMessage());
        return false;
    }
}