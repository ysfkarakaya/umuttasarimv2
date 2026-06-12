<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['project_authenticated']) || $_SESSION['project_authenticated'] !== true) {
    header('Location: ../index.php');
    exit;
}

$langRoot = realpath(__DIR__ . '/../../data/lang');

$defaultSkipKeys = [
    'id', 'sef_url', 'slug', 'url', 'image', 'resim', 'logo', 'icon', 'path', 'file',
    'created_at', 'updated_at', 'deleted_at', 'email', 'telephone', 'phone', 'favicon',
    '@context', '@type', 'locale', 'canonical', 'manifest', 'theme-color', 'charset',
    'viewport', 'google-site-verification', 'msvalidate.01', 'latitude', 'longitude'
];

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
    return $path;
}

function safePath(string $base, string $relative): string
{
    $relative = normalizeRelativePath($relative);
    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
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
        ];
    }

    usort($directories, static fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));
    usort($files, static fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

    return array_merge($directories, $files);
}

function shouldSkipByKey(string $path, array $skipKeys): bool
{
    $path = strtolower($path);
    $translatableIndicators = ['placeholder', 'label', 'title', 'text', 'desc', 'subject', 'content', 'header', 'footer', 'name', 'btn', 'button', 'tooltip', 'badge', 'subtitle'];
    foreach ($translatableIndicators as $indicator) {
        if (strpos($path, $indicator) !== false) {
            return false;
        }
    }
    
    $segments = preg_split('/[.\/]/', $path);
    foreach ($segments as $segment) {
        $segment = trim($segment, '[]$0123456789');
        if ($segment === '') {
            continue;
        }
        $words = preg_split('/[_-]/', $segment);
        foreach ($skipKeys as $skipKey) {
            $skipKey = strtolower(trim($skipKey));
            if ($skipKey === '') {
                continue;
            }
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

function getValueByPath($data, array $path)
{
    $cursor = $data;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        $cursor = $cursor[$segment];
    }
    return $cursor;
}

/**
 * Compares the translatable strings of a target language file against the source (tr) file.
 * Returns 'untranslated' when every translatable string is identical to the source,
 * 'translated' when at least one differs (or there is nothing to translate),
 * and 'missing' when the target file is absent.
 */
function compareFileTranslation(string $sourceFile, string $targetFile, array $skipKeys): string
{
    if (!is_file($targetFile)) {
        return 'missing';
    }

    $sourceData = json_decode((string) file_get_contents($sourceFile), true);
    $targetData = json_decode((string) file_get_contents($targetFile), true);
    if (!is_array($sourceData) || !is_array($targetData)) {
        return 'translated';
    }

    $entries = [];
    collectTranslatableStrings($sourceData, $entries, $skipKeys);

    $total = count($entries);
    if ($total === 0) {
        return 'translated';
    }

    $identical = 0;
    foreach ($entries as $entry) {
        $targetValue = getValueByPath($targetData, $entry['path']);
        if (is_string($targetValue) && trim($targetValue) === trim($entry['value'])) {
            $identical++;
        }
    }

    return $identical === $total ? 'untranslated' : 'translated';
}

/**
 * Walks every JSON file under the source (tr) language directory and returns a
 * map of relative file path => translation status for the given target language.
 */
function compareLangTranslations(string $langRoot, string $targetLang, array $skipKeys): array
{
    $sourceBase = safePath($langRoot, 'tr');
    $targetBase = $langRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, normalizeRelativePath($targetLang));

    if (!is_dir($sourceBase)) {
        return [];
    }

    $statuses = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceBase, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'json') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceBase) + 1));
        $targetFile = $targetBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $statuses[$relative] = compareFileTranslation($item->getPathname(), $targetFile, $skipKeys);
    }

    return $statuses;
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
        . "CRITICAL: Never translate brand names/company names such as 'Umut Tasarım', 'Umut Tasarım kent ekipmanları', 'Umut Tasarım Kent Ekipmanları' or variations of them. Always keep them exactly as they are in all target languages. "
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

    foreach (['items', 'translations', 'choices', 'data', 'results', 'list'] as $key) {
        if (isset($translated[$key]) && is_array($translated[$key])) {
            $translated = $translated[$key];
            break;
        }
    }

    if (isset($translated['index']) && isset($translated['text'])) {
        $translated = [$translated];
    }

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

