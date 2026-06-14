<?php
require_once '../config.php';

$query = trim($_GET['q'] ?? '');
if (!$query) {
    echo json_encode(['results' => []]);
    exit();
}

$url = "https://api.themoviedb.org/3/search/person?query=" . urlencode($query) . "&language=en-US&page=1";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . TMDB_TOKEN,
    'Accept: application/json',
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
