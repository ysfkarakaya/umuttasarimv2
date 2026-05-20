<?php
declare(strict_types=1);

$langRoot = realpath(__DIR__ . '/../data/lang');
$defaultSourceLang = 'tr';
$defaultModel = 'gemini-2.0-flash';

if ($langRoot === false || !is_dir($langRoot)) {
    http_response_code(500);
    echo 'data/lang dizini bulunamadi.';
    exit;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function listLanguages(string $langRoot): array
{
    $items = [];
    foreach (scandir($langRoot) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $langRoot . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            $items[] = $entry;
        }
    }
    sort($items);
    return $items;
}

function normalizeRelativePath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = preg_replace('#/+#', '/', $path);
    $path = trim((string) $path, '/');
    if ($path === '.' || $path === './') {
        return '';
    }
    return $path;
}

function safePath(string $base, string $relative = ''): string
{
    $relative = normalizeRelativePath($relative);
    $full = $relative === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $realBase = realpath($base);
    if ($realBase === false) {
        throw new RuntimeException('Temel dizin bulunamadi.');
    }

    if (file_exists($full)) {
        $realFull = realpath($full);
        if ($realFull === false || strpos($realFull, $realBase) !== 0) {
            throw new RuntimeException('Gecersiz yol secimi.');
        }
        return $realFull;
    }

    $parent = realpath(dirname($full));
    if ($parent === false || strpos($parent, $realBase) !== 0) {
        throw new RuntimeException('Gecersiz yol secimi.');
    }

    return $full;
}

function ensureDir(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Dizin olusturulamadi: ' . $directory);
    }
}

function copyFileWithDir(string $source, string $destination): void
{
    ensureDir(dirname($destination));
    if (!copy($source, $destination)) {
        throw new RuntimeException('Dosya kopyalanamadi: ' . $source);
    }
}

function copyDirectoryRecursive(string $source, string $destination): void
{
    ensureDir($destination);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $targetPath = $destination . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            ensureDir($targetPath);
            continue;
        }
        copyFileWithDir($item->getPathname(), $targetPath);
    }
}

function prepareTargetScope(string $langRoot, string $sourceLang, string $targetLang, string $scope, string $relativePath): array
{
    $sourceBase = safePath($langRoot, $sourceLang);
    $targetBase = $langRoot . DIRECTORY_SEPARATOR . $targetLang;
    ensureDir($targetBase);

    if ($scope === 'all' || $relativePath === '') {
        copyDirectoryRecursive($sourceBase, $targetBase);
        return ['copied' => 'all'];
    }

    $sourcePath = safePath($sourceBase, $relativePath);
    $targetPath = $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, normalizeRelativePath($relativePath));

    if (is_dir($sourcePath)) {
        copyDirectoryRecursive($sourcePath, $targetPath);
        return ['copied' => 'directory', 'path' => normalizeRelativePath($relativePath)];
    }

    copyFileWithDir($sourcePath, $targetPath);
    return ['copied' => 'file', 'path' => normalizeRelativePath($relativePath)];
}

function collectPathOptions(string $baseDir): array
{
    $directories = [];
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($baseDir) + 1));
        if ($item->isDir()) {
            $directories[] = $relative;
            continue;
        }
        if (strtolower($item->getExtension()) === 'json') {
            $files[] = $relative;
        }
    }

    sort($directories);
    sort($files);

    return ['directories' => $directories, 'files' => $files];
}

function buildTreeNodes(string $baseDir, string $currentDir = ''): array
{
    $scanDir = $currentDir === '' ? $baseDir : $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentDir);
    $directories = [];
    $files = [];

    foreach (scandir($scanDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $scanDir . DIRECTORY_SEPARATOR . $entry;
        $relativePath = ltrim(($currentDir === '' ? '' : $currentDir . '/') . $entry, '/');
        if (is_dir($fullPath)) {
            $children = buildTreeNodes($baseDir, str_replace('\\', '/', $relativePath));
            $jsonCount = 0;
            foreach ($children as $child) {
                $jsonCount += $child['type'] === 'file' ? 1 : ($child['json_count'] ?? 0);
            }

            $directories[] = [
                'type' => 'directory',
                'name' => $entry,
                'path' => str_replace('\\', '/', $relativePath),
                'json_count' => $jsonCount,
                'children' => $children,
            ];
            continue;
        }

        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'json') {
            continue;
        }

        $files[] = [
            'type' => 'file',
            'name' => $entry,
            'path' => str_replace('\\', '/', $relativePath),
            'json_count' => 1,
        ];
    }

    usort($directories, static fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));
    usort($files, static fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

    return array_merge($directories, $files);
}

