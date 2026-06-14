<?php
// Cross-origin session cookies (required for Vercel → Render)
session_set_cookie_params([
    'lifetime' => 86400 * 30,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None',
]);
session_start();

define('TMDB_TOKEN', getenv('TMDB_TOKEN') ?: 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0OGM5ZmU1OTdlNWZiNjBiMDc1MDhkMjQyOTM3YTE0NCIsIm5iZiI6MTc2NTAzODAxOC4xMTEsInN1YiI6IjY5MzQ1N2MyMDc4OTgwZWEyNWQxZjkzOCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.FnIBD-e1Wwo5f3m-Lx7rk6P3zwdNioWQgEyeBw2MoRs');

// Use individual env vars (Render) or fall back to local dev values
if (getenv('DB_HOST')) {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: 5432;
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME') ?: 'postgres';
    $dsn  = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
} else {
    $host = 'localhost';
    $port = 5433;
    $user = 'postgres';
    $pass = 'Has107jam';
    $name = 'mediarate';
    $dsn  = "pgsql:host=$host;port=$port;dbname=$name";
}

// Allow frontend origin — supports multiple comma-separated URLs
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('FRONTEND_URL') ?: 'http://localhost:5173')));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin  = in_array($requestOrigin, $allowedOrigins) ? $requestOrigin : ($allowedOrigins[0] ?? '*');

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize schema on first run
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        avatar_url TEXT,
        created_at TIMESTAMP DEFAULT NOW()
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_media (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        tmdb_id INT NOT NULL,
        media_type VARCHAR(10) NOT NULL,
        status VARCHAR(20) NOT NULL,
        rating INT,
        review TEXT,
        title VARCHAR(255),
        poster_path VARCHAR(255),
        runtime INT,
        season_counts JSONB,
        updated_at TIMESTAMP DEFAULT NOW(),
        UNIQUE(user_id, media_type, tmdb_id)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_episode_ratings (
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        show_id INT NOT NULL,
        season_number INT NOT NULL,
        episode_number INT NOT NULL,
        rating INT,
        watched BOOLEAN DEFAULT FALSE,
        updated_at TIMESTAMP DEFAULT NOW(),
        PRIMARY KEY (user_id, show_id, season_number, episode_number)
    )
");
