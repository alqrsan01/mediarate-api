<?php

require_once '../config.php';
require_once '../auth/jwt.php';

if (get_token_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = get_token_user_id();

// Per-type stats
$stmt = $pdo->prepare('
    SELECT
        media_type,
        COUNT(*) AS total,
        SUM(CASE WHEN status = \'watched\'  THEN 1 ELSE 0 END) AS watched,
        SUM(CASE WHEN status = \'watching\' THEN 1 ELSE 0 END) AS watching,
        SUM(CASE WHEN status = \'wishlist\' THEN 1 ELSE 0 END) AS wishlist,
        SUM(CASE WHEN status = \'dropped\'  THEN 1 ELSE 0 END) AS dropped,
        ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) AS avg_rating,
        COUNT(rating) AS rated_count,
        SUM(CASE WHEN status IN (\'watched\',\'watching\') AND runtime IS NOT NULL THEN runtime ELSE 0 END) AS watch_time_minutes
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

// Ratings distribution (1-10)
$ratingRows = $pdo->prepare('
    SELECT rating, COUNT(*) as cnt
    FROM user_media
    WHERE user_id = ? AND rating IS NOT NULL
    GROUP BY rating ORDER BY rating
');
$ratingRows->execute([$user_id]);
$ratingsAll = array_fill(1, 10, 0);
foreach ($ratingRows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ratingsAll[(int)$r['rating']] = (int)$r['cnt'];
}

$ratingMovie = $pdo->prepare('
    SELECT rating, COUNT(*) as cnt
    FROM user_media
    WHERE user_id = ? AND media_type = \'movie\' AND rating IS NOT NULL
    GROUP BY rating ORDER BY rating
');
$ratingMovie->execute([$user_id]);
$ratingsMovie = array_fill(1, 10, 0);
foreach ($ratingMovie->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ratingsMovie[(int)$r['rating']] = (int)$r['cnt'];
}

$ratingTV = $pdo->prepare('
    SELECT rating, COUNT(*) as cnt
    FROM user_media
    WHERE user_id = ? AND media_type = \'tv\' AND rating IS NOT NULL
    GROUP BY rating ORDER BY rating
');
$ratingTV->execute([$user_id]);
$ratingsTV = array_fill(1, 10, 0);
foreach ($ratingTV->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ratingsTV[(int)$r['rating']] = (int)$r['cnt'];
}

// Genre breakdown
$genreRows = $pdo->prepare('SELECT genres, media_type FROM user_media WHERE user_id = ? AND genres IS NOT NULL AND genres != \'\'');
$genreRows->execute([$user_id]);
$allCounts   = [];
$movieCounts = [];
$tvCounts    = [];
foreach ($genreRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $names = explode(',', $row['genres']);
    foreach ($names as $g) {
        $g = trim($g);
        if (!$g) continue;
        $allCounts[$g]   = ($allCounts[$g] ?? 0) + 1;
        if ($row['media_type'] === 'movie') $movieCounts[$g] = ($movieCounts[$g] ?? 0) + 1;
        else                                $tvCounts[$g]    = ($tvCounts[$g]    ?? 0) + 1;
    }
}
arsort($allCounts);   $genresAll   = array_slice($allCounts,   0, 10, true);
arsort($movieCounts); $genresMovie = array_slice($movieCounts, 0, 10, true);
arsort($tvCounts);    $genresTV    = array_slice($tvCounts,    0, 10, true);

// Decade breakdown
$decadeRows = $pdo->prepare('
    SELECT FLOOR(release_year / 10) * 10 AS decade, media_type, COUNT(*) as cnt
    FROM user_media
    WHERE user_id = ? AND release_year IS NOT NULL AND status IN (\'watched\',\'watching\')
    GROUP BY decade, media_type
    ORDER BY decade
');
$decadeRows->execute([$user_id]);
$dAll = []; $dMovie = []; $dTV = [];
foreach ($decadeRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $d = (int)$row['decade'];
    $dAll[$d]   = ($dAll[$d] ?? 0) + (int)$row['cnt'];
    if ($row['media_type'] === 'movie') $dMovie[$d] = ($dMovie[$d] ?? 0) + (int)$row['cnt'];
    else                                $dTV[$d]    = ($dTV[$d]    ?? 0) + (int)$row['cnt'];
}
ksort($dAll); ksort($dMovie); ksort($dTV);

echo json_encode([
    'stats'   => $combined,
    'movie'   => $movie,
    'tv'      => $tv,
    'ratings' => ['all' => $ratingsAll, 'movie' => $ratingsMovie, 'tv' => $ratingsTV],
    'genres'  => ['all' => $genresAll,  'movie' => $genresMovie,  'tv' => $genresTV],
    'decades' => ['all' => $dAll,       'movie' => $dMovie,       'tv' => $dTV],
]);