function collectJsonFiles(string $langRoot, string $sourceLang, string $targetLang, string $scope, string $relativePath): array
{
    $sourceBase = safePath($langRoot, $sourceLang);
    $targetBase = safePath($langRoot, $targetLang);
    $pairs = [];

    if ($scope === 'all' || $relativePath === '') {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceBase, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'json') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceBase) + 1));
            $pairs[] = [
                'relative' => $relative,
                'source' => $item->getPathname(),
                'target' => $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative),
            ];
        }
        return $pairs;
    }

    $sourcePath = safePath($sourceBase, $relativePath);
    $normalized = normalizeRelativePath($relativePath);

    if (is_file($sourcePath)) {
        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) !== 'json') {
            throw new RuntimeException('Secilen dosya JSON olmali.');
        }
        return [[
            'relative' => $normalized,
            'source' => $sourcePath,
            'target' => $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
        ]];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'json') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceBase) + 1));
        $pairs[] = [
            'relative' => $relative,
            'source' => $item->getPathname(),
            'target' => $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative),
        ];
    }

    return $pairs;
}

function shouldSkipByKey(string $path, array $skipKeys): bool
{
    $path = strtolower($path);
    foreach ($skipKeys as $skipKey) {
        $skipKey = strtolower(trim($skipKey));
        if ($skipKey === '') {
            continue;
        }
        if (strpos($path, $skipKey) !== false) {
            return true;
        }
    }
    return false;
}

function shouldTranslateString(string $path, string $value, array $skipKeys): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }

    if (shouldSkipByKey($path, $skipKeys)) {
        return false;
    }

    if (preg_match('/^(https?:\/\/|www\.|mailto:|tel:)/i', $trimmed)) {
        return false;
    }

    if (strpos($trimmed, 'upload/') === 0 || strpos($trimmed, '/upload/') !== false) {
        return false;
    }

    // Keep media file paths and names unchanged.
    if (preg_match('/\.(png|jpe?g|gif|webp|svg|avif|bmp|ico|tiff|obj|tif|glb|gltf|mp4|mp3|wav|ogg|flac|m4a|aac?)$/i', $trimmed)) {
        return false;
    }

    if (preg_match('/^[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}$/', $trimmed)) {
        return false;
    }

    if (preg_match('/^[-_a-z0-9\/]+\.json$/i', $trimmed)) {
        return false;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/', $trimmed)) {
        return false;
    }

    if (preg_match('/^[0-9._:#\/\-]+$/', $trimmed)) {
        return false;
    }

    if (preg_match('/^[a-z0-9-]+$/', $trimmed) && strpos($trimmed, ' ') === false && strpos($trimmed, '_') === false) {
        return false;
    }

    return true;
}

function collectTranslatableStrings(mixed $data, array &$entries, array $skipKeys, string $path = '$'): void
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $childPath = $path . '.' . (string) $key;
            collectTranslatableStrings($value, $entries, $skipKeys, $childPath);
        }
        return;
    }

    if (!is_string($data)) {
        return;
    }

    if (!shouldTranslateString($path, $data, $skipKeys)) {
        return;
    }

    $entries[] = ['path' => $path, 'value' => $data];
}

function setValueByPath(array &$data, string $path, string $value): void
{
    $segments = explode('.', substr($path, 2));
    $cursor = &$data;
    foreach ($segments as $segment) {
        if (ctype_digit($segment)) {
            $segment = (int) $segment;
        }
        $cursor = &$cursor[$segment];
    }
    $cursor = $value;
}

