<?php
$webroot = realpath(__DIR__ . '/../');
$fileParam = trim($_GET['file'] ?? '');

if ($fileParam === '') {
    http_response_code(400);
    die('Dosya parametresi eksik. Kullanım: ?file=upload/urunler/10/dosya.dxf');
}

// Normalize and resolve — must stay inside webroot
$fileParam = ltrim(str_replace('\\', '/', $fileParam), '/');
$filePath = realpath($webroot . '/' . $fileParam);

if ($filePath === false || strpos($filePath, $webroot) !== 0) {
    http_response_code(403);
    die('Geçersiz dosya yolu.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if (!in_array($ext, ['dwg', 'dxf'])) {
    http_response_code(400);
    die('Sadece DWG ve DXF dosyaları desteklenmektedir.');
}

if (!file_exists($filePath)) {
    http_response_code(404);
    die('Dosya bulunamadı: ' . htmlspecialchars($fileParam));
}

$filename = basename($filePath);
$filesize = filesize($filePath);
$fileurl = '/' . $fileParam;

function viewer_fmt_size(int $b): string
{
    if ($b < 1024)
        return $b . ' B';
    if ($b < 1048576)
        return round($b / 1024, 1) . ' KB';
    return round($b / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($filename) ?> — DWG Görüntüleyici</title>
    <link href="/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0a0c14;
            color: #e2e8f0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Toolbar ─────────────────────────────────────────── */
        .viewer-toolbar {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .9rem;
            background: #141622;
            border-bottom: 1px solid #2d3148;
            flex-shrink: 0;
        }

        .toolbar-logo {
            display: flex;
            align-items: center;
            gap: .5rem;
            text-decoration: none;
            color: #6c8ef5;
            font-size: .85rem;
            font-weight: 700;
            margin-right: .25rem;
        }

        .toolbar-logo i {
            font-size: 1.1rem;
        }

        .toolbar-sep {
            width: 1px;
            height: 20px;
            background: #2d3148;
            margin: 0 .1rem;
        }

        .toolbar-filename {
            flex: 1;
            font-size: .85rem;
            font-weight: 600;
            color: #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .toolbar-filename span {
            color: #4b5563;
            font-weight: 400;
            margin-left: .4rem;
        }

        .toolbar-btn {
            display: flex;
            align-items: center;
            gap: .35rem;
            background: #1e2340;
            border: 1px solid #2d3148;
            color: #94a3b8;
            padding: .35rem .7rem;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            white-space: nowrap;
            user-select: none;
        }

        .toolbar-btn:hover {
            background: #2d3148;
            color: #e2e8f0;
        }

        .toolbar-btn i {
            font-size: .9rem;
        }

        /* ── Viewer wrap ─────────────────────────────────────── */
        .viewer-wrap {
            flex: 1;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* DXF container (dxf-viewer injects canvas inside) */
        #dxfContainer {
            flex: 1;
            min-height: 0;
            background: #0a0c14;
        }
        #dxfContainer canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        /* Status bar */
        .canvas-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .3rem .9rem;
            background: rgba(20, 22, 34, .85);
            border-top: 1px solid #2d3148;
            font-size: .72rem;
            color: #4b5563;
            flex-shrink: 0;
        }

        .canvas-status span {
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        /* DWG info panel */
        #dwgInfo {
            display: none;
            align-items: center;
            justify-content: center;
            position: absolute;
            inset: 0;
        }

        #dwgInfo.active {
            display: flex;
        }

        .dwg-info-card {
            background: #141622;
            border: 1px solid #2d3148;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 480px;
            width: 90%;
            text-align: center;
        }

        .dwg-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            background: #1e3a5f;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #60a5fa;
        }

        .dwg-info-card h5 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: .3rem;
        }

        .dwg-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin: 1.2rem 0;
        }

        .dwg-meta-item {
            background: #0f1117;
            border-radius: 8px;
            padding: .65rem;
            text-align: left;
        }

        .dwg-meta-item label {
            display: block;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #4b5563;
            margin-bottom: .2rem;
        }

        .dwg-meta-item span {
            font-size: .85rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .dwg-note {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            background: #1a1d2e;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .75rem;
            color: #64748b;
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .dwg-note i {
            color: #6c8ef5;
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .btn-dl {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #6c8ef5;
            color: #fff;
            border: none;
            padding: .6rem 1.4rem;
            border-radius: 8px;
            font-size: .85rem;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-dl:hover {
            background: #5a7de8;
            color: #fff;
        }

        /* Loading overlay */
        .viewer-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(10, 12, 20, .9);
            flex-direction: column;
            gap: .75rem;
            z-index: 10;
        }

        .viewer-loading.hidden {
            display: none;
        }

        .spinner-ring {
            width: 40px;
            height: 40px;
            border: 3px solid #2d3148;
            border-top-color: #6c8ef5;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Error state */
        .viewer-error {
            display: none;
            align-items: center;
            justify-content: center;
            position: absolute;
            inset: 0;
            flex-direction: column;
            gap: .75rem;
            color: #64748b;
        }

        .viewer-error.active {
            display: flex;
        }

        .viewer-error i {
            font-size: 3rem;
            color: #f87171;
        }

        .viewer-error p {
            margin: 0;
            font-size: .9rem;
        }
    </style>
</head>

<body>

    <!-- Toolbar -->
    <div class="viewer-toolbar">
        <a href="/dwg-viewer/" class="toolbar-logo" title="Dosya listesine dön">
            <i class="bi bi-grid-3x3-gap"></i>
        </a>
        <div class="toolbar-sep"></div>

        <div class="toolbar-filename" id="toolbarFilename">
            <?= htmlspecialchars($filename) ?><span><?= viewer_fmt_size($filesize) ?></span>
        </div>

        <?php if ($ext === 'dxf'): ?>
            <button class="toolbar-btn" id="btnZoomIn" title="Yakınlaştır"><i class="bi bi-zoom-in"></i></button>
            <button class="toolbar-btn" id="btnZoomOut" title="Uzaklaştır"><i class="bi bi-zoom-out"></i></button>
            <button class="toolbar-btn" id="btnFit" title="Tümünü sığdır"><i class="bi bi-fullscreen"></i> Sığdır</button>
            <div class="toolbar-sep"></div>
        <?php endif; ?>

        <a class="toolbar-btn" href="<?= htmlspecialchars($fileurl) ?>" download="<?= htmlspecialchars($filename) ?>">
            <i class="bi bi-download"></i> İndir
        </a>
    </div>

    <!-- Viewer area -->
    <div class="viewer-wrap" id="viewerWrap">

        <?php if ($ext === 'dxf'): ?>
            <div id="dxfContainer"></div>
            <div class="canvas-status">
                <span><i class="bi bi-layers"></i> <span id="statusLayers">—</span> katman</span>
                <span>Kaydır: sürükle &nbsp;·&nbsp; Zoom: tekerlek</span>
            </div>
        <?php else: ?>
            <div id="dwgInfo" class="active">
                <div class="dwg-info-card">
                    <div class="dwg-icon-wrap"><i class="bi bi-file-earmark-code"></i></div>
                    <h5><?= htmlspecialchars($filename) ?></h5>
                    <p style="font-size:.8rem;color:#4b5563;margin-bottom:0">AutoCAD Binary Drawing</p>
                    <div class="dwg-meta">
                        <div class="dwg-meta-item"><label>Boyut</label><span><?= viewer_fmt_size($filesize) ?></span></div>
                        <div class="dwg-meta-item"><label>Format</label><span>DWG</span></div>
                        <div class="dwg-meta-item">
                            <label>Değiştirilme</label><span><?= date('d.m.Y', filemtime($filePath)) ?></span></div>
                        <div class="dwg-meta-item"><label>Durum</label><span style="color:#34d399">Hazır</span></div>
                    </div>
                    <div class="dwg-note">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>DWG dosyaları kapalı kaynak formattır ve tarayıcıda doğrudan işlenemez. Önizleme için DXF
                            formatına dönüştürün.</span>
                    </div>
                    <a class="btn-dl" href="<?= htmlspecialchars($fileurl) ?>"
                        download="<?= htmlspecialchars($filename) ?>">
                        <i class="bi bi-download"></i> İndir
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Loading overlay (DXF only) -->
        <?php if ($ext === 'dxf'): ?>
            <div class="viewer-loading" id="viewerLoading">
                <div class="spinner-ring"></div>
                <small style="color:#64748b">Dosya yükleniyor…</small>
            </div>
            <div class="viewer-error" id="viewerError">
                <i class="bi bi-exclamation-triangle"></i>
                <p id="viewerErrorMsg">Dosya yüklenemedi.</p>
                <a class="toolbar-btn" href="<?= htmlspecialchars($fileurl) ?>" download>
                    <i class="bi bi-download"></i> İndir
                </a>
            </div>
        <?php endif; ?>

    </div>

    <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
    <?php if ($ext === 'dxf'): ?>
        <script src="/dwg-viewer/assets/dxf-viewer.bundle.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const { DxfViewer, Color } = window.DxfViewerLib;
                const container = document.getElementById('dxfContainer');
                const viewer = new DxfViewer(container, {
                    clearColor: new Color(0x0a0c14),
                    autoResize: true,
                });

                document.getElementById('btnZoomIn').addEventListener('click',  () => viewer.Zoom(1.25));
                document.getElementById('btnZoomOut').addEventListener('click', () => viewer.Zoom(1 / 1.25));
                document.getElementById('btnFit').addEventListener('click',     () => viewer.FitView());

                const FILE_URL = <?= json_encode($fileurl) ?>;

                try {
                    await viewer.Load({ url: FILE_URL, fonts: ['/dwg-viewer/assets/fonts/Roboto-LightItalic.ttf'] });
                    document.getElementById('viewerLoading').classList.add('hidden');
                    const layers = viewer.GetLayers();
                    document.getElementById('statusLayers').textContent = layers ? layers.length : '—';
                } catch (err) {
                    document.getElementById('viewerLoading').classList.add('hidden');
                    document.getElementById('viewerErrorMsg').textContent = 'Dosya yüklenemedi: ' + err.message;
                    document.getElementById('viewerError').classList.add('active');
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>
