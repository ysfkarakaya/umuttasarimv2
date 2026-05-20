<?php
/**
 * API Data Synchronization Interface
 * Fetches data from remote APIs and saves to local JSON files.
 */

// Configuration for Sync Tasks
$syncTasks = [
    'ayarlar' => [
        'title' => 'Site Ayarları',
        'url' => 'https://v2.umutapp.com/api/v1/ayarlar.php?limit=100',
        'headers' => [
            'Authorization: Bearer 123',
            'Accept: application/json'
        ],
        'save_to' => 'lang/tr/ayarlar/ayar.json',
        'extract' => 'values'
    ],
    'kartlar' => [
        'title' => 'Kartlar ve Detayları',
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
        'title' => 'Ürünler (Kategori Bazlı)',
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
        'title' => 'Ürün Detayları (Tüm Ürünler)',
        'local_source' => 'lang/tr/urunler/', // Dizin bazlı kaynak
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
        'title' => 'Sertifika İçerikleri (Ürün)',
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
        'title' => 'Sertifika İçerikleri (Kategori)',
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

/**
 * Helper: Flatten tree structure
 */
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

/**
 * Helper: Slugify names for safe filenames
 */
function slugify($text)
{
    if (empty($text))
        return 'n-a';
    $turkish = array('ı', 'ş', 'ğ', 'ü', 'ö', 'ç', 'İ', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç');
    $english = array('i', 's', 'g', 'u', 'o', 'c', 'i', 's', 'g', 'u', 'o', 'c');
    $text = str_replace($turkish, $english, $text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text;
}

/**
 * Helper: cURL Request
 */
function apiRequest($url, $headers)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    // curl_close skipped as requested/deprecated
    return ['response' => $response, 'code' => $httpCode, 'error' => $error];
}

/**
 * Helper: Process a single sync task (can be recursive)
 */
function processTask($item, $subTaskConfig, $headers, &$processedIds, &$subCount)
{
    if (!is_array($item))
        return;

    $itemId = $item[$subTaskConfig['id_key']] ?? null;
    $itemName = $item[$subTaskConfig['name_key']] ?? 'item';

    if (!$itemId || isset($processedIds[$itemId]))
        return;
    $processedIds[$itemId] = true;

    // Build Sub URL
    $subUrl = $subTaskConfig['base_url'] . $itemId;
    if (isset($subTaskConfig['url_suffix'])) {
        $subUrl .= str_replace('{name}', $itemName, $subTaskConfig['url_suffix']);
    }

    $subResult = apiRequest($subUrl, $headers);
    if ($subResult['code'] === 200) {
        $subData = json_decode($subResult['response'], true);
        $extractedSub = $subData;
        if (isset($subTaskConfig['extract'])) {
            $extractedSub = $subData[$subTaskConfig['extract']] ?? $subData;
        }

        $subFileName = ($subTaskConfig['name_key'] === 'sef_url' || $subTaskConfig['name_key'] === 'kat_sef_url') ? $itemName : slugify($itemName);
        $subPath = __DIR__ . '/' . str_replace('{name}', $subFileName, $subTaskConfig['save_to']);

        if (!is_dir(dirname($subPath)))
            mkdir(dirname($subPath), 0755, true);
        file_put_contents($subPath, json_encode($extractedSub, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $subCount++;

        // Recursive check: "benzerler" support for product details
        if (isset($extractedSub['benzerler']) && is_array($extractedSub['benzerler'])) {
            foreach ($extractedSub['benzerler'] as $benzerUrun) {
                processTask($benzerUrun, $subTaskConfig, $headers, $processedIds, $subCount);
            }
        }
    }
}

// Handle AJAX Request
if (isset($_GET['action']) && $_GET['action'] === 'sync' && isset($_GET['key'])) {
    header('Content-Type: application/json');
    $key = $_GET['key'];
    $task = $syncTasks[$key];
    $primaryData = null;
    $batchData = []; // To store multiple files' data if source is directory
    $subCount = 0; // Initialize subCount here 

    // Use local source if defined, otherwise fetch from API
    if (isset($task['local_source'])) {
        $sourcePath = __DIR__ . '/' . $task['local_source'];

        if (is_dir($sourcePath)) {
            // Directory mode: Scan for JSON files
            $files = glob($sourcePath . '/*.json');
            foreach ($files as $file) {
                $content = json_decode(file_get_contents($file), true);
                if (is_array($content)) {
                    $batchData = array_merge($batchData, $content);
                }
            }
            $primaryData = $batchData;
        } elseif (file_exists($sourcePath)) {
            $primaryData = json_decode(file_get_contents($sourcePath), true);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Local source not found: ' . $task['local_source']]);
            exit;
        }
    } else {
        $result = apiRequest($task['url'], $task['headers'] ?? []);

        if ($result['error']) {
            echo json_encode(['status' => 'error', 'message' => 'CURL Error: ' . $result['error']]);
            exit;
        }

        if ($result['code'] !== 200) {
            echo json_encode(['status' => 'error', 'message' => 'HTTP Error: ' . $result['code'], 'response' => $result['response']]);
            exit;
        }

        $data = json_decode($result['response'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON response from API']);
            exit;
        }

        $primaryData = $data;
        if (isset($task['extract']) && !empty($task['extract'])) {
            $primaryData = $data[$task['extract']] ?? null;
        }

        if (isset($task['save_to'])) {
            $primaryPath = __DIR__ . '/' . $task['save_to'];
            if (!is_dir(dirname($primaryPath)))
                mkdir(dirname($primaryPath), 0755, true);
            file_put_contents($primaryPath, json_encode($primaryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (isset($task['group_by']) && is_array($primaryData)) {
            $groups = [];
            foreach ($primaryData as $item) {
                $itemKey = $item[$task['group_by']] ?? 'unknown';
                $groups[$itemKey][] = $item;
            }

            foreach ($groups as $groupKey => $groupItems) {
                $groupFileName = slugify($groupKey);
                $groupPath = __DIR__ . '/' . str_replace('{key}', $groupFileName, $task['group_save_to']);
                if (!is_dir(dirname($groupPath)))
                    mkdir(dirname($groupPath), 0755, true);
                file_put_contents($groupPath, json_encode($groupItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $subCount++;

                // Özel: Bazı grupların detaylarını tekil dosyalara böl (Örn: Blog detayları için)
                if (isset($task['group_split'][$groupKey])) {
                    $splitCfg = $task['group_split'][$groupKey];
                    foreach ($groupItems as $gItem) {
                        if (isset($gItem[$splitCfg['detail_key']]) && is_array($gItem[$splitCfg['detail_key']])) {
                            foreach ($gItem[$splitCfg['detail_key']] as $detail) {
                                $detailName = $detail[$splitCfg['name_key']] ?? 'item';
                                $detailFileName = slugify($detailName);
                                $detailPath = __DIR__ . '/' . str_replace('{name}', $detailFileName, $splitCfg['save_to']);

                                if (!is_dir(dirname($detailPath)))
                                    mkdir(dirname($detailPath), 0755, true);

                                file_put_contents($detailPath, json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                $subCount++;
                            }
                        }
                    }
                }
            }
        }

        if (isset($task['split_details']) && is_array($primaryData)) {
            foreach ($primaryData as $item) {
                if (isset($item[$task['split_details']['detail_key']]) && is_array($item[$task['split_details']['detail_key']])) {
                    foreach ($item[$task['split_details']['detail_key']] as $detail) {
                        $detailName = $detail[$task['split_details']['name_key']] ?? 'item';
                        $detailFileName = slugify($detailName);
                        $detailPath = __DIR__ . '/' . str_replace('{name}', $detailFileName, $task['split_details']['save_to']);

                        if (!is_dir(dirname($detailPath)))
                            mkdir(dirname($detailPath), 0755, true);
                        file_put_contents($detailPath, json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $subCount++;
                    }
                }
            }
        }
    }

    // Handle Sub Tasks
    if (isset($task['sub_tasks']) && is_array($primaryData)) {
        $itemsToProcess = $primaryData;
        if (isset($task['sub_tasks']['is_tree']) && $task['sub_tasks']['is_tree']) {
            $itemsToProcess = flattenTree($primaryData);
        }

        $processedIds = [];
        foreach ($itemsToProcess as $item) {
            processTask($item, $task['sub_tasks'], $task['headers'] ?? [], $processedIds, $subCount);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Senkronizasyon tamamlandı. Ana dosya ve ' . $subCount . ' adet alt dosya güncellendi.',
        'time' => date('H:i:s d.m.Y')
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMUT | API Command Center</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-base: #020617;
            --bg-sidebar: #0f172a;
            --bg-console: #000000;
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
            background-color: var(--bg-base);
            color: var(--text-title);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        header {
            height: 70px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .logo span {
            color: var(--primary);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .stats {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
        }

        .stat span:first-child {
            color: var(--text-body);
            margin-right: 5px;
        }

        .stat span:last-child {
            font-weight: 600;
            color: var(--primary);
        }

        /* Main Layout */
        .wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Sidebar: Buttons */
        .sidebar {
            width: 350px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--glass-border);
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .section-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-body);
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
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

        .btn-all {
            background: var(--primary);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            margin-top: auto;
            transition: 0.3s;
        }

        .btn-all:hover {
            box-shadow: 0 0 20px var(--primary-glow);
        }

        /* Console Area */
        .monitor {
            flex: 1;
            background: var(--bg-console);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .monitor-header {
            background: #111;
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #222;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-body);
        }

        .console-output {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        /* Log Styling */
        .log-line {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(10px);
            }
        }

        .log-time {
            color: #52525b;
            min-width: 100px;
        }

        .log-type {
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .log-type.info {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }

        .log-type.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .log-type.error {
            background: rgba(244, 63, 94, 0.1);
            color: var(--error);
        }

        .log-type.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .log-msg {
            color: #e2e8f0;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Animations */
        .pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 0.5;
            }

            50% {
                opacity: 1;
            }

            100% {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">UMUTAPP <span>API</span></div>

        <div class="header-actions">
            <div class="stats">
                <div class="stat"><span>Görevler:</span> <span id="stat-total">0</span></div>
                <div class="stat"><span>Aktif:</span> <span id="stat-active">0</span></div>
                <div class="stat"><span>Son Update:</span> <span id="stat-last">-</span></div>
            </div>
            <button class="btn-all" id="sync-all-btn" onclick="syncAll()"
                style="margin-top: 0; padding: 0.6rem 1.5rem;">Tümünü Başlat</button>
        </div>
    </header>

    <div class="wrapper">
        <div class="sidebar">
            <div class="section-label">SENKRONİZASYON GÖREVLERİ</div>

            <?php foreach ($syncTasks as $key => $task): ?>
                <div class="sync-card" id="card-<?php echo $key; ?>" onclick="sync('<?php echo $key; ?>')">
                    <h3><?php echo $task['title']; ?></h3>
                    <p><?php echo isset($task['save_to']) ? $task['save_to'] : 'Local: ' . $task['local_source']; ?></p>
                    <div
                        style="font-size: 0.65rem; color: #52525b; margin-top: 6px; display: flex; align-items: center; gap: 4px;">
                        <span style="opacity: 0.6;">Update:</span>
                        <span class="task-last-update" id="update-<?php echo $key; ?>">-</span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div
                style="margin-top: auto; padding: 1rem; background: rgba(255,255,255,0.02); border-radius: 12px; font-size: 0.8rem; color: var(--text-body);">
                <strong>Bilgi:</strong> Bir görev başlattığınızda sağdaki konsoldan tüm süreci gerçek zamanlı takip
                edebilirsiniz.
            </div>
        </div>

        <div class="monitor">
            <div class="monitor-header">
                <div><span>LIVE_TERMINAL_V2.0</span></div>
                <div style="display: flex; gap: 1rem;">
                    <span id="auto-scroll-status" style="color: var(--success);">● Auto-Scroll ON</span>
                    <button onclick="clearConsole()"
                        style="background:transparent; border:none; color:inherit; cursor:pointer; text-decoration:underline;">Konsolu
                        Temizle</button>
                </div>
            </div>
            <div class="console-output" id="log-console"></div>
        </div>

        <!-- Custom Modern Modal -->
        <div id="modal-overlay"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center;">
            <div
                style="background: rgba(30, 41, 59, 0.9); border: 1px solid var(--glass-border); border-radius: 24px; padding: 2.5rem; width: 450px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); animation: modalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
                <div
                    style="width: 64px; height: 64px; background: rgba(99, 102, 241, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: var(--primary);">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 id="modal-title" style="font-size: 1.5rem; margin-bottom: 0.8rem; font-weight: 700;">Onay Gerekiyor
                </h2>
                <p id="modal-msg" style="color: var(--text-body); margin-bottom: 2rem; line-height: 1.6;">Bu işlemi
                    başlatmak istediğinize emin misiniz?</p>
                <div style="display: flex; gap: 1rem;">
                    <button onclick="modalResolve(false)"
                        style="flex: 1; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: white; cursor: pointer; font-weight: 600;">İPTAL</button>
                    <button onclick="modalResolve(true)"
                        style="flex: 1; padding: 0.8rem; border-radius: 12px; border: none; background: var(--primary); color: white; cursor: pointer; font-weight: 600; box-shadow: 0 10px 15px -3px var(--primary-glow);">SİSTEMİ
                        BAŞLAT</button>
                </div>
            </div>
        </div>

        <style>
            @keyframes modalIn {
                from {
                    opacity: 0;
                    transform: scale(0.8) translateY(20px);
                }

                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
        </style>
    </div>

    <script>
        const tasksCount = <?php echo count($syncTasks); ?>;
        document.getElementById('stat-total').innerText = tasksCount;

        // Cookie Helpers
        function setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Formats raw '18:25:00 31.03.2026' into '31 Mar, 18:25'
        function formatDateTime(raw) {
            if (!raw || raw === '-' || !raw.includes(' ')) return raw;
            try {
                const [time, date] = raw.split(' ');
                const [h, m] = time.split(':');
                const [d, mo] = date.split('.');
                const months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                return `${parseInt(d)} ${months[parseInt(mo) - 1]}, ${h}:${m}`;
            } catch (e) { return raw; }
        }

        // Initialize last update from cookie
        const lastSync = getCookie('last_api_sync');
        if (lastSync) document.getElementById('stat-last').innerText = formatDateTime(lastSync);

        // Initialize each task's last update
        document.querySelectorAll('.task-last-update').forEach(span => {
            const key = span.id.replace('update-', '');
            const taskLastSync = getCookie('last_sync_' + key);
            if (taskLastSync) span.innerText = formatDateTime(taskLastSync);
        });

        // Custom Modal Logic
        let modalResolveFunc = null;
        function showModal(title, msg) {
            document.getElementById('modal-title').innerText = title;
            document.getElementById('modal-msg').innerText = msg;
            document.getElementById('modal-overlay').style.display = 'flex';
            return new Promise((resolve) => { modalResolveFunc = resolve; });
        }
        function modalResolve(val) {
            document.getElementById('modal-overlay').style.display = 'none';
            if (modalResolveFunc) modalResolveFunc(val);
        }

        function addLog(msg, type = 'info') {
            const console = document.getElementById('log-console');
            const time = new Date().toLocaleTimeString();
            const line = document.createElement('div');
            line.className = 'log-line';

            line.innerHTML = `
                <span class="log-time">${time}</span>
                <span class="log-type ${type}">${type}</span>
                <span class="log-msg">${msg}</span>
            `;

            console.appendChild(line);
            console.scrollTop = console.scrollHeight;
        }

        function clearConsole() {
            document.getElementById('log-console').innerHTML = '';
            addLog('Konsol temizlendi. Sistem beklemede.');
        }

        async function sync(key, autoConfirm = false) {
            const card = document.getElementById('card-' + key);
            const title = card.querySelector('h3').innerText;
            const activeStat = document.getElementById('stat-active');

            if (card.classList.contains('loading')) return;

            if (!autoConfirm) {
                const confirmed = await showModal('Görevi Başlat', `"${title}" senkronizasyonunu başlatmak istediğinize emin misiniz?`);
                if (!confirmed) return false;
            }

            card.classList.add('loading');
            activeStat.innerText = parseInt(activeStat.innerText) + 1;
            addLog(`Başlatılıyor: ${title}...`, 'info');

            try {
                const response = await fetch(`get-api.php?action=sync&key=${key}`);
                const result = await response.json();

                if (result.status === 'success') {
                    addLog(`${title}: ${result.message}`, 'success');
                    const formatted = formatDateTime(result.time);
                    document.getElementById('stat-last').innerText = formatted;
                    document.getElementById('update-' + key).innerText = formatted;
                    setCookie('last_api_sync', result.time, 30);
                    setCookie('last_sync_' + key, result.time, 30);
                    return true;
                } else {
                    addLog(`${title} Hatası: ${result.message}`, 'error');
                    return false;
                }
            } catch (error) {
                addLog(`${title}: Kritik bağlantı hatası!`, 'error');
                return false;
            } finally {
                card.classList.remove('loading');
                activeStat.innerText = Math.max(0, parseInt(activeStat.innerText) - 1);
            }
        }

        async function syncAll() {
            const btn = document.getElementById('sync-all-btn');
            const keys = Array.from(document.querySelectorAll('.sync-card')).map(b => b.id.replace('card-', ''));

            const confirmed = await showModal('Toplu Senkronizasyon', 'TÜM senkronizasyon görevlerini sırasıyla başlatmak üzeresiniz. Onaylıyor musunuz?');
            if (!confirmed) return;

            btn.disabled = true;
            addLog('Toplu senkronizasyon operasyonu başlatıldı...', 'warning');

            for (const key of keys) {
                await sync(key, true);
            }

            btn.disabled = false;
            addLog('Tüm görevler tamamlandı.', 'success');
        }

        // Initial Log
        addLog('UmutAPP API OS v2.5 başlatıldı. Komut bekliyor...');
    </script>
</body>

</html>