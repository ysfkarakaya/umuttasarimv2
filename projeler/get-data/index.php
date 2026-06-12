<?php
/**
 * Unified API synchronization and image download control center.
 */

$syncTasks = [
    'ayarlar' => [
        'title' => 'Site Ayarlari',
        'url' => 'https://v2.umutapp.com/api/v1/ayarlar.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/ayarlar/ayar.json',
        'extract' => 'values'
    ],
    'sayfa_aciklamalari' => [
        'title' => 'Sayfa Aciklamalari ve Urun Serileri',
        'url' => 'https://v2.umutapp.com/api/v1/sayfa_aciklamalari.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/sayfa_aciklamalari/sayfa_aciklamalari.json',
        'extract' => 'data'
    ],
    'kartlar' => [
        'title' => 'Kartlar ve Detaylari',
        'url' => 'https://v2.umutapp.com/api/v1/kartlar_full.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/kartlar/kartlar.json',
        'extract' => 'data',
        'group_by' => 'kart_kategori',
        'group_save_to' => 'lang/tr/kartlar/{key}.json',
        'group_split' => [
            'blog' => [
                'detail_key' => 'detaylar',
                'name_key' => 'kart_detay_adi',
                'save_to' => 'lang/tr/blog/{name}.json'
            ]
        ]
    ],
    'kategoriler' => [
        'title' => 'Kategoriler',
        'url' => 'https://v2.umutapp.com/api/v1/kategoriler.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/kategoriler/kategoriler.json',
        'extract' => 'data'
    ],
    'urunler' => [
        'title' => 'Urunler (Kategori Bazli)',
        'local_source' => 'lang/tr/kategoriler/kategoriler.json',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'sub_tasks' => [
            'is_tree' => true,
            'base_url' => 'https://v2.umutapp.com/api/v1/urunler.php?limit=100&tip=urun&kategori_id=',
            'id_key' => 'kat_id',
            'name_key' => 'kat_sef_url',
            'save_to' => 'lang/tr/urunler/{name}.json',
            'extract' => 'data'
        ]
    ],
    'urun_detaylar' => [
        'title' => 'Urun Detaylari (Tum Urunler)',
        'local_source' => 'lang/tr/urunler/',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'sub_tasks' => [
            'base_url' => 'https://v2.umutapp.com/api/v1/urun_detay.php?limit=100&id=',
            'id_key' => 'urun_id',
            'name_key' => 'sef_url',
            'url_suffix' => '&sef_url={name}',
            'save_to' => 'lang/tr/urun-detay/{name}.json',
            'extract' => 'data'
        ]
    ],
    'sayfalar' => [
        'title' => 'Sayfalar',
        'url' => 'https://v2.umutapp.com/api/v1/kartlar_full.php?kategori=sayfalar&limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'extract' => 'data',
        'split_details' => [
            'detail_key' => 'detaylar',
            'name_key' => 'kart_detay_adi',
            'save_to' => 'lang/tr/sayfalar/{name}.json'
        ]
    ],
    'referanslar' => [
        'title' => 'Referanslar ve Projeler',
        'url' => 'https://v2.umutapp.com/api/v1/referanslar.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/referanslar/referanslar.json',
        'extract' => 'data'
    ],
    'sertifikalar' => [
        'title' => 'Sertifika Icerikleri (Urun)',
        'local_source' => 'lang/tr/urunler/',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'sub_tasks' => [
            'base_url' => 'https://v2.umutapp.com/api/v1/sertifikalar.php?urun_id=',
            'id_key' => 'urun_id',
            'name_key' => 'sef_url',
            'save_to' => 'lang/tr/sertifikalar/{name}.json',
            'extract' => 'data'
        ]
    ],
    'sertifikalar_kategori' => [
        'title' => 'Sertifika Icerikleri (Kategori)',
        'local_source' => 'lang/tr/kategoriler/kategoriler.json',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'sub_tasks' => [
            'is_tree' => true,
            'base_url' => 'https://v2.umutapp.com/api/v1/sertifikalar.php?kat_id=',
            'id_key' => 'kat_id',
            'name_key' => 'kat_sef_url',
            'save_to' => 'lang/tr/sertifikalar/{name}.json',
            'extract' => 'data'
        ]
    ],
];

