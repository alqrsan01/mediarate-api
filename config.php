<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'mediarate');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('TMDB_TOKEN', getenv('TMDB_TOKEN') ?: 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0OGM5ZmU1OTdlNWZiNjBiMDc1MDhkMjQyOTM3YTE0NCIsIm5iZiI6MTc2NTAzODAxOC4xMTEsInN1YiI6IjY5MzQ1N2MyMDc4OTgwZWEyNWQxZjkzOCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.FnIBD-e1Wwo5f3m-Lx7rk6P3zwdNioWQgEyeBw2MoRs');

session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'None',
]);
session_start();

$allowed_origin = getenv('FRONTEND_URL') ?: 'http://localhost:5173';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . $allowed_origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Auto-migrate: add columns if they don't exist
try { $pdo->exec("ALTER TABLE user_media ADD COLUMN title VARCHAR(255) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE user_media ADD COLUMN poster_path VARCHAR(255) NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE user_media ADD COLUMN runtime INT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE user_media ADD COLUMN season_counts JSON NULL"); } catch (PDOException $e) {}
