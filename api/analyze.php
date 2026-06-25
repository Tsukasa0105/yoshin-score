<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POSTメソッドのみ対応しています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── API Key ────────────────────────────────────────────────────────
function loadApiKey(): string {
    $key = getenv('ANTHROPIC_API_KEY');
    if ($key !== false && $key !== '') return $key;

    // .env in parent directory (above web root)
    $candidates = [
        __DIR__ . '/../.env',
        __DIR__ . '/../../.env',
        __DIR__ . '/.env',
    ];
    foreach ($candidates as $path) {
        if (!file_exists($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            if (trim($k) === 'ANTHROPIC_API_KEY') {
                return trim($v, " \t\r\n\"'");
            }
        }
    }
    return '';
}

$apiKey = loadApiKey();
if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'APIキーが設定されていません。サーバー管理者に連絡してください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── File validation ────────────────────────────────────────────────
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'ファイルがサーバーの最大サイズを超えています',
        UPLOAD_ERR_FORM_SIZE  => 'ファイルがフォームの最大サイズを超えています',
        UPLOAD_ERR_PARTIAL    => 'ファイルのアップロードが途中で中断されました',
        UPLOAD_ERR_NO_FILE    => 'ファイルが選択されていません',
        UPLOAD_ERR_NO_TMP_DIR => 'テンポラリフォルダが見つかりません',
        UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました',
    ];
    $code  = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg   = $uploadErrors[$code] ?? 'ファイルのアップロードに失敗しました';
    http_response_code(400);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$file    = $_FILES['file'];
$maxSize = 20 * 1024 * 1024; // 20 MB

// Verify the file was actually uploaded via HTTP POST (security)
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => '不正なファイルアップロードです'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルサイズが大きすぎます（最大20MB）'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'html', 'htm'];

