<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$year    = intval($_GET['year'] ?? date('Y'));

// Counts by media type
$stmt = $pdo->prepare("
    SELECT
        media_type,
        COUNT(*) AS total,
        SUM(rating IS NOT NULL) AS rated_count,
        ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) AS avg_rating,
        SUM(COALESCE(runtime, 0)) AS watch_time_minutes
    FROM user_media
    WHERE user_id = ?
      AND status = 'watched'
      AND YEAR(updated_at) = ?
    GROUP BY media_type
");
$stmt->execute([$user_id, $year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byType = [];
foreach ($rows as $row) {
    $byType[$row['media_type']] = [
        'total'              => (int)$row['total'],
        'rated_count'        => (int)$row['rated_count'],
        'avg_rating'         => $row['avg_rating'],
        'watch_time_minutes' => (int)$row['watch_time_minutes'],
    ];
}

$empty = ['total' => 0, 'rated_count' => 0, 'avg_rating' => null, 'watch_time_minutes' => 0];
$movie = $byType['movie'] ?? $empty;
$tv    = $byType['tv']    ?? $empty;

// Monthly breakdown
$stmt2 = $pdo->prepare("
    SELECT MONTH(updated_at) AS month, media_type, COUNT(*) AS count
    FROM user_media
    WHERE user_id = ? AND status = 'watched' AND YEAR(updated_at) = ?
    GROUP BY MONTH(updated_at), media_type
    ORDER BY month
");
$stmt2->execute([$user_id, $year]);
$monthRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$monthly = array_fill(1, 12, ['movie' => 0, 'tv' => 0]);
foreach ($monthRows as $row) {
    $monthly[(int)$row['month']][$row['media_type']] = (int)$row['count'];
}

// Items list (title + poster from stored columns)
$stmt3 = $pdo->prepare("
    SELECT tmdb_id, media_type, title, poster_path, rating, updated_at
    FROM user_media
    WHERE user_id = ? AND status = 'watched' AND YEAR(updated_at) = ?
    ORDER BY updated_at DESC
");
$stmt3->execute([$user_id, $year]);
$items = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'year'    => $year,
    'movie'   => $movie,
    'tv'      => $tv,
    'total'   => $movie['total'] + $tv['total'],
    'monthly' => array_values($monthly),
    'items'   => $items,
]);
