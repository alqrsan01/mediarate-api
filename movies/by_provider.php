<?php
require_once '../config.php';

$provider_id = intval($_GET['provider_id'] ?? 0);
$page        = intval($_GET['page'] ?? 1);

if (!$provider_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing provider_id']);
    exit();
}

if ($page < 1) $page = 1;

$url = "https://api.themoviedb.org/3/discover/movie?language=en-US&sort_by=popularity.desc&page={$page}&with_watch_providers={$provider_id}&watch_region=US";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . TMDB_TOKEN,
    'Accept: application/json',
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
