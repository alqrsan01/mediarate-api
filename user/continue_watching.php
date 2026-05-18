<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        um.tmdb_id,
        um.title,
        um.poster_path,
        um.updated_at,
        um.season_counts,
        COUNT(CASE WHEN uer.watched = 1 THEN 1 END)   AS watched_episodes,
        MAX(uer.updated_at)                            AS last_activity,
        (
            SELECT season_number FROM user_episode_ratings
            WHERE user_id = ? AND show_id = um.tmdb_id AND watched = 1
            ORDER BY season_number DESC, episode_number DESC LIMIT 1
        ) AS last_season,
        (
            SELECT episode_number FROM user_episode_ratings
            WHERE user_id = ? AND show_id = um.tmdb_id AND watched = 1
            ORDER BY season_number DESC, episode_number DESC LIMIT 1
        ) AS last_episode
    FROM user_media um
    LEFT JOIN user_episode_ratings uer
        ON uer.user_id = um.user_id
        AND uer.show_id = um.tmdb_id
    WHERE um.user_id = ?
      AND um.media_type = 'tv'
      AND um.status = 'watching'
    GROUP BY um.tmdb_id, um.title, um.poster_path, um.updated_at, um.season_counts
    ORDER BY COALESCE(MAX(uer.updated_at), um.updated_at) DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = array_map(function($row) {
    $lastSeason   = $row['last_season']  ? (int)$row['last_season']  : null;
    $lastEpisode  = $row['last_episode'] ? (int)$row['last_episode'] : null;
    $seasonCounts = $row['season_counts'] ? json_decode($row['season_counts'], true) : null;

    $nextSeason  = $lastSeason  !== null ? $lastSeason : 1;
    $nextEpisode = $lastEpisode !== null ? $lastEpisode + 1 : 1;

    // If we know season lengths, check if the last watched episode was the season finale
    if ($lastSeason !== null && $lastEpisode !== null && $seasonCounts !== null) {
        $idx = $lastSeason - 1;
        if (isset($seasonCounts[$idx]) && $lastEpisode >= $seasonCounts[$idx]) {
            $nextSeason  = $lastSeason + 1;
            $nextEpisode = 1;
        }
    }

    return [
        'tmdb_id'          => (int)$row['tmdb_id'],
        'title'            => $row['title'],
        'poster_path'      => $row['poster_path'],
        'watched_episodes' => (int)$row['watched_episodes'],
        'last_season'      => $lastSeason,
        'last_episode'     => $lastEpisode,
        'next_season'      => $nextSeason,
        'next_episode'     => $nextEpisode,
        'last_activity'    => $row['last_activity'] ?? $row['updated_at'],
    ];
}, $rows);

echo json_encode(['items' => $items]);
