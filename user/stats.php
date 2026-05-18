<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Per-type stats
$stmt = $pdo->prepare('
    SELECT
        media_type,
        COUNT(*)                                             AS total,
        SUM(status = "watched")                             AS watched,
        SUM(status = "watching")                            AS watching,
        SUM(status = "wishlist")                            AS wishlist,
        SUM(status = "dropped")                             AS dropped,
        ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) AS avg_rating,
        SUM(rating IS NOT NULL)                             AS rated_count,
        SUM(CASE WHEN status IN ("watched","watching") AND runtime IS NOT NULL THEN runtime ELSE 0 END) AS watch_time_minutes
    FROM user_media
    WHERE user_id = ?
    GROUP BY media_type
');
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byType = [];
foreach ($rows as $row) {
    $byType[$row['media_type']] = [
        'total'               => (int)$row['total'],
        'watched'             => (int)$row['watched'],
        'watching'            => (int)$row['watching'],
        'wishlist'            => (int)$row['wishlist'],
        'dropped'             => (int)$row['dropped'],
        'avg_rating'          => $row['avg_rating'],
        'rated_count'         => (int)$row['rated_count'],
        'watch_time_minutes'  => (int)$row['watch_time_minutes'],
    ];
}

$empty = ['total'=>0,'watched'=>0,'watching'=>0,'wishlist'=>0,'dropped'=>0,'avg_rating'=>null,'rated_count'=>0,'watch_time_minutes'=>0];
$movie = $byType['movie'] ?? $empty;
$tv    = $byType['tv']    ?? $empty;

// Combined avg
$stmt2 = $pdo->prepare('SELECT ROUND(AVG(rating), 1) FROM user_media WHERE user_id = ? AND rating IS NOT NULL');
$stmt2->execute([$user_id]);
$combinedAvg = $stmt2->fetchColumn();

$combined = [
    'total'              => $movie['total']              + $tv['total'],
    'watched'            => $movie['watched']            + $tv['watched'],
    'watching'           => $movie['watching']           + $tv['watching'],
    'wishlist'           => $movie['wishlist']           + $tv['wishlist'],
    'dropped'            => $movie['dropped']            + $tv['dropped'],
    'avg_rating'         => $combinedAvg ?: null,
    'rated_count'        => $movie['rated_count']        + $tv['rated_count'],
    'watch_time_minutes' => $movie['watch_time_minutes'] + $tv['watch_time_minutes'],
];

echo json_encode([
    'stats' => $combined,
    'movie' => $movie,
    'tv'    => $tv,
]);