$imageSourceUrl = 'https://v2.umutapp.com/';
$dataDir = realpath(__DIR__ . '/../../data');
$imageJsonRoot = $dataDir . '/lang/tr';
$projectRoot = realpath(__DIR__ . '/../..');
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];

function flattenTree($tree, $childrenKey = 'children')
{
    $flat = [];
    foreach ($tree as $node) {
        $children = $node[$childrenKey] ?? [];
        unset($node[$childrenKey]);
        $flat[] = $node;
        if (!empty($children)) {
            $flat = array_merge($flat, flattenTree($children, $childrenKey));
        }
    }

    return $flat;
}

function slugify($text)
{
    if (empty($text)) {
        return 'n-a';
    }

    $turkish = array('ı', 'ş', 'ğ', 'ü', 'ö', 'ç', 'İ', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç');
    $english = array('i', 's', 'g', 'u', 'o', 'c', 'i', 's', 'g', 'u', 'o', 'c');
    $text = str_replace($turkish, $english, $text);

    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);

    return strtolower($text);
}

function apiRequest($url, $headers)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'code' => $httpCode,
        'error' => $error,
    ];
}

function ensureDirectory($path)
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

function writeJsonFile($path, $data, &$savedFiles)
{
    ensureDirectory($path);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $savedFiles[$path] = true;
}