if (!in_array($ext, $allowedExts, true)) {
    http_response_code(400);
    echo json_encode(['error' => '対応していないファイル形式です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// MIME type validation via finfo (defense in depth)
$allowedMimes = [
    'application/pdf',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/html', 'application/xhtml+xml',
    'application/zip',           // xlsx is a ZIP
    'application/octet-stream',  // generic binary fallback
];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if ($mimeType === false || !in_array($mimeType, $allowedMimes, true)) {
    // Allow text-based formats that finfo may detect differently
    if (!str_starts_with((string)$mimeType, 'text/') &&
        !str_starts_with((string)$mimeType, 'image/') &&
        !str_starts_with((string)$mimeType, 'application/')) {
        http_response_code(400);
        echo json_encode(['error' => '対応していないファイル形式です（MIMEタイプ不正）'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── System prompt ──────────────────────────────────────────────────
$systemPrompt = <<<'PROMPT'
あなたは中小企業向け財務戦略コンサルティング「株式会社セラヴィ」の審査AIです。
アップロードされた決算書（PDF、画像、Excel、HTML等）を読み取り、以下の審査基準に従って機械的かつ正確にスコアリングを行ってください。

## 入力情報の抽出
決算書から以下の数値を必ず抽出してください。抽出できない場合は「読み取り不可」と明示し、推測で埋めないでください。

### 貸借対照表（B/S）から
- 純資産合計（自己資本）
- 総資産（資産合計）
- 流動資産
- 流動負債
- 有利子負債（短期借入金 + 長期借入金 + 社債 + リース債務等の利息発生負債）

### 損益計算書（P/L）から
- 売上高（直近期・前期）
- 営業利益（直近期・前期）

### 補足情報
- 決算期（年月）
- 現預金残高（任意：月商比較用）
- 月次固定費（任意：預金持続月数算定用）

## スコアリング基準（合計50点）

### 2-1. 自己資本比率（20点）
計算式：自己資本比率 = 純資産合計 ÷ 総資産 × 100

| 区分 | 配点 |
|---|---|
| 30%以上 | 20点 |
| 15%以上30%未満 | 12点 |
| 5%以上15%未満 | 5点 |
| 5%未満（プラス） | 0点 |
| 債務超過（純資産マイナス） | 足切り（総合判定NG） |

債務超過の場合は他項目の点数に関わらず「審査NG（足切り）」と判定。

### 2-2. 営業利益（直近2期）（15点）

| 区分 | 配点 |
|---|---|
| 2期連続黒字 | 15点 |
| 直近黒字／前期赤字 | 10点 |
| 直近赤字／前期黒字 | 5点 |
| 2期連続赤字 | 0点 |

2期連続赤字（0点）でも、現預金残高で月次固定費12ヶ月以上を賄える場合は、総合判定コメント欄で「許容可」と注記すること（点数は0点のまま）。

### 2-3. 流動比率（8点）
計算式：流動比率 = 流動資産 ÷ 流動負債 × 100

| 区分 | 配点 |
|---|---|
| 150%以上 | 8点 |
| 100%以上150%未満 | 5点 |
| 100%未満 | 0点 |

### 2-4. 有利子負債月商倍率（7点）
計算式：有利子負債月商倍率 = 有利子負債 ÷（売上高 ÷ 12）

| 区分 | 配点 |
|---|---|
| 3ヶ月以下 | 7点 |
| 3ヶ月超6ヶ月以下 | 4点 |
| 6ヶ月超 | 0点 |

## 出力フォーマット
必ず以下のHTML形式のみで出力してください。前後に説明文を加えないこと。

<div class="financial-scoring-report">
  <h2>📊 決算書スコアリング判定結果</h2>
  <section class="company-info">
    <h3>会社情報</h3>
    <ul>
      <li>会社名：[抽出した会社名]</li>
      <li>決算期：[YYYY年MM月期]</li>
      <li>判定日：[YYYY年MM月DD日]</li>
    </ul>
  </section>
  <section class="extracted-data">
    <h3>抽出データ</h3>
    <table>
      <thead><tr><th>項目</th><th>直近期</th><th>前期</th></tr></thead>
      <tbody>
        <tr><td>売上高</td><td>XXX千円</td><td>XXX千円</td></tr>
        <tr><td>営業利益</td><td>XXX千円</td><td>XXX千円</td></tr>
        <tr><td>純資産</td><td>XXX千円</td><td>-</td></tr>
        <tr><td>総資産</td><td>XXX千円</td><td>-</td></tr>
        <tr><td>流動資産</td><td>XXX千円</td><td>-</td></tr>
        <tr><td>流動負債</td><td>XXX千円</td><td>-</td></tr>
        <tr><td>有利子負債</td><td>XXX千円</td><td>-</td></tr>
      </tbody>
    </table>
  </section>
  <section class="scoring-detail">
    <h3>スコアリング詳細</h3>
    <table>
      <thead><tr><th>項目</th><th>計算値・根拠</th><th>判定区分</th><th>配点</th><th>満点</th></tr></thead>
      <tbody>
        <tr><td>2-1. 自己資本比率</td><td>XX,XXX千円 ÷ XX,XXX千円 × 100 = XX.X%</td><td>[該当区分]</td><td>XX点</td><td>20点</td></tr>
        <tr><td>2-2. 営業利益（直近2期）</td><td>直近：黒字／前期：黒字</td><td>[該当区分]</td><td>XX点</td><td>15点</td></tr>
        <tr><td>2-3. 流動比率</td><td>XX,XXX千円 ÷ XX,XXX千円 × 100 = XX.X%</td><td>[該当区分]</td><td>XX点</td><td>8点</td></tr>
        <tr><td>2-4. 有利子負債月商倍率</td><td>XX,XXX千円 ÷（XXX,XXX千円 ÷ 12）= X.Xヶ月</td><td>[該当区分]</td><td>XX点</td><td>7点</td></tr>
        <tr class="total"><td><strong>合計</strong></td><td>-</td><td>-</td><td><strong>XX点</strong></td><td><strong>50点</strong></td></tr>
      </tbody>
    </table>
  </section>
  <section class="judgment">
    <h3>総合判定</h3>
    <p class="judgment-result">[判定結果：適格／要検討／課題多／不適格／審査NG（足切り）]</p>
    <p class="judgment-comment">[コメント]</p>
  </section>
  <section class="warnings">
    <h3>⚠️ 注意事項・読み取り不可項目</h3>
    <ul>
      <li>[読み取れなかった項目があれば明示。なければ「特になし」と記載]</li>
    </ul>
  </section>
</div>

## 判定ロジックの厳守事項
1. 数値は決算書からの実数値のみ。推測・補完禁止。読み取れない項目は「読み取り不可」として「判定保留」とする。
2. 計算過程を必ず計算値・根拠列に明示する。
3. 境界値：「30%以上」はちょうど30.0%を含む。「15〜30%」は15.0%以上30.0%未満。「100〜150%」は100.0%以上150.0%未満。「3〜6ヶ月」は3.0ヶ月超6.0ヶ月以下。
4. 債務超過の場合：他の配点に関わらず総合判定を「審査NG（足切り）」とし、合計点の下にその旨を明記する。
5. コメント：35点以上→「適格。資金調達実現可能性が高い水準」、25〜34点→「要検討。改善余地あり、追加情報の確認推奨」、15〜24点→「課題多。財務改善コンサル先行を推奨」、14点以下→「不適格。現状での資金調達は困難」、足切り→「審査NG。債務超過の解消が先決」。
6. 赤字許容ルール：2-2が0点かつ現預金残高・月次固定費の情報がある場合のみ「現預金で◯ヶ月持続可能 → 総合判定で許容可」と注記。
PROMPT;

// ── Build Claude API message ───────────────────────────────────────
$tmpPath  = $file['tmp_name'];
$messages = [];

if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
    $mimeMap = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mimeType  = $mimeMap[$ext] ?? 'image/jpeg';
    $imageData = base64_encode((string)file_get_contents($tmpPath));

    $messages[] = [
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mimeType,
                    'data'       => $imageData,
                ],
            ],
            [
                'type' => 'text',
                'text' => '上記の決算書画像を解析し、与信スコアリングを行ってください。',
            ],
        ],
    ];

} elseif ($ext === 'pdf') {
    $pdfData = base64_encode((string)file_get_contents($tmpPath));

    $messages[] = [
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'document',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'application/pdf',
                    'data'       => $pdfData,
                ],
            ],
            [
                'type' => 'text',
                'text' => '上記の決算書PDFを解析し、与信スコアリングを行ってください。',
            ],
        ],
    ];

} elseif (in_array($ext, ['xls', 'xlsx'], true)) {
    $excelText = buildExcelText($tmpPath, $ext, $file['name']);
    $messages[] = [
        'role'    => 'user',
        'content' => "以下のExcel決算書データを解析し、与信スコアリングを行ってください。\n\n" . $excelText,
    ];

} elseif (in_array($ext, ['html', 'htm'], true)) {
    $raw     = (string)file_get_contents($tmpPath);
    $text    = strip_tags($raw);
    $text    = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text    = (string)preg_replace('/[ \t]+/', ' ', $text);
    $text    = (string)preg_replace('/\n{3,}/', "\n\n", $text);
    $text    = trim($text);

    $messages[] = [
        'role'    => 'user',
        'content' => "以下のHTML決算書データを解析し、与信スコアリングを行ってください。\n\n" . $text,
    ];
}