function translateSingleText(string $apiProvider, string $apiKey, string $model, string $sourceLang, string $targetLang, string $text): string
{
    $prompt = "You are translating website content from {$sourceLang} to {$targetLang}. "
        . "Preserve HTML tags, placeholders, variables, brand names, URLs, file paths and technical tokens. "
        . "CRITICAL: Never translate brand names/company names such as 'Umut Tasarım', 'Umut Tasarım kent ekipmanları', 'Umut Tasarım Kent Ekipmanları' or variations of them. Always keep them exactly as they are in all target languages. "
        . "Translate natural language only. Return ONLY the translated text without markdown code blocks, quotes or explanations. Text: " . $text;

    if ($apiProvider === 'vertex') {
        $url = 'https://vertex.claude.gg/v1/projects/test/locations/global/publishers/google/models/' . rawurlencode($model) . ':generateContent';
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey
        ];
        $body = [
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
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
                'content' => $prompt,
            ]],
            'temperature' => 0.2,
            'max_tokens' => 2048
        ];
    } else {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $headers = [
            'Content-Type: application/json'
        ];
        $body = [
            'contents' => [[
                'parts' => [[
                    'text' => $prompt,
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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
        $resultText = $decoded['choices'][0]['message']['content'] ?? null;
    } else {
        $resultText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    if (!is_string($resultText) || trim($resultText) === '') {
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

    $resultText = trim($resultText);
    if (preg_match('/^```(?:[a-zA-Z]+)?\s*([\s\S]*?)\s*```$/i', $resultText, $matches)) {
        $resultText = trim($matches[1]);
    }

    return $resultText;
}

// Action router
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    try {
        if ($action === 'languages') {
            jsonResponse([
                'status' => 'success',
                'languages' => listLanguages($langRoot),
            ]);
        }

        if ($action === 'tree') {
            $lang = $_GET['lang'] ?? '';
            $languages = listLanguages($langRoot);
            if (!in_array($lang, $languages, true)) {
                throw new RuntimeException('Gecersiz dil secimi.');
            }
            $base = safePath($langRoot, $lang);
            jsonResponse([
                'status' => 'success',
                'tree' => buildTreeNodes($base),
            ]);
        }

        if ($action === 'compare_translations') {
            $lang = $_GET['lang'] ?? '';
            $languages = listLanguages($langRoot);
            if (!in_array($lang, $languages, true)) {
                throw new RuntimeException('Gecersiz dil secimi.');
            }
            if ($lang === 'tr') {
                throw new RuntimeException('Turkce kaynak dil oldugu icin karsilastirma yapilamaz.');
            }
            global $defaultSkipKeys;
            jsonResponse([
                'status' => 'success',
                'statuses' => compareLangTranslations($langRoot, $lang, $defaultSkipKeys),
            ]);
        }

        if ($action === 'load') {
            $lang = $_GET['lang'] ?? '';
            $path = $_GET['path'] ?? '';
            $languages = listLanguages($langRoot);
            if (!in_array($lang, $languages, true)) {
                throw new RuntimeException('Gecersiz dil secimi.');
            }
            $filePath = safePath(safePath($langRoot, $lang), $path);
            if (!is_file($filePath)) {
                throw new RuntimeException('Dosya bulunamadi.');
            }
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new RuntimeException('Dosya okunamadi.');
            }
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                jsonResponse([
                    'status' => 'error',
                    'message' => 'Gecersiz JSON dosya formatı: ' . json_last_error_msg(),
                    'raw' => $content
                ], 422);
            }
            jsonResponse([
                'status' => 'success',
                'data' => $decoded,
                'raw' => $content
            ]);
        }

        if ($action === 'save') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $lang = $_POST['lang'] ?? '';
            $path = $_POST['path'] ?? '';
            $rawPayload = $_POST['json'] ?? '';
            
            $languages = listLanguages($langRoot);
            if (!in_array($lang, $languages, true)) {
                throw new RuntimeException('Gecersiz dil secimi.');
            }
            
            $decoded = json_decode($rawPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Gecersiz JSON verisi: ' . json_last_error_msg());
            }

            $filePath = safePath(safePath($langRoot, $lang), $path);
            
            // Auto Backup
            if (is_file($filePath)) {
                $backupPath = $filePath . '.bak';
                copy($filePath, $backupPath);
            } else {
                ensureDir(dirname($filePath));
            }

            $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($filePath, $formatted) === false) {
                throw new RuntimeException('Dosya yazilamadi.');
            }

            jsonResponse([
                'status' => 'success',
                'message' => 'Dosya başarıyla kaydedildi.',
            ]);
        }

        if ($action === 'create_lang') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $newLang = trim(strtolower($_POST['new_lang'] ?? ''));
            $refLang = trim(strtolower($_POST['ref_lang'] ?? ''));
            
            if ($newLang === '' || !preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $newLang)) {
                throw new RuntimeException('Gecersiz dil kodu. Sadece tr, en, de gibi formatlar kabul edilir.');
            }
            
            $languages = listLanguages($langRoot);
            if (!in_array($refLang, $languages, true)) {
                throw new RuntimeException('Gecersiz referans dil secimi.');
            }

            $newLangDir = $langRoot . DIRECTORY_SEPARATOR . $newLang;
            if (is_dir($newLangDir)) {
                throw new RuntimeException('Bu dil dizini zaten mevcut.');
            }

            $refLangDir = safePath($langRoot, $refLang);
            copyDirectoryRecursive($refLangDir, $newLangDir);

            jsonResponse([
                'status' => 'success',
                'message' => 'Yeni dil, ' . strtoupper($refLang) . ' referans alınarak başarıyla oluşturuldu: ' . strtoupper($newLang),
            ]);
        }

        if ($action === 'get_api_settings') {
            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_settings.json';
            $accounts = [];
            if (is_file($settingsPath)) {
                $content = file_get_contents($settingsPath);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded) && isset($decoded['accounts'])) {
                        $accounts = $decoded['accounts'];
                    }
                }
            }
            jsonResponse([
                'status' => 'success',
                'accounts' => $accounts
            ]);
        }

        if ($action === 'save_api_settings') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $rawAccounts = $_POST['accounts'] ?? '[]';
            $accounts = json_decode($rawAccounts, true);
            if (!is_array($accounts)) {
                throw new RuntimeException('Gecersiz hesap verileri.');
            }
            
            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_settings.json';
            $payload = ['accounts' => $accounts];
            file_put_contents($settingsPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            
            jsonResponse([
                'status' => 'success',
                'message' => 'API ayarları başarıyla kaydedildi.'
            ]);
        }

        if ($action === 'translate_text') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $accountId = $_POST['account_id'] ?? '';
            $text = $_POST['text'] ?? '';
            $sourceLang = 'tr'; // Enforce 'tr'
            $targetLang = $_POST['target_lang'] ?? 'en';
            $model = $_POST['model'] ?? '';

            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili kaynak dil oldugu icin ceviri yapilamaz.');
            }

            if ($text === '') {
                throw new RuntimeException('Cevrilecek metin bos olamaz.');
            }

            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_settings.json';
            $account = null;
            if (is_file($settingsPath)) {
                $content = file_get_contents($settingsPath);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded) && isset($decoded['accounts'])) {
                        foreach ($decoded['accounts'] as $acc) {
                            if ($acc['id'] === $accountId) {
                                $account = $acc;
                                break;
                            }
                        }
                    }
                }
            }

            if (!$account) {
                throw new RuntimeException('Secilen API hesabi bulunamadi.');
            }

            $provider = $account['provider'] ?? 'gemini';
            $apiKey = $account['key'] ?? '';
            if ($apiKey === '') {
                throw new RuntimeException('API key bos olamaz.');
            }

            if ($model === '') {
                $model = !empty($account['default_model']) ? $account['default_model'] : ($provider === 'claudegg' ? 'claude-3-5-sonnet-20241022' : 'gemini-2.0-flash');
            }

            $translation = translateSingleText($provider, $apiKey, $model, $sourceLang, $targetLang, $text);

            jsonResponse([
                'status' => 'success',
                'translated' => $translation
            ]);
        }

        if ($action === 'translate_file') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $accountId = $_POST['account_id'] ?? '';
            $rawJson = $_POST['json'] ?? '';
            $sourceLang = 'tr'; // Always 'tr'
            $targetLang = $_POST['target_lang'] ?? 'en';
            $model = $_POST['model'] ?? '';

            if ($targetLang === 'tr') {
                throw new RuntimeException('Turkce dili kaynak dil oldugu icin ceviri yapilamaz.');
            }

            if ($rawJson === '' && isset($_POST['path']) && $_POST['path'] !== '') {
                $path = $_POST['path'];
                $sourceFilePath = safePath(safePath($langRoot, 'tr'), $path);
                if (is_file($sourceFilePath)) {
                    $rawJson = file_get_contents($sourceFilePath) ?: '';
                }
            }

            if ($rawJson === '') {
                throw new RuntimeException('Cevrilecek veri bos olamaz.');
            }

            $data = json_decode($rawJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Gecersiz JSON verisi: ' . json_last_error_msg());
            }

            $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_settings.json';
            $account = null;
            if (is_file($settingsPath)) {
                $content = file_get_contents($settingsPath);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded) && isset($decoded['accounts'])) {
                        foreach ($decoded['accounts'] as $acc) {
                            if ($acc['id'] === $accountId) {
                                $account = $acc;
                                break;
                            }
                        }
                    }
                }
            }

            if (!$account) {
                throw new RuntimeException('Secilen API hesabi bulunamadi.');
            }

            $provider = $account['provider'] ?? 'gemini';
            $apiKey = $account['key'] ?? '';
            if ($apiKey === '') {
                throw new RuntimeException('API key bos olamaz.');
            }

            if ($model === '') {
                $model = !empty($account['default_model']) ? $account['default_model'] : ($provider === 'claudegg' ? 'claude-3-5-sonnet-20241022' : 'gemini-2.0-flash');
            }

            // We gather translatable strings
            $entries = [];
            global $defaultSkipKeys;
            collectTranslatableStrings($data, $entries, $defaultSkipKeys);

            if (!empty($entries)) {
                // translate in chunks of 40
                foreach (array_chunk($entries, 40) as $chunk) {
                    $translations = geminiTranslateBatch($provider, $apiKey, $model, $sourceLang, $targetLang, $chunk);
                    foreach ($chunk as $index => $entry) {
                        setValueByPath($data, $entry['path'], $translations[$index]);
                    }
                }
            }

            jsonResponse([
                'status' => 'success',
                'data' => $data
            ]);
        }

        if ($action === 'test_api') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new RuntimeException('Gecersiz istek metodu.');
            }
            $accountId = $_POST['account_id'] ?? '';
            $provider = $_POST['provider'] ?? '';
            $apiKey = $_POST['key'] ?? '';
            $model = $_POST['model'] ?? '';

            if ($accountId !== '') {
                $settingsPath = __DIR__ . DIRECTORY_SEPARATOR . 'api_settings.json';
                $account = null;
                if (is_file($settingsPath)) {
                    $content = file_get_contents($settingsPath);
                    if ($content !== false) {
                        $decoded = json_decode($content, true);
                        if (is_array($decoded) && isset($decoded['accounts'])) {
                            foreach ($decoded['accounts'] as $acc) {
                                if ($acc['id'] === $accountId) {
                                    $account = $acc;
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$account) {
                    throw new RuntimeException('Test edilmek istenen hesap bulunamadı.');
                }
                $provider = $account['provider'] ?? 'gemini';
                $apiKey = $account['key'] ?? '';
                $model = $account['default_model'] ?? '';
            }

            if ($apiKey === '') {
                throw new RuntimeException('API key boş olamaz.');
            }

            if ($model === '') {
                $model = $provider === 'claudegg' ? 'claude-3-5-sonnet-20241022' : 'gemini-2.0-flash';
            }

            // Perform single translation test
            $translation = translateSingleText($provider, $apiKey, $model, 'en', 'tr', 'Hello');

            jsonResponse([
                'status' => 'success',
                'message' => 'API bağlantısı başarılı.',
                'translated' => $translation
            ]);
        }

        throw new RuntimeException('Gecersiz islem.');
    } catch (Throwable $exception) {
        $msg = $exception->getMessage();
        $details = null;
        if (strpos($msg, 'API_ERROR_JSON:') === 0) {
            $jsonStr = substr($msg, strlen('API_ERROR_JSON:'));
            $details = json_decode($jsonStr, true);
            $msg = $details['message'] ?? 'API hatası oluştu.';
        }
        jsonResponse([
            'status' => 'error',
            'message' => $msg,
            'details' => $details
        ], 422);
    }
}

$languages = listLanguages($langRoot);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern JSON Dil Editörü</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/dracula.min.css">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-color: #111827;
            --border-color: #1f2937;
            --line-color: #374151;
            --text-color: #f3f4f6;
            --muted-color: #9ca3af;
            --primary-color: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --success-color: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --error-color: #ef4444;
            --error-glow: rgba(239, 68, 68, 0.15);
            --warning-color: #f59e0b;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Topbar styling */
        .topbar {
            background-color: var(--panel-color);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 50;
        }

        .topbar h1 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar h1 span {
            background: linear-gradient(135deg, #818cf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            background-color: var(--border-color);
            border: 1px solid var(--line-color);
            color: var(--text-color);
            padding: 8px 16px;
            font-size: 0.88rem;
            font-weight: 500;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            outline: none;
        }

        .btn:hover {
            background-color: var(--line-color);
            border-color: var(--muted-color);
        }

        .btn.primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }

        .btn.primary:hover {
            background-color: #4f46e5;
            box-shadow: 0 0 12px var(--primary-glow);
        }

        .btn.success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: #fff;
        }

        .btn.success:hover {
            background-color: #059669;
            box-shadow: 0 0 12px var(--success-glow);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Layout Grid */
        .layout {
            flex-grow: 1;
            display: grid;
            grid-template-columns: 320px 1fr 0fr; /* Third column expands when reference selected */
            height: calc(100vh - 65px);
            overflow: hidden;
            transition: var(--transition);
        }

        .layout.show-reference {
            grid-template-columns: 320px 1fr 380px;
        }

        .panel {
            background-color: var(--panel-color);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .panel-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }

        /* Forms / Selectors */
        .select-wrapper {
            position: relative;
            width: 100%;
        }

        select, input {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 9px 12px;
            font-size: 0.88rem;
            border-radius: var(--radius-sm);
            outline: none;
            transition: var(--transition);
        }

        select:focus, input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        /* File Tree */
        .tree-search-wrapper {
            margin-bottom: 14px;
        }

        .file-tree {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .tree-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.88rem;
            user-select: none;
        }

        .tree-item:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .tree-item.active {
            background-color: var(--primary-glow);
            color: #818cf8;
            font-weight: 500;
        }

        .tree-item-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
        }

        .tree-children {
            margin-left: 14px;
            padding-left: 10px;
            border-left: 1px dashed var(--border-color);
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        /* Translation status badges */
        .tree-item-name {
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tree-badge {
            flex-shrink: 0;
            font-size: 0.66rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 10px;
            line-height: 1.4;
            white-space: nowrap;
            letter-spacing: 0.02em;
        }

        .tree-badge.translated {
            background-color: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .tree-badge.untranslated {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .tree-badge.missing {
            background-color: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .tree-badge.folder-count {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        /* Workspace area (Center) */
        .workspace {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg-color);
        }

        .workspace-header {
            padding: 12px 24px;
            background-color: var(--panel-color);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .file-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .file-info h2 {
            font-size: 1rem;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }

        .file-info span {
            font-size: 0.78rem;
            color: var(--muted-color);
        }

        .tabs {
            display: flex;
            background-color: rgba(0,0,0,0.25);
            padding: 4px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--muted-color);
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-btn.active {
            background-color: var(--border-color);
            color: var(--text-color);
        }

        .workspace-body {
            flex-grow: 1;
            overflow-y: auto;
            position: relative;
            min-height: 0; /* allow body to shrink so the console panel below stays visible */
        }

        .tab-content {
            display: none;
            height: 100%;
        }

        .tab-content.active {
            display: block;
        }

        /* Search inside JSON */
        .json-search-bar {
            padding: 10px 24px;
            background-color: rgba(255,255,255,0.01);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Visual Editor UI Elements */
        .visual-editor {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .json-card {
            background-color: var(--panel-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            overflow: hidden;
            transition: var(--transition);
        }

        .json-card-head {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            background-color: rgba(255, 255, 255, 0.01);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .json-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.92rem;
            color: #818cf8;
        }

        .json-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .json-card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .json-card-body.collapsed {
            display: none;
        }

        .json-row {
            display: grid;
            grid-template-columns: 240px 1fr auto;
            gap: 16px;
            align-items: start;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .json-row:hover {
            background-color: rgba(255,255,255,0.02);
            border-color: rgba(255,255,255,0.01);
        }

        .json-key {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.88rem;
            color: #a5b4fc;
            padding-top: 8px;
            word-break: break-all;
        }

        .json-value {
            width: 100%;
        }

        textarea.json-textarea {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 12px;
            font-size: 0.88rem;
            border-radius: var(--radius-sm);
            outline: none;
            resize: none;
            overflow-y: hidden;
            transition: var(--transition);
        }

        textarea.json-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .json-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .icon-btn {
            background: none;
            border: 1px solid transparent;
            color: var(--muted-color);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
        }

        .icon-btn:hover {
            color: var(--text-color);
            background-color: var(--border-color);
            border-color: var(--line-color);
        }

        .icon-btn.danger:hover {
            color: #fff;
            background-color: var(--error-color);
            border-color: var(--error-color);
            box-shadow: 0 0 8px var(--error-glow);
        }

        .icon-btn.success:hover {
            color: #fff;
            background-color: var(--success-color);
            border-color: var(--success-color);
            box-shadow: 0 0 8px var(--success-glow);
        }

        /* CodeMirror Editor Area */
        .raw-editor-container {
            height: 100%;
            position: relative;
        }

        .CodeMirror {
            height: 100% !important;
            font-family: 'JetBrains Mono', monospace !important;
            font-size: 0.88rem !important;
            line-height: 1.5 !important;
        }

        /* Right Panel (Reference Guide) */
        .reference-panel {
            background-color: var(--panel-color);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: var(--transition);
        }

        .reference-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ref-card {
            background-color: rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ref-key {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--muted-color);
            word-break: break-all;
        }

        .ref-val {
            font-size: 0.88rem;
            color: var(--text-color);
            background-color: rgba(255,255,255,0.02);
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.01);
            word-break: break-word;
            cursor: pointer;
            position: relative;
        }

        .ref-val:hover {
            background-color: rgba(99,102,241,0.08);
            border-color: var(--primary-glow);
        }

        /* Modal styling */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(5, 8, 16, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.2s ease-out;
        }

        .modal {
            background-color: var(--panel-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 480px;
            overflow: hidden;
            animation: slideUp 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-head {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(255,255,255,0.01);
        }

        .modal-head strong {
            font-size: 1.05rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--muted-color);
            font-size: 1.5rem;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: var(--error-color);
        }

        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .modal-foot {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background-color: rgba(255,255,255,0.01);
        }

        /* Toast notification system */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background-color: var(--panel-color);
            border: 1px solid var(--border-color);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-color);
            font-size: 0.88rem;
            font-weight: 500;
            min-width: 280px;
            max-width: 420px;
            animation: slideIn 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid var(--primary-color);
        }

        .toast.success {
            border-left-color: var(--success-color);
        }

        .toast.error {
            border-left-color: var(--error-color);
        }

        .toast.warning {
            border-left-color: var(--warning-color);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(24px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 40px;
            color: var(--muted-color);
            gap: 12px;
            text-align: center;
        }

        .empty-state h3 {
            color: var(--text-color);
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Expand Icon rotation */
        .expander-icon {
            transition: var(--transition);
        }
        .expander-icon.collapsed {
            transform: rotate(-90deg);
        }

        .json-row.highlighted {
            background-color: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.2);
        }

        /* Log Console Panel */
        .console-panel {
            background-color: #050810;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 180px;
            min-height: 40px;
            max-height: 60vh;
            flex-shrink: 0; /* never let the panel get squeezed out by the editor body */
            transition: var(--transition);
            position: relative;
        }

        .console-panel.collapsed {
            height: 40px !important;
        }

        .console-header {
            background-color: rgba(255,255,255,0.02);
            border-bottom: 1px solid var(--border-color);
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 600;
            user-select: none;
            cursor: pointer;
            height: 40px;
            box-sizing: border-box;
        }

        .console-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted-color);
        }

        .console-title span.badge {
            background-color: var(--line-color);
            color: var(--text-color);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.72rem;
        }

        .console-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Outfit', sans-serif;
        }

        .console-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 12px 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .console-row {
            border-bottom: 1px solid rgba(255,255,255,0.02);
            padding-bottom: 4px;
            word-break: break-all;
        }

        .console-row.info { color: #f3f4f6; }
        .console-row.success { color: #10b981; }
        .console-row.warning { color: #f59e0b; }
        .console-row.error { color: #ef4444; }

        .console-row .timestamp {
            color: #4b5563;
            margin-right: 8px;
            font-size: 0.75rem;
        }

        .console-details-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.75rem;
            padding: 0 4px;
            text-decoration: underline;
            margin-left: 8px;
        }

        .console-details-btn:hover {
            color: #818cf8;
        }

        .console-details-content {
            background-color: rgba(255,255,255,0.01);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            margin-top: 6px;
            font-size: 0.75rem;
            white-space: pre-wrap;
            color: #9ca3af;
            max-height: 200px;
            overflow-y: auto;
        }

        /* Tree checkbox styles */
        .tree-checkbox-wrapper {
            display: inline-flex;
            align-items: center;
            margin-right: 6px;
            cursor: pointer;
        }

        .tree-checkbox-wrapper input[type="checkbox"] {
            width: 14px;
            height: 14px;
            cursor: pointer;
            accent-color: var(--primary-color);
            margin: 0;
        }

        /* Sidebar Tools Toolbar */
        .sidebar-tools {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-tools .btn {
            flex-grow: 1;
            justify-content: center;
            padding: 6px 10px;
            font-size: 0.78rem;
        }
    </style>
</head>
<body>

    <header class="topbar">
        <h1><span>JSON Dil Editörü</span></h1>
        <div class="topbar-actions">
            <button class="btn" id="create-lang-btn" type="button">➕ Yeni Dil Ekle</button>
            <button class="btn" id="api-settings-btn" type="button">🔑 Apiler</button>
            <button class="btn" id="toggle-ref-btn" type="button">📖 Referans Panelini Aç</button>
            <button class="btn success" id="save-file-btn" type="button" disabled>💾 Dosyayı Kaydet</button>
        </div>
    </header>

    <div class="layout" id="app-layout">
        
        <!-- Sidebar Panel (Languages & Trees) -->
        <aside class="panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Düzenlenen Dil</span>
                </div>
                <div class="select-wrapper">
                    <select id="editor-lang-select">
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lang === 'tr' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strtoupper($lang), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="panel-body">
                <div class="tree-search-wrapper">
                    <input type="text" id="file-search-input" placeholder="Dosya ara...">
                </div>
                <div class="sidebar-tools">
                    <button class="btn" id="check-translations-btn" type="button" style="width:100%;">🔍 Çevirileri Kontrol Et</button>
                    <button class="btn success" id="batch-translate-btn" type="button" disabled>🤖 Seçilenleri Çevir</button>
                    <button class="btn" id="clear-tree-selection-btn" type="button" style="display: none;">🧹 Temizle</button>
                </div>
                <ul class="file-tree" id="file-tree-container">
                    <!-- Tree items rendered via JS -->
                </ul>
            </div>
        </aside>

        <!-- Work Area Panel (Center) -->
        <main class="workspace">
            <div class="workspace-header" id="workspace-header-bar" style="display: none;">
                <div class="file-info">
                    <h2 id="active-file-title">select_lang.json</h2>
                    <span id="active-file-path">data/lang/tr/select_lang.json</span>
                </div>
                <div class="tabs">
                    <button class="tab-btn active" id="tab-visual-btn">📝 Görsel Editör</button>
                    <button class="tab-btn" id="tab-raw-btn">💻 JSON Kodu</button>
                </div>
            </div>

            <div class="json-search-bar" id="json-search-bar" style="display: none; justify-content: space-between; gap: 16px; align-items: center; flex-wrap: wrap;">
                <input type="text" id="json-inner-search" placeholder="Anahtar veya Değer ara..." style="flex-grow: 1; min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <span style="font-size: 0.85rem; color: var(--muted-color); white-space: nowrap;">Çeviri API:</span>
                    <select id="active-api-select" style="padding: 6px 12px; font-size: 0.82rem; width: 180px; background-color: var(--panel-color); border-color: var(--border-color); color: var(--text-color);">
                        <option value="">-- API Hesabı Seçin --</option>
                    </select>
                    <input type="text" id="active-model-input" placeholder="Model (Opsiyonel)" style="padding: 6px 12px; font-size: 0.82rem; width: 140px; background-color: var(--panel-color); border-color: var(--border-color); color: var(--text-color);">
                    <button class="btn success" id="translate-file-btn" style="padding: 6px 12px; font-size: 0.82rem; height: 34px;" type="button">🤖 Dosyayı Çevir</button>
                </div>
            </div>

            <div class="workspace-body">
                <!-- Empty State -->
                <div class="empty-state" id="empty-state-pane">
                    <h3>JSON Dosyası Seçilmedi</h3>
                    <p>Düzenleme yapmak için sol paneldeki dosya ağacından bir JSON dosyası seçin.</p>
                </div>

                <!-- Visual Editor Content -->
                <div class="tab-content active" id="content-visual" style="display: none;">
                    <div class="visual-editor" id="visual-editor-container">
                        <!-- Visual nodes dynamically generated -->
                    </div>
                </div>

                <!-- Raw Editor Content -->
                <div class="tab-content" id="content-raw" style="display: none;">
                    <div class="raw-editor-container">
                        <textarea id="raw-json-textarea"></textarea>
                    </div>
                </div>
            </div>

            <!-- Log Console Panel -->
            <div class="console-panel collapsed" id="console-panel">
                <div class="console-header" id="console-toggle">
                    <div class="console-title">
                        <span>🖥️ Sistem Log Konsolu</span>
                        <span class="badge" id="console-badge-count">0</span>
                    </div>
                    <div class="console-actions">
                        <button class="btn" id="console-clear-btn" style="padding: 2px 8px; font-size: 0.75rem;" type="button">🧹 Temizle</button>
                        <span id="console-toggle-icon">▲ Göster</span>
                    </div>
                </div>
                <div class="console-body" id="console-log-body">
                    <!-- Logs appended via JavaScript -->
                </div>
            </div>
        </main>

        <!-- Reference Guide Panel (Right side-by-side) -->
        <aside class="reference-panel">
            <div class="panel-head">
                <div class="panel-title">
                    <span>Referans Dil Kılavuzu</span>
                    <button class="icon-btn" id="close-ref-panel-btn" style="padding:2px; height:24px; width:24px;">&times;</button>
                </div>
                <div class="select-wrapper">
                    <select id="reference-lang-select">
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lang === 'tr' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strtoupper($lang), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="reference-body" id="reference-body-container">
                <div style="color:var(--muted-color); font-size:0.88rem; text-align:center; padding-top:40px;">
                    Seçilen JSON dosyasının referans dildeki içeriği burada listelenecektir.
                </div>
            </div>
        </aside>

    </div>

    <!-- Modals -->
    <!-- Create Language Modal -->
    <div class="modal-backdrop" id="create-lang-modal" style="display: none;">
        <div class="modal">
            <div class="modal-head">
                <strong>Yeni Dil Dizini Oluştur</strong>
                <button class="modal-close" id="close-create-lang-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.85rem; color:var(--muted-color);">Dil Kodu (Örn: en, de, fr, ru)</label>
                        <input type="text" id="new-lang-code" placeholder="Örn: en" maxlength="6">
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label style="font-size:0.85rem; color:var(--muted-color);">Referans Dil (Kopyalanacak Yapı)</label>
                        <select id="new-lang-ref-select">
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $lang === 'tr' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(strtoupper($lang), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn" id="cancel-create-lang-modal">Vazgeç</button>
                <button class="btn primary" id="confirm-create-lang">Oluştur</button>
            </div>
        </div>
    </div>

    <!-- Rename Key Modal -->
    <div class="modal-backdrop" id="rename-key-modal" style="display: none;">
        <div class="modal">
            <div class="modal-head">
                <strong>Anahtarı Yeniden Adlandır</strong>
                <button class="modal-close" id="close-rename-key-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.85rem; color:var(--muted-color);">Yeni Anahtar Adı</label>
                    <input type="text" id="rename-key-input">
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn" id="cancel-rename-key-modal">Vazgeç</button>
                <button class="btn primary" id="confirm-rename-key">Değiştir</button>
            </div>
        </div>
    </div>

    <!-- Add Element Modal -->
    <div class="modal-backdrop" id="add-element-modal" style="display: none;">
        <div class="modal">
            <div class="modal-head">
                <strong>Yeni Eleman Ekle</strong>
                <button class="modal-close" id="close-add-element-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; flex-direction:column; gap:6px;" id="add-key-name-field-group">
                    <label style="font-size:0.85rem; color:var(--muted-color);">Anahtar İsmi</label>
                    <input type="text" id="add-element-key" placeholder="Anahtar adı girin">
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:0.85rem; color:var(--muted-color);">Veri Tipi</label>
                    <select id="add-element-type">
                        <option value="string">Metin (String)</option>
                        <option value="number">Sayı (Number)</option>
                        <option value="boolean">Doğru/Yanlış (Boolean)</option>
                        <option value="object">Nesne (Object)</option>
                        <option value="array">Dizi (Array)</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn" id="cancel-add-element-modal">Vazgeç</button>
                <button class="btn primary" id="confirm-add-element">Ekle</button>
            </div>
        </div>
    </div>

    <!-- API Settings Modal -->
    <div class="modal-backdrop" id="api-settings-modal" style="display: none;">
        <div class="modal" style="max-width: 580px;">
            <div class="modal-head">
                <strong>API Hesap Yönetimi</strong>
                <button class="modal-close" id="close-api-settings-modal">&times;</button>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                <div style="display:flex; flex-direction:column; gap:10px;" id="api-accounts-list">
                    <!-- Populated dynamically -->
                </div>
                
                <hr style="border:0; border-top:1px solid var(--border-color); margin: 16px 0;">
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <strong id="api-form-title">Yeni API Hesabı Ekle</strong>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-size:0.8rem; color:var(--muted-color);">Sağlayıcı</label>
                            <select id="new-api-provider" style="background-color: var(--bg-color); border-color: var(--border-color); color: var(--text-color);">
                                <option value="gemini">Gemini (Resmi)</option>
                                <option value="vertex">Vertex (vertex.claude.gg)</option>
                                <option value="claudegg">Claude.gg (api.claude.gg)</option>
                            </select>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-size:0.8rem; color:var(--muted-color);">Hesap İsmi / Etiket</label>
                            <input type="text" id="new-api-name" placeholder="Örn: Gemini Asıl Hesap">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-size:0.8rem; color:var(--muted-color);">API Key</label>
                            <input type="password" id="new-api-key" placeholder="AIzaSy... veya sk-...">
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="font-size:0.8rem; color:var(--muted-color);">Varsayılan Model (Seçmeli)</label>
                            <input type="text" id="new-api-default-model" placeholder="Örn: gemini-2.0-flash">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn" id="test-new-api-btn" type="button" style="margin-right: auto;">⚡ Test Et</button>
                <button class="btn" id="cancel-edit-api-btn" type="button" style="display: none;">Vazgeç</button>
                <button class="btn" id="cancel-api-settings-modal">Kapat</button>
                <button class="btn primary" id="save-new-api-btn">Hesap Ekle</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <!-- CodeMirror JS dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>

    <script>
        // Global variables & elements
        const editorLangSelect = document.getElementById('editor-lang-select');
        const referenceLangSelect = document.getElementById('reference-lang-select');
        const fileTreeContainer = document.getElementById('file-tree-container');
        const fileSearchInput = document.getElementById('file-search-input');
        const saveFileBtn = document.getElementById('save-file-btn');
        const createLangBtn = document.getElementById('create-lang-btn');
        const toggleRefBtn = document.getElementById('toggle-ref-btn');
        const closeRefPanelBtn = document.getElementById('close-ref-panel-btn');
        const appLayout = document.getElementById('app-layout');
        const emptyStatePane = document.getElementById('empty-state-pane');
        const workspaceHeaderBar = document.getElementById('workspace-header-bar');
        const activeFileTitle = document.getElementById('active-file-title');
        const activeFilePath = document.getElementById('active-file-path');
        const contentVisual = document.getElementById('content-visual');
        const contentRaw = document.getElementById('content-raw');
        const visualEditorContainer = document.getElementById('visual-editor-container');
        const jsonSearchBar = document.getElementById('json-search-bar');
        const jsonInnerSearch = document.getElementById('json-inner-search');
        const referenceBodyContainer = document.getElementById('reference-body-container');

        // API elements
        const apiSettingsBtn = document.getElementById('api-settings-btn');
        const apiSettingsModal = document.getElementById('api-settings-modal');
        const closeApiSettingsModal = document.getElementById('close-api-settings-modal');
        const cancelApiSettingsModal = document.getElementById('cancel-api-settings-modal');
        const apiAccountsList = document.getElementById('api-accounts-list');
        const saveNewApiBtn = document.getElementById('save-new-api-btn');
        const newApiProvider = document.getElementById('new-api-provider');
        const newApiName = document.getElementById('new-api-name');
        const newApiKey = document.getElementById('new-api-key');
        const activeApiSelect = document.getElementById('active-api-select');
        const activeModelInput = document.getElementById('active-model-input');

        // Modal elements
        const createLangModal = document.getElementById('create-lang-modal');
        const newLangCodeInput = document.getElementById('new-lang-code');
        const confirmCreateLangBtn = document.getElementById('confirm-create-lang');

        const renameKeyModal = document.getElementById('rename-key-modal');
        const renameKeyInput = document.getElementById('rename-key-input');
        const confirmRenameKeyBtn = document.getElementById('confirm-rename-key');

        const addElementModal = document.getElementById('add-element-modal');
        const addElementKey = document.getElementById('add-element-key');
        const addElementType = document.getElementById('add-element-type');
        const addKeyNameFieldGroup = document.getElementById('add-key-name-field-group');
        const confirmAddElementBtn = document.getElementById('confirm-add-element');

        // State variables
        let treeData = [];
        let expandedFolders = new Set();
        let selectedFilePath = '';
        let currentJsonData = null;
        let referenceJsonData = null;
        let activeTab = 'visual';
        let codeMirrorEditor = null;
        let isRefPanelOpen = false;
        let isDirty = false;
        let currentSearchQuery = '';
        let apiAccounts = [];
        let checkedFiles = new Set();
        let checkedFolders = new Set();
        let lastCheckedFilePath = null;
        let lastCheckedFolderPath = null;
        let translationStatuses = {}; // { relativePath: 'translated' | 'untranslated' | 'missing' }

        // Modal state variables
        let modalRenamePath = null;
        let modalAddElementPath = null;
        let modalAddElementIsArray = false;

        // Toast Toast System
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'fadeIn 0.25s reverse forwards';
                setTimeout(() => toast.remove(), 250);
            }, 3000);
        }

        // Initialize CodeMirror
        function initCodeMirror() {
            if (!codeMirrorEditor) {
                codeMirrorEditor = CodeMirror.fromTextArea(document.getElementById('raw-json-textarea'), {
                    mode: 'application/json',
                    theme: 'dracula',
                    lineNumbers: true,
                    tabSize: 2,
                    lineWrapping: true
                });
                
                codeMirrorEditor.on('change', () => {
                    if (activeTab === 'raw') {
                        try {
                            const val = codeMirrorEditor.getValue();
                            if (val.trim() !== '') {
                                currentJsonData = JSON.parse(val);
                                markAsDirty(true);
                            }
                        } catch (e) {
                            // Syntax error is fine while typing, don't update data
                        }
                    }
                });
            }
        }

        // Mark as Dirty/Changed
        function markAsDirty(dirty) {
            isDirty = dirty;
            saveFileBtn.disabled = !dirty;
            if (dirty) {
                activeFileTitle.textContent = selectedFilePath.split('/').pop() + ' *';
            } else {
                activeFileTitle.textContent = selectedFilePath.split('/').pop();
            }
        }

        // Fetch API Helper
        async function fetchApi(action, params = {}, options = {}) {
            const urlParams = new URLSearchParams({ action, ...params });
            const response = await fetch(`index.php?${urlParams.toString()}`, options);
            const data = await response.json();
            if (data.status !== 'success') {
                throw new Error(data.message || 'Bir hata oluştu.');
            }
            return data;
        }

        // Load Tree Nodes
        async function loadTree() {
            try {
                const lang = editorLangSelect.value;
                const result = await fetchApi('tree', { lang });
                treeData = result.tree || [];
                renderTree();
            } catch (e) {
                showToast(e.message, 'error');
            }
        }

        // Render Tree
        function renderTree() {
            const filter = fileSearchInput.value.trim().toLowerCase();
            
            function buildHtml(nodes) {
                let html = '';
                nodes.forEach(node => {
                    const matchesFilter = !filter || node.name.toLowerCase().includes(filter) || (node.path && node.path.toLowerCase().includes(filter));
                    
                    if (node.type === 'directory') {
                        const hasChildren = node.children && node.children.length > 0;
                        const isExpanded = expandedFolders.has(node.path);
                        const subHtml = hasChildren && isExpanded ? `<ul class="tree-children">${buildHtml(node.children)}</ul>` : '';
                        
                        // Show folder if it matches, or if any child matches
                        const childrenMatch = hasChildren && childNodeMatchesFilter(node.children, filter);
                        
                        if (matchesFilter || childrenMatch) {
                            const untranslatedCount = countUntranslatedInNode(node);
                            const folderBadge = untranslatedCount > 0
                                ? `<span class="tree-badge folder-count" title="${untranslatedCount} çevrilmemiş dosya">${untranslatedCount} çevrilmemiş</span>`
                                : '';
                            html += `
                                <li>
                                    <div class="tree-item" data-type="directory" data-path="${escapeHtml(node.path)}">
                                        <span class="tree-checkbox-wrapper">
                                            <input type="checkbox" class="tree-folder-checkbox" data-path="${escapeHtml(node.path)}">
                                        </span>
                                        <span class="tree-item-icon expander-icon ${isExpanded ? '' : 'collapsed'}">▼</span>
                                        <span class="tree-item-icon">📁</span>
                                        <span class="tree-item-name">${escapeHtml(node.name)}</span>
                                        ${folderBadge}
                                    </div>
                                    ${subHtml}
                                </li>
                            `;
                        }
                    } else if (node.type === 'file') {
                        if (matchesFilter) {
                            const activeClass = selectedFilePath === node.path ? 'active' : '';
                            const status = translationStatuses[node.path];
                            let fileBadge = '';
                            if (status === 'translated') {
                                fileBadge = `<span class="tree-badge translated" title="Çevrilmiş">✓ Çevrildi</span>`;
                            } else if (status === 'untranslated') {
                                fileBadge = `<span class="tree-badge untranslated" title="Kaynak dil ile aynı, çevrilmemiş">✗ Çevrilmedi</span>`;
                            } else if (status === 'missing') {
                                fileBadge = `<span class="tree-badge missing" title="Hedef dilde dosya yok">⚠ Eksik</span>`;
                            }
                            html += `
                                <li>
                                    <div class="tree-item ${activeClass}" data-type="file" data-path="${escapeHtml(node.path)}">
                                        <span class="tree-checkbox-wrapper">
                                            <input type="checkbox" class="tree-file-checkbox" data-path="${escapeHtml(node.path)}">
                                        </span>
                                        <span class="tree-item-icon">📄</span>
                                        <span class="tree-item-name">${escapeHtml(node.name)}</span>
                                        ${fileBadge}
                                    </div>
                                </li>
                            `;
                        }
                    }
                });
                return html;
            }

            fileTreeContainer.innerHTML = buildHtml(treeData);
            restoreCheckboxStates();
        }

        // Helper to check if any child matches search query
        function childNodeMatchesFilter(children, query) {
            if (!query) return true;
            return children.some(node => {
                if (node.type === 'file') {
                    return node.name.toLowerCase().includes(query) || node.path.toLowerCase().includes(query);
                }
                if (node.type === 'directory') {
                    return node.name.toLowerCase().includes(query) || (node.children && childNodeMatchesFilter(node.children, query));
                }
                return false;
            });
        }

        // Count untranslated/missing files inside a directory node (recursive)
        function countUntranslatedInNode(node) {
            let count = 0;
            (node.children || []).forEach(child => {
                if (child.type === 'file') {
                    const status = translationStatuses[child.path];
                    if (status === 'untranslated' || status === 'missing') {
                        count++;
                    }
                } else if (child.type === 'directory') {
                    count += countUntranslatedInNode(child);
                }
            });
            return count;
        }

        // Compare current editing language against source (tr) and flag translated/untranslated files
        async function checkTranslations() {
            const lang = editorLangSelect.value;
            const btn = document.getElementById('check-translations-btn');

            if (lang === 'tr') {
                showToast('Türkçe kaynak dil olduğu için karşılaştırma yapılamaz.', 'warning');
                return;
            }

            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Kontrol ediliyor...';
            addConsoleLog(`[Çeviri Kontrolü Başladı] ${lang.toUpperCase()} dili TR kaynak dili ile karşılaştırılıyor.`, 'info');

            try {
                const result = await fetchApi('compare_translations', { lang });
                translationStatuses = result.statuses || {};

                const values = Object.values(translationStatuses);
                const untranslated = values.filter(s => s === 'untranslated').length;
                const missing = values.filter(s => s === 'missing').length;
                const translated = values.filter(s => s === 'translated').length;

                renderTree();
                showToast(`Kontrol tamamlandı: ${translated} çevrildi, ${untranslated} çevrilmedi, ${missing} eksik.`, 'success');
                addConsoleLog(`[Çeviri Kontrolü Bitti] Toplam ${values.length} dosya | ✓ ${translated} çevrildi | ✗ ${untranslated} çevrilmedi | ⚠ ${missing} eksik.`, 'success');
            } catch (e) {
                showToast(e.message, 'error');
                addConsoleLog(`[Çeviri Kontrolü Hatası] ${e.message}`, 'error', e.details || e);
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }

        // Escape HTML for security
        function escapeHtml(text) {
            if (typeof text !== 'string') return String(text);
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Load JSON File Details
        async function loadJsonFile(path) {
            if (isDirty) {
                if (!confirm('Kaydedilmemiş değişiklikleriniz var. Değişiklikleri iptal edip yeni dosyayı yüklemek istediğinize emin misiniz?')) {
                    return;
                }
            }

            selectedFilePath = path;
            emptyStatePane.style.display = 'none';
            workspaceHeaderBar.style.display = 'flex';
            jsonSearchBar.style.display = 'flex';
            
            // Toggle tab content visibility
            if (activeTab === 'visual') {
                contentVisual.style.display = 'block';
                contentRaw.style.display = 'none';
            } else {
                contentVisual.style.display = 'none';
                contentRaw.style.display = 'block';
            }

            try {
                const lang = editorLangSelect.value;
                activeFileTitle.textContent = path.split('/').pop();
                activeFilePath.textContent = `data/lang/${lang}/${path}`;

                const result = await fetchApi('load', { lang, path });
                currentJsonData = result.data;
                
                // Load raw code in CM
                initCodeMirror();
                codeMirrorEditor.setValue(JSON.stringify(currentJsonData, null, 2));
                
                // Rerender visual UI
                renderVisualEditor();
                markAsDirty(false);

                // Fetch reference lang JSON if open
                if (isRefPanelOpen) {
                    await loadReferenceJson();
                }

                // Render tree node active highlights
                renderTree();
            } catch (e) {
                showToast(e.message, 'error');
            }
        }

        // Load Reference JSON side-by-side
        async function loadReferenceJson() {
            if (!selectedFilePath) return;
            const refLang = referenceLangSelect.value;
            referenceBodyContainer.innerHTML = `<div style="color:var(--muted-color); font-size:0.88rem; text-align:center; padding-top:40px;">Yükleniyor...</div>`;
            try {
                const result = await fetchApi('load', { lang: refLang, path: selectedFilePath });
                referenceJsonData = result.data;
                renderReferencePane();
            } catch (e) {
                referenceJsonData = null;
                referenceBodyContainer.innerHTML = `<div style="color:var(--error-color); font-size:0.85rem; text-align:center; padding:16px;">Bu dosya seçilen referans dilde (${refLang.toUpperCase()}) bulunamadı veya geçersiz.</div>`;
            }
        }

        // Save JSON File
        async function saveJsonFile() {
            if (!selectedFilePath) return;

            // Sync visual data to CM if visual tab is open
            if (activeTab === 'visual') {
                codeMirrorEditor.setValue(JSON.stringify(currentJsonData, null, 2));
            }

            const rawJson = codeMirrorEditor.getValue();
            try {
                // Validate before saving
                JSON.parse(rawJson);
            } catch (e) {
                showToast('JSON Kodu hatalı olduğu için kaydedilemez: ' + e.message, 'error');
                return;
            }

            const fd = new FormData();
            fd.append('lang', editorLangSelect.value);
            fd.append('path', selectedFilePath);
            fd.append('json', rawJson);

            addConsoleLog(`[Dosya Kaydediliyor] Yol: data/lang/${editorLangSelect.value}/${selectedFilePath}`, 'info');

            try {
                const result = await fetchApi('save', {}, {
                    method: 'POST',
                    body: fd
                });
                showToast(result.message, 'success');
                addConsoleLog(`[Dosya Kaydedildi] data/lang/${editorLangSelect.value}/${selectedFilePath} başarıyla diske yazıldı.`, 'success');
                markAsDirty(false);
            } catch (e) {
                showToast(e.message, 'error');
                addConsoleLog(`[Dosya Kaydetme Hatası] Yol: ${selectedFilePath}, Hata: ${e.message}`, 'error', e.details || e);
            }
        }

        // Helper to update deep path value in javascript object
        function setDeepValue(obj, path, value) {
            let current = obj;
            for (let i = 0; i < path.length - 1; i++) {
                current = current[path[i]];
            }
            current[path[path.length - 1]] = value;
            markAsDirty(true);
        }

        // Helper to delete deep path
        function deleteDeepKey(obj, path) {
            let current = obj;
            for (let i = 0; i < path.length - 1; i++) {
                current = current[path[i]];
            }
            const lastSegment = path[path.length - 1];
            if (Array.isArray(current)) {
                current.splice(lastSegment, 1);
            } else {
                delete current[lastSegment];
            }
            markAsDirty(true);
        }

        // Helper to rename deep path key
        function renameDeepKey(obj, path, newKey) {
            let current = obj;
            for (let i = 0; i < path.length - 1; i++) {
                current = current[path[i]];
            }
            const oldKey = path[path.length - 1];
            if (oldKey === newKey) return;
            
            // Check if key already exists
            if (current[newKey] !== undefined) {
                showToast('Aynı isimde başka bir anahtar zaten var.', 'warning');
                return;
            }
            
            // Move item to new key maintaining order
            const temp = {};
            for (const k in current) {
                if (k === oldKey) {
                    temp[newKey] = current[oldKey];
                } else {
                    temp[k] = current[k];
                }
            }
            
            // Reassign
            for (const k in current) delete current[k];
            Object.assign(current, temp);
            
            markAsDirty(true);
        }

        // Helper to add deep key
        function addDeepKey(obj, path, keyName, valueType) {
            let current = obj;
            for (let i = 0; i < path.length; i++) {
                current = current[path[i]];
            }
            
            let defaultValue = "";
            if (valueType === "number") defaultValue = 0;
            if (valueType === "boolean") defaultValue = false;
            if (valueType === "array") defaultValue = [];
            if (valueType === "object") defaultValue = {};
            
            if (Array.isArray(current)) {
                current.push(defaultValue);
            } else {
                if (current[keyName] !== undefined) {
                    showToast('Bu anahtar zaten mevcut.', 'warning');
                    return;
                }
                current[keyName] = defaultValue;
            }
            markAsDirty(true);
        }

        // Helper to move elements inside array
        function moveArrayItem(obj, path, index, direction) {
            let current = obj;
            for (let i = 0; i < path.length; i++) {
                current = current[path[i]];
            }
            if (!Array.isArray(current)) return;
            
            const targetIdx = index + direction;
            if (targetIdx < 0 || targetIdx >= current.length) return;
            
            const temp = current[index];
            current[index] = current[targetIdx];
            current[targetIdx] = temp;
            
            markAsDirty(true);
            renderVisualEditor();
        }

        // Recursive Visual Tree Form Renderer
        function buildVisualHtml(obj, path = []) {
            if (obj === null || obj === undefined) return '';

            let html = '';
            const isArray = Array.isArray(obj);

            for (const key in obj) {
                const value = obj[key];
                const currentPath = path.concat(key);
                const pathStr = currentPath.join('.');
                const isObj = typeof value === 'object' && value !== null;

                // Search query match
                let isMatch = true;
                if (currentSearchQuery) {
                    const keyString = String(key).toLowerCase();
                    const valueString = isObj ? '' : String(value).toLowerCase();
                    isMatch = keyString.includes(currentSearchQuery) || valueString.includes(currentSearchQuery);
                }

                if (isObj) {
                    const subArray = Array.isArray(value);
                    const label = subArray ? 'Array' : 'Object';
                    const len = subArray ? value.length : Object.keys(value).length;
                    const pathAttr = escapeHtml(JSON.stringify(currentPath));
                    const isFolderExpanded = !expandedFolders.has(pathStr); // Default open

                    html += `
                        <div class="json-card" id="card-${pathStr}">
                            <div class="json-card-head" onclick="toggleVisualFolder('${pathStr}')">
                                <span class="json-card-title">
                                    <span class="tree-item-icon expander-icon ${isFolderExpanded ? '' : 'collapsed'}" id="expand-icon-${pathStr}">▼</span>
                                    <span>${escapeHtml(key)}</span>
                                    <span style="font-size:0.75rem; color:var(--muted-color); font-weight:normal;">(${len} elemanlı ${label})</span>
                                </span>
                                <div class="json-card-actions" onclick="event.stopPropagation();">
                                    <button class="icon-btn" onclick="openAddElementModal(${pathAttr}, ${subArray})" title="Eleman Ekle">➕</button>
                                    ${!isArray ? `<button class="icon-btn" onclick="openRenameKeyModal(${pathAttr}, '${escapeHtml(key)}')" title="Anahtarı Yeniden Adlandır">✏️</button>` : ''}
                                    <button class="icon-btn danger" onclick="deleteNode(${pathAttr})" title="Sil">🗑️</button>
                                </div>
                            </div>
                            <div class="json-card-body ${isFolderExpanded ? '' : 'collapsed'}" id="card-body-${pathStr}">
                                ${buildVisualHtml(value, currentPath)}
                            </div>
                        </div>
                    `;
                } else {
                    // Primitive values
                    const pathAttr = escapeHtml(JSON.stringify(currentPath));
                    let inputHtml = '';
                    
                    if (typeof value === 'boolean') {
                        inputHtml = `
                            <select onchange="updatePrimitiveValue(${pathAttr}, this.value === 'true')">
                                <option value="true" ${value ? 'selected' : ''}>True (Doğru)</option>
                                <option value="false" ${!value ? 'selected' : ''}>False (Yanlış)</option>
                            </select>
                        `;
                    } else if (typeof value === 'number') {
                        inputHtml = `<input type="number" value="${value}" oninput="updatePrimitiveValue(${pathAttr}, parseFloat(this.value))">`;
                    } else {
                        // String or fallback
                        inputHtml = `<textarea class="json-textarea" rows="1" oninput="updatePrimitiveValue(${pathAttr}, this.value); autoResize(this);">${escapeHtml(value)}</textarea>`;
                    }

                    let arrayControls = '';
                    if (isArray) {
                        const idx = parseInt(key);
                        const parentPathAttr = escapeHtml(JSON.stringify(path));
                        arrayControls = `
                            <button class="icon-btn" onclick="moveArrayItem(currentJsonData, ${parentPathAttr}, ${idx}, -1)" title="Yukarı Taşı">▲</button>
                            <button class="icon-btn" onclick="moveArrayItem(currentJsonData, ${parentPathAttr}, ${idx}, 1)" title="Aşağı Taşı">▼</button>
                        `;
                    }

                    let translateBtnHtml = '';
                    
                    if (isMatch) {
                        html += `
                            <div class="json-row ${currentSearchQuery ? 'highlighted' : ''}">
                                <div class="json-key">
                                    <span>${escapeHtml(key)}</span>
                                </div>
                                <div class="json-value">
                                    ${inputHtml}
                                </div>
                                <div class="json-actions">
                                    ${arrayControls}
                                    ${!isArray ? `<button class="icon-btn" onclick="openRenameKeyModal(${pathAttr}, '${escapeHtml(key)}')" title="Anahtarı Yeniden Adlandır">✏️</button>` : ''}
                                    <button class="icon-btn danger" onclick="deleteNode(${pathAttr})" title="Sil">🗑️</button>
                                </div>
                            </div>
                        `;
                    }
                }
            }

            return html;
        }

        // Render visual workspace
        function renderVisualEditor() {
            if (!currentJsonData) return;
            const html = buildVisualHtml(currentJsonData);
            visualEditorContainer.innerHTML = html || '<div class="empty-state"><h3>Boş Nesne</h3><button class="btn primary" onclick="openAddElementModal([], false)">Yeni Eleman Ekle</button></div>';
            
            // Trigger auto resize for all textareas
            document.querySelectorAll('textarea.json-textarea').forEach(textarea => {
                autoResize(textarea);
            });
        }

        // Textarea auto-resize
        function autoResize(el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        }

        // Toggle Folder Fold
        window.toggleVisualFolder = function(folderPath) {
            const body = document.getElementById(`card-body-${folderPath}`);
            const icon = document.getElementById(`expand-icon-${folderPath}`);
            if (body) {
                const collapsed = body.classList.toggle('collapsed');
                if (collapsed) {
                    expandedFolders.add(folderPath);
                    icon.classList.add('collapsed');
                } else {
                    expandedFolders.delete(folderPath);
                    icon.classList.remove('collapsed');
                }
            }
        };

        // Update value inside JS object
        window.updatePrimitiveValue = function(path, val) {
            setDeepValue(currentJsonData, path, val);
        };

        // Delete key
        window.deleteNode = function(path) {
            if (confirm(`"${path.join('.')}" elemanını silmek istediğinize emin misiniz?`)) {
                deleteDeepKey(currentJsonData, path);
                renderVisualEditor();
            }
        };

        // Modal triggers
        window.openRenameKeyModal = function(path, currentKey) {
            modalRenamePath = path;
            renameKeyInput.value = currentKey;
            renameKeyModal.style.display = 'flex';
            renameKeyInput.focus();
        };

        window.openAddElementModal = function(parentPath, isArray) {
            modalAddElementPath = parentPath;
            modalAddElementIsArray = isArray;
            
            // If parent is array, hide Key Name field input
            if (isArray) {
                addKeyNameFieldGroup.style.display = 'none';
                addElementKey.value = '_index'; // Dummy key name
            } else {
                addKeyNameFieldGroup.style.display = 'flex';
                addElementKey.value = '';
            }

            addElementModal.style.display = 'flex';
            addElementKey.focus();
        };

        // Render reference guide
        function renderReferencePane() {
            if (!referenceJsonData) return;
            
            let html = '';
            function traverse(obj, path = []) {
                for (const k in obj) {
                    const v = obj[k];
                    const currentPath = path.concat(k);
                    if (v && typeof v === 'object') {
                        traverse(v, currentPath);
                    } else {
                        const pathStr = currentPath.join('.');
                        const valueStr = escapeHtml(String(v));
                        html += `
                            <div class="ref-card">
                                <div class="ref-key">${escapeHtml(pathStr)}</div>
                                <div class="ref-val" onclick="copyRefValue(this)" title="Tıklayarak Kopyalayın">${valueStr}</div>
                            </div>
                        `;
                    }
                }
            }

            traverse(referenceJsonData);
            referenceBodyContainer.innerHTML = html || '<div class="empty-state"><p>Referans dilde veri bulunamadı.</p></div>';
        }

        // Copy reference key to clipboard
        window.copyRefValue = function(el) {
            const val = el.textContent;
            navigator.clipboard.writeText(val).then(() => {
                showToast('Kopyalandı!', 'success');
            }).catch(err => {
                showToast('Kopyalama başarısız.', 'error');
            });
        };

        // Checkbox Helper functions
        function restoreCheckboxStates() {
            document.querySelectorAll('.tree-file-checkbox').forEach(cb => {
                cb.checked = checkedFiles.has(cb.dataset.path);
            });
            document.querySelectorAll('.tree-folder-checkbox').forEach(cb => {
                cb.checked = checkedFolders.has(cb.dataset.path);
            });
        }

        function toggleChildCheckboxes(folderPath, isChecked) {
            function traverse(nodes) {
                for (const node of nodes) {
                    if (node.type === 'directory') {
                        if (node.path === folderPath) {
                            selectNodeChildren(node, isChecked);
                            return true;
                        }
                        if (node.children && traverse(node.children)) {
                            return true;
                        }
                    }
                }
                return false;
            }

            function selectNodeChildren(node, isChecked) {
                if (node.type === 'file') {
                    if (isChecked) {
                        checkedFiles.add(node.path);
                    } else {
                        checkedFiles.delete(node.path);
                    }
                } else if (node.type === 'directory') {
                    if (isChecked) {
                        checkedFolders.add(node.path);
                    } else {
                        checkedFolders.delete(node.path);
                    }
                    if (node.children) {
                        node.children.forEach(child => selectNodeChildren(child, isChecked));
                    }
                }
            }

            traverse(treeData);
            restoreCheckboxStates();
        }

        function updateBatchToolsVisibility() {
            const batchTranslateBtn = document.getElementById('batch-translate-btn');
            const clearTreeSelectionBtn = document.getElementById('clear-tree-selection-btn');
            const currentLang = editorLangSelect.value;
            
            const totalChecked = checkedFiles.size;
            
            if (currentLang === 'tr') {
                batchTranslateBtn.disabled = true;
                batchTranslateBtn.textContent = `🤖 Türkçe Çevrilemez`;
                batchTranslateBtn.style.opacity = '0.5';
                batchTranslateBtn.title = 'Türkçe kaynak dil olduğu için yapay zeka çevirisi yapılamaz.';
                clearTreeSelectionBtn.style.display = totalChecked > 0 ? 'inline-flex' : 'none';
                return;
            } else {
                batchTranslateBtn.style.opacity = '1';
                batchTranslateBtn.title = '';
            }
            
            if (totalChecked > 0) {
                batchTranslateBtn.disabled = false;
                batchTranslateBtn.textContent = `🤖 Seçilenleri Çevir (${totalChecked})`;
                clearTreeSelectionBtn.style.display = 'inline-flex';
            } else {
                batchTranslateBtn.disabled = true;
                batchTranslateBtn.textContent = `🤖 Seçilenleri Çevir`;
                clearTreeSelectionBtn.style.display = 'none';
            }
        }

        function updateTranslationControls() {
            const currentLang = editorLangSelect.value;
            const translateFileBtn = document.getElementById('translate-file-btn');
            
            if (currentLang === 'tr') {
                if (translateFileBtn) {
                    translateFileBtn.disabled = true;
                    translateFileBtn.style.opacity = '0.5';
                    translateFileBtn.title = 'Türkçe kaynak dil olduğu için yapay zeka çevirisi yapılamaz.';
                }
            } else {
                if (translateFileBtn) {
                    translateFileBtn.disabled = false;
                    translateFileBtn.style.opacity = '1';
                    translateFileBtn.title = '';
                }
            }
            updateBatchToolsVisibility();
        }

        // Log Console Panel Helpers
        let logCount = 0;
        function toggleConsolePanel() {
            const panel = document.getElementById('console-panel');
            const icon = document.getElementById('console-toggle-icon');
            const isCollapsed = panel.classList.toggle('collapsed');
            icon.textContent = isCollapsed ? '▲ Göster' : '▼ Gizle';
        }

        function addConsoleLog(message, type = 'info', details = null) {
            const body = document.getElementById('console-log-body');
            if (!body) return;
            
            logCount++;
            document.getElementById('console-badge-count').textContent = logCount;
            
            const row = document.createElement('div');
            row.className = `console-row ${type}`;
            
            const timestamp = document.createElement('span');
            timestamp.className = 'timestamp';
            const now = new Date();
            timestamp.textContent = `[${now.toLocaleTimeString()}]`;
            row.appendChild(timestamp);
            
            const text = document.createElement('span');
            text.textContent = message;
            row.appendChild(text);
            
            if (details) {
                const detailsId = `log-details-${Date.now()}-${Math.floor(Math.random()*1000)}`;
                
                const btn = document.createElement('button');
                btn.className = 'console-details-btn';
                btn.textContent = 'Detaylar';
                btn.type = 'button';
                btn.onclick = () => {
                    const el = document.getElementById(detailsId);
                    if (el.style.display === 'none') {
                        el.style.display = 'block';
                    } else {
                        el.style.display = 'none';
                    }
                };
                row.appendChild(btn);
                
                const detailsPre = document.createElement('pre');
                detailsPre.id = detailsId;
                detailsPre.className = 'console-details-content';
                detailsPre.style.display = 'none';
                detailsPre.textContent = typeof details === 'object' ? JSON.stringify(details, null, 2) : String(details);
                row.appendChild(detailsPre);
            }
            
            body.appendChild(row);
            body.scrollTop = body.scrollHeight;
            
            if (type === 'error') {
                const panel = document.getElementById('console-panel');
                if (panel.classList.contains('collapsed')) {
                    toggleConsolePanel();
                }
            }
        }

        // API Connection Tester
        window.testSavedApiAccount = async function(id) {
            addConsoleLog(`[API Test] Hesabı test ediliyor: ID ${id}...`, 'info');
            const fd = new FormData();
            fd.append('account_id', id);
            try {
                const result = await fetchApi('test_api', {}, {
                    method: 'POST',
                    body: fd
                });
                showToast('API bağlantısı başarılı.', 'success');
                addConsoleLog(`[API Test Başarılı] Dönen cevap: "${result.translated}"`, 'success');
            } catch (e) {
                showToast('API bağlantı testi başarısız oldu.', 'error');
                addConsoleLog(`[API Test Hatası] Hata: ${e.message}`, 'error', e.details || e);
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            loadTree();
            updateTranslationControls();

            // Language Select triggers
            editorLangSelect.addEventListener('change', () => {
                translationStatuses = {}; // reset status badges when switching language
                loadTree();
                if (selectedFilePath) {
                    loadJsonFile(selectedFilePath);
                }
                updateTranslationControls();
            });

            // Check translations against source language
            document.getElementById('check-translations-btn').addEventListener('click', checkTranslations);

            referenceLangSelect.addEventListener('change', () => {
                if (isRefPanelOpen) {
                    loadReferenceJson();
                }
            });

            // Search input tree filtration
            fileSearchInput.addEventListener('input', renderTree);

            // Workspace Inner search filter
            jsonInnerSearch.addEventListener('input', () => {
                currentSearchQuery = jsonInnerSearch.value.trim().toLowerCase();
                renderVisualEditor();
            });

            // Tabs buttons
            document.getElementById('tab-visual-btn').addEventListener('click', (e) => {
                if (activeTab === 'visual') return;
                activeTab = 'visual';
                document.getElementById('tab-visual-btn').classList.add('active');
                document.getElementById('tab-raw-btn').classList.remove('active');
                contentVisual.style.display = 'block';
                contentRaw.style.display = 'none';
                
                // Sync code to visual data
                try {
                    const rawVal = codeMirrorEditor.getValue();
                    if (rawVal.trim() !== '') {
                        currentJsonData = JSON.parse(rawVal);
                        renderVisualEditor();
                    }
                } catch(e) {
                    showToast('Visual Moduna geçilemiyor: Ham kodda JSON hataları var.', 'warning');
                    activeTab = 'raw';
                    document.getElementById('tab-visual-btn').classList.remove('active');
                    document.getElementById('tab-raw-btn').classList.add('active');
                    contentVisual.style.display = 'none';
                    contentRaw.style.display = 'block';
                }
            });

            document.getElementById('tab-raw-btn').addEventListener('click', (e) => {
                if (activeTab === 'raw') return;
                activeTab = 'raw';
                document.getElementById('tab-raw-btn').classList.add('active');
                document.getElementById('tab-visual-btn').classList.remove('active');
                contentVisual.style.display = 'none';
                contentRaw.style.display = 'block';
                
                // Sync visual data structure to editor
                codeMirrorEditor.setValue(JSON.stringify(currentJsonData, null, 2));
                codeMirrorEditor.refresh();
            });

            // Reference Toggle
            toggleRefBtn.addEventListener('click', () => {
                isRefPanelOpen = !isRefPanelOpen;
                if (isRefPanelOpen) {
                    appLayout.classList.add('show-reference');
                    toggleRefBtn.textContent = '📖 Referans Panelini Kapat';
                    loadReferenceJson();
                } else {
                    appLayout.classList.remove('show-reference');
                    toggleRefBtn.textContent = '📖 Referans Panelini Aç';
                }
            });

            closeRefPanelBtn.addEventListener('click', () => {
                isRefPanelOpen = false;
                appLayout.classList.remove('show-reference');
                toggleRefBtn.textContent = '📖 Referans Panelini Aç';
            });

            // Save trigger
            saveFileBtn.addEventListener('click', saveJsonFile);

            // Shortcuts key binder
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if (selectedFilePath && isDirty) {
                        saveJsonFile();
                    }
                }
            });

            // Modals Confirmation Handlers
            // 1. Rename Key
            confirmRenameKeyBtn.addEventListener('click', () => {
                const newKey = renameKeyInput.value.trim();
                if (!newKey) {
                    showToast('Anahtar adı boş olamaz.', 'warning');
                    return;
                }
                renameDeepKey(currentJsonData, modalRenamePath, newKey);
                renameKeyModal.style.display = 'none';
                renderVisualEditor();
            });

            // 2. Add Element
            confirmAddElementBtn.addEventListener('click', () => {
                const key = addElementKey.value.trim();
                const type = addElementType.value;

                if (!modalAddElementIsArray && !key) {
                    showToast('Anahtar adı girmelisiniz.', 'warning');
                    return;
                }

                addDeepKey(currentJsonData, modalAddElementPath, key, type);
                addElementModal.style.display = 'none';
                renderVisualEditor();
            });

            // 3. New Language
            createLangBtn.addEventListener('click', () => {
                newLangCodeInput.value = '';
                createLangModal.style.display = 'flex';
                newLangCodeInput.focus();
            });

            confirmCreateLangBtn.addEventListener('click', async () => {
                const code = newLangCodeInput.value.trim();
                const refSelect = document.getElementById('new-lang-ref-select');
                const ref = refSelect.value;
                if (!code) {
                    showToast('Dil kodu boş bırakılamaz.', 'warning');
                    return;
                }
                const fd = new FormData();
                fd.append('new_lang', code);
                fd.append('ref_lang', ref);

                addConsoleLog(`[Yeni Dil Oluşturuluyor] Hedef Dil: ${code.toUpperCase()}, Referans Dil: ${ref.toUpperCase()}`, 'info');

                try {
                    const result = await fetchApi('create_lang', {}, {
                        method: 'POST',
                        body: fd
                    });
                    showToast(result.message, 'success');
                    addConsoleLog(`[Yeni Dil Başarıyla Oluşturuldu] ${result.message}`, 'success');
                    createLangModal.style.display = 'none';
                    
                    // Add new option dynamically to all language selects
                    [editorLangSelect, referenceLangSelect, refSelect].forEach(selectEl => {
                        const opt = document.createElement('option');
                        opt.value = code;
                        opt.textContent = code.toUpperCase();
                        selectEl.appendChild(opt);
                    });
                    
                } catch(e) {
                    showToast(e.message, 'error');
                    addConsoleLog(`[Yeni Dil Oluşturma Hatası] Hedef Dil: ${code.toUpperCase()}, Hata: ${e.message}`, 'error', e.details || e);
                }
            });

            // API Settings Handlers
            let editingAccountId = null;

            apiSettingsBtn.addEventListener('click', () => {
                resetApiForm();
                apiSettingsModal.style.display = 'flex';
            });

            closeApiSettingsModal.addEventListener('click', () => {
                apiSettingsModal.style.display = 'none';
                resetApiForm();
            });
            cancelApiSettingsModal.addEventListener('click', () => {
                apiSettingsModal.style.display = 'none';
                resetApiForm();
            });

            window.editApiAccount = function(id) {
                const account = apiAccounts.find(acc => acc.id === id);
                if (!account) return;

                editingAccountId = id;
                newApiProvider.value = account.provider;
                newApiName.value = account.name;
                newApiKey.value = account.key;
                document.getElementById('new-api-default-model').value = account.default_model || '';

                document.getElementById('api-form-title').textContent = 'API Hesabını Düzenle: ' + account.name;
                saveNewApiBtn.textContent = 'Hesabı Güncelle';
                document.getElementById('cancel-edit-api-btn').style.display = 'inline-flex';

                const modalBody = document.querySelector('#api-settings-modal .modal-body');
                if (modalBody) {
                    modalBody.scrollTo({
                        top: modalBody.scrollHeight,
                        behavior: 'smooth'
                    });
                }
                newApiName.focus();
            };

            window.resetApiForm = function() {
                editingAccountId = null;
                newApiName.value = '';
                newApiKey.value = '';
                document.getElementById('new-api-default-model').value = '';
                document.getElementById('api-form-title').textContent = 'Yeni API Hesabı Ekle';
                saveNewApiBtn.textContent = 'Hesap Ekle';
                document.getElementById('cancel-edit-api-btn').style.display = 'none';
            };

            document.getElementById('cancel-edit-api-btn').addEventListener('click', resetApiForm);

            saveNewApiBtn.addEventListener('click', async () => {
                const provider = newApiProvider.value;
                const name = newApiName.value.trim();
                const key = newApiKey.value.trim();
                const defaultModel = document.getElementById('new-api-default-model').value.trim();

                if (!name || !key) {
                    showToast('Hesap ismi ve API Key alanları zorunludur.', 'warning');
                    return;
                }

                if (editingAccountId) {
                    const idx = apiAccounts.findIndex(acc => acc.id === editingAccountId);
                    if (idx !== -1) {
                        apiAccounts[idx].provider = provider;
                        apiAccounts[idx].name = name;
                        apiAccounts[idx].key = key;
                        apiAccounts[idx].default_model = defaultModel;
                    }
                    resetApiForm();
                } else {
                    const newAccount = {
                        id: provider + '-' + Date.now(),
                        provider,
                        name,
                        key,
                        default_model: defaultModel
                    };
                    apiAccounts.push(newAccount);
                }

                await saveApiSettings();
                
                newApiName.value = '';
                newApiKey.value = '';
                document.getElementById('new-api-default-model').value = '';
            });

            // Helper API management actions
            async function loadApiSettings() {
                try {
                    const result = await fetchApi('get_api_settings');
                    apiAccounts = result.accounts || [];
                    renderApiAccountsList();
                    populateApiSelect();
                } catch (e) {
                    showToast('API ayarları yüklenemedi: ' + e.message, 'error');
                }
            }

            function renderApiAccountsList() {
                if (apiAccounts.length === 0) {
                    apiAccountsList.innerHTML = `<div style="color:var(--muted-color); font-size:0.88rem; text-align:center; padding:12px;">Tanımlı API hesabı bulunmuyor.</div>`;
                    return;
                }
                
                let html = '';
                apiAccounts.forEach(acc => {
                    const providerLabel = acc.provider === 'claudegg' ? 'Claude.gg' : (acc.provider === 'vertex' ? 'Vertex' : 'Gemini');
                    const maskedKey = acc.key ? acc.key.substring(0, 6) + '...' + acc.key.substring(acc.key.length - 4) : '';
                    const modelLabel = acc.default_model ? ` | Model: ${acc.default_model}` : '';
                    html += `
                        <div style="background-color:rgba(0,0,0,0.2); border:1px solid var(--border-color); padding:10px 14px; border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <strong style="font-size:0.9rem;">${escapeHtml(acc.name)}</strong>
                                <span style="font-size:0.75rem; color:var(--muted-color);">${providerLabel} | ${escapeHtml(maskedKey)}${escapeHtml(modelLabel)}</span>
                            </div>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button class="icon-btn success" onclick="testSavedApiAccount('${acc.id}')" title="Bağlantıyı Test Et">⚡</button>
                                <button class="icon-btn" onclick="editApiAccount('${acc.id}')" title="Hesabı Düzenle">✏️</button>
                                <button class="icon-btn danger" onclick="deleteApiAccount('${acc.id}')" title="Hesabı Sil">🗑️</button>
                            </div>
                        </div>
                    `;
                });
                apiAccountsList.innerHTML = html;
            }

            function populateApiSelect() {
                const currentVal = activeApiSelect.value;
                activeApiSelect.innerHTML = `<option value="">-- API Hesabı Seçin --</option>`;
                apiAccounts.forEach(acc => {
                    const opt = document.createElement('option');
                    opt.value = acc.id;
                    opt.textContent = acc.name;
                    if (acc.id === currentVal) {
                        opt.selected = true;
                    }
                    activeApiSelect.appendChild(opt);
                });
            }

            window.deleteApiAccount = async function(id) {
                if (!confirm('Bu API hesabını silmek istediğinize emin misiniz?')) return;
                apiAccounts = apiAccounts.filter(acc => acc.id !== id);
                if (editingAccountId === id) {
                    resetApiForm();
                }
                await saveApiSettings();
            };

            async function saveApiSettings() {
                const fd = new FormData();
                fd.append('accounts', JSON.stringify(apiAccounts));
                try {
                    const result = await fetchApi('save_api_settings', {}, {
                        method: 'POST',
                        body: fd
                    });
                    showToast(result.message, 'success');
                    renderApiAccountsList();
                    populateApiSelect();
                } catch (e) {
                    showToast('API ayarları kaydedilemedi: ' + e.message, 'error');
                }
            }

            window.translateValueInline = async function(path, btnEl) {
                const activeAccountId = activeApiSelect.value;
                if (!activeAccountId) {
                    showToast('Lütfen önce yukarıdaki bardan aktif bir API hesabı seçin.', 'warning');
                    return;
                }

                let sourceText = '';
                if (referenceJsonData) {
                    let current = referenceJsonData;
                    let found = true;
                    for (let i = 0; i < path.length; i++) {
                        if (current[path[i]] !== undefined) {
                            current = current[path[i]];
                        } else {
                            found = false;
                            break;
                        }
                    }
                    if (found && typeof current === 'string') {
                        sourceText = current;
                    }
                }

                if (sourceText === '') {
                    let current = currentJsonData;
                    let found = true;
                    for (let i = 0; i < path.length; i++) {
                        if (current[path[i]] !== undefined) {
                            current = current[path[i]];
                        } else {
                            found = false;
                            break;
                        }
                    }
                    if (found && typeof current === 'string') {
                        sourceText = current;
                    }
                }

                if (sourceText.trim() === '') {
                    showToast('Çevrilecek kaynak metin bulunamadı.', 'warning');
                    return;
                }

                const originalText = btnEl.textContent;
                btnEl.textContent = '⏳';
                btnEl.disabled = true;

                const sourceLang = 'tr';
                const targetLang = editorLangSelect.value;
                const selectedModel = activeModelInput.value.trim();

                const fd = new FormData();
                fd.append('account_id', activeAccountId);
                fd.append('text', sourceText);
                fd.append('source_lang', sourceLang);
                fd.append('target_lang', targetLang);
                fd.append('model', selectedModel);

                addConsoleLog(`[Satır Çevirisi İstendi] (${sourceLang.toUpperCase()} -> ${targetLang.toUpperCase()}) Değer: "${sourceText}"`, 'info');

                try {
                    const result = await fetchApi('translate_text', {}, {
                        method: 'POST',
                        body: fd
                    });
                    
                    setDeepValue(currentJsonData, path, result.translated);
                    renderVisualEditor();
                    showToast('Çeviri başarıyla yapıldı.', 'success');
                    addConsoleLog(`[Satır Çevirisi Başarılı] Orijinal: "${sourceText}" -> Çeviri: "${result.translated}"`, 'success');
                } catch (e) {
                    showToast('Çeviri hatası: ' + e.message, 'error');
                    addConsoleLog(`[Satır Çevirisi Hatası] Kaynak: "${sourceText}", Hata: ${e.message}`, 'error', e.details || e);
                } finally {
                    btnEl.textContent = originalText;
                    btnEl.disabled = false;
                }
            };

            // Active API change listener
            activeApiSelect.addEventListener('change', () => {
                const selectedId = activeApiSelect.value;
                const account = apiAccounts.find(acc => acc.id === selectedId);
                if (account) {
                    activeModelInput.value = account.default_model || (account.provider === 'claudegg' ? 'claude-3-5-sonnet-20241022' : 'gemini-2.0-flash');
                } else {
                    activeModelInput.value = '';
                }
            });

            // Translate file click listener
            const translateFileBtn = document.getElementById('translate-file-btn');
            translateFileBtn.addEventListener('click', async () => {
                const activeAccountId = activeApiSelect.value;
                if (!activeAccountId) {
                    showToast('Lütfen önce aktif bir API hesabı seçin.', 'warning');
                    return;
                }

                if (!selectedFilePath || !currentJsonData) {
                    showToast('Lütfen önce düzenlemek için bir JSON dosyası seçin.', 'warning');
                    return;
                }

                const targetLang = editorLangSelect.value;
                if (targetLang === 'tr') {
                    showToast('Türkçe kaynak dil olduğu için yapay zeka çevirisi yapılamaz.', 'warning');
                    return;
                }
                const sourceLang = 'tr'; // Always 'tr'
                const sourceLabel = 'TR (Türkçe)';
                const selectedModel = activeModelInput.value.trim();

                if (!confirm(`Bu işlem, ${sourceLabel} içeriğini kaynak alarak tüm dosyayı yapay zeka ile ${targetLang.toUpperCase()} diline çevirecektir. Devam etmek istiyor musunuz?`)) {
                    return;
                }

                addConsoleLog(`[Dosya Çevirisi Başladı] Dosya: ${selectedFilePath}, Kaynak Dil: ${sourceLang.toUpperCase()}, Hedef Dil: ${targetLang.toUpperCase()}`, 'info');

                const originalText = translateFileBtn.textContent;
                translateFileBtn.textContent = '⏳ Çevriliyor...';
                translateFileBtn.disabled = true;

                const fd = new FormData();
                fd.append('account_id', activeAccountId);
                fd.append('path', selectedFilePath);
                fd.append('source_lang', sourceLang);
                fd.append('target_lang', targetLang);
                fd.append('model', selectedModel);

                try {
                    const result = await fetchApi('translate_file', {}, {
                        method: 'POST',
                        body: fd
                    });

                    currentJsonData = result.data;
                    
                    // Update raw code in CM if initialized
                    if (codeMirrorEditor) {
                        codeMirrorEditor.setValue(JSON.stringify(currentJsonData, null, 2));
                    }
                    
                    renderVisualEditor();
                    markAsDirty(true);
                    showToast('Dosya çevirisi başarıyla tamamlandı. Kaydetmek için "Dosyayı Kaydet" butonuna basabilirsiniz.', 'success');
                    addConsoleLog(`[Dosya Çevirisi Başarılı] ${selectedFilePath} başarıyla çevrildi. Dosyayı diske kaydetmek için "Dosyayı Kaydet" butonuna basabilirsiniz.`, 'success');
                } catch (e) {
                    showToast('Çeviri hatası: ' + e.message, 'error');
                    addConsoleLog(`[Dosya Çevirisi Hatası] Dosya: ${selectedFilePath}, Hata: ${e.message}`, 'error', e.details || e);
                } finally {
                    translateFileBtn.textContent = originalText;
                    translateFileBtn.disabled = false;
                }
            });

            // Checkbox change listener
            fileTreeContainer.addEventListener('change', (e) => {
                if (e.target.classList.contains('tree-file-checkbox')) {
                    const path = e.target.dataset.path;
                    if (e.target.checked) {
                        checkedFiles.add(path);
                    } else {
                        checkedFiles.delete(path);
                    }
                    updateBatchToolsVisibility();
                } else if (e.target.classList.contains('tree-folder-checkbox')) {
                    const path = e.target.dataset.path;
                    const isChecked = e.target.checked;
                    if (isChecked) {
                        checkedFolders.add(path);
                    } else {
                        checkedFolders.delete(path);
                    }
                    toggleChildCheckboxes(path, isChecked);
                    updateBatchToolsVisibility();
                }
            });

            // Shift+Click multi selection for tree checkboxes
            fileTreeContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('tree-file-checkbox')) {
                    const cb = e.target;
                    const path = cb.dataset.path;
                    if (e.shiftKey && lastCheckedFilePath) {
                        const cbs = Array.from(document.querySelectorAll('.tree-file-checkbox'));
                        const lastCb = cbs.find(x => x.dataset.path === lastCheckedFilePath);
                        if (lastCb && lastCb !== cb) {
                            const start = cbs.indexOf(lastCb);
                            const end = cbs.indexOf(cb);
                            if (start !== -1 && end !== -1) {
                                const range = [start, end].sort((a, b) => a - b);
                                const isChecked = cb.checked;
                                for (let i = range[0]; i <= range[1]; i++) {
                                    const currentCb = cbs[i];
                                    currentCb.checked = isChecked;
                                    const currentPath = currentCb.dataset.path;
                                    if (isChecked) {
                                        checkedFiles.add(currentPath);
                                    } else {
                                        checkedFiles.delete(currentPath);
                                    }
                                }
                                updateBatchToolsVisibility();
                            }
                        }
                    }
                    lastCheckedFilePath = path;
                } else if (e.target.classList.contains('tree-folder-checkbox')) {
                    const cb = e.target;
                    const path = cb.dataset.path;
                    if (e.shiftKey && lastCheckedFolderPath) {
                        const cbs = Array.from(document.querySelectorAll('.tree-folder-checkbox'));
                        const lastCb = cbs.find(x => x.dataset.path === lastCheckedFolderPath);
                        if (lastCb && lastCb !== cb) {
                            const start = cbs.indexOf(lastCb);
                            const end = cbs.indexOf(cb);
                            if (start !== -1 && end !== -1) {
                                const range = [start, end].sort((a, b) => a - b);
                                const isChecked = cb.checked;
                                for (let i = range[0]; i <= range[1]; i++) {
                                    const currentCb = cbs[i];
                                    currentCb.checked = isChecked;
                                    const currentPath = currentCb.dataset.path;
                                    if (isChecked) {
                                        checkedFolders.add(currentPath);
                                    } else {
                                        checkedFolders.delete(currentPath);
                                    }
                                    toggleChildCheckboxes(currentPath, isChecked);
                                }
                                updateBatchToolsVisibility();
                            }
                        }
                    }
                    lastCheckedFolderPath = path;
                }
            });

            // Sidebar clear selection click listener
            document.getElementById('clear-tree-selection-btn').addEventListener('click', () => {
                checkedFiles.clear();
                checkedFolders.clear();
                restoreCheckboxStates();
                updateBatchToolsVisibility();
            });

            // Batch translation runner click listener
            const batchTranslateBtn = document.getElementById('batch-translate-btn');
            batchTranslateBtn.addEventListener('click', async () => {
                const activeAccountId = activeApiSelect.value;
                if (!activeAccountId) {
                    showToast('Lütfen önce aktif bir API hesabı seçin.', 'warning');
                    return;
                }

                const targetLang = editorLangSelect.value;
                if (targetLang === 'tr') {
                    showToast('Türkçe kaynak dil olduğu için yapay zeka çevirisi yapılamaz.', 'warning');
                    return;
                }
                const refLang = 'tr'; // Always 'tr'
                const filePaths = Array.from(checkedFiles);

                if (!confirm(`Seçilen ${filePaths.length} adet JSON dosyası TR (Türkçe) dilinden ${targetLang.toUpperCase()} diline toplu olarak çevrilecektir. Devam etmek istiyor musunuz?`)) {
                    return;
                }

                // Show console panel
                const panel = document.getElementById('console-panel');
                if (panel.classList.contains('collapsed')) {
                    toggleConsolePanel();
                }

                addConsoleLog(`[Toplu Çeviri Başladı] Toplam ${filePaths.length} dosya işlenecek.`, 'info');

                const originalText = batchTranslateBtn.textContent;
                batchTranslateBtn.textContent = '⏳ Çevriliyor...';
                batchTranslateBtn.disabled = true;

                let successCount = 0;
                let failCount = 0;

                for (let i = 0; i < filePaths.length; i++) {
                    const filePath = filePaths[i];
                    addConsoleLog(`[İşlem Sırası: ${i+1}/${filePaths.length}] ${filePath} çevriliyor...`, 'info');

                    try {
                        // 1. Load reference file
                        addConsoleLog(`-> Referans yükleniyor: ${refLang}/${filePath}`, 'info');
                        const refResult = await fetchApi('load', { lang: refLang, path: filePath });
                        
                        // 2. Call translate_file
                        addConsoleLog(`-> API çeviri isteği gönderiliyor...`, 'info');
                        const transFd = new FormData();
                        transFd.append('account_id', activeAccountId);
                        transFd.append('source_lang', refLang);
                        transFd.append('target_lang', targetLang);
                        transFd.append('model', activeModelInput.value.trim());
                        transFd.append('json', JSON.stringify(refResult.data));

                        const transResult = await fetchApi('translate_file', {}, {
                            method: 'POST',
                            body: transFd
                        });

                        // 3. Save to target path
                        addConsoleLog(`-> Çeviri kaydediliyor: ${targetLang}/${filePath}`, 'info');
                        const saveFd = new FormData();
                        saveFd.append('lang', targetLang);
                        saveFd.append('path', filePath);
                        saveFd.append('json', JSON.stringify(transResult.data, null, 2));

                        await fetchApi('save', {}, {
                            method: 'POST',
                            body: saveFd
                        });

                        successCount++;
                        addConsoleLog(`[Başarılı] ${filePath} başarıyla çevrildi ve diske kaydedildi.`, 'success');

                        // If currently active file is the one we translated, reload it
                        if (selectedFilePath === filePath) {
                            currentJsonData = transResult.data;
                            if (codeMirrorEditor) {
                                codeMirrorEditor.setValue(JSON.stringify(currentJsonData, null, 2));
                            }
                            renderVisualEditor();
                            markAsDirty(false);
                        }

                    } catch (e) {
                        failCount++;
                        addConsoleLog(`[Hata] ${filePath} çevrilirken hata oluştu: ${e.message}`, 'error', e.details || e);
                    }
                }

                addConsoleLog(`[Toplu Çeviri Tamamlandı] Başarılı: ${successCount}, Başarısız: ${failCount}`, successCount > 0 ? 'success' : 'warning');
                showToast(`Toplu çeviri tamamlandı. Başarılı: ${successCount}, Başarısız: ${failCount}`, successCount > 0 ? 'success' : 'error');

                batchTranslateBtn.textContent = originalText;
                batchTranslateBtn.disabled = false;

                // Clear selection after completion
                checkedFiles.clear();
                checkedFolders.clear();
                restoreCheckboxStates();
                updateBatchToolsVisibility();
                
                // Refresh directory tree
                loadTree();
            });

            // Console toggle event listener
            document.getElementById('console-toggle').addEventListener('click', (e) => {
                if (e.target.id === 'console-clear-btn') return;
                toggleConsolePanel();
            });

            // Console clear click listener
            document.getElementById('console-clear-btn').addEventListener('click', () => {
                document.getElementById('console-log-body').innerHTML = '';
                logCount = 0;
                document.getElementById('console-badge-count').textContent = '0';
            });

            // Test new API configuration button
            document.getElementById('test-new-api-btn').addEventListener('click', async () => {
                const provider = newApiProvider.value;
                const name = newApiName.value.trim();
                const key = newApiKey.value.trim();
                const defaultModel = document.getElementById('new-api-default-model').value.trim();

                if (!key) {
                    showToast('API Key girmelisiniz.', 'warning');
                    return;
                }

                addConsoleLog(`[API Test] Yeni hesap girdisi test ediliyor (${provider})...`, 'info');
                const fd = new FormData();
                fd.append('provider', provider);
                fd.append('key', key);
                fd.append('model', defaultModel);

                const testBtn = document.getElementById('test-new-api-btn');
                const originalText = testBtn.textContent;
                testBtn.textContent = '⏳ Test Ediliyor...';
                testBtn.disabled = true;

                try {
                    const result = await fetchApi('test_api', {}, {
                        method: 'POST',
                        body: fd
                    });
                    showToast('API bağlantısı başarılı.', 'success');
                    addConsoleLog(`[API Test Başarılı] Dönen cevap: "${result.translated}"`, 'success');
                } catch (e) {
                    showToast('API bağlantı testi başarısız oldu.', 'error');
                    addConsoleLog(`[API Test Hatası] Hata: ${e.message}`, 'error', e.details || e);
                } finally {
                    testBtn.textContent = originalText;
                    testBtn.disabled = false;
                }
            });

            // Load settings
            loadApiSettings();

            // Modal cancel buttons
            document.getElementById('cancel-create-lang-modal').addEventListener('click', () => createLangModal.style.display = 'none');
            document.getElementById('close-create-lang-modal').addEventListener('click', () => createLangModal.style.display = 'none');
            
            document.getElementById('cancel-rename-key-modal').addEventListener('click', () => renameKeyModal.style.display = 'none');
            document.getElementById('close-rename-key-modal').addEventListener('click', () => renameKeyModal.style.display = 'none');

            document.getElementById('cancel-add-element-modal').addEventListener('click', () => addElementModal.style.display = 'none');
            document.getElementById('close-add-element-modal').addEventListener('click', () => addElementModal.style.display = 'none');

            // Tree events delegation
            fileTreeContainer.addEventListener('click', (e) => {
                if (e.target.closest('.tree-checkbox-wrapper') || e.target.classList.contains('tree-file-checkbox') || e.target.classList.contains('tree-folder-checkbox')) {
                    return;
                }
                const item = e.target.closest('.tree-item');
                if (!item) return;

                const path = item.dataset.path;
                const type = item.dataset.type;

                if (type === 'directory') {
                    if (expandedFolders.has(path)) {
                        expandedFolders.delete(path);
                    } else {
                        expandedFolders.add(path);
                    }
                    renderTree();
                } else if (type === 'file') {
                    loadJsonFile(path);
                }
            });
        });
    </script>
</body>
</html>