function processTask($item, $subTaskConfig, $headers, &$processedIds, &$subCount, &$savedFiles)
{
    global $dataDir;
    if (!is_array($item)) {
        return;
    }

    $itemId = $item[$subTaskConfig['id_key']] ?? null;
    $itemName = $item[$subTaskConfig['name_key']] ?? 'item';

    if (!$itemId || isset($processedIds[$itemId])) {
        return;
    }

    $processedIds[$itemId] = true;
    $subUrl = $subTaskConfig['base_url'] . $itemId;
    if (isset($subTaskConfig['url_suffix'])) {
        $subUrl .= str_replace('{name}', $itemName, $subTaskConfig['url_suffix']);
    }

    $subResult = apiRequest($subUrl, $headers);
    if ($subResult['code'] !== 200 || $subResult['error']) {
        return;
    }

    $subData = json_decode($subResult['response'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return;
    }

    $extractedSub = $subData;
    if (isset($subTaskConfig['extract'])) {
        $extractedSub = $subData[$subTaskConfig['extract']] ?? $subData;
    }

    $subFileName = ($subTaskConfig['name_key'] === 'sef_url' || $subTaskConfig['name_key'] === 'kat_sef_url')
        ? $itemName
        : slugify($itemName);
    $subPath = $dataDir . '/' . str_replace('{name}', $subFileName, $subTaskConfig['save_to']);
    writeJsonFile($subPath, $extractedSub, $savedFiles);
    $subCount++;

    if (isset($extractedSub['benzerler']) && is_array($extractedSub['benzerler'])) {
        foreach ($extractedSub['benzerler'] as $benzerUrun) {
            processTask($benzerUrun, $subTaskConfig, $headers, $processedIds, $subCount, $savedFiles);
        }
    }
}

function loadLocalSourceData($task)
{
    global $dataDir;
    $sourcePath = $dataDir . '/' . $task['local_source'];

    if (is_dir($sourcePath)) {
        $batchData = [];
        foreach (glob($sourcePath . '/*.json') as $file) {
            $content = json_decode(file_get_contents($file), true);
            if (is_array($content)) {
                $batchData = array_merge($batchData, $content);
            }
        }

        return $batchData;
    }

    if (file_exists($sourcePath)) {
        return json_decode(file_get_contents($sourcePath), true);
    }

    return null;
}

function executeSyncTask($key, $task, &$savedFiles)
{
    global $dataDir;
    $primaryData = null;
    $subCount = 0;

    if (isset($task['local_source'])) {
        $primaryData = loadLocalSourceData($task);
        if ($primaryData === null) {
            return [
                'status' => 'error',
                'message' => 'Local source not found: ' . $task['local_source'],
            ];
        }
    } else {
        $result = apiRequest($task['url'], $task['headers'] ?? []);
        if ($result['error']) {
            return [
                'status' => 'error',
                'message' => 'CURL Error: ' . $result['error'],
            ];
        }

        if ($result['code'] !== 200) {
            return [
                'status' => 'error',
                'message' => 'HTTP Error: ' . $result['code'],
                'response' => $result['response'],
            ];
        }

        $data = json_decode($result['response'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => 'error',
                'message' => 'Invalid JSON response from API',
            ];
        }

        $primaryData = $data;
        if (isset($task['extract']) && $task['extract'] !== '') {
            $primaryData = $data[$task['extract']] ?? null;
        }

        if (isset($task['save_to'])) {
            $primaryPath = $dataDir . '/' . $task['save_to'];
            writeJsonFile($primaryPath, $primaryData, $savedFiles);
        }

        if (isset($task['group_by']) && is_array($primaryData)) {
            $groups = [];
            foreach ($primaryData as $item) {
                $itemKey = $item[$task['group_by']] ?? 'unknown';
                $groups[$itemKey][] = $item;
            }

            foreach ($groups as $groupKey => $groupItems) {
                $groupFileName = slugify($groupKey);
                $groupPath = $dataDir . '/' . str_replace('{key}', $groupFileName, $task['group_save_to']);
                writeJsonFile($groupPath, $groupItems, $savedFiles);
                $subCount++;

                if (isset($task['group_split'][$groupKey])) {
                    $splitCfg = $task['group_split'][$groupKey];
                    foreach ($groupItems as $groupItem) {
                        if (!isset($groupItem[$splitCfg['detail_key']]) || !is_array($groupItem[$splitCfg['detail_key']])) {
                            continue;
                        }

                        foreach ($groupItem[$splitCfg['detail_key']] as $detail) {
                            $detailName = $detail[$splitCfg['name_key']] ?? 'item';
                            $detailPath = $dataDir . '/' . str_replace('{name}', slugify($detailName), $splitCfg['save_to']);
                            writeJsonFile($detailPath, $detail, $savedFiles);
                            $subCount++;
                        }
                    }
                }
            }
        }

        if (isset($task['split_details']) && is_array($primaryData)) {
            foreach ($primaryData as $item) {
                if (!isset($item[$task['split_details']['detail_key']]) || !is_array($item[$task['split_details']['detail_key']])) {
                    continue;
                }

                foreach ($item[$task['split_details']['detail_key']] as $detail) {
                    $detailName = $detail[$task['split_details']['name_key']] ?? 'item';
                    $detailPath = $dataDir . '/' . str_replace('{name}', slugify($detailName), $task['split_details']['save_to']);
                    writeJsonFile($detailPath, $detail, $savedFiles);
                    $subCount++;
                }
            }
        }
    }

    if (isset($task['sub_tasks']) && is_array($primaryData)) {
        $itemsToProcess = $primaryData;
        if (!empty($task['sub_tasks']['is_tree'])) {
            $itemsToProcess = flattenTree($primaryData);
        }

        $processedIds = [];
        foreach ($itemsToProcess as $item) {
            processTask($item, $task['sub_tasks'], $task['headers'] ?? [], $processedIds, $subCount, $savedFiles);
        }
    }

    return [
        'status' => 'success',
        'task_key' => $key,
        'sub_count' => $subCount,
    ];
}

function normalizeScanTargets($targets, $fallbackDir)
{
    $normalized = [];
    foreach ($targets as $target) {
        if (!$target) {
            continue;
        }
        $normalized[$target] = true;
    }

    if (empty($normalized)) {
        $normalized[$fallbackDir] = true;
    }

    return array_keys($normalized);
}

function collectJsonFiles($targets)
{
    $files = [];

    foreach ($targets as $target) {
        if (is_file($target) && strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'json') {
            $files[$target] = true;
            continue;
        }

        if (!is_dir($target)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'json') {
                $files[$item->getPathname()] = true;
            }
        }
    }

    return array_keys($files);
}

function collectUploadPaths($data, &$paths, $onlyImages, $imageExtensions)
{
    if (!is_array($data) && !is_object($data)) {
        return;
    }

    foreach ($data as $value) {
        if (is_string($value) && strpos($value, 'upload/') === 0) {
            $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            if (!$onlyImages || in_array($extension, $imageExtensions, true)) {
                $paths[$value] = true;
            }
            continue;
        }

        if (is_array($value) || is_object($value)) {
            collectUploadPaths($value, $paths, $onlyImages, $imageExtensions);
        }
    }
}

