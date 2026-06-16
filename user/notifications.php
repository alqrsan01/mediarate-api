<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$user_id = get_token_user_id();
if ($user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Get all TV shows the user is currently watching, with their stored season_counts
$stmt = $pdo->prepare("
    SELECT tmdb_id, title, poster_path, season_counts
    FROM user_media
    WHERE user_id = ? AND media_type = 'tv' AND status = 'watching' AND season_counts IS NOT NULL
    LIMIT 20
");
$stmt->execute([$user_id]);
$shows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notifications = [];

foreach ($shows as $show) {
    $storedCounts = json_decode($show['season_counts'], true);
    if (!is_array($storedCounts) || empty($storedCounts)) continue;
    $storedSeasonCount = count($storedCounts);

    // Fetch current season data from TMDB
    $url = "https://api.themoviedb.org/3/tv/{$show['tmdb_id']}?language=en-US";
    $opts = stream_context_create(['http' => [
        'header' => "Authorization: Bearer " . TMDB_TOKEN . "\r\nAccept: application/json\r\n",
        'timeout' => 5,
    ]]);
    $resp = @file_get_contents($url, false, $opts);
    if (!$resp) continue;
    $tmdbData = json_decode($resp, true);
    if (!isset($tmdbData['seasons'])) continue;

    // Count only real seasons (season_number > 0)
    $currentSeasons = array_filter($tmdbData['seasons'], fn($s) => $s['season_number'] > 0);
    $currentSeasonCount = count($currentSeasons);

    if ($currentSeasonCount > $storedSeasonCount) {
        // Find the new seasons (ones beyond what user had)
        $newSeasons = array_values(array_filter($currentSeasons, fn($s) => $s['season_number'] > $storedSeasonCount));
        foreach ($newSeasons as $ns) {
            $airDate = $ns['air_date'] ?? null;
            // Only notify if already aired or no air date
            if ($airDate && strtotime($airDate) > time()) continue;
            $notifications[] = [
                'tmdb_id'       => (int)$show['tmdb_id'],
                'title'         => $show['title'],
                'poster_path'   => $show['poster_path'],
                'season_number' => (int)$ns['season_number'],
                'season_name'   => $ns['name'] ?? "Season {$ns['season_number']}",
                'episode_count' => (int)($ns['episode_count'] ?? 0),
                'air_date'      => $airDate,
            ];
        }
    }
}

echo json_encode(['notifications' => $notifications]);
