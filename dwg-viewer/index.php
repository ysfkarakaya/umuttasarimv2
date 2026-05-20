<?php
$uploadDir = __DIR__ . '/files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$files = [];
foreach (scandir($uploadDir) as $item) {
    if ($item === '.' || $item === '..') continue;
    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
    if (!in_array($ext, ['dwg', 'dxf'])) continue;
    $files[] = [
        'name' => $item,
        'size' => filesize($uploadDir . $item),
        'time' => filemtime($uploadDir . $item),
        'ext'  => $ext,
    ];
}
usort($files, fn($a, $b) => $b['time'] - $a['time']);

function fmt_size(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DWG Görüntüleyici | Umut Tasarım</title>
    <link href="/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Top Bar ─────────────────────────────────────────── */
        .topbar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .6rem 1.25rem;
            background: #1a1d2e;
            border-bottom: 1px solid #2d3148;
            flex-shrink: 0;
        }
        .topbar-logo {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .5px;
        }
        .topbar-logo i { font-size: 1.3rem; color: #6c8ef5; }
        .topbar-title { flex: 1; font-size: .9rem; color: #94a3b8; }
        .topbar-back {
            display: flex; align-items: center; gap: .4rem;
            color: #94a3b8; text-decoration: none; font-size: .85rem;
            transition: color .2s;
        }
        .topbar-back:hover { color: #e2e8f0; }

        /* ── Layout ──────────────────────────────────────────── */
        .app-body {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* ── Sidebar ─────────────────────────────────────────── */
        .sidebar {
            width: 280px;
            min-width: 280px;
            background: #141622;
            border-right: 1px solid #2d3148;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-header {
            padding: 1rem 1.1rem .75rem;
            border-bottom: 1px solid #2d3148;
        }
        .sidebar-header h6 {
            margin: 0 0 .75rem;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
        }

        /* Upload Zone */
        .upload-zone {
            border: 2px dashed #2d3148;
            border-radius: 8px;
            padding: 1.1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: #0f1117;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: #6c8ef5;
            background: #1a1d2e;
        }
        .upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-zone i { font-size: 1.5rem; color: #6c8ef5; display: block; margin-bottom: .4rem; }
        .upload-zone p { margin: 0; font-size: .78rem; color: #64748b; }
        .upload-zone strong { color: #94a3b8; }

        /* Progress */
        .upload-progress {
            display: none;
            margin-top: .6rem;
        }
        .upload-progress .progress { height: 4px; background: #2d3148; border-radius: 2px; }
        .upload-progress .progress-bar { background: #6c8ef5; border-radius: 2px; transition: width .3s; }
        .upload-progress small { font-size: .72rem; color: #64748b; }

        /* File List */
        .file-list {
            flex: 1;
            overflow-y: auto;
            padding: .5rem 0;
        }
        .file-list::-webkit-scrollbar { width: 4px; }
        .file-list::-webkit-scrollbar-track { background: transparent; }
        .file-list::-webkit-scrollbar-thumb { background: #2d3148; border-radius: 2px; }

        .file-empty {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 120px;
            color: #374151; font-size: .8rem; text-align: center; padding: 1rem;
        }
        .file-empty i { font-size: 2rem; margin-bottom: .5rem; }

        .file-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .55rem 1.1rem;
            cursor: pointer;
            border-radius: 0;
            transition: background .15s;
            position: relative;
        }
        .file-item:hover { background: #1a1d2e; }
        .file-item.active { background: #1e2340; border-left: 3px solid #6c8ef5; }
        .file-item:not(.active) { border-left: 3px solid transparent; }

        .file-icon {
            width: 32px; height: 32px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .file-icon.dwg { background: #1e3a5f; color: #60a5fa; }
        .file-icon.dxf { background: #1e3a2f; color: #34d399; }

        .file-info { flex: 1; min-width: 0; }
        .file-name {
            font-size: .8rem; font-weight: 500; color: #e2e8f0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .file-meta { font-size: .7rem; color: #4b5563; }

        .file-actions {
            display: none; gap: .2rem;
        }
        .file-item:hover .file-actions { display: flex; }
        .file-action-btn {
            background: none; border: none; color: #4b5563;
            padding: .25rem; border-radius: 4px; cursor: pointer;
            transition: color .2s, background .2s; font-size: .85rem;
            display: flex; align-items: center; justify-content: center;
        }
        .file-action-btn:hover { color: #e2e8f0; background: #2d3148; }
        .file-action-btn.del:hover { color: #f87171; }

        /* ── Viewer ─────────────────────────────────────────── */
        .viewer-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #0f1117;
        }

        .viewer-toolbar {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .9rem;
            background: #141622;
            border-bottom: 1px solid #2d3148;
            flex-shrink: 0;
        }
        .viewer-filename {
            flex: 1;
            font-size: .85rem;
            font-weight: 600;
            color: #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .viewer-filename span { color: #4b5563; font-weight: 400; }

        .toolbar-btn {
            display: flex; align-items: center; gap: .35rem;
            background: #1e2340; border: 1px solid #2d3148;
            color: #94a3b8; padding: .35rem .75rem; border-radius: 6px;
            font-size: .78rem; cursor: pointer; transition: all .2s;
            text-decoration: none; white-space: nowrap;
        }
        .toolbar-btn:hover { background: #2d3148; color: #e2e8f0; }
        .toolbar-btn i { font-size: .9rem; }

        .toolbar-divider { width: 1px; height: 20px; background: #2d3148; margin: 0 .2rem; }

        .viewer-wrap {
            flex: 1;
            overflow: hidden;
            position: relative;
        }

        /* Empty state */
        .viewer-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 1rem;
            color: #374151;
        }
        .viewer-empty i { font-size: 4rem; }
        .viewer-empty h5 { margin: 0; font-size: 1rem; color: #4b5563; }
        .viewer-empty p { margin: 0; font-size: .8rem; text-align: center; max-width: 280px; }

        /* DXF Viewer */
        #dxfViewer {
            width: 100%;
            height: 100%;
            display: none;
            flex-direction: column;
        }
        #dxfViewer.active { display: flex; }

        #dxfContainer {
            flex: 1;
            min-height: 0;
            background: #0a0c14;
        }
        /* dxf-viewer injects its own canvas — make it fill the container */
        #dxfContainer canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        .canvas-status {
            display: flex; align-items: center; gap: 1rem;
            padding: .3rem .9rem;
            background: #141622; border-top: 1px solid #2d3148;
            font-size: .72rem; color: #4b5563;
            flex-shrink: 0;
        }
        .canvas-status span { display: flex; align-items: center; gap: .3rem; }

        /* DWG Info Panel */
        #dwgInfo {
            width: 100%; height: 100%;
            display: none;
            align-items: center; justify-content: center;
        }
        #dwgInfo.active { display: flex; }

        .dwg-info-card {
            background: #141622;
            border: 1px solid #2d3148;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 480px;
            width: 90%;
            text-align: center;
        }
        .dwg-info-card .icon-wrap {
            width: 72px; height: 72px; border-radius: 16px;
            background: #1e3a5f; display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 1.5rem; font-size: 2rem; color: #60a5fa;
        }
        .dwg-info-card h5 { font-size: 1.1rem; font-weight: 700; margin-bottom: .3rem; }
        .dwg-info-card .file-detail {
            font-size: .8rem; color: #4b5563; margin-bottom: 1.5rem;
        }
        .dwg-meta-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .75rem; margin-bottom: 1.75rem;
        }
        .dwg-meta-item {
            background: #0f1117; border-radius: 8px; padding: .75rem;
            text-align: left;
        }
        .dwg-meta-item label { display: block; font-size: .65rem; text-transform: uppercase; letter-spacing: .5px; color: #4b5563; margin-bottom: .2rem; }
        .dwg-meta-item span { font-size: .85rem; color: #94a3b8; font-weight: 500; }

        .dwg-note {
            display: flex; gap: .6rem; align-items: flex-start;
            background: #1a1d2e; border-radius: 8px; padding: .75rem 1rem;
            font-size: .75rem; color: #64748b; text-align: left; margin-bottom: 1.5rem;
        }
        .dwg-note i { color: #6c8ef5; flex-shrink: 0; margin-top: .1rem; }

        .dwg-actions { display: flex; gap: .75rem; justify-content: center; }
        .btn-download {
            display: flex; align-items: center; gap: .5rem;
            background: #6c8ef5; color: #fff; border: none;
            padding: .6rem 1.25rem; border-radius: 8px; font-size: .85rem;
            cursor: pointer; text-decoration: none; transition: background .2s;
        }
        .btn-download:hover { background: #5a7de8; color: #fff; }

        /* Loading overlay */
        .viewer-loading {
            position: absolute; inset: 0;
            display: none; align-items: center; justify-content: center;
            background: rgba(10,12,20,.85); z-index: 10; flex-direction: column; gap: .75rem;
        }
        .viewer-loading.active { display: flex; }
        .spinner-ring {
            width: 40px; height: 40px;
            border: 3px solid #2d3148;
            border-top-color: #6c8ef5;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Toast */
        .toast-stack {
            position: fixed; bottom: 1.5rem; right: 1.5rem;
            display: flex; flex-direction: column; gap: .5rem; z-index: 9999;
        }
        .toast-item {
            display: flex; align-items: center; gap: .6rem;
            background: #1a1d2e; border: 1px solid #2d3148;
            border-radius: 8px; padding: .65rem 1rem;
            font-size: .82rem; color: #e2e8f0;
            animation: slideIn .2s ease; min-width: 220px;
        }
        .toast-item.success { border-left: 3px solid #34d399; }
        .toast-item.error   { border-left: 3px solid #f87171; }
        .toast-item i.success { color: #34d399; }
        .toast-item i.error   { color: #f87171; }
        @keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: none; opacity: 1; } }

        /* Responsive */
        @media (max-width: 640px) {
            .sidebar { width: 220px; min-width: 220px; }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<header class="topbar">
    <a href="/index.php" class="topbar-logo">
        <i class="bi bi-grid-3x3-gap"></i>
        Umut Tasarım
    </a>
    <div class="topbar-divider" style="width:1px;height:20px;background:#2d3148;"></div>
    <div class="topbar-title">DWG / DXF Görüntüleyici</div>
    <a href="/index.php" class="topbar-back">
        <i class="bi bi-arrow-left"></i> Siteye Dön
    </a>
</header>

<div class="app-body">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h6>Dosya Yükle</h6>
            <div class="upload-zone" id="uploadZone">
                <input type="file" id="fileInput" accept=".dwg,.dxf" multiple>
                <i class="bi bi-cloud-arrow-up"></i>
                <p><strong>Sürükle & Bırak</strong><br>ya da tıkla seç</p>
                <p style="margin-top:.4rem;font-size:.68rem;">.DWG · .DXF · Maks 100 MB</p>
            </div>
            <div class="upload-progress" id="uploadProgress">
                <div class="progress">
                    <div class="progress-bar" id="progressBar" style="width:0%"></div>
                </div>
                <small id="progressText">Yükleniyor…</small>
            </div>
        </div>

        <div class="file-list" id="fileList">
            <?php if (empty($files)): ?>
            <div class="file-empty" id="emptyMsg">
                <i class="bi bi-folder2-open"></i>
                Henüz dosya yok.<br>Yukarıdan yükleyin.
            </div>
            <?php else: ?>
            <?php foreach ($files as $f): ?>
            <div class="file-item"
                 data-name="<?= htmlspecialchars($f['name']) ?>"
                 data-ext="<?= $f['ext'] ?>"
                 data-url="/dwg-viewer/files/<?= rawurlencode($f['name']) ?>"
                 data-size="<?= $f['size'] ?>"
                 data-time="<?= $f['time'] ?>">
                <div class="file-icon <?= $f['ext'] ?>"><?= strtoupper($f['ext']) ?></div>
                <div class="file-info">
                    <div class="file-name" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></div>
                    <div class="file-meta"><?= fmt_size($f['size']) ?> &middot; <?= date('d.m.Y', $f['time']) ?></div>
                </div>
                <div class="file-actions">
                    <button class="file-action-btn dl" title="İndir"><i class="bi bi-download"></i></button>
                    <button class="file-action-btn del" title="Sil"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Viewer -->
    <main class="viewer-area">
        <div class="viewer-toolbar">
            <div class="viewer-filename" id="viewerFilename">
                <span>Görüntülemek için bir dosya seçin</span>
            </div>

            <button class="toolbar-btn" id="btnZoomIn" title="Yakınlaştır" style="display:none">
                <i class="bi bi-zoom-in"></i>
            </button>
            <button class="toolbar-btn" id="btnZoomOut" title="Uzaklaştır" style="display:none">
                <i class="bi bi-zoom-out"></i>
            </button>
            <button class="toolbar-btn" id="btnFit" title="Sığdır" style="display:none">
                <i class="bi bi-fullscreen"></i> Sığdır
            </button>
            <div class="toolbar-divider" id="tbDivider" style="display:none"></div>
            <a class="toolbar-btn" id="btnDownload" href="#" download style="display:none">
                <i class="bi bi-download"></i> İndir
            </a>
        </div>

        <div class="viewer-wrap" id="viewerWrap">
            <!-- Empty state -->
            <div class="viewer-empty" id="viewerEmpty">
                <i class="bi bi-file-earmark-code"></i>
                <h5>Dosya Seçilmedi</h5>
                <p>Sol panelden bir DWG veya DXF dosyası seçin.<br>DXF dosyaları tarayıcıda görüntülenir.</p>
            </div>

            <!-- DXF Viewer (dxf-viewer library) -->
            <div id="dxfViewer">
                <div id="dxfContainer"></div>
                <div class="canvas-status">
                    <span><i class="bi bi-layers"></i> <span id="statusLayers">—</span> katman</span>
                    <span>Kaydırmak için sürükle · Zoom için tekerlek</span>
                </div>
            </div>

            <!-- DWG Info -->
            <div id="dwgInfo">
                <div class="dwg-info-card">
                    <div class="icon-wrap"><i class="bi bi-file-earmark-code"></i></div>
                    <h5 id="dwgName">dosya.dwg</h5>
                    <p class="file-detail" id="dwgDetail">—</p>
                    <div class="dwg-meta-grid">
                        <div class="dwg-meta-item">
                            <label>Boyut</label>
                            <span id="dwgSize">—</span>
                        </div>
                        <div class="dwg-meta-item">
                            <label>Format</label>
                            <span>AutoCAD DWG</span>
                        </div>
                        <div class="dwg-meta-item">
                            <label>Tarih</label>
                            <span id="dwgDate">—</span>
                        </div>
                        <div class="dwg-meta-item">
                            <label>Durum</label>
                            <span style="color:#34d399">Hazır</span>
                        </div>
                    </div>
                    <div class="dwg-note">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>DWG dosyaları kapalı kaynak formattır ve tarayıcıda doğrudan işlenemez. Tarayıcıda önizleme için dosyayı DXF formatına dönüştürün.</span>
                    </div>
                    <div class="dwg-actions">
                        <a class="btn-download" id="dwgDownloadBtn" href="#" download>
                            <i class="bi bi-download"></i> İndir
                        </a>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div class="viewer-loading" id="viewerLoading">
                <div class="spinner-ring"></div>
                <small style="color:#64748b">Dosya yükleniyor…</small>
            </div>
        </div>
    </main>
</div>

<!-- Toast container -->
<div class="toast-stack" id="toastStack"></div>

<script src="/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="/dwg-viewer/assets/dxf-viewer.bundle.js"></script>
<script>
/* =====================================================================
   DXF Viewer instance (lazy, shared across file switches)
   ===================================================================== */
let viewerInstance = null;

function getOrCreateViewer() {
    if (viewerInstance) return viewerInstance;
    const { DxfViewer, Color } = window.DxfViewerLib;
    const container = document.getElementById('dxfContainer');
    viewerInstance = new DxfViewer(container, {
        clearColor: new Color(0x0a0c14),
        autoResize: true,
    });
    return viewerInstance;
}

/* =====================================================================
   App State & UI
   ===================================================================== */
let activeFile = null;

function toast(msg, type = 'success') {
    const stack = document.getElementById('toastStack');
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill success' : 'bi-x-circle-fill error'}"></i>${msg}`;
    stack.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function fmtSize(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
}

function fmtDate(ts) {
    return new Date(ts * 1000).toLocaleDateString('tr-TR');
}

function setLoading(on) {
    document.getElementById('viewerLoading').classList.toggle('active', on);
}

function showEmpty() {
    document.getElementById('viewerEmpty').style.display = '';
    document.getElementById('dxfViewer').classList.remove('active');
    document.getElementById('dwgInfo').classList.remove('active');
    document.getElementById('viewerFilename').innerHTML = '<span>Görüntülemek için bir dosya seçin</span>';
    ['btnZoomIn', 'btnZoomOut', 'btnFit', 'btnDownload', 'tbDivider'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
}

function openFile(item) {
    const name = item.dataset.name;
    const ext  = item.dataset.ext;
    const url  = item.dataset.url;
    const size = parseInt(item.dataset.size);
    const time = parseInt(item.dataset.time);

    document.querySelectorAll('.file-item').forEach(i => i.classList.remove('active'));
    item.classList.add('active');
    activeFile = { name, ext, url, size, time };

    document.getElementById('viewerEmpty').style.display = 'none';

    const dlBtn = document.getElementById('btnDownload');
    dlBtn.href = url;
    dlBtn.download = name;
    dlBtn.style.display = '';
    document.getElementById('tbDivider').style.display = '';
    document.getElementById('viewerFilename').innerHTML =
        `${escHtml(name)} <span style="margin-left:.5rem;font-weight:400;color:#4b5563">${fmtSize(size)}</span>`;

    if (ext === 'dxf') {
        openDxf(url, name);
    } else {
        openDwgInfo(name, size, time, url);
    }
}

async function openDxf(url) {
    document.getElementById('dwgInfo').classList.remove('active');
    document.getElementById('dxfViewer').classList.add('active');
    ['btnZoomIn', 'btnZoomOut', 'btnFit'].forEach(id => {
        document.getElementById(id).style.display = '';
    });
    setLoading(true);

    try {
        const viewer = getOrCreateViewer();
        await viewer.Load({ url, fonts: ['/dwg-viewer/assets/fonts/Roboto-LightItalic.ttf'] });
        setLoading(false);
        const layers = viewer.GetLayers();
        document.getElementById('statusLayers').textContent = layers ? layers.length : '—';
    } catch (err) {
        setLoading(false);
        toast('DXF hatası: ' + err.message, 'error');
    }
}

function openDwgInfo(name, size, time, url) {
    document.getElementById('dxfViewer').classList.remove('active');
    ['btnZoomIn', 'btnZoomOut', 'btnFit'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    document.getElementById('dwgName').textContent   = name;
    document.getElementById('dwgDetail').textContent = 'AutoCAD Binary Drawing';
    document.getElementById('dwgSize').textContent   = fmtSize(size);
    document.getElementById('dwgDate').textContent   = fmtDate(time);
    document.getElementById('dwgDownloadBtn').href     = url;
    document.getElementById('dwgDownloadBtn').download = name;
    document.getElementById('dwgInfo').classList.add('active');
}

/* =====================================================================
   File List – dynamic add after upload
   ===================================================================== */
function addFileToList(file) {
    const empty = document.getElementById('emptyMsg');
    if (empty) empty.remove();

    const list = document.getElementById('fileList');
    const div  = document.createElement('div');
    div.className    = 'file-item';
    div.dataset.name = file.filename;
    div.dataset.ext  = file.ext;
    div.dataset.url  = file.url;
    div.dataset.size = file.size;
    div.dataset.time = Math.floor(Date.now() / 1000);
    div.innerHTML = `
        <div class="file-icon ${file.ext}">${file.ext.toUpperCase()}</div>
        <div class="file-info">
            <div class="file-name" title="${escHtml(file.filename)}">${escHtml(file.filename)}</div>
            <div class="file-meta">${fmtSize(file.size)} &middot; ${new Date().toLocaleDateString('tr-TR')}</div>
        </div>
        <div class="file-actions">
            <button class="file-action-btn dl" title="İndir"><i class="bi bi-download"></i></button>
            <button class="file-action-btn del" title="Sil"><i class="bi bi-trash3"></i></button>
        </div>`;
    list.prepend(div);
    bindFileItem(div);
    div.click();
}

function escHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function bindFileItem(item) {
    item.addEventListener('click', e => {
        if (e.target.closest('.file-action-btn')) return;
        openFile(item);
    });

    item.querySelector('.dl').addEventListener('click', () => {
        const a = document.createElement('a');
        a.href = item.dataset.url;
        a.download = item.dataset.name;
        a.click();
    });

    item.querySelector('.del').addEventListener('click', () => {
        if (!confirm(`"${item.dataset.name}" silinsin mi?`)) return;
        fetch('/dwg-viewer/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'filename=' + encodeURIComponent(item.dataset.name),
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (activeFile && activeFile.name === item.dataset.name) {
                    showEmpty();
                    activeFile = null;
                }
                item.remove();
                if (!document.querySelector('.file-item')) {
                    document.getElementById('fileList').innerHTML =
                        '<div class="file-empty" id="emptyMsg"><i class="bi bi-folder2-open"></i>Henüz dosya yok.<br>Yukarıdan yükleyin.</div>';
                }
                toast('Dosya silindi.');
            } else {
                toast(res.error || 'Silinemedi.', 'error');
            }
        });
    });
}

/* =====================================================================
   Upload
   ===================================================================== */
function uploadFile(file) {
    const prog    = document.getElementById('uploadProgress');
    const bar     = document.getElementById('progressBar');
    const progTxt = document.getElementById('progressText');
    const zone    = document.getElementById('uploadZone');

    zone.style.pointerEvents = 'none';
    prog.style.display = 'block';
    bar.style.width    = '0%';
    progTxt.textContent = 'Yükleniyor…';

    const fd = new FormData();
    fd.append('file', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/dwg-viewer/upload.php');

    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const pct = Math.round(e.loaded / e.total * 100);
            bar.style.width  = pct + '%';
            progTxt.textContent = `${pct}% — ${fmtSize(e.loaded)} / ${fmtSize(e.total)}`;
        }
    };

    xhr.onload = () => {
        zone.style.pointerEvents = '';
        prog.style.display = 'none';
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                toast(`"${res.original}" yüklendi.`);
                addFileToList(res);
            } else {
                toast(res.error || 'Yükleme başarısız.', 'error');
            }
        } catch {
            toast('Sunucu hatası.', 'error');
        }
    };

    xhr.onerror = () => {
        zone.style.pointerEvents = '';
        prog.style.display = 'none';
        toast('Ağ hatası, yükleme başarısız.', 'error');
    };

    xhr.send(fd);
}

/* =====================================================================
   Init
   ===================================================================== */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnZoomIn').addEventListener('click',  () => { if (viewerInstance) viewerInstance.Zoom(1.25); });
    document.getElementById('btnZoomOut').addEventListener('click', () => { if (viewerInstance) viewerInstance.Zoom(1 / 1.25); });
    document.getElementById('btnFit').addEventListener('click',     () => { if (viewerInstance) viewerInstance.FitView(); });

    document.querySelectorAll('.file-item').forEach(bindFileItem);

    const zone = document.getElementById('uploadZone');
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const files = [...e.dataTransfer.files].filter(f => /\.(dwg|dxf)$/i.test(f.name));
        if (files.length) files.forEach(uploadFile);
        else toast('Sadece .dwg veya .dxf dosyası yükleyebilirsiniz.', 'error');
    });

    document.getElementById('fileInput').addEventListener('change', e => {
        [...e.target.files].forEach(uploadFile);
        e.target.value = '';
    });
});
</script>
</body>
</html>