function downloadResource($path, $sourceUrl, $rootDir)
{
    // URL segmentlerini urlencode ederek boşluk ve özel karakterleri güvenli hale getiriyoruz.
    $pathSegments = explode('/', ltrim($path, '/'));
    $encodedSegments = array_map('rawurlencode', $pathSegments);
    $encodedPath = implode('/', $encodedSegments);

    $remoteUrl = rtrim($sourceUrl, '/') . '/' . $encodedPath;
    $localPath = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (file_exists($localPath)) {
        return ['status' => 'existing', 'path' => $path];
    }

    $dir = dirname($localPath);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return ['status' => 'error', 'path' => $path, 'message' => 'Klasor olusturulamadi'];
    }

    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UmutAPP Control Center/2.0');

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => 'error', 'path' => $path, 'message' => $error];
    }

    if ($httpCode !== 200 || !$data) {
        return ['status' => 'error', 'path' => $path, 'message' => 'HTTP ' . $httpCode];
    }

    if (file_put_contents($localPath, $data) === false) {
        return ['status' => 'error', 'path' => $path, 'message' => 'Yazma hatasi'];
    }

    return ['status' => 'downloaded', 'path' => $path];
}

function executeImageDownload($targets, $sourceUrl, $rootDir, $jsonRoot, $imageExtensions, $onlyImages = true)
{
    if (!$rootDir || !is_dir($rootDir)) {
        return [
            'status' => 'error',
            'message' => 'Proje ana dizini tespit edilemedi.',
        ];
    }

    $targets = normalizeScanTargets($targets, $jsonRoot);
    $jsonFiles = collectJsonFiles($targets);
    $resourcePaths = [];

    foreach ($jsonFiles as $file) {
        $content = json_decode(file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            continue;
        }
        collectUploadPaths($content, $resourcePaths, $onlyImages, $imageExtensions);
    }

    $stats = [
        'status' => 'success',
        'scanned_files' => count($jsonFiles),
        'queued' => count($resourcePaths),
        'downloaded' => 0,
        'existing' => 0,
        'errors' => 0,
        'error_paths' => [],
    ];

    foreach (array_keys($resourcePaths) as $resourcePath) {
        $result = downloadResource($resourcePath, $sourceUrl, $rootDir);
        if ($result['status'] === 'downloaded') {
            $stats['downloaded']++;
        } elseif ($result['status'] === 'existing') {
            $stats['existing']++;
        } else {
            $stats['errors']++;
            $stats['error_paths'][] = $resourcePath . ' (' . ($result['message'] ?? 'bilinmeyen hata') . ')';
        }
    }

    return $stats;
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['action'];
    if ($action === 'sync') {
        $key = $_GET['key'] ?? '';
        if (!isset($syncTasks[$key])) {
            echo json_encode(['status' => 'error', 'message' => 'Gecersiz gorev anahtari.']);
            exit;
        }

        $savedFiles = [];
        $syncResult = executeSyncTask($key, $syncTasks[$key], $savedFiles);
        if ($syncResult['status'] !== 'success') {
            echo json_encode($syncResult);
            exit;
        }

        $response = [
            'status' => 'success',
            'message' => 'Senkronizasyon tamamlandi. Ana veri ve ' . $syncResult['sub_count'] . ' adet alt dosya guncellendi.',
            'time' => date('H:i:s d.m.Y'),
            'images' => null,
        ];

        if (!empty($_GET['with_images'])) {
            $imageResult = executeImageDownload(array_keys($savedFiles), $imageSourceUrl, $projectRoot, $imageJsonRoot, $imageExtensions, true);
            if ($imageResult['status'] === 'error') {
                echo json_encode($imageResult);
                exit;
            }

            $response['images'] = $imageResult;
            $response['message'] .= ' Gorsel taramasi tamamlandi: ' . $imageResult['downloaded'] . ' yeni, ' . $imageResult['existing'] . ' mevcut, ' . $imageResult['errors'] . ' hata.';
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'images') {
        $targets = [];
        if (!empty($_GET['key']) && isset($syncTasks[$_GET['key']])) {
            $task = $syncTasks[$_GET['key']];
            if (isset($task['save_to'])) {
                $targets[] = $dataDir . '/' . dirname($task['save_to']);
            }
            if (isset($task['group_save_to'])) {
                $targets[] = $dataDir . '/' . dirname($task['group_save_to']);
            }
            if (isset($task['split_details']['save_to'])) {
                $targets[] = $dataDir . '/' . dirname($task['split_details']['save_to']);
            }
            if (isset($task['group_split']) && is_array($task['group_split'])) {
                foreach ($task['group_split'] as $splitConfig) {
                    $targets[] = $dataDir . '/' . dirname($splitConfig['save_to']);
                }
            }
            if (isset($task['sub_tasks']['save_to'])) {
                $targets[] = $dataDir . '/' . dirname($task['sub_tasks']['save_to']);
            }
        }

        $imageResult = executeImageDownload($targets, $imageSourceUrl, $projectRoot, $imageJsonRoot, $imageExtensions, true);
        $imageResult['time'] = date('H:i:s d.m.Y');
        echo json_encode($imageResult, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Gecersiz istek.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMUT | API Control Center v2</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #22c55e;
            --primary-glow: rgba(34, 197, 94, 0.35);
            --secondary: #38bdf8;
            --secondary-glow: rgba(56, 189, 248, 0.28);
            --bg-base: #020617;
            --bg-sidebar: #0f172a;
            --bg-console: radial-gradient(circle at top, rgba(14, 165, 233, 0.12), transparent 30%), #020617;
            --surface: rgba(15, 23, 42, 0.88);
            --surface-soft: rgba(255, 255, 255, 0.04);
            --text-title: #f8fafc;
            --text-body: #94a3b8;
            --glass-border: rgba(255, 255, 255, 0.08);
            --success: #10b981;
            --error: #f43f5e;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(34, 197, 94, 0.12), transparent 25%),
                radial-gradient(circle at right, rgba(56, 189, 248, 0.1), transparent 22%),
                var(--bg-base);
            color: var(--text-title);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        header {
            min-height: 80px;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            gap: 1rem;
        }

        .title-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .logo span {
            color: var(--secondary);
        }

        .subtitle {
            color: var(--text-body);
            font-size: 0.85rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .stats {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            padding: 0.8rem 1rem;
            background: var(--surface-soft);
            border: 1px solid var(--glass-border);
            border-radius: 18px;
        }

        .stat span:first-child {
            color: var(--text-body);
            margin-right: 0.4rem;
        }

        .stat span:last-child {
            color: var(--secondary);
            font-weight: 600;
        }

        .action-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .action-btn {
            border: none;
            border-radius: 14px;
            padding: 0.85rem 1.2rem;
            font-weight: 700;
            cursor: pointer;
            color: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .action-btn:disabled {
            cursor: not-allowed;
            opacity: 0.65;
            transform: none;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary), #16a34a);
            box-shadow: 0 12px 28px -12px var(--primary-glow);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, var(--secondary), #0284c7);
            box-shadow: 0 12px 28px -12px var(--secondary-glow);
        }

        .wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .sidebar {
            width: 360px;
            background: rgba(15, 23, 42, 0.96);
            border-right: 1px solid var(--glass-border);
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .section-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--text-body);
        }

        .sync-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.2rem;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .sync-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary);
            transform: scale(1.02);
        }

        .sync-card.loading {
            border-color: var(--warning);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.2);
        }

        .sync-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .sync-card p {
            font-size: 0.75rem;
            color: var(--text-body);
            font-family: monospace;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-radius: 18px;
            background: var(--surface-soft);
            border: 1px solid var(--glass-border);
            color: var(--text-body);
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .monitor {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: var(--bg-console);
        }

        .monitor-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            color: var(--text-body);
            font-size: 0.82rem;
            background: rgba(2, 6, 23, 0.7);
        }

        .monitor-header strong {
            color: var(--text-title);
        }

        .monitor-tools {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .link-btn {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
            text-decoration: underline;
        }

        .console-output {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .log-line {
            display: grid;
            grid-template-columns: 90px 82px minmax(0, 1fr);
            gap: 0.8rem;
            margin-bottom: 0.65rem;
            animation: fadeIn 0.25s ease;
        }

        .log-time {
            color: #64748b;
        }

        .log-type {
            width: fit-content;
            padding: 0.14rem 0.5rem;
            border-radius: 999px;
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
        }

        .log-type.info {
            color: var(--secondary);
            background: rgba(56, 189, 248, 0.12);
        }

        .log-type.success {
            color: var(--success);
            background: rgba(16, 185, 129, 0.12);
        }

        .log-type.error {
            color: var(--error);
            background: rgba(244, 63, 94, 0.12);
        }

        .log-type.warning {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.12);
        }

        .log-msg {
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .log-msg strong {
            color: white;
        }

        #modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(10px);
            z-index: 9999;
        }

        .modal-card {
            width: min(520px, 100%);
            padding: 2rem;
            border-radius: 28px;
            border: 1px solid var(--glass-border);
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(15, 23, 42, 0.9));
            box-shadow: 0 30px 80px -40px rgba(0, 0, 0, 0.8);
            animation: modalIn 0.28s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .modal-icon {
            width: 62px;
            height: 62px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(56, 189, 248, 0.12);
            color: var(--secondary);
            margin-bottom: 1.25rem;
        }

        .modal-card h2 {
            font-size: 1.45rem;
            margin-bottom: 0.65rem;
        }

        .modal-card p {
            color: var(--text-body);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .modal-btn {
            flex: 1 1 140px;
            padding: 0.9rem 1rem;
            border-radius: 14px;
            border: 1px solid var(--glass-border);
            cursor: pointer;
            font-weight: 700;
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .modal-btn.primary {
            border: none;
            background: linear-gradient(135deg, var(--primary), #16a34a);
        }

        .modal-btn.secondary {
            border: none;
            background: linear-gradient(135deg, var(--secondary), #0284c7);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.96) translateY(12px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.7);
            border-radius: 999px;
        }

        @media (max-width: 960px) {
            body {
                overflow: auto;
            }

            .wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                max-height: none;
            }

            .log-line {
                grid-template-columns: 1fr;
                gap: 0.35rem;
            }

            header,
            .monitor-header {
                padding-inline: 1rem;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="title-wrap">
            <div class="logo">UMUTAPP <span>Control Center</span></div>
            <div class="subtitle">API veri senkronizasyonu ve gorsel aktarimi tek panelde yonetilir.</div>
        </div>

        <div class="header-actions">
            <div class="stats">
                <div class="stat"><span>Gorevler:</span><span id="stat-total">0</span></div>
                <div class="stat"><span>Aktif:</span><span id="stat-active">0</span></div>
                <div class="stat"><span>Son Update:</span><span id="stat-last">-</span></div>
            </div>

            <div class="action-bar">
                <button class="action-btn secondary" id="download-images-btn" onclick="downloadImages()">Direkt
                    Gorselleri Cek</button>
                <button class="action-btn primary" id="sync-all-btn" onclick="syncAll()">Tumunu Baslat</button>
            </div>
        </div>
    </header>

    <div class="wrapper">
        <aside class="sidebar">
            <div class="section-label">Senkronizasyon Gorevleri</div>

            <?php foreach ($syncTasks as $key => $task): ?>
                <div class="sync-card" id="card-<?php echo $key; ?>" onclick="promptSync('<?php echo $key; ?>')">
                    <h3><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($task['save_to'] ?? ('Local: ' . $task['local_source']), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div style="font-size: 0.65rem; color: #52525b; margin-top: 6px; display: flex; align-items: center; gap: 4px;">
                        <span style="opacity: 0.6;">Update:</span>
                        <span class="task-last-update" id="update-<?php echo $key; ?>">-</span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="sidebar-footer">
                Bir goreve tikladiginizda once veri senkronizasyonu, ardindan isterseniz ayni goreve ait gorsel taramasi
                calistirilir. Ustteki bagimsiz buton tum JSON kaynaklarini tarayarak sadece gorsel indirmeyi baslatir.
            </div>
        </aside>

        <section class="monitor">
            <div class="monitor-header">
                <div><strong>LIVE_TERMINAL_V2.1</strong> <span>Durum akisi ve islem loglari</span></div>
                <div class="monitor-tools">
                    <span id="auto-scroll-status" style="color: var(--success);">● Auto-Scroll ON</span>
                    <button class="link-btn" onclick="clearConsole()">Konsolu Temizle</button>
                </div>
            </div>
            <div class="console-output" id="log-console"></div>
        </section>
    </div>

    <div id="modal-overlay">
        <div class="modal-card">
            <div class="modal-icon">
                <svg width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                        d="M12 9v3.75m0 3h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                </svg>
            </div>
            <h2 id="modal-title">Islem Secimi</h2>
            <p id="modal-msg">Bu gorev icin hangi akisi calistirmak istiyorsunuz?</p>
            <div class="modal-actions" id="modal-actions"></div>
        </div>
    </div>

    <script>
        const tasksCount = <?php echo count($syncTasks); ?>;
        const statTotal = document.getElementById('stat-total');
        const statActive = document.getElementById('stat-active');
        const statLast = document.getElementById('stat-last');
        const logConsole = document.getElementById('log-console');
        const syncAllButton = document.getElementById('sync-all-btn');
        const downloadImagesButton = document.getElementById('download-images-btn');

        statTotal.innerText = tasksCount;

        function setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + date.toUTCString() + ';path=/';
        }

        function getCookie(name) {
            const nameEQ = name + '=';
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let current = ca[i];
                while (current.charAt(0) === ' ') {
                    current = current.substring(1);
                }
                if (current.indexOf(nameEQ) === 0) {
                    return current.substring(nameEQ.length);
                }
            }
            return null;
        }

        function formatDateTime(raw) {
            if (!raw || raw === '-' || !raw.includes(' ')) {
                return raw;
            }

            try {
                const [time, date] = raw.split(' ');
                const [hour, minute] = time.split(':');
                const [day, month] = date.split('.');
                const months = ['Ocak', 'Subat', 'Mart', 'Nisan', 'Mayis', 'Haziran', 'Temmuz', 'Agustos', 'Eylul', 'Ekim', 'Kasim', 'Aralik'];
                return `${parseInt(day, 10)} ${months[parseInt(month, 10) - 1]}, ${hour}:${minute}`;
            } catch (error) {
                return raw;
            }
        }

        function setBusy(isBusy) {
            syncAllButton.disabled = isBusy;
            downloadImagesButton.disabled = isBusy;
        }

        function updateActive(delta) {
            statActive.innerText = Math.max(0, parseInt(statActive.innerText, 10) + delta);
        }

        function addLog(message, type = 'info') {
            const line = document.createElement('div');
            line.className = 'log-line';
            line.innerHTML = `
                <span class="log-time">${new Date().toLocaleTimeString()}</span>
                <span class="log-type ${type}">${type}</span>
                <span class="log-msg">${message}</span>
            `;
            logConsole.appendChild(line);
            logConsole.scrollTop = logConsole.scrollHeight;
        }

        function clearConsole() {
            logConsole.innerHTML = '';
            addLog('Konsol temizlendi. Sistem beklemede.');
        }

        function restoreTimestamps() {
            const lastSync = getCookie('last_api_sync_v2');
            if (lastSync) {
                statLast.innerText = formatDateTime(lastSync);
            }

            document.querySelectorAll('.task-last-update').forEach((element) => {
                const key = element.id.replace('update-', '');
                const value = getCookie('last_sync_v2_' + key);
                if (value) {
                    element.innerText = formatDateTime(value);
                }
            });
        }

        let modalResolver = null;

        function showChoiceModal(title, message, buttons) {
            const overlay = document.getElementById('modal-overlay');
            const actions = document.getElementById('modal-actions');
            document.getElementById('modal-title').innerText = title;
            document.getElementById('modal-msg').innerText = message;
            actions.innerHTML = '';

            buttons.forEach((button) => {
                const element = document.createElement('button');
                element.className = `modal-btn ${button.className || ''}`.trim();
                element.innerText = button.label;
                element.onclick = () => resolveModal(button.value);
                actions.appendChild(element);
            });

            overlay.style.display = 'flex';
            return new Promise((resolve) => {
                modalResolver = resolve;
            });
        }

        function resolveModal(value) {
            document.getElementById('modal-overlay').style.display = 'none';
            if (modalResolver) {
                modalResolver(value);
                modalResolver = null;
            }
        }

        async function promptSync(key) {
            const card = document.getElementById('card-' + key);
            if (card.classList.contains('loading')) {
                return;
            }

            const title = card.querySelector('h3').innerText;
            const choice = await showChoiceModal(
                'Gorev Baslat',
                `"${title}" icin once veri senkronizasyonu yapilir. Sonrasinda gorselleri de cekmek ister misiniz?`,
                [
                    { label: 'Iptal', value: 'cancel' },
                    { label: 'Sadece Veri', value: 'data', className: 'secondary' },
                    { label: 'Veri + Gorseller', value: 'all', className: 'primary' }
                ]
            );

            if (choice === 'cancel') {
                return;
            }

            await syncTask(key, choice === 'all');
        }

        async function syncTask(key, withImages = false) {
            const card = document.getElementById('card-' + key);
            const title = card.querySelector('h3').innerText;

            card.classList.add('loading');
            updateActive(1);
            setBusy(true);
            addLog(`<strong>${title}</strong> baslatildi. Mod: ${withImages ? 'Veri + Gorseller' : 'Sadece Veri'}`, 'info');

            try {
                const response = await fetch(`?action=sync&key=${encodeURIComponent(key)}&with_images=${withImages ? 1 : 0}`);
                const result = await response.json();

                if (result.status !== 'success') {
                    addLog(`<strong>${title}</strong> hatasi: ${result.message}`, 'error');
                    return false;
                }

                const formatted = formatDateTime(result.time);
                statLast.innerText = formatted;
                document.getElementById('update-' + key).innerText = formatted;
                setCookie('last_api_sync_v2', result.time, 30);
                setCookie('last_sync_v2_' + key, result.time, 30);

                addLog(`<strong>${title}</strong>: ${result.message}`, 'success');
                if (result.images) {
                    addLog(`Gorsel ozeti: ${result.images.downloaded} yeni, ${result.images.existing} mevcut, ${result.images.errors} hata, ${result.images.scanned_files} JSON tarandi.`, result.images.errors ? 'warning' : 'info');
                }
                return true;
            } catch (error) {
                addLog(`<strong>${title}</strong>: kritik baglanti hatasi.`, 'error');
                return false;
            } finally {
                card.classList.remove('loading');
                updateActive(-1);
                setBusy(false);
            }
        }

        async function syncAll() {
            const choice = await showChoiceModal(
                'Toplu Senkronizasyon',
                'Tum gorevleri sirasiyla calistiracaksiniz. Gorsellerin de cekilmesini istiyor musunuz?',
                [
                    { label: 'Iptal', value: 'cancel' },
                    { label: 'Sadece Veri', value: 'data', className: 'secondary' },
                    { label: 'Veri + Gorseller', value: 'all', className: 'primary' }
                ]
            );

            if (choice === 'cancel') {
                return;
            }

            const withImages = choice === 'all';
            const keys = Array.from(document.querySelectorAll('.sync-card')).map((card) => card.id.replace('card-', ''));

            setBusy(true);
            addLog(`Toplu senkronizasyon baslatildi. Mod: ${withImages ? 'Veri + Gorseller' : 'Sadece Veri'}`, 'warning');

            try {
                for (const key of keys) {
                    await syncTask(key, withImages);
                }
                addLog('Tum gorevler tamamlandi.', 'success');
            } finally {
                setBusy(false);
            }
        }

        async function downloadImages() {
            const choice = await showChoiceModal(
                'Bagimsiz Gorsel Indirme',
                'Tum JSON kaynaklari taranacak ve eksik gorseller proje klasorune indirilecek. Devam edilsin mi?',
                [
                    { label: 'Iptal', value: 'cancel' },
                    { label: 'Gorselleri Cek', value: 'run', className: 'primary' }
                ]
            );

            if (choice !== 'run') {
                return;
            }

            setBusy(true);
            updateActive(1);
            addLog('Bagimsiz gorsel taramasi baslatildi...', 'info');

            try {
                const response = await fetch('?action=images');
                const result = await response.json();

                if (result.status !== 'success') {
                    addLog(`Gorsel indirme hatasi: ${result.message}`, 'error');
                    return;
                }

                statLast.innerText = formatDateTime(result.time);
                setCookie('last_api_sync_v2', result.time, 30);
                addLog(`Gorsel taramasi tamamlandi: ${result.downloaded} yeni, ${result.existing} mevcut, ${result.errors} hata, ${result.scanned_files} JSON tarandi.`, result.errors ? 'warning' : 'success');
            } catch (error) {
                addLog('Gorsel indirme istegi basarisiz oldu.', 'error');
            } finally {
                updateActive(-1);
                setBusy(false);
            }
        }

        restoreTimestamps();
        addLog('UmutAPP Control Center v2 hazir. Bir goreve tiklayarak modu secin.');
    </script>
</body>

</html>
