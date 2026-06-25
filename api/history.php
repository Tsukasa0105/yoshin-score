<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataDir     = __DIR__ . '/data';
$historyFile = $dataDir . '/history.json';

function loadHistory(string $file): array {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    return ($raw !== false) ? (json_decode($raw, true) ?: []) : [];
}

function persistHistory(array $history, string $dataDir, string $file): void {
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? '';

// GET: list or single result
if ($method === 'GET') {
    $history = loadHistory($historyFile);

    if ($id !== '') {
        foreach ($history as $entry) {
            if ($entry['id'] === $id) {
                echo json_encode($entry, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => '結果が見つかりません'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Return list without html payload (lighter)
    $list = array_map(static function (array $e): array {
        return [
            'id'             => $e['id'],
            'timestamp'      => $e['timestamp'],
            'date'           => $e['date'],
            'company_name'   => $e['company_name'],
            'file_name'      => $e['file_name'],
            'score'          => $e['score'],
            'judgment'       => $e['judgment'],
            'judgment_class' => $e['judgment_class'] ?? 'judgment-ok',
        ];
    }, $history);

    echo json_encode($list, JSON_UNESCAPED_UNICODE);
    exit;
}

// DELETE or POST with action=delete
if ($method === 'DELETE' || ($method === 'POST' && ($_POST['action'] ?? '') === 'delete')) {
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'IDが必要です'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $history = loadHistory($historyFile);
    $history = array_values(array_filter($history, static fn(array $e): bool => $e['id'] !== $id));
    persistHistory($history, $dataDir, $historyFile);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