function buildPrompt(string $sourceLang, string $targetLang, array $items): string
{
    $payload = [];
    foreach ($items as $index => $item) {
        $payload[] = [
            'index' => $index,
            'text' => $item['value'],
        ];
    }

    return "You are translating website JSON content from {$sourceLang} to {$targetLang}. "
        . "Keep JSON order and return ONLY a JSON array. Preserve HTML tags, placeholders, variables, brand names, URLs, file paths and technical tokens. "
        . "Translate natural language only. Output format: [{\"index\":0,\"text\":\"translated\"}]. Input: "
        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function geminiTranslateBatch(string $apiKey, string $model, string $sourceLang, string $targetLang, array $items): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $body = [
        'contents' => [[
            'parts' => [[
                'text' => buildPrompt($sourceLang, $targetLang, $items),
            ]],
        ]],
        'generationConfig' => [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json',
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException('Gemini baglanti hatasi: ' . $error);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Gemini HTTP hatasi: ' . $status . ' | ' . (string) $response);
    }

    $decoded = json_decode((string) $response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        throw new RuntimeException('Gemini bos ya da gecersiz cevap dondurdu.');
    }

    $translated = json_decode($text, true);
    if (!is_array($translated)) {
        throw new RuntimeException('Gemini JSON cevap dondurmedi.');
    }

    $mapped = [];
    foreach ($translated as $row) {
        if (!isset($row['index'], $row['text'])) {
            continue;
        }
        $mapped[(int) $row['index']] = (string) $row['text'];
    }

    if (count($mapped) !== count($items)) {
        throw new RuntimeException('Gemini cevap sayisi beklenenle eslesmedi.');
    }

    ksort($mapped);
    return array_values($mapped);
}

function translateJsonFile(string $sourceFile, string $targetFile, string $apiKey, string $model, string $sourceLang, string $targetLang, array $skipKeys): array
{
    $content = file_get_contents($sourceFile);
    if ($content === false) {
        throw new RuntimeException('Kaynak dosya okunamadi: ' . $sourceFile);
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Gecersiz JSON: ' . basename($sourceFile));
    }

    $entries = [];
    collectTranslatableStrings($data, $entries, $skipKeys);
    if ($entries === []) {
        copyFileWithDir($sourceFile, $targetFile);
        return ['translated_count' => 0, 'copied_only' => true];
    }

    foreach (array_chunk($entries, 40) as $offset => $chunk) {
        $translations = geminiTranslateBatch($apiKey, $model, $sourceLang, $targetLang, $chunk);
        foreach ($chunk as $index => $entry) {
            setValueByPath($data, $entry['path'], $translations[$index]);
        }
    }

    ensureDir(dirname($targetFile));
    file_put_contents($targetFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return ['translated_count' => count($entries), 'copied_only' => false];
}

$languages = listLanguages($langRoot);
if (!in_array($defaultSourceLang, $languages, true)) {
    $defaultSourceLang = $languages[0] ?? 'tr';
}

$pathOptions = collectPathOptions(safePath($langRoot, $defaultSourceLang));
$defaultSkipKeys = [
    'id', 'sef_url', 'slug', 'url', 'image', 'resim', 'logo', 'icon', 'path', 'file',
    'created_at', 'updated_at', 'deleted_at', 'email', 'telephone', 'phone', 'favicon',
    '@context', '@type', 'locale', 'canonical', 'manifest', 'theme-color', 'charset',
    'viewport', 'google-site-verification', 'msvalidate.01', 'latitude', 'longitude'
];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    try {
        if ($action === 'options') {
            $sourceLang = $_GET['source_lang'] ?? $defaultSourceLang;
            if (!in_array($sourceLang, $languages, true)) {
                throw new RuntimeException('Gecersiz kaynak dil.');
            }
            jsonResponse([
                'status' => 'success',
                'options' => collectPathOptions(safePath($langRoot, $sourceLang)),
            ]);
        }

        if ($action === 'tree') {
            $sourceLang = $_GET['source_lang'] ?? $defaultSourceLang;
            if (!in_array($sourceLang, $languages, true)) {
                throw new RuntimeException('Gecersiz kaynak dil.');
            }
            $sourceBase = safePath($langRoot, $sourceLang);
            jsonResponse([
                'status' => 'success',
                'tree' => buildTreeNodes($sourceBase),
            ]);
        }

        if ($action === 'prepare') {
            $sourceLang = $_POST['source_lang'] ?? $defaultSourceLang;
            $targetLang = $_POST['target_lang'] ?? 'en';
            $scope = $_POST['scope'] ?? 'all';
            $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
            prepareTargetScope($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            jsonResponse([
                'status' => 'success',
                'message' => strtoupper($sourceLang) . ' klasoru ' . strtoupper($targetLang) . ' icin hazirlandi.',
            ]);
        }

        if ($action === 'inspect') {
            $sourceLang = $_POST['source_lang'] ?? $defaultSourceLang;
            $targetLang = $_POST['target_lang'] ?? 'en';
            $scope = $_POST['scope'] ?? 'all';
            $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
            if (!in_array($sourceLang, $languages, true)) {
                throw new RuntimeException('Gecersiz kaynak dil.');
            }
            ensureDir($langRoot . DIRECTORY_SEPARATOR . $targetLang);
            $pairs = collectJsonFiles($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            jsonResponse([
                'status' => 'success',
                'summary' => [
                    'json_files' => count($pairs),
                    'target_lang_exists' => is_dir($langRoot . DIRECTORY_SEPARATOR . $targetLang),
                    'sample' => array_slice(array_map(static fn ($pair) => $pair['relative'], $pairs), 0, 12),
                ],
            ]);
        }

        if ($action === 'translate') {
            $sourceLang = $_POST['source_lang'] ?? $defaultSourceLang;
            $targetLang = $_POST['target_lang'] ?? 'en';
            $scope = $_POST['scope'] ?? 'all';
            $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $model = trim((string) ($_POST['model'] ?? $defaultModel));
            $prepareFirst = !empty($_POST['prepare_first']);
            $skipKeys = array_filter(array_map('trim', explode(',', (string) ($_POST['skip_keys'] ?? implode(', ', $defaultSkipKeys)))));

            if ($apiKey === '') {
                throw new RuntimeException('Gemini API key gerekli.');
            }
            if (!in_array($sourceLang, $languages, true)) {
                throw new RuntimeException('Gecersiz kaynak dil.');
            }
            if ($sourceLang === $targetLang) {
                throw new RuntimeException('Kaynak ve hedef dil ayni olamaz.');
            }

            if ($prepareFirst || !is_dir($langRoot . DIRECTORY_SEPARATOR . $targetLang)) {
                prepareTargetScope($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            }

            $pairs = collectJsonFiles($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            $summary = [
                'files_total' => count($pairs),
                'files_done' => 0,
                'strings_translated' => 0,
                'copied_only' => 0,
                'errors' => [],
            ];

            foreach ($pairs as $pair) {
                try {
                    $result = translateJsonFile($pair['source'], $pair['target'], $apiKey, $model, $sourceLang, $targetLang, $skipKeys);
                    $summary['files_done']++;
                    $summary['strings_translated'] += $result['translated_count'];
                    if ($result['copied_only']) {
                        $summary['copied_only']++;
                    }
                } catch (Throwable $exception) {
                    $summary['errors'][] = $pair['relative'] . ': ' . $exception->getMessage();
                }
            }

            jsonResponse([
                'status' => $summary['errors'] === [] ? 'success' : 'partial',
                'message' => 'Ceviri islemi tamamlandi.',
                'summary' => $summary,
            ]);
        }

        jsonResponse(['status' => 'error', 'message' => 'Gecersiz islem.'], 400);
    } catch (Throwable $exception) {
        jsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 422);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ceviri Otomasyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f8fb;
            --panel: #ffffff;
            --line: #e5e9f2;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #0f766e;
            --primary-soft: #e6fffb;
            --blue: #2563eb;
            --warning: #b45309;
            --danger: #b91c1c;
            --radius: 16px;
            --shadow: 0 12px 30px -20px rgba(0, 0, 0, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Manrope', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 18px;
        }

        .app {
            max-width: 1480px;
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .topbar {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .topbar h1 {
            font-size: 1.35rem;
        }

        .topbar p {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .quick-stats {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip {
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--muted);
        }

        .layout {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr) 340px;
            gap: 16px;
            align-items: start;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: #fafbfd;
        }

        .panel-head strong {
            font-size: 0.95rem;
        }

        .panel-body {
            padding: 14px 16px;
            display: grid;
            gap: 12px;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 0.78rem;
            color: var(--muted);
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 12px;
            background: #fff;
            color: var(--text);
            outline: none;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .modes {
            display: grid;
            gap: 8px;
        }

        .mode {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            cursor: pointer;
            display: grid;
            gap: 4px;
        }

        .mode.active {
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .mode small {
            color: var(--muted);
        }

        .selection {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            background: #fcfcfd;
            display: grid;
            gap: 6px;
        }

        .selection code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.76rem;
            color: #374151;
            word-break: break-all;
        }

        .explorer-top {
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }

        .explorer-tools {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tree {
            max-height: 760px;
            overflow: auto;
            padding: 12px;
        }

        .tree-list,
        .tree-children {
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .tree-children {
            margin-left: 14px;
            padding-left: 12px;
            border-left: 1px dashed var(--line);
            margin-top: 8px;
        }

        .tree-row {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            background: #fff;
            cursor: pointer;
        }

        .tree-row.active {
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .tree-main {
            display: grid;
            grid-template-columns: 30px 42px minmax(0, 1fr);
            gap: 8px;
            align-items: center;
        }

        .tree-toggle,
        .tree-icon {
            width: 30px;
            height: 30px;
            border: 1px solid var(--line);
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            background: #fff;
            color: #4b5563;
        }

        .tree-toggle.placeholder {
            opacity: 0;
            pointer-events: none;
        }

        .tree-icon {
            width: 42px;
            color: var(--blue);
            border-color: #dbe7ff;
            background: #f3f7ff;
        }

        .tree-copy {
            min-width: 0;
            display: grid;
            gap: 2px;
        }

        .tree-copy strong {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .tree-copy span {
            color: var(--muted);
            font-size: 0.74rem;
            font-family: 'JetBrains Mono', monospace;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tree-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn,
        .btn-inline {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            cursor: pointer;
            transition: 0.2s;
        }

        .btn {
            padding: 10px 12px;
            font-weight: 600;
        }

        .btn-inline {
            padding: 7px 10px;
            font-size: 0.75rem;
        }

        .btn:hover,
        .btn-inline:hover {
            transform: translateY(-1px);
        }

        .btn.primary,
        .btn-inline.primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .btn.warn {
            background: #fff7ed;
            border-color: #fed7aa;
            color: var(--warning);
        }

        .btn.blue {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: var(--blue);
        }

        .actions {
            display: grid;
            gap: 8px;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .metric {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            background: #fff;
        }

        .metric span {
            display: block;
            color: var(--muted);
            font-size: 0.72rem;
            margin-bottom: 5px;
        }

        .metric strong {
            font-size: 1.2rem;
        }

        .console {
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .console-head {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
        }

        .status {
            font-size: 0.76rem;
            color: var(--muted);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 10px;
        }

        .logs {
            max-height: 380px;
            overflow: auto;
            padding: 12px;
            display: grid;
            gap: 8px;
        }

        .log-row {
            display: grid;
            grid-template-columns: 62px 66px minmax(0, 1fr);
            gap: 8px;
            align-items: start;
        }

        .log-row span,
        .log-row div {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.74rem;
        }

        .log-time {
            color: var(--muted);
        }

        .log-kind {
            text-transform: uppercase;
            border-radius: 999px;
            padding: 3px 7px;
            text-align: center;
        }

        .log-kind.info {
            color: var(--blue);
            background: #eff6ff;
        }

        .log-kind.success {
            color: var(--primary);
            background: #ecfdf5;
        }

        .log-kind.warning {
            color: var(--warning);
            background: #fff7ed;
        }

        .log-kind.error {
            color: var(--danger);
            background: #fef2f2;
        }

        .log-message {
            color: #374151;
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 1260px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .row {
                grid-template-columns: 1fr;
            }

            .explorer-top {
                grid-template-columns: 1fr;
            }

            .tree-row,
            .log-row {
                grid-template-columns: 1fr;
            }

            .tree-main {
                grid-template-columns: 30px 42px minmax(0, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="app">
        <header class="topbar">
            <div>
                <h1>Ceviri Kontrol Paneli</h1>
                <p>Tum TR dizini, belirli klasor veya tek JSON secip kopyalama, analiz ve ceviri islemi baslat.</p>
            </div>
            <div class="quick-stats">
                <div class="chip" id="chip-source">SRC: TR</div>
                <div class="chip" id="chip-target">TGT: EN</div>
                <div class="chip" id="chip-mode">MODE: FULL</div>
            </div>
        </header>

        <section class="layout">
            <aside class="panel">
                <div class="panel-head"><strong>Ayarlar</strong></div>
                <div class="panel-body">
                    <div class="row">
                        <div class="field">
                            <label for="source-lang">Kaynak dil</label>
                            <select id="source-lang">
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lang === $defaultSourceLang ? 'selected' : ''; ?>><?php echo htmlspecialchars(strtoupper($lang), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="target-lang">Hedef dil</label>
                            <input id="target-lang" value="en" placeholder="en, de, ar...">
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="model">Gemini model</label>
                            <input id="model" value="<?php echo htmlspecialchars($defaultModel, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="field">
                            <label for="api-key">API key</label>
                            <input id="api-key" type="password" placeholder="AIza...">
                        </div>
                    </div>

                    <div class="field">
                        <label>Islem modu</label>
                        <div class="modes" id="mode-list">
                            <div class="mode active" data-mode="all">
                                <strong>FULL - Tum TR dizini</strong>
                                <small>Yeni dil klasorunu bastan hazirlamak icin.</small>
                            </div>
                            <div class="mode" data-mode="directory">
                                <strong>DIR - Belirli klasor</strong>
                                <small>Sadece secilen klasor icindeki JSON dosyalari.</small>
                            </div>
                            <div class="mode" data-mode="file">
                                <strong>JSON - Tek dosya</strong>
                                <small>Tek bir JSON dosyasini test etmek icin.</small>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="relative-path">Secili yol</label>
                        <input id="relative-path" placeholder="Ornek: blog veya blog/ornek.json">
                    </div>

                    <div class="field">
                        <label for="skip-keys">Korunacak anahtar desenleri</label>
                        <textarea id="skip-keys"><?php echo htmlspecialchars(implode(', ', $defaultSkipKeys), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <label style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:0.85rem;">
                        <input id="prepare-first" type="checkbox" checked>
                        Ceviri oncesi hedef dili kaynak yapidan hazirla
                    </label>

                    <div class="selection">
                        <strong id="selection-title">Secili kapsam</strong>
                        <code id="selection-path">Tum kaynak dil klasoru</code>
                    </div>
                </div>
            </aside>

            <main class="panel">
                <div class="explorer-top">
                    <input id="tree-search" placeholder="Dizin veya JSON ara...">
                    <div class="explorer-tools">
                        <button class="btn blue" id="refresh-tree-btn" type="button">Yenile</button>
                        <button class="btn" id="select-all-btn" type="button">Tum TR</button>
                    </div>
                </div>
                <div class="panel-body" style="padding-top:10px; padding-bottom:10px;">
                    <div class="quick-stats">
                        <div class="chip" id="meta-dir-count">0 DIR</div>
                        <div class="chip" id="meta-file-count">0 JSON</div>
                    </div>
                </div>
                <div class="tree" id="tree-root"></div>
            </main>

            <aside class="panel">
                <div class="panel-head"><strong>Calistir ve Sonuclar</strong></div>
                <div class="panel-body">
                    <div class="actions">
                        <button class="btn blue" id="inspect-btn" type="button">Analiz Et</button>
                        <button class="btn warn" id="prepare-btn" type="button">Kopyala</button>
                        <button class="btn primary" id="translate-btn" type="button">Ceviriyi Baslat</button>
                    </div>

                    <div class="metric-grid">
                        <div class="metric"><span>JSON</span><strong id="sum-files">0</strong></div>
                        <div class="metric"><span>Metin</span><strong id="sum-strings">0</strong></div>
                        <div class="metric"><span>Kopya</span><strong id="sum-copied">0</strong></div>
                        <div class="metric"><span>Hata</span><strong id="sum-errors">0</strong></div>
                    </div>

                    <div class="console">
                        <div class="console-head">
                            <strong>Canli Log</strong>
                            <span class="status" id="status-badge">Hazir</span>
                        </div>
                        <div class="logs" id="log"></div>
                    </div>
                </div>
            </aside>
        </section>
    </div>

    <script>
        const sourceLang = document.getElementById('source-lang');
        const targetLang = document.getElementById('target-lang');
        const model = document.getElementById('model');
        const apiKey = document.getElementById('api-key');
        const prepareFirst = document.getElementById('prepare-first');
        const relativePath = document.getElementById('relative-path');
        const skipKeys = document.getElementById('skip-keys');
        const modeItems = Array.from(document.querySelectorAll('[data-mode]'));
        const treeRoot = document.getElementById('tree-root');
        const treeSearch = document.getElementById('tree-search');
        const logRoot = document.getElementById('log');

        const statusBadge = document.getElementById('status-badge');
        const chipSource = document.getElementById('chip-source');
        const chipTarget = document.getElementById('chip-target');
        const chipMode = document.getElementById('chip-mode');
        const metaDirCount = document.getElementById('meta-dir-count');
        const metaFileCount = document.getElementById('meta-file-count');
        const selectionTitle = document.getElementById('selection-title');
        const selectionPath = document.getElementById('selection-path');

        const summaryEls = {
            files: document.getElementById('sum-files'),
            strings: document.getElementById('sum-strings'),
            copied: document.getElementById('sum-copied'),
            errors: document.getElementById('sum-errors')
        };

        const actionButtons = [
            document.getElementById('inspect-btn'),
            document.getElementById('prepare-btn'),
            document.getElementById('translate-btn'),
            document.getElementById('refresh-tree-btn'),
            document.getElementById('select-all-btn')
        ];

        let selectedMode = 'all';
        let selectedPath = '';
        let treeData = [];
        let expanded = new Set(['']);
        let treeFilter = '';
        let busy = false;

        apiKey.value = localStorage.getItem('gemini_api_key') || '';
        model.value = localStorage.getItem('gemini_model') || model.value;
        targetLang.value = localStorage.getItem('target_lang') || targetLang.value;
        skipKeys.value = localStorage.getItem('translation_skip_keys') || skipKeys.value;

        function addLog(message, type = 'info') {
            const row = document.createElement('div');
            row.className = 'log-row';
            row.innerHTML = `
                <span class="log-time">${new Date().toLocaleTimeString()}</span>
                <span class="log-kind ${type}">${type}</span>
                <div class="log-message">${message}</div>
            `;
            logRoot.appendChild(row);
            logRoot.scrollTop = logRoot.scrollHeight;
        }

        function setBusy(nextBusy, label = 'Calisiyor') {
            busy = nextBusy;
            statusBadge.textContent = nextBusy ? label : 'Hazir';
            actionButtons.forEach((btn) => {
                btn.disabled = nextBusy;
            });
        }

        function savePrefs() {
            localStorage.setItem('gemini_api_key', apiKey.value.trim());
            localStorage.setItem('gemini_model', model.value.trim());
            localStorage.setItem('target_lang', targetLang.value.trim());
            localStorage.setItem('translation_skip_keys', skipKeys.value);
        }

        function selectedText() {
            if (selectedMode === 'all') return 'Tum kaynak dil klasoru';
            if (selectedPath) return selectedPath;
            return selectedMode === 'directory' ? 'Bir klasor secin' : 'Bir JSON dosyasi secin';
        }

        function syncUi() {
            chipSource.textContent = `SRC: ${sourceLang.value.toUpperCase()}`;
            chipTarget.textContent = `TGT: ${(targetLang.value.trim() || 'EN').toUpperCase()}`;
            chipMode.textContent = `MODE: ${selectedMode === 'all' ? 'FULL' : selectedMode === 'directory' ? 'DIR' : 'JSON'}`;
            selectionTitle.textContent = selectedMode === 'all' ? 'Secili kapsam: Tum TR dizini' : `Secili kapsam: ${selectedMode === 'directory' ? 'Klasor' : 'JSON'}`;
            selectionPath.textContent = selectedText();
            relativePath.value = selectedMode === 'all' ? '' : selectedPath;
            modeItems.forEach((item) => item.classList.toggle('active', item.dataset.mode === selectedMode));
        }

        function resetSummary() {
            summaryEls.files.textContent = '0';
            summaryEls.strings.textContent = '0';
            summaryEls.copied.textContent = '0';
            summaryEls.errors.textContent = '0';
        }

        function updateSummary(summary) {
            if (typeof summary.json_files === 'number') summaryEls.files.textContent = String(summary.json_files);
            if (typeof summary.files_done === 'number') summaryEls.files.textContent = String(summary.files_done);
            if (typeof summary.strings_translated === 'number') summaryEls.strings.textContent = String(summary.strings_translated);
            if (typeof summary.copied_only === 'number') summaryEls.copied.textContent = String(summary.copied_only);
            if (Array.isArray(summary.errors)) summaryEls.errors.textContent = String(summary.errors.length);
        }

        function buildFormData(includeApi = false) {
            const fd = new FormData();
            fd.append('source_lang', sourceLang.value);
            fd.append('target_lang', targetLang.value.trim());
            fd.append('scope', selectedMode);
            fd.append('relative_path', selectedPath);
            fd.append('prepare_first', prepareFirst.checked ? '1' : '');
            fd.append('skip_keys', skipKeys.value);
            fd.append('model', model.value.trim());
            if (includeApi) fd.append('api_key', apiKey.value.trim());
            return fd;
        }

        async function postAction(action, fd) {
            const res = await fetch(`index.php?action=${encodeURIComponent(action)}`, {
                method: 'POST',
                body: fd
            });
            return res.json();
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function selectScope(mode, path = '') {
            selectedMode = mode;
            selectedPath = mode === 'all' ? '' : path;
            if (selectedPath) {
                let current = '';
                selectedPath.split('/').slice(0, -1).forEach((segment) => {
                    current = current ? `${current}/${segment}` : segment;
                    expanded.add(current);
                });
            }
            syncUi();
            renderTree();
        }

        function toggle(path) {
            if (expanded.has(path)) expanded.delete(path);
            else expanded.add(path);
            renderTree();
        }

        function filterTree(nodes, query) {
            if (!query) return nodes;
            const q = query.toLowerCase();
            const result = [];
            nodes.forEach((node) => {
                const match = node.name.toLowerCase().includes(q) || node.path.toLowerCase().includes(q);
                if (node.type === 'directory') {
                    const children = filterTree(node.children || [], query);
                    if (match || children.length) result.push({ ...node, children });
                } else if (match) {
                    result.push(node);
                }
            });
            return result;
        }

        function countTree(nodes) {
            let dirs = 0;
            let files = 0;
            nodes.forEach((node) => {
                if (node.type === 'directory') {
                    dirs += 1;
                    const sub = countTree(node.children || []);
                    dirs += sub.dirs;
                    files += sub.files;
                } else {
                    files += 1;
                }
            });
            return { dirs, files };
        }

        function renderNodes(nodes) {
            if (!nodes.length) {
                return '<div style="padding:14px;border:1px solid var(--line);border-radius:12px;color:var(--muted);">Sonuc bulunamadi.</div>';
            }

            return `<ul class="tree-list">${nodes.map((node) => {
                const hasChildren = node.type === 'directory' && (node.children || []).length > 0;
                const isOpen = expanded.has(node.path);
                const active = selectedMode === node.type && selectedPath === node.path;
                const meta = node.type === 'directory' ? `${node.json_count || 0} JSON` : node.path;

                return `
                    <li>
                        <div class="tree-row ${active ? 'active' : ''}" data-select-type="${node.type}" data-select-path="${escapeHtml(node.path)}">
                            <div class="tree-main">
                                <button class="tree-toggle ${hasChildren ? '' : 'placeholder'}" type="button" ${hasChildren ? `data-toggle-path="${escapeHtml(node.path)}"` : ''}>${hasChildren ? (isOpen ? '-' : '+') : '+'}</button>
                                <div class="tree-icon">${node.type === 'directory' ? 'DIR' : 'JSON'}</div>
                                <div class="tree-copy">
                                    <strong>${escapeHtml(node.name)}</strong>
                                    <span>${escapeHtml(meta)}</span>
                                </div>
                            </div>
                            <div class="tree-actions">
                                <button class="btn-inline" type="button" data-copy-type="${node.type}" data-copy-path="${escapeHtml(node.path)}">Kopyala</button>
                                <button class="btn-inline primary" type="button" data-translate-type="${node.type}" data-translate-path="${escapeHtml(node.path)}">Cevir</button>
                            </div>
                        </div>
                        ${hasChildren && isOpen ? `<div class="tree-children">${renderNodes(node.children)}</div>` : ''}
                    </li>
                `;
            }).join('')}</ul>`;
        }

        function renderTree() {
            const view = filterTree(treeData, treeFilter);
            const counts = countTree(view);
            metaDirCount.textContent = `${counts.dirs} DIR`;
            metaFileCount.textContent = `${counts.files} JSON`;
            treeRoot.innerHTML = renderNodes(view);
        }

        async function refreshTree(showLog = true) {
            const res = await fetch(`index.php?action=tree&source_lang=${encodeURIComponent(sourceLang.value)}`);
            const data = await res.json();
            if (data.status !== 'success') {
                addLog(data.message || 'Dosya agaci alinamadi.', 'error');
                return;
            }
            treeData = data.tree || [];
            expanded = new Set(['']);
            renderTree();
            if (showLog) addLog('Dosya listesi yenilendi.', 'info');
        }

        async function runInspect() {
            savePrefs();
            setBusy(true, 'Analiz');
            resetSummary();
            addLog(`${selectedText()} icin analiz basladi.`, 'info');
            try {
                const result = await postAction('inspect', buildFormData(false));
                if (result.status !== 'success') {
                    addLog(result.message || 'Analiz basarisiz.', 'error');
                    return;
                }
                updateSummary(result.summary);
                addLog(`Analiz tamamlandi: ${result.summary.json_files} JSON bulundu.`, 'success');
            } catch (error) {
                addLog('Analiz sirasinda hata olustu.', 'error');
            } finally {
                setBusy(false);
            }
        }

        async function runPrepare() {
            savePrefs();
            setBusy(true, 'Kopyalaniyor');
            addLog(`${selectedText()} kopyalaniyor...`, 'warning');
            try {
                const result = await postAction('prepare', buildFormData(false));
                if (result.status !== 'success') {
                    addLog(result.message || 'Kopyalama basarisiz.', 'error');
                    return;
                }
                addLog(result.message, 'success');
            } catch (error) {
                addLog('Kopyalama sirasinda hata olustu.', 'error');
            } finally {
                setBusy(false);
            }
        }

        async function runTranslate() {
            savePrefs();
            if (!apiKey.value.trim()) {
                addLog('API key girmeden ceviri baslatilamaz.', 'error');
                return;
            }

            setBusy(true, 'Ceviriliyor');
            resetSummary();
            addLog(`${selectedText()} cevriliyor...`, 'warning');
            try {
                const result = await postAction('translate', buildFormData(true));
                if (result.status === 'error') {
                    addLog(result.message || 'Ceviri basarisiz.', 'error');
                    return;
                }
                updateSummary(result.summary);
                addLog(`${result.message} ${result.summary.files_done}/${result.summary.files_total} dosya tamamlandi.`, result.status === 'success' ? 'success' : 'warning');
                if (result.summary.errors.length) {
                    addLog(`Hatalar:\n${result.summary.errors.join('\n')}`, 'error');
                }
            } catch (error) {
                addLog('Ceviri sirasinda hata olustu.', 'error');
            } finally {
                setBusy(false);
            }
        }

        modeItems.forEach((item) => {
            item.addEventListener('click', () => {
                const mode = item.dataset.mode;
                selectScope(mode, mode === 'all' ? '' : selectedPath);
            });
        });

        document.getElementById('refresh-tree-btn').addEventListener('click', () => refreshTree(true));
        document.getElementById('select-all-btn').addEventListener('click', () => selectScope('all', ''));
        document.getElementById('inspect-btn').addEventListener('click', runInspect);
        document.getElementById('prepare-btn').addEventListener('click', runPrepare);
        document.getElementById('translate-btn').addEventListener('click', runTranslate);

        treeSearch.addEventListener('input', () => {
            treeFilter = treeSearch.value.trim();
            renderTree();
        });

        relativePath.addEventListener('input', () => {
            const value = relativePath.value.trim();
            if (!value) {
                if (selectedMode === 'all') selectScope('all', '');
                else selectScope(selectedMode, '');
                return;
            }
            const nextMode = selectedMode === 'all' ? 'directory' : selectedMode;
            selectScope(nextMode, value);
        });

        treeRoot.addEventListener('click', async (event) => {
            if (busy) return;

            const toggleBtn = event.target.closest('[data-toggle-path]');
            if (toggleBtn) {
                toggle(toggleBtn.dataset.togglePath || '');
                return;
            }

            const copyBtn = event.target.closest('[data-copy-path]');
            if (copyBtn) {
                selectScope(copyBtn.dataset.copyType || 'file', copyBtn.dataset.copyPath || '');
                await runPrepare();
                return;
            }

            const translateBtn = event.target.closest('[data-translate-path]');
            if (translateBtn) {
                selectScope(translateBtn.dataset.translateType || 'file', translateBtn.dataset.translatePath || '');
                await runTranslate();
                return;
            }

            const row = event.target.closest('[data-select-path]');
            if (row) {
                selectScope(row.dataset.selectType || 'file', row.dataset.selectPath || '');
            }
        });

        sourceLang.addEventListener('change', async () => {
            syncUi();
            await refreshTree(true);
        });

        targetLang.addEventListener('input', syncUi);

        syncUi();
        refreshTree(false);
        addLog('Panel hazir. Mod sec, dosya sec ve islemi baslat.', 'info');
    </script>
</body>

</html>