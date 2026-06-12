<?php
include_once 'inc/functions.php';
$lang = active_lang();
$slug = $_GET['slug'] ?? 'kalite-politikasi';
$filePath = __DIR__ . "/data/lang/{$lang}/sayfalar/{$slug}.json";

$pageData = null;
if (file_exists($filePath)) {
    $pageData = json_decode(file_get_contents($filePath), true);
}

$pageTitle = $pageData['kart_detay_adi'] ?? "Kalite Politikası";
$pageContent = $pageData['kart_detay_aciklama'] ?? "";

$kaliteJsonPath = __DIR__ . "/data/lang/{$lang}/kalite.json";
if (!file_exists($kaliteJsonPath)) {
    $kaliteJsonPath = __DIR__ . "/data/lang/tr/kalite.json";
}
$kaliteData = [];
if (file_exists($kaliteJsonPath)) {
    $kaliteData = json_decode(file_get_contents($kaliteJsonPath), true);
}

$hasSidebar = true;
?>
<?php
include_once 'inc/header.php';
?>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 2.5rem;
        line-height: 1.1;
        margin-top: 50px;
    }

    /* Puzzle Ana Kapsayıcı */
    .materials-section {
        padding: 30px 0;
        position: relative;
        /* height: calc(100vh - 80px); */
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        background: #fff;
    }

    /* Puzzle Kapsayıcı Alanı */
    .puzzle-board {
        position: relative;
        margin: 0 auto;
        transition: width 0.3s ease, height 0.3s ease;
    }

    /* Puzzle Parçası Grubu Geçiş Yumuşatmaları */
    .puzzle-piece-wrapper {
        position: absolute;
        transition: transform 0.8s cubic-bezier(0.25, 1, 0.5, 1), filter 0.3s ease, opacity 0.5s ease;
        cursor: pointer;
        z-index: 10;
    }

    /* Aktif/Seçilmiş Parça Parlama Efekti */
    .puzzle-piece-wrapper.active {
        filter: drop-shadow(0px 10px 30px rgba(59, 130, 246, 0.65));
        z-index: 50;
    }

    .puzzle-piece-wrapper:hover {
        filter: drop-shadow(0px 12px 24px rgba(0, 0, 0, 0.25));
        opacity: 0.98;
        z-index: 40;
    }

    /* Görseller orijinal boyutlarında ölçeklenerek gösterilir */
    .puzzle-img {
        display: block;
        width: 100%;
        height: 100%;
        pointer-events: none;
        user-select: none;
    }

    /* İnteraktif Metin Katmanı */
    .puzzle-text-layer-interactive {
        position: absolute;
        font-weight: 700;
        line-height: 1.25;
        user-select: none;
        pointer-events: none;
        z-index: 20;
    }

    /* Dark Overlay */
    .puzzle-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0);
        z-index: 90;
        pointer-events: none;
        transition: background 0.35s ease;
    }
    .puzzle-overlay.active {
        background: rgba(0, 0, 0, 0.45);
        pointer-events: auto;
    }

    /* Info Panel */
    .piece-info-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.92);
        width: 450px;
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(16px) saturate(1.6);
        -webkit-backdrop-filter: blur(16px) saturate(1.6);
        border: 1px solid rgba(255,255,255,0.35);
        border-radius: 14px;
        padding: 20px 22px;
        z-index: 300;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
        box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    }
    .piece-info-panel.visible {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
        pointer-events: auto;
    }
    .piece-info-panel .pip-title {
        font-size: 0.78rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 8px;
        line-height: 1.3;
        text-shadow: 0 1px 4px rgba(0,0,0,0.4);
    }
    .piece-info-panel .pip-body {
        font-size: 0.63rem;
        color: rgba(255,255,255,0.9);
        line-height: 1.6;
        text-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }

    /* Responsive */
    @media (max-width: 991px) {
        .piece-info-panel {
            width: 200px;
        }
        .materials-section {
            height: auto;
            min-height: calc(100vh - 80px);
            padding: 40px 20px;
        }
    }

    /* =====================
       RESPONSIVE BREAKPOINT
    ===================== */
    @media (max-width: 768px) {
        /* Puzzle board'u gizle */
        #main-board,
        .quality-docs {
            display: none !important;
        }
        /* Mobile slider'i göster */
        .mobile-slider-section {
            display: flex !important;
        }
    }
    @media (min-width: 769px) {
        /* Mobile slider'i gizle */
        .mobile-slider-section {
            display: none !important;
        }
    }

    /* =====================
       MOBILE SWIPER SLIDER
    ===================== */
    .mobile-slider-section {
        width: 100%;
        padding: 24px 16px 40px;
        flex-direction: column;
        align-items: center;
        background: #fff;
    }

    .mobile-swiper {
        width: 100%;
        max-width: 420px;
        padding-bottom: 48px !important;
    }

    .mobile-swiper .swiper-slide {
        border-radius: 18px;
        overflow: hidden;
        background: linear-gradient(145deg, #1a1a2e, #16213e);
        box-shadow: 0 12px 40px rgba(0,0,0,0.22);
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .mobile-swiper .slide-img-wrap {
        width: 100%;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.04);
    }

    .mobile-swiper .slide-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 12px;
    }

    .mobile-swiper .slide-body {
        padding: 18px 20px 24px;
        text-align: center;
    }

    .mobile-swiper .slide-title {
        font-size: 0.9rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 8px;
        line-height: 1.3;
    }

    .mobile-swiper .slide-desc {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.75);
        line-height: 1.65;
    }

    /* Pagination noktası rengi */
    .mobile-swiper .swiper-pagination-bullet {
        background: #aaa;
        opacity: 0.5;
    }
    .mobile-swiper .swiper-pagination-bullet-active {
        background: #f39c12;
        opacity: 1;
    }

    /* Ok butonları */
    .mobile-swiper .swiper-button-next,
    .mobile-swiper .swiper-button-prev {
        width: 38px;
        height: 38px;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 50%;
        color: #f39c12;
        top: 45%;
        margin-top: -19px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .mobile-swiper .swiper-button-next:hover,
    .mobile-swiper .swiper-button-prev:hover {
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.35);
        box-shadow: 0 8px 25px rgba(243, 156, 18, 0.25);
    }
    .mobile-swiper .swiper-button-next {
        right: 12px;
    }
    .mobile-swiper .swiper-button-prev {
        left: 12px;
    }
    .mobile-swiper .swiper-button-next::after,
    .mobile-swiper .swiper-button-prev::after {
        font-size: 0.85rem;
        font-weight: 900;
    }

    /* Mobilde quality-docs */
    .mobile-quality-docs {
        width: 90%;
        margin-top: 24px;
        font-family: inherit;
    }
    .mobile-quality-docs .qd-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #f39c12;
        margin-bottom: 6px;
        display: block;
    }
    .mobile-quality-docs p {
        font-size: 0.65rem;
        color: #555;
        line-height: 1.6;
        margin: 0 0 4px 0;
    }
    .mobile-quality-docs p strong {
        color: #333;
        font-weight: 700;
    }

    .quality-docs {
        width: 90%;
        margin: 18px 0 0 0;
        padding: 0 4px;
        font-family: inherit;
        align-self: center;
    }

    .quality-docs .qd-title {
        font-size: 0.72rem;
        font-weight: 700;
        color: #f39c12;
        margin-bottom: 6px;
        display: block;
    }

    .quality-docs p {
        font-size: 0.63rem;
        color: #555;
        line-height: 1.55;
        margin: 0 0 3px 0;
    }

    .quality-docs p strong {
        color: #333;
        font-weight: 700;
    }
