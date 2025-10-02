<?php
require_once 'config.php';
require_once 'includes/db_connect.php';

echo "<h3>Rating System Diagnostic</h3>";

try {
    // Check if triggers exist
    echo "<h4>1. Checking Triggers:</h4>";
    $stmt = $db->query("SHOW TRIGGERS WHERE `Trigger` LIKE '%review%'");
    $triggers = $stmt->fetchAll();

    if (!empty($triggers)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Trigger</th><th>Event</th><th>Table</th><th>Timing</th></tr>";
        foreach ($triggers as $trigger) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($trigger['Trigger']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Event']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Table']) . "</td>";
            echo "<td>" . htmlspecialchars($trigger['Timing']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No review triggers found! You need to run database/add_ratings_system.sql</p>";
    }

    // Check reviews table
    echo "<h4>2. Reviews Summary:</h4>";
    $stmt = $db->query("
        SELECT
            doctor_id,
            COUNT(*) as review_count,
            AVG(rating) as avg_rating,
            MIN(rating) as min_rating,
            MAX(rating) as max_rating
        FROM reviews
        GROUP BY doctor_id
    ");
    $review_stats = $stmt->fetchAll();

    if (!empty($review_stats)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Doctor ID</th><th>Reviews</th><th>Avg Rating</th><th>Min</th><th>Max</th></tr>";
        foreach ($review_stats as $stat) {
            echo "<tr>";
            echo "<td>" . $stat['doctor_id'] . "</td>";
            echo "<td>" . $stat['review_count'] . "</td>";
            echo "<td><strong>" . number_format($stat['avg_rating'], 2) . "</strong></td>";
            echo "<td>" . $stat['min_rating'] . "</td>";
            echo "<td>" . $stat['max_rating'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No reviews found in database.</p>";
    }

    // Check doctor_profiles table
    echo "<h4>3. Doctor Profiles Rating Status:</h4>";
    $stmt = $db->query("
        SELECT
            dp.user_id,
            u.first_name,
            u.last_name,
            dp.rating as profile_rating,
            dp.total_reviews as profile_total_reviews,
            (SELECT COUNT(*) FROM reviews WHERE doctor_id = dp.user_id) as actual_review_count,
            (SELECT AVG(rating) FROM reviews WHERE doctor_id = dp.user_id) as actual_avg_rating
        FROM doctor_profiles dp
        JOIN users u ON dp.user_id = u.id
        ORDER BY dp.user_id
    ");
    $doctors = $stmt->fetchAll();

    if (!empty($doctors)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Doctor</th><th>Profile Rating</th><th>Profile Total</th><th>Actual Avg</th><th>Actual Count</th><th>Status</th></tr>";
        foreach ($doctors as $doc) {
            $mismatch = false;
            if ($doc['profile_total_reviews'] != $doc['actual_review_count']) {
                $mismatch = true;
            }
            if (abs($doc['profile_rating'] - ($doc['actual_avg_rating'] ?? 0)) > 0.01) {
                $mismatch = true;
            }

            echo "<tr" . ($mismatch ? " style='background-color: #ffcccc;'" : "") . ">";
            echo "<td>Dr. " . htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) . "</td>";
            echo "<td>" . number_format($doc['profile_rating'], 2) . "</td>";
            echo "<td>" . $doc['profile_total_reviews'] . "</td>";
            echo "<td>" . ($doc['actual_avg_rating'] ? number_format($doc['actual_avg_rating'], 2) : '0.00') . "</td>";
            echo "<td>" . $doc['actual_review_count'] . "</td>";
            echo "<td>" . ($mismatch ? '<strong style="color: red;">❌ MISMATCH</strong>' : '<span style="color: green;">✓ OK</span>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<p><small>Note: Red rows indicate mismatch between profile ratings and actual reviews.</small></p>";
    } else {
        echo "<p>No doctors found.</p>";
    }

    // Fix button
    echo "<h4>4. Fix Ratings:</h4>";
    if (isset($_GET['fix'])) {
        echo "<p style='color: blue;'>Running fix...</p>";
        $stmt = $db->query("
            UPDATE doctor_profiles dp
            SET
                rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE doctor_id = dp.user_id), 0.00),
                total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = dp.user_id)
        ");
        echo "<p style='color: green;'><strong>✓ Ratings recalculated successfully!</strong></p>";
        echo "<p><a href='check_ratings.php'>Refresh to see updated values</a></p>";
    } else {
        echo "<p><a href='check_ratings.php?fix=1' class='button' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Click here to recalculate all ratings</a></p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
