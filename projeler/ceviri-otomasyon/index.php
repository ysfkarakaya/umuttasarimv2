<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['project_authenticated']) || $_SESSION['project_authenticated'] !== true) {
    header('Location: ../index.php');
    exit;
}

@set_time_limit(0);
@ini_set('max_execution_time', '0');

$langRoot = realpath(__DIR__ . '/../../data/lang');
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
    
    // If the path contains indicators of translatable label content, do NOT skip it!
    $translatableIndicators = ['placeholder', 'label', 'title', 'text', 'desc', 'subject', 'content', 'header', 'footer', 'name', 'btn', 'button', 'tooltip', 'badge', 'subtitle'];
    foreach ($translatableIndicators as $indicator) {
        if (strpos($path, $indicator) !== false) {
            return false;
        }
    }
    
    // Split the path into segments (e.g. $.items.0.id -> ['items', '0', 'id'])
    $segments = preg_split('/[.\/]/', $path);
    foreach ($segments as $segment) {
        $segment = trim($segment, '[]$0123456789'); // strip $, brackets, numbers
        if ($segment === '') {
            continue;
        }
        
        // Split segment by underscore or dash to get individual words (e.g. "created_at" -> ["created", "at"])
        $words = preg_split('/[_-]/', $segment);
        
        foreach ($skipKeys as $skipKey) {
            $skipKey = strtolower(trim($skipKey));
            if ($skipKey === '') {
                continue;
            }
            
            // Check if any word in the key matches the skip key exactly
            foreach ($words as $word) {
                if ($word === $skipKey) {
                    return true;
                }
            }
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

function collectTranslatableStrings($data, array &$entries, array $skipKeys, array $path = []): void
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $childPath = array_merge($path, [$key]);
            collectTranslatableStrings($value, $entries, $skipKeys, $childPath);
        }
        return;
    }

    if (!is_string($data)) {
        return;
    }

    $pathStr = implode('.', $path);
    if (!shouldTranslateString($pathStr, $data, $skipKeys)) {
        return;
    }

    $entries[] = ['path' => $path, 'value' => $data];
}

