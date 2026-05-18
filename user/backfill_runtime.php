<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all items without runtime
$stmt = $pdo->prepare('SELECT id, tmdb_id, media_type FROM user_media WHERE user_id = ? AND runtime IS NULL');
$stmt->execute([$user_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$failed  = 0;

foreach ($items as $item) {
    $url = $item['media_type'] === 'movie'
        ? "https://api.themoviedb.org/3/movie/{$item['tmdb_id']}?language=en-US"
        : "https://api.themoviedb.org/3/tv/{$item['tmdb_id']}?language=en-US";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . TMDB_TOKEN,
        'Accept: application/json',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!$data) { $failed++; continue; }

    if ($item['media_type'] === 'movie') {
        $runtime = isset($data['runtime']) && $data['runtime'] > 0 ? (int)$data['runtime'] : null;
        $title       = $data['title'] ?? null;
        $poster_path = $data['poster_path'] ?? null;
    } else {
        $avg_ep = isset($data['episode_run_time'][0]) && $data['episode_run_time'][0] > 0
            ? (int)$data['episode_run_time'][0]
            : 30;
        $episodes    = (int)($data['number_of_episodes'] ?? 0);
        $runtime     = $episodes > 0 ? $episodes * $avg_ep : null;
        $title       = $data['name'] ?? null;
        $poster_path = $data['poster_path'] ?? null;
    }

    $upd = $pdo->prepare('
        UPDATE user_media
        SET runtime = ?, title = COALESCE(title, ?), poster_path = COALESCE(poster_path, ?)
        WHERE id = ?
    ');
    $upd->execute([$runtime, $title, $poster_path, $item['id']]);
    $updated++;
}

echo json_encode([
    'done'    => true,
    'updated' => $updated,
    'failed'  => $failed,
    'total'   => count($items),
]);
