<?php
require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
  http_response_code(400);
  echo json_encode(['error' => 'All fields are required']);
  exit();
}

$stmt = $pdo->prepare('SELECT id, username, email, avatar_url, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid email or password']);
  exit();
}

$_SESSION['user_id'] = $user['id'];

unset($user['password_hash']);
echo json_encode(['user' => $user]);