function setValueByPath(array &$data, array $path, string $value): void
{
    $cursor = &$data;
    foreach ($path as $segment) {
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
        . "CRITICAL: Do not translate the brand names \"Umut Tasarım\" and \"Umut Tasarım ve kent ekipmanları\" under any circumstances. Keep them exactly as they are in all target languages. "
        . "Translate natural language only. Output format: [{\"index\":0,\"text\":\"translated\"}]. Input: "
        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function geminiTranslateBatch(string $apiProvider, string $apiKey, string $model, string $sourceLang, string $targetLang, array $items): array
{
    if ($apiProvider === 'vertex') {
        $url = 'https://vertex.claude.gg/v1/projects/test/locations/global/publishers/google/models/' . rawurlencode($model) . ':generateContent';
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey
        ];
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
    } elseif ($apiProvider === 'claudegg') {
        $url = 'https://api.claude.gg/v1/chat/completions';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        $body = [
            'model' => $model,
            'messages' => [[
                'role' => 'user',
                'content' => buildPrompt($sourceLang, $targetLang, $items),
            ]],
            'temperature' => 0.2,
            'max_tokens' => 8192
        ];
    } else {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $headers = [
            'Content-Type: application/json'
        ];
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
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $displayUrl = $apiProvider === 'claudegg' ? $url : ($apiProvider === 'vertex' ? $url : 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
    $providerName = $apiProvider === 'claudegg' ? 'Claude.gg' : ($apiProvider === 'vertex' ? 'Vertex' : 'Gemini');

    if ($error) {
        $errData = [
            'provider' => $providerName,
            'status' => 0,
            'url' => $displayUrl,
            'model' => $model,
            'message' => 'Bağlantı Hatası: ' . $error,
            'response' => ''
        ];
        throw new RuntimeException('API_ERROR_JSON:' . json_encode($errData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    if ($status < 200 || $status >= 300) {
        $errData = [
            'provider' => $providerName,
            'status' => $status,
            'url' => $displayUrl,
            'model' => $model,
            'message' => 'HTTP Hatası: ' . $status,
            'response' => (string) $response
        ];
        throw new RuntimeException('API_ERROR_JSON:' . json_encode($errData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $decoded = json_decode((string) $response, true);
    if ($apiProvider === 'claudegg') {
        $text = $decoded['choices'][0]['message']['content'] ?? null;
    } else {
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    if (!is_string($text) || trim($text) === '') {
        $errData = [
            'provider' => $providerName,
            'status' => $status,
            'url' => $displayUrl,
            'model' => $model,
            'message' => 'Boş veya geçersiz yanıt formatı.',
            'response' => (string) $response
        ];
        throw new RuntimeException('API_ERROR_JSON:' . json_encode($errData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $text = trim($text);
    if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $text, $matches)) {
        $text = trim($matches[1]);
    }

    $translated = json_decode($text, true);
    if (!is_array($translated)) {
        $errData = [
            'provider' => $providerName,
            'status' => $status,
            'url' => $displayUrl,
            'model' => $model,
            'message' => 'Model yanıtı geçerli JSON formatında değil.',
            'response' => (string) $response
        ];
        throw new RuntimeException('API_ERROR_JSON:' . json_encode($errData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // 1. If wrapped in standard response keys
    foreach (['items', 'translations', 'choices', 'data', 'results', 'list'] as $key) {
        if (isset($translated[$key]) && is_array($translated[$key])) {
            $translated = $translated[$key];
            break;
        }
    }

    // 2. If it's a single translation object (e.g. {"index":0,"text":"..."}), wrap it
    if (isset($translated['index']) && isset($translated['text'])) {
        $translated = [$translated];
    }

    // 3. If it's an associative array where keys are indexes (e.g. {"0": "text"} or {"0": {"text": "..."}})
    if (!isset($translated[0]) && count($translated) > 0) {
        $newList = [];
        foreach ($translated as $key => $val) {
            if (is_numeric($key)) {
                if (is_array($val) && isset($val['text'])) {
                    $newList[] = [
                        'index' => (int) $key,
                        'text' => $val['text']
                    ];
                } elseif (is_array($val) && isset($val['index'], $val['text'])) {
                    $newList[] = $val;
                } elseif (is_string($val)) {
                    $newList[] = [
                        'index' => (int) $key,
                        'text' => $val
                    ];
                }
            }
        }
        if (count($newList) > 0) {
            $translated = $newList;
        }
    }

    $mapped = [];
    foreach ($translated as $row) {
        if (!isset($row['index'], $row['text'])) {
            continue;
        }
        $mapped[(int) $row['index']] = (string) $row['text'];
    }

    $result = [];
    foreach ($items as $index => $item) {
        if (isset($mapped[$index])) {
            $result[$index] = $mapped[$index];
        } else {
            $result[$index] = $item['value'];
        }
    }
    return $result;
}

function translateJsonFile(string $apiProvider, string $sourceFile, string $targetFile, string $apiKey, string $model, string $sourceLang, string $targetLang, array $skipKeys): array
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
        $translations = geminiTranslateBatch($apiProvider, $apiKey, $model, $sourceLang, $targetLang, $chunk);
        foreach ($chunk as $index => $entry) {
            setValueByPath($data, $entry['path'], $translations[$index]);
        }
    }

    ensureDir(dirname($targetFile));
    file_put_contents($targetFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return ['translated_count' => count($entries), 'copied_only' => false];
}

function translateMultipleJsonFiles(string $apiProvider, array $pairs, string $apiKey, string $model, string $sourceLang, string $targetLang, array $skipKeys): array
{
    $filesData = [];
    $allEntries = [];

    foreach ($pairs as $pairIndex => $pair) {
        if (!file_exists($pair['source'])) {
            continue;
        }
        $content = file_get_contents($pair['source']);
        if ($content === false) {
            throw new RuntimeException('Kaynak dosya okunamadi: ' . $pair['source']);
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Gecersiz JSON: ' . basename($pair['source']));
        }

        $entries = [];
        collectTranslatableStrings($data, $entries, $skipKeys);

        $filesData[$pairIndex] = [
            'pair' => $pair,
            'data' => $data,
            'entries' => $entries,
        ];

        foreach ($entries as $entryIndex => $entry) {
            $allEntries[] = [
                'file_index' => $pairIndex,
                'entry_index' => $entryIndex,
                'value' => $entry['value'],
            ];
        }
    }

    $summary = [
        'files_total' => count($pairs),
        'files_done' => 0,
        'strings_translated' => 0,
        'copied_only' => 0,
        'errors' => [],
        'details' => [],
    ];

    foreach ($filesData as $pairIndex => $fData) {
        $summary['details'][$fData['pair']['relative']] = [
            'status' => empty($fData['entries']) ? 'copied' : 'success',
            'strings_translated' => count($fData['entries']),
            'error' => null,
            'time' => time(),
        ];
    }

    if (empty($allEntries)) {
        foreach ($filesData as $fData) {
            copyFileWithDir($fData['pair']['source'], $fData['pair']['target']);
            $summary['copied_only']++;
            $summary['files_done']++;
        }
        return $summary;
    }

    $chunks = array_chunk($allEntries, 40);
    foreach ($chunks as $chunkIndex => $chunk) {
        try {
            $items = [];
            foreach ($chunk as $entryRef) {
                $items[] = ['value' => $entryRef['value']];
            }

            $translations = geminiTranslateBatch($apiProvider, $apiKey, $model, $sourceLang, $targetLang, $items);

            foreach ($chunk as $idx => $entryRef) {
                $fileIndex = $entryRef['file_index'];
                $entryIndex = $entryRef['entry_index'];
                $translatedText = $translations[$idx] ?? '';

                $entryPath = $filesData[$fileIndex]['entries'][$entryIndex]['path'];
                setValueByPath($filesData[$fileIndex]['data'], $entryPath, $translatedText);
                $summary['strings_translated']++;
            }
        } catch (Throwable $exception) {
            $summary['errors'][] = 'Grup ' . ($chunkIndex + 1) . ' hatasi: ' . $exception->getMessage();
            foreach ($chunk as $entryRef) {
                $fileIndex = $entryRef['file_index'];
                $relPath = $filesData[$fileIndex]['pair']['relative'];
                $summary['details'][$relPath] = [
                    'status' => 'error',
                    'strings_translated' => 0,
                    'error' => $exception->getMessage(),
                    'time' => time(),
                ];
            }
        }
    }

    foreach ($filesData as $fData) {
        $relPath = $fData['pair']['relative'];
        if (isset($summary['details'][$relPath]) && $summary['details'][$relPath]['status'] === 'error') {
            continue;
        }

        try {
            if (empty($fData['entries'])) {
                copyFileWithDir($fData['pair']['source'], $fData['pair']['target']);
                $summary['copied_only']++;
            } else {
                ensureDir(dirname($fData['pair']['target']));
                file_put_contents($fData['pair']['target'], json_encode($fData['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $summary['files_done']++;
        } catch (Throwable $exception) {
            $summary['errors'][] = $fData['pair']['relative'] . ' yazilamadi: ' . $exception->getMessage();
            $summary['details'][$relPath] = [
                'status' => 'error',
                'strings_translated' => 0,
                'error' => $exception->getMessage(),
                'time' => time(),
            ];
        }
    }

    return $summary;
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
            $sourceLang = 'tr';
            jsonResponse([
                'status' => 'success',
                'options' => collectPathOptions(safePath($langRoot, $sourceLang)),
            ]);
        }

        if ($action === 'tree') {
            $sourceLang = 'tr';
            $targetLang = trim(strtolower($_GET['target_lang'] ?? 'en'));
            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili hedef/duzenlenen dil olamaz. Yapay zeka cevirisi calismaz.');
            }
            $sourceBase = safePath($langRoot, $sourceLang);
            $history = $_SESSION['translation_history'][$targetLang] ?? [];
            jsonResponse([
                'status' => 'success',
                'tree' => buildTreeNodes($sourceBase),
                'history' => $history,
            ]);
        }

        if ($action === 'clear_history') {
            unset($_SESSION['translation_history']);
            jsonResponse([
                'status' => 'success',
                'message' => 'Ceviri gecmisi sifirlandi.',
            ]);
        }

        if ($action === 'check_untranslated') {
            $sourceLang = 'tr';
            $targetLang = trim(strtolower($_GET['target_lang'] ?? 'en'));
            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili hedef/duzenlenen dil olamaz. Yapay zeka cevirisi calismaz.');
            }
            $sourceBase = safePath($langRoot, $sourceLang);
            $targetBase = safePath($langRoot, $targetLang);

            $sourceFiles = [];
            $collect = static function ($dir, $baseLen) use (&$sourceFiles, &$collect) {
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    $full = $dir . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($full)) {
                        $collect($full, $baseLen);
                    } elseif (is_file($full) && strtolower(pathinfo($full, PATHINFO_EXTENSION)) === 'json') {
                        $sourceFiles[] = substr($full, $baseLen);
                    }
                }
            };
            $collect($sourceBase, strlen($sourceBase) + 1);

            $untranslated = [];
            foreach ($sourceFiles as $relPath) {
                $normalizedRel = str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
                $srcFile = $sourceBase . DIRECTORY_SEPARATOR . $relPath;
                $tgtFile = $targetBase . DIRECTORY_SEPARATOR . $relPath;

                if (!is_file($tgtFile)) {
                    $untranslated[] = $normalizedRel;
                } else {
                    $srcContent = file_get_contents($srcFile);
                    $tgtContent = file_get_contents($tgtFile);
                    if ($srcContent === $tgtContent) {
                        $untranslated[] = $normalizedRel;
                    }
                }
            }

            jsonResponse([
                'status' => 'success',
                'untranslated' => $untranslated,
            ]);
        }

        if ($action === 'prepare') {
            $sourceLang = 'tr';
            $targetLang = trim(strtolower($_POST['target_lang'] ?? 'en'));
            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili hedef/duzenlenen dil olamaz. Yapay zeka cevirisi calismaz.');
            }
            $scope = $_POST['scope'] ?? 'all';
            if ($scope === 'batch') {
                $relativePaths = json_decode($_POST['relative_paths'] ?? '[]', true);
                if (!is_array($relativePaths)) {
                    throw new RuntimeException('Gecersiz relative_paths parametresi.');
                }
                foreach ($relativePaths as $relPath) {
                    prepareTargetScope($langRoot, $sourceLang, $targetLang, 'file', normalizeRelativePath($relPath));
                }
                jsonResponse([
                    'status' => 'success',
                    'message' => 'Secilen batch dosyalari ' . strtoupper($targetLang) . ' icin kopyalandi.',
                ]);
            } else {
                $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
                prepareTargetScope($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
                jsonResponse([
                    'status' => 'success',
                    'message' => strtoupper($sourceLang) . ' klasoru ' . strtoupper($targetLang) . ' icin hazirlandi.',
                ]);
            }
        }

        if ($action === 'inspect') {
            $sourceLang = 'tr';
            $targetLang = trim(strtolower($_POST['target_lang'] ?? 'en'));
            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili hedef/duzenlenen dil olamaz. Yapay zeka cevirisi calismaz.');
            }
            $scope = $_POST['scope'] ?? 'all';
            ensureDir($langRoot . DIRECTORY_SEPARATOR . $targetLang);
            if ($scope === 'batch') {
                $relativePaths = json_decode($_POST['relative_paths'] ?? '[]', true);
                if (!is_array($relativePaths)) {
                    throw new RuntimeException('Gecersiz relative_paths parametresi.');
                }
                $pairs = [];
                $targetBase = safePath($langRoot, $targetLang);
                foreach ($relativePaths as $relPath) {
                    $normalized = normalizeRelativePath($relPath);
                    $sourceBase = safePath($langRoot, $sourceLang);
                    $sourcePath = safePath($sourceBase, $normalized);
                    if (is_file($sourcePath) && strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'json') {
                        $pairs[] = [
                            'relative' => $normalized,
                            'source' => $sourcePath,
                            'target' => $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
                        ];
                    }
                }
            } else {
                $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
                $pairs = collectJsonFiles($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            }
            jsonResponse([
                'status' => 'success',
                'summary' => [
                    'json_files' => count($pairs),
                    'target_lang_exists' => is_dir($langRoot . DIRECTORY_SEPARATOR . $targetLang),
                    'sample' => array_slice(array_map(static fn ($pair) => $pair['relative'], $pairs), 0, 12),
                    'files' => array_map(static fn ($pair) => $pair['relative'], $pairs),
                ],
            ]);
        }

        if ($action === 'translate') {
            $sourceLang = 'tr';
            $targetLang = trim(strtolower($_POST['target_lang'] ?? 'en'));
            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili hedef/duzenlenen dil olamaz. Yapay zeka cevirisi calismaz.');
            }
            $scope = $_POST['scope'] ?? 'all';
            $apiProvider = trim((string) ($_POST['api_provider'] ?? 'gemini'));
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $model = trim((string) ($_POST['model'] ?? $defaultModel));
            $prepareFirst = !empty($_POST['prepare_first']);
            $skipKeys = array_filter(array_map('trim', explode(',', (string) ($_POST['skip_keys'] ?? implode(', ', $defaultSkipKeys)))));

            if ($apiKey === '') {
                throw new RuntimeException('API key gerekli.');
            }

            if ($scope === 'batch') {
                $relativePaths = json_decode($_POST['relative_paths'] ?? '[]', true);
                if (!is_array($relativePaths)) {
                    throw new RuntimeException('Gecersiz relative_paths parametresi.');
                }

                if ($prepareFirst || !is_dir($langRoot . DIRECTORY_SEPARATOR . $targetLang)) {
                    foreach ($relativePaths as $relPath) {
                        prepareTargetScope($langRoot, $sourceLang, $targetLang, 'file', normalizeRelativePath($relPath));
                    }
                }

                $pairs = [];
                $targetBase = safePath($langRoot, $targetLang);
                foreach ($relativePaths as $relPath) {
                    $normalized = normalizeRelativePath($relPath);
                    $sourceBase = safePath($langRoot, $sourceLang);
                    $sourcePath = safePath($sourceBase, $normalized);
                    if (is_file($sourcePath) && strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'json') {
                        $pairs[] = [
                            'relative' => $normalized,
                            'source' => $sourcePath,
                            'target' => $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
                        ];
                    }
                }
            } else {
                $relativePath = normalizeRelativePath($_POST['relative_path'] ?? '');
                if ($prepareFirst || !is_dir($langRoot . DIRECTORY_SEPARATOR . $targetLang)) {
                    prepareTargetScope($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
                }
                $pairs = collectJsonFiles($langRoot, $sourceLang, $targetLang, $scope, $relativePath);
            }

            $summary = translateMultipleJsonFiles($apiProvider, $pairs, $apiKey, $model, $sourceLang, $targetLang, $skipKeys);

            if (!isset($_SESSION['translation_history'])) {
                $_SESSION['translation_history'] = [];
            }
            if (!isset($_SESSION['translation_history'][$targetLang])) {
                $_SESSION['translation_history'][$targetLang] = [];
            }
            if (isset($summary['details']) && is_array($summary['details'])) {
                foreach ($summary['details'] as $relPath => $info) {
                    $_SESSION['translation_history'][$targetLang][$relPath] = $info;
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
            transition: all 0.2s ease;
        }

        .tree-row.active {
            border-color: var(--primary);
            background: var(--primary-soft);
        }

        .tree-row.checked {
            border-color: rgba(15, 118, 110, 0.35);
            background: rgba(15, 118, 110, 0.05);
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

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-content {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.25s ease-out;
        }

        .modal-head {
            padding: 16px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfd;
        }

        .modal-head strong {
            font-size: 1.05rem;
            color: var(--text);
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--muted);
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            transition: color 0.2s;
        }

        .modal-close-btn:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 16px;
            overflow: auto;
            background: #f8fafc;
            flex-grow: 1;
        }

        .modal-body pre {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: #334155;
            white-space: pre-wrap;
            word-break: break-all;
            background: #fff;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            border: 1px solid transparent;
            margin-left: 6px;
            text-transform: uppercase;
        }
        .status-badge.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .status-badge.copied {
            background: #f3f4f6;
            border-color: #e5e7eb;
            color: #374151;
        }
        .status-badge.error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
            cursor: pointer;
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
                <button class="btn warn" id="clear-history-btn" type="button" style="padding: 7px 12px; font-size: 0.78rem;">Geçmişi Sıfırla</button>
                <button class="btn blue" id="check-untranslated-btn" type="button" style="padding: 7px 12px; font-size: 0.78rem;">Çevrilmeyenleri Kontrol Et</button>
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
                            <select id="source-lang" disabled>
                                <option value="tr" selected>TR (TÜRKÇE)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="target-lang">Hedef dil</label>
                            <input id="target-lang" value="en" placeholder="en, de, ar...">
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="api-provider">API Sağlayıcı</label>
                            <select id="api-provider">
                                <option value="gemini">Gemini (Resmi)</option>
                                <option value="vertex">Vertex (vertex.claude.gg)</option>
                                <option value="claudegg">Claude.gg (api.claude.gg)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="model">Model</label>
                            <input id="model" value="<?php echo htmlspecialchars($defaultModel, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="api-key">API Key</label>
                        <input id="api-key" type="password" placeholder="AIza...">
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
                            <div class="mode" data-mode="batch">
                                <strong>BATCH - Toplu Seçim</strong>
                                <small>Dizin ağacından istediğiniz dosyaları seçip toplu çevirin.</small>
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
                        <button class="btn warn" id="clear-selected-btn" type="button" style="display: none;">Seçimi Temizle</button>
                        <button class="btn primary" id="select-untranslated-btn" type="button" style="display: none;">Sadece Değiştirilmeyenleri Seç</button>
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

    <!-- Hata Detay Modalı -->
    <div id="error-modal" class="modal-backdrop" style="display: none;">
        <div class="modal-content">
            <div class="modal-head">
                <strong>Hata Detayları</strong>
                <button id="close-modal-btn" class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <pre id="modal-error-details"></pre>
            </div>
        </div>
    </div>

    <script>
        const sourceLang = document.getElementById('source-lang');
        const targetLang = document.getElementById('target-lang');
        const apiProvider = document.getElementById('api-provider');
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
        let translationHistory = {};
        let selectedBatchFiles = new Set();
        let lastClickedPath = null;
        let hasCheckedUntranslated = false;
        let untranslatedFiles = [];

        apiProvider.value = localStorage.getItem('api_provider') || 'gemini';
        apiKey.value = localStorage.getItem('gemini_api_key') || '';
        model.value = localStorage.getItem('gemini_model') || model.value;
        targetLang.value = localStorage.getItem('target_lang') || targetLang.value;
        skipKeys.value = localStorage.getItem('translation_skip_keys') || skipKeys.value;

        function updateApiKeyPlaceholder() {
            if (apiProvider.value === 'vertex') {
                apiKey.placeholder = 'Vertex API Key (x-goog-api-key)...';
            } else if (apiProvider.value === 'claudegg') {
                apiKey.placeholder = 'Claude.gg Bearer Token (sk-...)...';
            } else {
                apiKey.placeholder = 'AIza...';
            }
        }

        apiProvider.addEventListener('change', () => {
            updateApiKeyPlaceholder();
            savePrefs();
        });

        updateApiKeyPlaceholder();

        function addLog(message, type = 'info', details = null) {
            const row = document.createElement('div');
            row.className = 'log-row';
            
            let displayMessage = message;
            let detailBtnHtml = '';
            
            if (details) {
                if (details.includes('API_ERROR_JSON:')) {
                    try {
                        const jsonStr = details.substring(details.indexOf('API_ERROR_JSON:') + 15);
                        const errObj = JSON.parse(jsonStr);
                        const prefix = message.substring(0, message.indexOf('API_ERROR_JSON:'));
                        displayMessage = `${prefix}${errObj.message} (HTTP ${errObj.status})`;
                    } catch (e) {
                        const parts = details.split('|');
                        if (parts.length > 1) {
                            displayMessage = parts[0].trim();
                        }
                    }
                } else {
                    const parts = details.split('|');
                    if (parts.length > 1) {
                        displayMessage = parts[0].trim();
                    }
                }
                const escDetails = escapeHtml(details);
                detailBtnHtml = ` <button class="btn-inline" style="padding: 2px 6px; font-size: 0.65rem; margin-left: 6px; border-color: var(--danger); color: var(--danger); background: #fef2f2; font-weight: bold; cursor: pointer;" type="button" data-error-detail="${escDetails}">Hata Detayı</button>`;
            }

            row.innerHTML = `
                <span class="log-time">${new Date().toLocaleTimeString()}</span>
                <span class="log-kind ${type}">${type}</span>
                <div class="log-message">${displayMessage}${detailBtnHtml}</div>
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
            localStorage.setItem('api_provider', apiProvider.value);
            localStorage.setItem('gemini_api_key', apiKey.value.trim());
            localStorage.setItem('gemini_model', model.value.trim());
            localStorage.setItem('target_lang', targetLang.value.trim());
            localStorage.setItem('translation_skip_keys', skipKeys.value);
        }

        function selectedText() {
            if (selectedMode === 'all') return 'Tum kaynak dil klasoru';
            if (selectedMode === 'batch') return `${selectedBatchFiles.size} dosya seçildi`;
            if (selectedPath) return selectedPath;
            return selectedMode === 'directory' ? 'Bir klasor secin' : 'Bir JSON dosyasi secin';
        }

        function syncUi() {
            const isTr = targetLang.value.trim().toLowerCase() === 'tr';
            
            const inspectBtn = document.getElementById('inspect-btn');
            const prepareBtn = document.getElementById('prepare-btn');
            const translateBtn = document.getElementById('translate-btn');
            const checkUntranslatedBtn = document.getElementById('check-untranslated-btn');
            
            [inspectBtn, prepareBtn, translateBtn, checkUntranslatedBtn].forEach(btn => {
                if (btn) {
                    btn.disabled = isTr || busy;
                    if (isTr) {
                        btn.style.opacity = '0.5';
                        btn.title = 'Türkçe dili hedef/düzenlenen dil olduğu için işlem yapılamaz.';
                    } else {
                        btn.style.opacity = '1';
                        btn.title = '';
                    }
                }
            });

            chipSource.textContent = `SRC: TR`;
            chipTarget.textContent = `TGT: ${(targetLang.value.trim() || 'EN').toUpperCase()}`;
            chipMode.textContent = `MODE: ${selectedMode === 'all' ? 'FULL' : selectedMode === 'directory' ? 'DIR' : selectedMode === 'batch' ? 'BATCH' : 'JSON'}`;
            selectionTitle.textContent = selectedMode === 'all' ? 'Secili kapsam: Tum TR dizini' : `Secili kapsam: ${selectedMode === 'directory' ? 'Klasor' : selectedMode === 'batch' ? 'Toplu Seçim' : 'JSON'}`;
            selectionPath.textContent = selectedText();
            relativePath.value = (selectedMode === 'all' || selectedMode === 'batch') ? '' : selectedPath;
            
            const pathField = relativePath.closest('.field');
            if (pathField) {
                pathField.style.display = (selectedMode === 'all' || selectedMode === 'batch') ? 'none' : 'grid';
            }
            const clearSelectedBtn = document.getElementById('clear-selected-btn');
            if (clearSelectedBtn) {
                clearSelectedBtn.style.display = selectedBatchFiles.size > 0 ? 'inline-block' : 'none';
            }
            
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
            if (selectedMode === 'batch') {
                fd.append('relative_paths', JSON.stringify(Array.from(selectedBatchFiles)));
            } else {
                fd.append('relative_path', selectedPath);
            }
            fd.append('prepare_first', prepareFirst.checked ? '1' : '');
            fd.append('skip_keys', skipKeys.value);
            fd.append('api_provider', apiProvider.value);
            fd.append('model', model.value.trim());
            if (includeApi) fd.append('api_key', apiKey.value.trim());
            return fd;
        }

        async function postAction(action, fd) {
            const res = await fetch(`index.php?action=${encodeURIComponent(action)}`, {
                method: 'POST',
                body: fd
            });
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Sunucu geçersiz yanıt döndürdü.\n\nAPI Yanıtı:\n${text}`);
            }
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
            if (mode !== 'batch') {
                selectedBatchFiles.clear();
            }
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

        function collectAllFilesUnderDir(dirPath) {
            const files = [];
            const findNode = (nodes) => {
                for (const node of nodes) {
                    if (node.path === dirPath) {
                        const collect = (n) => {
                            if (n.type === 'file') {
                                files.push(n.path);
                            } else if (n.type === 'directory' && n.children) {
                                n.children.forEach(collect);
                            }
                        };
                        collect(node);
                        return true;
                    }
                    if (node.type === 'directory' && node.children) {
                        if (findNode(node.children)) return true;
                    }
                }
                return false;
            };
            findNode(treeData);
            return files;
        }

        function renderNodes(nodes) {
            if (!nodes.length) {
                return '<div style="padding:14px;border:1px solid var(--line);border-radius:12px;color:var(--muted);">Sonuc bulunamadi.</div>';
            }

            const showActions = true;

            return `<ul class="tree-list">${nodes.map((node) => {
                const hasChildren = node.type === 'directory' && (node.children || []).length > 0;
                const isOpen = expanded.has(node.path);
                const active = selectedMode === node.type && selectedPath === node.path;
                const meta = node.type === 'directory' ? `${node.json_count || 0} JSON` : node.path;

                let badgeHtml = '';
                if (node.type === 'file' && translationHistory && translationHistory[node.path]) {
                    const hist = translationHistory[node.path];
                    if (hist.status === 'success') {
                        badgeHtml = `<span class="status-badge success">Başarılı (${hist.strings_translated})</span>`;
                    } else if (hist.status === 'copied') {
                        badgeHtml = `<span class="status-badge copied">Kopyalandı</span>`;
                    } else if (hist.status === 'error') {
                        const escErr = escapeHtml(hist.error || 'Bilinmeyen hata');
                        badgeHtml = `<span class="status-badge error" data-error-detail="${escErr}" title="Hata Detayı İçin Tıklayın">Hata</span>`;
                    }
                }

                let untranslatedBadgeHtml = '';
                if (hasCheckedUntranslated) {
                    if (node.type === 'file') {
                        const isUntranslated = untranslatedFiles.includes(node.path);
                        if (isUntranslated) {
                            untranslatedBadgeHtml = `<span class="status-badge error">Çevrilmemiş</span>`;
                        } else {
                            untranslatedBadgeHtml = `<span class="status-badge success">Çevrilmiş</span>`;
                        }
                    } else if (node.type === 'directory') {
                        const childFiles = collectAllFilesUnderDir(node.path);
                        const untranslatedCount = childFiles.filter(file => untranslatedFiles.includes(file)).length;
                        if (untranslatedCount > 0) {
                            untranslatedBadgeHtml = `<span class="status-badge error" style="text-transform: none;">${untranslatedCount} Çevrilmemiş</span>`;
                        } else if (childFiles.length > 0) {
                            untranslatedBadgeHtml = `<span class="status-badge success" style="text-transform: none;">Hepsi Çevrilmiş</span>`;
                        }
                    }
                }

                let isChecked = false;
                if (node.type === 'file') {
                    isChecked = selectedBatchFiles.has(node.path);
                } else if (node.type === 'directory') {
                    const childFiles = collectAllFilesUnderDir(node.path);
                    isChecked = childFiles.length > 0 && childFiles.every(file => selectedBatchFiles.has(file));
                }
                const checkboxHtml = `<input type="checkbox" class="tree-checkbox" data-checkbox-type="${node.type}" data-checkbox-path="${escapeHtml(node.path)}" ${isChecked ? 'checked' : ''} style="width:18px; height:18px; cursor:pointer; margin-right:8px;" onclick="event.stopPropagation();">`;

                const rowChecked = isChecked;

                return `
                    <li>
                        <div class="tree-row ${active ? 'active' : ''} ${rowChecked ? 'checked' : ''}" data-select-type="${node.type}" data-select-path="${escapeHtml(node.path)}">
                            <div class="tree-main">
                                <button class="tree-toggle ${hasChildren ? '' : 'placeholder'}" type="button" ${hasChildren ? `data-toggle-path="${escapeHtml(node.path)}"` : ''}>${hasChildren ? (isOpen ? '-' : '+') : '+'}</button>
                                ${checkboxHtml}
                                <div class="tree-icon">${node.type === 'directory' ? 'DIR' : 'JSON'}</div>
                                <div class="tree-copy">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <strong>${escapeHtml(node.name)}</strong>
                                        ${badgeHtml}
                                        ${untranslatedBadgeHtml}
                                    </div>
                                    <span>${escapeHtml(meta)}</span>
                                </div>
                            </div>
                            ${showActions ? `
                            <div class="tree-actions">
                                <button class="btn-inline" type="button" data-copy-type="${node.type}" data-copy-path="${escapeHtml(node.path)}">Kopyala</button>
                                <button class="btn-inline primary" type="button" data-translate-type="${node.type}" data-translate-path="${escapeHtml(node.path)}">Cevir</button>
                            </div>
                            ` : ''}
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
            const res = await fetch(`index.php?action=tree&source_lang=${encodeURIComponent(sourceLang.value)}&target_lang=${encodeURIComponent(targetLang.value.trim())}`);
            const data = await res.json();
            if (data.status !== 'success') {
                addLog(data.message || 'Dosya agaci alinamadi.', 'error');
                return;
            }
            treeData = data.tree || [];
            translationHistory = data.history || {};
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
                addLog('Analiz sirasinda hata olustu.', 'error', error.message);
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
                addLog('Kopyalama sirasinda hata olustu.', 'error', error.message);
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
            addLog(`${selectedText()} için çeviri süreci başlatılıyor...`, 'info');

            try {
                // 1. Analiz et ve dosya listesini al
                const inspectResult = await postAction('inspect', buildFormData(false));
                if (inspectResult.status !== 'success' || !inspectResult.summary || !inspectResult.summary.files) {
                    addLog(inspectResult.message || 'Dosya listesi alınamadı.', 'error');
                    setBusy(false);
                    return;
                }

                const files = inspectResult.summary.files;
                const totalFiles = files.length;
                if (totalFiles === 0) {
                    addLog('Çevrilecek JSON dosyası bulunamadı.', 'warning');
                    setBusy(false);
                    return;
                }

                addLog(`Toplam ${totalFiles} dosya tespit edildi.`, 'info');

                // 2. Eğer "Çeviri öncesi hedef dili kaynak yapıdan hazırla" seçiliyse bir kez kopyalama/hazırlama işlemi çalıştır
                if (prepareFirst.checked) {
                    addLog('Hedef dil klasör yapısı hazırlanıyor...', 'warning');
                    const prepResult = await postAction('prepare', buildFormData(false));
                    if (prepResult.status !== 'success') {
                        addLog('Klasör yapısı hazırlanırken hata oluştu, işleme devam ediliyor...', 'warning');
                    } else {
                        addLog('Klasör yapısı hazırlandı.', 'success');
                    }
                }

                const summary = {
                    files_total: totalFiles,
                    files_done: 0,
                    strings_translated: 0,
                    copied_only: 0,
                    errors: []
                };

                updateSummary(summary);

                // Dosyaları tek tek paketle
                const batchSize = 1;
                const fileBatches = [];
                for (let i = 0; i < files.length; i += batchSize) {
                    fileBatches.push(files.slice(i, i + batchSize));
                }

                const totalBatches = fileBatches.length;
                addLog(`Dosyalar ${totalBatches} adımda tek tek çevrilecek.`, 'info');

                // 3. Her bir dosyayı sırayla çevir
                for (let b = 0; b < totalBatches; b++) {
                    const batchFiles = fileBatches[b];
                    const fileName = batchFiles[0].split('/').pop();
                    addLog(`[Dosya ${b + 1}/${totalBatches}] ${fileName} işleniyor...`, 'info');

                    const fd = new FormData();
                    fd.append('source_lang', sourceLang.value);
                    fd.append('target_lang', targetLang.value.trim());
                    fd.append('scope', 'batch');
                    fd.append('relative_paths', JSON.stringify(batchFiles));
                    fd.append('prepare_first', '');
                    fd.append('skip_keys', skipKeys.value);
                    fd.append('api_provider', apiProvider.value);
                    fd.append('model', model.value.trim());
                    fd.append('api_key', apiKey.value.trim());

                    try {
                        const result = await postAction('translate', fd);
                        if (result.status === 'error') {
                            batchFiles.forEach(file => {
                                summary.errors.push(`${file}: ${result.message || 'Bilinmeyen hata'}`);
                                addLog(`${file} çevrilemedi: ${result.message}`, 'error', `${file}: ${result.message}`);
                            });
                        } else {
                            summary.files_done += result.summary.files_done || 0;
                            summary.strings_translated += result.summary.strings_translated || 0;
                            summary.copied_only += result.summary.copied_only || 0;
                            
                            if (result.summary.details) {
                                Object.assign(translationHistory, result.summary.details);
                                // Remove translated files from untranslatedFiles array
                                batchFiles.forEach(file => {
                                    const idx = untranslatedFiles.indexOf(file);
                                    if (idx !== -1) {
                                        untranslatedFiles.splice(idx, 1);
                                    }
                                });
                                renderTree();
                            }

                            if (result.summary.errors && result.summary.errors.length) {
                                result.summary.errors.forEach(e => {
                                    summary.errors.push(e);
                                    addLog(e, 'error', e);
                                });
                            }
                            
                            addLog(`[Dosya ${b + 1}/${totalBatches}] ${fileName} çevrildi. (${result.summary.strings_translated || 0} metin)`, 'success');
                        }
                    } catch (batchErr) {
                        batchFiles.forEach(file => {
                            summary.errors.push(`${file}: ${batchErr.message}`);
                            addLog(`${file} çevrilirken sistem hatası: ${batchErr.message}`, 'error', batchErr.message);
                            translationHistory[file] = {
                                status: 'error',
                                strings_translated: 0,
                                error: batchErr.message,
                                time: Date.now() / 1000
                            };
                        });
                        renderTree();
                    }

                    updateSummary(summary);

                    // API rate limit aşımını önlemek için gruplar arasında 600ms cooldown bekle
                    if (b < totalBatches - 1) {
                        await new Promise(resolve => setTimeout(resolve, 600));
                    }
                }

                // 4. Bitiş logunu yazdır
                if (summary.errors.length === 0) {
                    addLog(`Çeviri işlemi başarıyla tamamlandı! ${summary.files_done}/${totalFiles} dosya başarıyla işlendi.`, 'success');
                } else {
                    addLog(`Çeviri işlemi kısmi başarıyla tamamlandı. ${summary.files_done}/${totalFiles} dosya işlendi, ${summary.errors.length} hata alındı.`, 'warning');
                }

            } catch (err) {
                addLog('Çeviri süreci başlatılamadı veya kesildi.', 'error', err.message);
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

        document.getElementById('check-untranslated-btn').addEventListener('click', async () => {
            if (busy) return;
            setBusy(true, 'Kontrol ediliyor');
            addLog('Kaynak ve hedef dil dosyaları karşılaştırılıyor...', 'info');
            try {
                const res = await fetch(`index.php?action=check_untranslated&source_lang=${encodeURIComponent(sourceLang.value)}&target_lang=${encodeURIComponent(targetLang.value.trim())}`);
                const data = await res.json();
                if (data.status === 'success') {
                    untranslatedFiles = data.untranslated || [];
                    hasCheckedUntranslated = true;
                    renderTree();
                    addLog(`Karşılaştırma tamamlandı: ${untranslatedFiles.length} adet değiştirilmemiş/çevrilmemiş dosya bulundu.`, untranslatedFiles.length > 0 ? 'warning' : 'success');
                    
                    const selectUntranslatedBtn = document.getElementById('select-untranslated-btn');
                    if (selectUntranslatedBtn) {
                        selectUntranslatedBtn.style.display = untranslatedFiles.length > 0 ? 'inline-block' : 'none';
                    }
                } else {
                    addLog(data.message || 'Kontrol başarısız.', 'error');
                }
            } catch (err) {
                addLog('Karşılaştırma sırasında hata oluştu.', 'error', err.message);
            } finally {
                setBusy(false);
            }
        });

        document.getElementById('select-untranslated-btn').addEventListener('click', () => {
            if (untranslatedFiles.length === 0) return;
            selectedBatchFiles.clear();
            untranslatedFiles.forEach(file => selectedBatchFiles.add(file));
            selectedMode = 'batch';
            
            const selectUntranslatedBtn = document.getElementById('select-untranslated-btn');
            if (selectUntranslatedBtn) {
                selectUntranslatedBtn.style.display = 'none';
            }
            
            syncUi();
            renderTree();
            addLog(`${untranslatedFiles.length} adet çevrilmemiş/değiştirilmemiş dosya toplu seçime eklendi.`, 'success');
        });

        document.getElementById('refresh-tree-btn').addEventListener('click', () => refreshTree(true));
        const clearSelectedBtn = document.getElementById('clear-selected-btn');
        if (clearSelectedBtn) {
            clearSelectedBtn.addEventListener('click', () => {
                selectedBatchFiles.clear();
                syncUi();
                renderTree();
                addLog('Seçilen tüm dosyalar temizlendi.', 'info');
            });
        }
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

        function showModalWithError(rawDetail) {
            // Decode HTML entities
            rawDetail = rawDetail.replace(/&amp;/g, '&')
                                 .replace(/&lt;/g, '<')
                                 .replace(/&gt;/g, '>')
                                 .replace(/&quot;/g, '"')
                                 .replace(/&#039;/g, "'");

            if (rawDetail.includes('API_ERROR_JSON:')) {
                try {
                    const jsonStr = rawDetail.substring(rawDetail.indexOf('API_ERROR_JSON:') + 15);
                    const errObj = JSON.parse(jsonStr);
                    
                    let formattedText = `Hizmet Sağlayıcı: ${errObj.provider}\n`;
                    formattedText += `Model: ${errObj.model}\n`;
                    formattedText += `HTTP Durumu: ${errObj.status}\n`;
                    formattedText += `İstek URL: ${errObj.url}\n`;
                    formattedText += `Hata Açıklaması: ${errObj.message}\n\n`;
                    formattedText += `Ham API Yanıtı (Raw Response):\n----------------------------------------\n`;
                    formattedText += errObj.response;
                    
                    modalErrorDetails.textContent = formattedText;
                } catch (jsErr) {
                    modalErrorDetails.textContent = rawDetail;
                }
            } else {
                modalErrorDetails.textContent = rawDetail;
            }
            errorModal.style.display = 'flex';
        }

        treeRoot.addEventListener('click', async (event) => {
            const errorBadge = event.target.closest('.status-badge.error');
            if (errorBadge) {
                event.stopPropagation();
                showModalWithError(errorBadge.dataset.errorDetail);
                return;
            }

            if (busy) return;

            let path = null;
            let type = null;
            let targetCheckbox = null;

            const checkbox = event.target.closest('.tree-checkbox');
            const row = event.target.closest('[data-select-path]');

            if (checkbox) {
                event.stopPropagation();
                path = checkbox.dataset.checkboxPath;
                type = checkbox.dataset.checkboxType;
                targetCheckbox = checkbox;
            } else if (row && selectedMode === 'batch') {
                path = row.dataset.selectPath;
                type = row.dataset.selectType;
                targetCheckbox = row.querySelector('.tree-checkbox');
            }

            if (targetCheckbox) {
                // Determine target checked state
                const isChecked = checkbox ? checkbox.checked : !targetCheckbox.checked;
                if (!checkbox) {
                    targetCheckbox.checked = isChecked;
                }

                if (event.shiftKey && lastClickedPath) {
                    const checkboxes = Array.from(treeRoot.querySelectorAll('.tree-checkbox'));
                    const lastIdx = checkboxes.findIndex(cb => cb.getAttribute('data-checkbox-path') === lastClickedPath);
                    const currentIdx = checkboxes.findIndex(cb => cb === targetCheckbox);

                    if (lastIdx !== -1 && currentIdx !== -1) {
                        const start = Math.min(lastIdx, currentIdx);
                        const end = Math.max(lastIdx, currentIdx);

                        for (let i = start; i <= end; i++) {
                            const cb = checkboxes[i];
                            cb.checked = isChecked;
                            const cbPath = cb.getAttribute('data-checkbox-path');
                            const cbType = cb.getAttribute('data-checkbox-type');

                            if (cbType === 'directory') {
                                const childFiles = collectAllFilesUnderDir(cbPath);
                                childFiles.forEach(file => {
                                    if (isChecked) selectedBatchFiles.add(file);
                                    else selectedBatchFiles.delete(file);
                                });
                            } else {
                                if (isChecked) selectedBatchFiles.add(cbPath);
                                else selectedBatchFiles.delete(cbPath);
                            }
                        }
                    }
                } else {
                    if (type === 'directory') {
                        const childFiles = collectAllFilesUnderDir(path);
                        childFiles.forEach(file => {
                            if (isChecked) selectedBatchFiles.add(file);
                            else selectedBatchFiles.delete(file);
                        });
                    } else {
                        if (isChecked) selectedBatchFiles.add(path);
                        else selectedBatchFiles.delete(path);
                    }
                }

                lastClickedPath = path;

                // Update mode dynamically based on checked items count
                if (selectedBatchFiles.size > 0) {
                    selectedMode = 'batch';
                } else {
                    selectedMode = selectedPath ? (selectedPath.endsWith('.json') ? 'file' : 'directory') : 'all';
                }

                syncUi();
                renderTree();
                return;
            }

            const toggleBtn = event.target.closest('[data-toggle-path]');
            if (toggleBtn) {
                toggle(toggleBtn.dataset.togglePath || '');
                return;
            }

            const copyBtn = event.target.closest('[data-copy-path]');
            if (copyBtn) {
                if (targetLang.value.trim().toLowerCase() === 'tr') {
                    alert('Türkçe dili hedef/düzenlenen dil olduğu için işlem yapılamaz.');
                    return;
                }
                selectScope(copyBtn.dataset.copyType || 'file', copyBtn.dataset.copyPath || '');
                await runPrepare();
                return;
            }

            const translateBtn = event.target.closest('[data-translate-path]');
            if (translateBtn) {
                if (targetLang.value.trim().toLowerCase() === 'tr') {
                    alert('Türkçe dili hedef/düzenlenen dil olduğu için işlem yapılamaz.');
                    return;
                }
                selectScope(translateBtn.dataset.translateType || 'file', translateBtn.dataset.translatePath || '');
                await runTranslate();
                return;
            }

            if (row) {
                selectScope(row.dataset.selectType || 'file', row.dataset.selectPath || '');
            }
        });

        sourceLang.addEventListener('change', async () => {
            untranslatedFiles = [];
            hasCheckedUntranslated = false;
            const selectUntranslatedBtn = document.getElementById('select-untranslated-btn');
            if (selectUntranslatedBtn) selectUntranslatedBtn.style.display = 'none';
            syncUi();
            await refreshTree(true);
        });

        targetLang.addEventListener('input', syncUi);
        targetLang.addEventListener('change', async () => {
            untranslatedFiles = [];
            hasCheckedUntranslated = false;
            const selectUntranslatedBtn = document.getElementById('select-untranslated-btn');
            if (selectUntranslatedBtn) selectUntranslatedBtn.style.display = 'none';
            syncUi();
            await refreshTree(false);
        });

        document.getElementById('clear-history-btn').addEventListener('click', async () => {
            if (confirm('Tüm çeviri geçmişini sıfırlamak istediğinize emin misiniz?')) {
                try {
                    const res = await fetch('index.php?action=clear_history', { method: 'POST' });
                    const result = await res.json();
                    if (result.status === 'success') {
                        translationHistory = {};
                        renderTree();
                        addLog('Çeviri geçmişi sıfırlandı.', 'success');
                    } else {
                        addLog(result.message || 'Geçmiş sıfırlanamadı.', 'error');
                    }
                } catch (e) {
                    addLog('Geçmiş sıfırlanırken hata oluştu.', 'error', e.message);
                }
            }
        });

        // Modal Event Listeners
        const errorModal = document.getElementById('error-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const modalErrorDetails = document.getElementById('modal-error-details');

        closeModalBtn.addEventListener('click', () => {
            errorModal.style.display = 'none';
        });

        errorModal.addEventListener('click', (e) => {
            if (e.target === errorModal) {
                errorModal.style.display = 'none';
            }
        });

        logRoot.addEventListener('click', (e) => {
            const detailBtn = e.target.closest('[data-error-detail]');
            if (detailBtn) {
                showModalWithError(detailBtn.dataset.errorDetail);
            }
        });

        syncUi();
        refreshTree(false);
        addLog('Panel hazir. Mod sec, dosya sec ve islemi baslat.', 'info');
    </script>
</body>

</html>