// Guard: messages must not be empty
if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルを処理できませんでした。対応形式のファイルを再度アップロードしてください。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Excel helper ───────────────────────────────────────────────────
function buildExcelText(string $tmpPath, string $ext, string $originalName): string {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        /** @noinspection PhpIncludeInspection */
        require_once $autoload;
        try {
            /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader $reader */
            $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
            $spreadsheet = $reader->load($tmpPath);
            $text        = '';

            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $sheet        = $spreadsheet->getSheetByName($sheetName);
                $highestRow   = $sheet->getHighestDataRow();
                $highestCol   = $sheet->getHighestDataColumn();
                $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

                $text .= "## シート: {$sheetName}\n\n";
                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                        $col       = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                        $rowData[] = $sheet->getCell($col . $row)->getFormattedValue();
                    }
                    $text .= implode("\t", $rowData) . "\n";
                }
                $text .= "\n";
            }
            return $text;
        } catch (\Throwable $e) {
            return "Excelファイルの読み取りに失敗しました: " . $e->getMessage();
        }
    }

    return "Excelファイル（{$originalName}）が提供されました。\n"
         . "PhpSpreadsheetライブラリが未インストールのため、テキスト変換できませんでした。\n"
         . "ファイル内の財務数値を可能な範囲で解析してください。";
}

// ── Call Anthropic API ─────────────────────────────────────────────
$requestBody = json_encode([
    'model'      => 'claude-opus-4-8',
    'max_tokens' => 4096,
    'system'     => $systemPrompt,
    'messages'   => $messages,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: '          . $apiKey,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: pdfs-2024-09-25',
    ],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError !== '') {
    http_response_code(500);
    echo json_encode(['error' => 'API接続エラー: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    $errData = json_decode((string)$response, true);
    $errMsg  = $errData['error']['message'] ?? "APIエラー (HTTP {$httpCode})";
    http_response_code(500);
    echo json_encode(['error' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

$responseData = json_decode((string)$response, true);
$resultText   = $responseData['content'][0]['text'] ?? '';

// Extract only the HTML block if Claude wrapped it in markdown code fences
if (preg_match('/```html\s*([\s\S]*?)```/i', $resultText, $m)) {
    $resultText = $m[1];
}

// Ensure we at least have the outer div
if (!str_contains($resultText, 'financial-scoring-report')) {
    $resultText = '<div class="financial-scoring-report">' . $resultText . '</div>';
}

echo json_encode(['html' => trim($resultText)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
