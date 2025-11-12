<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/news.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$firebaseConfig = @require __DIR__ . '/../config/firebase.php';
$clientId = is_array($firebaseConfig) ? (string)($firebaseConfig['clientId'] ?? '') : '';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode([
        'items' => loadNewsItems(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: 'null', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$idToken = (string)($payload['idToken'] ?? '');
if ($idToken === '' || $clientId === '') {
    http_response_code(401);
    echo json_encode(['error' => '認証に失敗しました。']);
    exit;
}

$tokenInfo = verifyIdToken($idToken, $clientId);
if ($tokenInfo === null) {
    http_response_code(403);
    echo json_encode(['error' => 'Google認証トークンが無効です。']);
    exit;
}

switch ($method) {
    case 'POST':
        handlePost($payload);
        break;
    case 'DELETE':
        handleDelete($payload);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
}

function handlePost(array $payload): void
{
    $input = $payload['payload'] ?? [];
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'payload が見つかりません。']);
        return;
    }

    [$errors, $data] = validateNewsData($input);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        return;
    }

    $items = loadNewsItems();
    $id = trim((string)($input['id'] ?? ''));

    if ($id !== '') {
        $id = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $id));
        $id = trim($id, '-');
    }

    if ($id === '') {
        $id = generateUniqueId($items, generateNewsId($data['title'], $data['date']));
    }

    $item = [
        'id' => $id,
        'date' => $data['date'],
        'title' => $data['title'],
        'excerpt' => $data['excerpt'],
        'content' => $data['content'],
    ];

    $updated = false;
    foreach ($items as $index => $existing) {
        if (($existing['id'] ?? '') === $id) {
            $items[$index] = $item;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $items[] = $item;
    }

    usort($items, static function ($a, $b) {
        return strtotime($b['date'] ?? 0) <=> strtotime($a['date'] ?? 0);
    });

    if (!saveNewsItems($items)) {
        http_response_code(500);
        echo json_encode(['error' => '保存に失敗しました。']);
        return;
    }

    echo json_encode([
        'item' => $item,
        'updated' => $updated,
    ], JSON_UNESCAPED_UNICODE);
}

function handleDelete(array $payload): void
{
    $id = trim((string)($payload['id'] ?? ''));
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'IDが指定されていません。']);
        return;
    }

    $items = loadNewsItems();
    $initialCount = count($items);
    $items = array_values(array_filter($items, static fn($item) => ($item['id'] ?? '') !== $id));

    if ($initialCount === count($items)) {
        http_response_code(404);
        echo json_encode(['error' => '対象のお知らせが見つかりません。']);
        return;
    }

    if (!saveNewsItems($items)) {
        http_response_code(500);
        echo json_encode(['error' => '削除に失敗しました。']);
        return;
    }

    echo json_encode(['deleted' => true]);
}

function validateNewsData(array $input): array
{
    $errors = [];
    $title = trim((string)($input['title'] ?? ''));
    $date = trim((string)($input['date'] ?? ''));
    $excerpt = trim((string)($input['excerpt'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));

    if ($title === '') {
        $errors['title'] = 'タイトルを入力してください。';
    }

    if ($date === '') {
        $errors['date'] = '日付を入力してください。';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('Y/n/j', $date);
        if (!$dt) {
            $errors['date'] = '日付の形式が正しくありません (例: 2025-10-01)。';
        } else {
            $date = $dt->format('Y-m-d');
        }
    }

    if ($excerpt === '') {
        $errors['excerpt'] = '概要を入力してください。';
    }

    if ($content === '') {
        $errors['content'] = '本文を入力してください。';
    }

    return [$errors, [
        'title' => $title,
        'date' => $date,
        'excerpt' => $excerpt,
        'content' => $content,
    ]];
}

function generateUniqueId(array $items, string $baseId): string
{
    $id = $baseId;
    $counter = 2;

    $existingIds = array_map(static fn($item) => (string)($item['id'] ?? ''), $items);

    while (in_array($id, $existingIds, true)) {
        $id = sprintf('%s-%d', $baseId, $counter);
        $counter++;
    }

    return $id;
}

function verifyIdToken(string $idToken, string $clientId): ?array
{
    if ($idToken === '' || $clientId === '') {
        return null;
    }

    $endpoint = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return null;
    }

    if (($data['aud'] ?? '') !== $clientId) {
        return null;
    }

    if (isset($data['exp']) && (int)$data['exp'] < time()) {
        return null;
    }

    return $data;
}