</style>
</head>

<body class="materials-page">

    <!-- Sol Panel (Fixed) -->
    <div class="sidebar">
        <div>
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <h1 class="sidebar-title">
                <?php
                $words = explode(' ', $pageTitle);
                if (count($words) > 1) {
                    echo htmlspecialchars($words[0]) . '<br>' . htmlspecialchars(implode(' ', array_slice($words, 1)));
                } else {
                    echo htmlspecialchars($pageTitle);
                }
                ?>
            </h1>
        </div>

        <div class="sidebar-footer">
            <?php
            echo statik('sidebar_footer');
            ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Dark Overlay -->
        <div class="puzzle-overlay" id="puzzle-overlay"></div>

        <!-- Piece Info Panel -->
        <div class="piece-info-panel" id="piece-info-panel">
            <div class="pip-title" id="pip-title"></div>
            <div class="pip-body" id="pip-body"></div>
        </div>

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <!-- Puzzle Alanı -->
        <div class="materials-section">
            <div class="puzzle-board" id="main-board">

                <!-- Parça 1: Customer Focus and Satisfaction (1.png) -->
                <div class="puzzle-piece-wrapper" id="piece-1" data-id="1">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/1.png" alt="1" />
                    <div class="puzzle-text-layer-interactive" id="text-container-1"></div>
                </div>

                <!-- Parça 2: Continuous Improvement & Innovation (2.png) -->
                <div class="puzzle-piece-wrapper" id="piece-2" data-id="2">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/2.png" alt="2" />
                    <div class="puzzle-text-layer-interactive" id="text-container-2"></div>
                </div>

                <!-- Parça 3: Legal Compliance and Ethical Values (3.png) -->
                <div class="puzzle-piece-wrapper" id="piece-3" data-id="3">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/3.png" alt="3" />
                    <div class="puzzle-text-layer-interactive" id="text-container-3"></div>
                </div>

                <!-- Parça 4: Environmental Awareness and Sustainability (4.png) -->
                <div class="puzzle-piece-wrapper" id="piece-4" data-id="4">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/4.png" alt="4" />
                    <div class="puzzle-text-layer-interactive" id="text-container-4"></div>
                </div>

                <!-- Parça 5: Employee Development and Safety (5.png) -->
                <div class="puzzle-piece-wrapper" id="piece-5" data-id="5">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/5.png" alt="5" />
                    <div class="puzzle-text-layer-interactive" id="text-container-5"></div>
                </div>

                <!-- Parça 6: Stakeholder Collaboration and Social Responsibility (6.png) -->
                <div class="puzzle-piece-wrapper" id="piece-6" data-id="6">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/6.png" alt="6" />
                    <div class="puzzle-text-layer-interactive" id="text-container-6"></div>
                </div>

                <!-- Parça 7: Conclusion (7.png) -->
                <div class="puzzle-piece-wrapper" id="piece-7" data-id="7">
                    <img class="puzzle-img" src="https://v2.umuttasarim.com/assets/img/puzzle/7.png" alt="7" />
                    <div class="puzzle-text-layer-interactive" id="text-container-7"></div>
                </div>

            </div>

            <!-- Quality Documents and Certificates Text -->
            <div class="quality-docs">
                <span class="qd-title"><?php echo htmlspecialchars($kaliteData['qualityDocsTitle'] ?? 'Kalite Belgeleri ve Sertifikalar'); ?></span>
                <?php foreach (($kaliteData['qualityDocs'] ?? []) as $doc): ?>
                    <p><strong><?php echo htmlspecialchars($doc['label']); ?>:</strong> <?php echo htmlspecialchars($doc['text']); ?></p>
                <?php endforeach; ?>
            </div>

            <!-- Mobile Slider (768px altı) -->
            <div class="mobile-slider-section">

                <div class="mobile-swiper swiper" id="mobile-puzzle-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach (($kaliteData['pieces'] ?? []) as $id => $slide): ?>
                            <div class="swiper-slide">
                                <div class="slide-img-wrap"><img src="https://v2.umuttasarim.com/assets/img/puzzle/<?php echo htmlspecialchars($id); ?>.png" alt="<?php echo htmlspecialchars($slide['title']); ?>" /></div>
                                <div class="slide-body">
                                    <div class="slide-title"><?php echo htmlspecialchars($slide['title']); ?></div>
                                    <div class="slide-desc"><?php echo $slide['desc']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>

                <!-- Quality docs mobil -->
                <div class="mobile-quality-docs">
                    <span class="qd-title"><?php echo htmlspecialchars($kaliteData['qualityDocsTitle'] ?? 'Kalite Belgeleri ve Sertifikalar'); ?></span>
                    <?php foreach (($kaliteData['qualityDocs'] ?? []) as $doc): ?>
                        <p><strong><?php echo htmlspecialchars($doc['label']); ?>:</strong> <?php echo htmlspecialchars($doc['text']); ?></p>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>

    </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Puzzle Script ve Matematiksel Hizalama Motoru -->
    <script>
        // Ses Sentezleyicisi (Eğlenceli Tıklama Sesleri)
        function playPopSound(freq = 180, type = 'sine', duration = 0.15) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = type;
                osc.frequency.setValueAtTime(freq, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 3.5, ctx.currentTime + duration);

                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + duration);
            } catch (e) {
                // Tarayıcı ses desteğini engellerse sessiz geç
            }
        }

        // --- AKILLI ÖLÇEKLENDİRME SİSTEMİ ---
        const scaleFactor = 0.7;   // Görsel kalitesini bozmadan yapbozu %30 küçülten çarpan
        let isScattered = false;   // Parçaların dağıtılma durumunu takip eder

        // BELİRLEDİĞİNİZ KUSURSUZ KART MANUEL KOORDİNAT VERİLERİ (KİLİTLENDİ 🔒)
        const userCoords = {
            "1": { "x": -85, "y": -226 },
            "2": { "x": -20, "y": -34 },
            "3": { "x": 233, "y": 30 },
            "4": { "x": 480, "y": -36 },
            "5": { "x": 231, "y": 224 },
            "6": { "x": 482, "y": 286 },
            "7": { "x": 670, "y": 286 }
        };

        // AYARLADIĞINIZ METİN YERLEŞİM VE STİL KONFİGÜRASYONLARI (KİLİTLENDİ 🔒)
        const staticTextConfigs = {
            "1": { "x": 99, "y": 104, "fontSize": 17, "color": "#ffffff", "textAlign": "left", "width": 150 },
            "2": { "x": 90, "y": 165, "fontSize": 17, "color": "#ffffff", "textAlign": "left", "width": 140 },
            "3": { "x": 93, "y": 100, "fontSize": 17, "color": "#ffffff", "textAlign": "left", "width": 160 },
            "4": { "x": 86, "y": 162, "fontSize": 17, "color": "#ffffff", "textAlign": "left", "width": 150 },
            "5": { "x": 90, "y": 164, "fontSize": 17, "color": "#ffffff", "textAlign": "left", "width": 150 },
            "6": { "x": 55, "y": 170, "fontSize": 16, "color": "#ffffff", "textAlign": "left", "width": 160 },
            "7": { "x": 84, "y": 110, "fontSize": 16, "color": "#ffffff", "textAlign": "left", "width": 135 }
        };

        const dynamicPieces = <?php echo json_encode($kaliteData['pieces'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;

        const textConfigs = {};
        Object.keys(staticTextConfigs).forEach(id => {
            textConfigs[id] = {
                ...staticTextConfigs[id],
                text: dynamicPieces[id] ? dynamicPieces[id].text : ""
            };
        });

        // Ölçekli Hizalama, Metin Çizimi ve Sınır Hesaplayıcı
        function alignPuzzle() {
            let minX = Infinity, minY = Infinity;
            let maxX = -Infinity, maxY = -Infinity;

            Object.keys(userCoords).forEach(id => {
                const coords = userCoords[id];
                const $wrapper = $(`#piece-${id}`);
                const imgEl = $wrapper.find('img')[0];

                const w = imgEl.naturalWidth * scaleFactor;
                const h = imgEl.naturalHeight * scaleFactor;

                $wrapper.css({
                    'width': `${w}px`,
                    'height': `${h}px`
                });

                const x = coords.x * scaleFactor;
                const y = coords.y * scaleFactor;

                if (x < minX) minX = x;
                if (y < minY) minY = y;
                if (x + w > maxX) maxX = x + w;
                if (y + h > maxY) maxY = y + h;

                $wrapper.css('transform-origin', `${w / 2}px ${h / 2}px`);
            });

            const margin = 20;
            const boardW = maxX - minX + (margin * 2);
            const boardH = maxY - minY + (margin * 2);

            $('.puzzle-board').css({
                'width': `90%`,
                // 'width': `${boardW}px`,
                'height': `${boardH}px`
            });

            Object.keys(userCoords).forEach(id => {
                const coords = userCoords[id];
                const x = (coords.x * scaleFactor) - minX + margin;
                const y = (coords.y * scaleFactor) - minY + margin;

                $(`#piece-${id}`).css({
                    'left': `${x}px`,
                    'top': `${y}px`
                });

                renderTextLayer(id);
            });
        }

        // Metin Katmanını Konfigürasyon Değerlerine Göre Çizen Fonksiyon
        function renderTextLayer(id) {
            const config = textConfigs[id];
            const $textNode = $(`#text-container-${id}`);

            $textNode.css({
                'left': `${config.x * scaleFactor}px`,
                'top': `${config.y * scaleFactor}px`,
                'width': `${config.width * scaleFactor}px`,
                'font-size': `${config.fontSize * scaleFactor}px`,
                'color': config.color,
                'text-align': config.textAlign
            }).html(config.text.replace(/\n/g, '<br>'));
        }

        // Parçaları Ekran Çeperlerine Dağıtma Algoritması
        function scatterPuzzle() {
            isScattered = true;
            playPopSound(110, 'triangle', 0.25);
            $('.puzzle-piece-wrapper').removeClass('active').each(function() {
                const randomX = (Math.random() - 0.5) * 350;
                const randomY = (Math.random() - 0.5) * 350;
                const randomRotate = (Math.random() - 0.5) * 60;

                $(this).css({
                    'transform': `translate(${randomX}px, ${randomY}px) rotate(${randomRotate}deg)`
                });
            });
        }

        // Parçaları Yerine Kilitleme
        function assemblePuzzle() {
            isScattered = false;
            playPopSound(250, 'sine', 0.3);
            $('.puzzle-piece-wrapper').removeClass('active').css({
                'transform': 'translate(0px, 0px) rotate(0deg)'
            });
        }

        // Tüm Görsellerin Yüklenme Durumunu Kontrol Eden Döngü
        function checkAndAlign() {
            let allLoaded = true;
            $('.puzzle-img').each(function() {
                if (!this.complete || this.naturalWidth === 0) {
                    allLoaded = false;
                }
            });

            if (allLoaded) {
                alignPuzzle();
                scatterPuzzle();
                setTimeout(assemblePuzzle, 1000);
            } else {
                setTimeout(checkAndAlign, 50);
            }
        }

        // Sayfa Hazır Olduğunda Başlat
        $(document).ready(function() {
            checkAndAlign();

            // --- TIKLAMA / HOVER EYLEMLERİ ---
            $('.puzzle-piece-wrapper').on('click', function() {
                if (isScattered) return;

                const id = $(this).attr('data-id');
                const isActive = $(this).hasClass('active');

                if (isActive) {
                    $(this).removeClass('active').css({
                        'transform': 'translate(0px, 0px) rotate(0deg)'
                    });
                    playPopSound(200 + (id * 20), 'sine', 0.12);
                } else {
                    $('.puzzle-piece-wrapper').not(this).removeClass('active').css({
                        'transform': 'translate(0px, 0px) rotate(0deg)'
                    });

                    $(this).addClass('active').css({
                        'transform': 'translate(0px, -12px) rotate(0deg)'
                    });
                    playPopSound(260 + (id * 35), 'sine', 0.15);
                }
            });

            // --- HOVER BİLGİ PANELİ İÇERİKLERİ ---
            const pieceInfo = {};
            Object.keys(dynamicPieces).forEach(id => {
                pieceInfo[id] = {
                    title: dynamicPieces[id].title,
                    body: dynamicPieces[id].desc
                };
            });

            let hoverTimeout = null;

            $('.puzzle-piece-wrapper').hover(
                function() {
                    if (isScattered) return;
                    const id = $(this).attr('data-id');
                    if (!$(this).hasClass('active')) {
                        $(this).css({ 'transform': 'translate(0px, -6px) rotate(0deg)' });
                    }

                    clearTimeout(hoverTimeout);

                    // Overlay aç
                    $('#puzzle-overlay').addClass('active');

                    // Hover'lanan kartın z-index'ini overlay'ın üstüne çıkar
                    $('.puzzle-piece-wrapper').css('z-index', 10);
                    $(this).css('z-index', 200);

                    // Paneli konumlandır: kartın sağına veya ortaya
                    const pieceRect = this.getBoundingClientRect();
                    const panelW = 240;
                    const panelH = 200;
                    const gap = 16;

                    let left = pieceRect.right + gap;
                    let top  = pieceRect.top + (pieceRect.height / 2);

                    // Sağdan taşarsa sola yönlendir
                    if (left + panelW > window.innerWidth - 10) {
                        left = pieceRect.left - panelW - gap;
                    }
                    // Üstten/alttan taşarsa sınırla
                    top = Math.max(panelH / 2 + 10, Math.min(top, window.innerHeight - panelH / 2 - 10));

                    const info = pieceInfo[id] || {};
                    $('#pip-title').text(info.title || '');
                    $('#pip-body').html(info.body || '');

                    $('#piece-info-panel')
                        .css({
                            left: left + 'px',
                            top:  top  + 'px',
                            transform: 'translate(0, -50%) scale(0.92)'
                        })
                        .addClass('visible')
                        .css('transform', 'translate(0, -50%) scale(1)');
                },
                function() {
                    if (isScattered) return;
                    if (!$(this).hasClass('active')) {
                        $(this).css({ 'transform': 'translate(0px, 0px) rotate(0deg)' });
                    }

                    hoverTimeout = setTimeout(function() {
                        $('#puzzle-overlay').removeClass('active');
                        $('#piece-info-panel').removeClass('visible');
                        $('.puzzle-piece-wrapper').css('z-index', 10);
                    }, 120);
                }
            );

            // Overlay tıklanınca kapat
            $('#puzzle-overlay').on('click', function() {
                $(this).removeClass('active');
                $('#piece-info-panel').removeClass('visible');
                $('.puzzle-piece-wrapper').css('z-index', 10);
            });
        });

        // --- MOBİL SWIPER BAŞLATMA ---
        if (typeof Swiper !== 'undefined') {
            new Swiper('#mobile-puzzle-swiper', {
                loop: true,
                slidesPerView: 1,
                spaceBetween: 20,
                centeredSlides: true,
                grabCursor: true,
                effect: 'slide',
                pagination: {
                    el: '#mobile-puzzle-swiper .swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '#mobile-puzzle-swiper .swiper-button-next',
                    prevEl: '#mobile-puzzle-swiper .swiper-button-prev',
                },
            });
        }
    </script>
</body>

</html>