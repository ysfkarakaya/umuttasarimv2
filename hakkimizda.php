<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/kartlar/hakkimizda.json";
$aboutData = null;

if (file_exists($filePath)) {
    $json = json_decode(file_get_contents($filePath), true);
    $aboutData = $json[0] ?? null; // Get first record from top-level array
}

$hasSidebar = true;
$pageTitle = $aboutData['kart_adi'] ?? "Hakkımızda";
include_once 'inc/header.php';
?>
<style>
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        margin: 0;
        position: relative;
        z-index: 2;
    }

    .main-content {
        animation: none !important;
    }

    .custom-nav {
        z-index: 1020 !important;
    }

    .sidebar-child {
        display: none;
    }

    @media (min-width: 1200px) {
        .sidebar {
            overflow: visible !important;
        }

        .sidebar-child {
            display: block;
            position: absolute;
            bottom: 0;
            left: 100%;
            height: 85vh;
            pointer-events: none;
            z-index: 1001;
            transform: translateX(-29%);
            transition: all 0.3s ease;
            object-fit: contain;
            transform-origin: bottom left;
        }
    }

    @media (max-width: 1400px) and (min-width: 1200px) {
        .sidebar-child {
            height: 80vh;
            transform: translateX(-20%);
        }
    }

    /* --- About Page V2 Floating Style --- */
    .materials-section {
        padding: 20px 40px;
        position: relative;
        height: calc(100vh - 80px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: radial-gradient(circle at 50% 50%, #fdfdfd 0%, #f4f7f6 100%);
        animation: mainContentFadeIn 1s ease-out forwards;
    }

    .about-introduction {
        text-align: right;
        max-width: 450px;
        margin: 0 auto 5px;
        z-index: 10;
        position: relative;
    }

    .about-introduction p {
        font-size: 0.82rem;
        color: #555;
        line-height: 1.3;
        margin-bottom: 4px;
    }

    .cards-container {
        position: relative;
        flex: 1;
        width: 100%;
        height: 100%;
        perspective: 1000px;
    }

    .floating-card-wrapper {
        position: absolute;
        transition: transform 0.1s ease-out, opacity 0.8s ease;
        cursor: move;
        z-index: 5;
        user-select: none;
    }

    .about-card {
        container-type: inline-size;
        position: relative;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center center;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        width: 100%;
        background-color: transparent;
        /* Extra styles removed to keep only the texture visible */
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .floating-card-wrapper:hover {
        z-index: 100;
    }

    .floating-card-wrapper:hover .about-card {
        transform: scale(1.1);
    }

    .about-card h4 {
        font-weight: 700;
        font-size: clamp(12px, 4.5cqw, 40px);
        margin-bottom: 2cqw;
    }

    .about-card p,
    .about-card ul {
        font-size: clamp(9.5px, 2.9cqw, 30px);
        color: #445;
        line-height: 1.35;
        margin-bottom: 1.5cqw;
    }

    .about-card ul {
        padding-left: 4.5cqw;
        list-style-type: disc;
    }

    /* --- Unique Text Positioning Inside Textures --- */
    /* Change these values to move text around inside each texture */

    .vision-content {
        padding-top: 55cqw;
        padding-left: 12.5cqw;
        padding-right: 5cqw;
        /* Pushes text to the left side of the texture */
        text-align: left;
    }

    .vision-content h4 {
        font-size: clamp(14px, 5.2cqw, 44px);
    }

    .vision-content p {
        font-size: clamp(11px, 3.4cqw, 34px);
        line-height: 1.4;
    }

    .expertise-content {
        padding-top: 31.5cqw;
        padding-left: 8cqw;
        padding-right: 40cqw;
        text-align: left;
    }

    .approach-content {
        padding-top: 16cqw;
        padding-left: 5cqw;
        padding-right: 44cqw;
        text-align: left;
    }

    .approach-content h4 {
        font-size: clamp(11px, 4cqw, 35px);
        margin-bottom: 1cqw;
    }

    .approach-content p,
    .approach-content ul {
        font-size: clamp(8.5px, 2.5cqw, 25px);
        line-height: 1.25;
        margin-bottom: 1cqw;
    }

    .commitment-content {
        padding-top: 4.8cqw;
        padding-left: 37cqw;
        padding-right: 4.8cqw;
        text-align: left;
    }

    .commitment-content h4 {
        font-size: clamp(11px, 4cqw, 32px);
    }

    .commitment-content p,
    .commitment-content ul {
        font-size: clamp(8.5px, 2.4cqw, 22px);
        line-height: 1.25;
    }

    .infrastructure-content {
        padding-top: 16.25cqw;
        padding-left: 40cqw;
        padding-right: 5cqw;
        text-align: left;
    }

    /* Initial sizes for the containers to match texture aspect ratios roughly */
    .card-vision {
        width: 16.66vw;
        aspect-ratio: 372 / 356;
        background-image: url('assets/img/texture-1.png');
    }

    .card-expertise {
        width: 19.79vw;
        aspect-ratio: 469 / 454;
        background-image: url('assets/img/texture-3.png');
    }

    .card-approach {
        width: 23.43vw;
        aspect-ratio: 499 / 369;
        background-image: url('assets/img/texture-2.png');
    }

    .card-commitment {
        width: 21.87vw;
        aspect-ratio: 553 / 381;
        background-image: url('assets/img/texture-4.png');
    }

    @media (min-width: 1200px) {
        .about-card {
            width: 100% !important;
            height: 100% !important;
            background-size: 100% 100% !important;
        }
    }

    .card-infrastructure {
        width: 20.83vw;
        aspect-ratio: 501 / 454;
        background-image: url('assets/img/texture-5.png');
    }

    /* Connection Lines (Optional but cool if added later) */
    .connection-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
        pointer-events: none;
        opacity: 0.2;
    }

    @media (max-width: 1199px) {
        .materials-section {
            height: auto;
            padding: 40px 0;
            /* No side padding for full-width scroll */
            overflow: visible;
        }

        .cards-container {
            display: flex;
            flex-direction: row;
            /* Horizontal */
            gap: 20px;
            perspective: none;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding: 10px 20px 30px;
            /* Padding for the shadow and cards */
            -webkit-overflow-scrolling: touch;
        }

        .cards-container::-webkit-scrollbar {
            display: none;
            /* Hide scrollbar for cleaner look */
        }

        .floating-card-wrapper {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
            width: 85vw !important;
            /* Most of the screen width */
            min-width: 85vw !important;
            height: auto !important;
            scroll-snap-align: center;
            opacity: 1 !important;
        }

        .about-card {
            background-size: 100% 100%;
            min-height: auto;
            height: auto;
            width: 100% !important;
            background-color: transparent;
        }
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
                $title = statik('sidebar_title_about');
                echo htmlspecialchars($title);
                ?>
            </h1>
        </div>

        <img src="assets/img/sidebar-cocuk.png" alt="Child" class="sidebar-child">
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="container-fluid d-flex flex-column h-100">
                <div class="about-introduction">
                    <?php echo $aboutData['kart_aciklama'] ?? ''; ?>
                </div>

                <div class="cards-container" id="cards-container">
                    <?php
                    if (isset($aboutData['detaylar']) && is_array($aboutData['detaylar'])) {
                        foreach ($aboutData['detaylar'] as $detay) {
                            echo $detay['kart_detay_aciklama'];
                        }
                    }
                    ?>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('cards-container');
            const wrappers = document.querySelectorAll('.floating-card-wrapper');
            const mobileMediaQuery = window.matchMedia('(max-width: 1199px)');

            let activeDraggingCard = null;
            let startMouseX, startMouseY, startCardTop, startCardLeft;
            window.mouseX = window.innerWidth / 2;
            window.mouseY = window.innerHeight / 2;

            // --- CONFIGURATION ---
            // Set to 1 to enable dragging, 0 to disable
            const isDraggable = 1;
            // ---------------------

            const isMobileView = () => mobileMediaQuery.matches;

            // Reference sizes for the layout calculations (based on 1200x600 coordinate plane)
            const baseCoords = {
                "card-vision": { x: 94.157, y: -276.723, w: 372, h: 356 },
                "card-expertise": { x: 756.98, y: -231.448, w: 469, h: 454 },
                "card-approach": { x: 176.338, y: -10.845, w: 499, h: 369 },
                "card-commitment": { x: 629.286, y: 231.38, w: 553, h: 381 },
                "card-infrastructure": { x: 113.972, y: 298.667, w: 501, h: 454 }
            };

            const applyPositions = () => {
                if (isMobileView()) {
                    wrappers.forEach(wrapper => {
                        wrapper.style.top = '';
                        wrapper.style.left = '';
                        wrapper.style.width = '';
                        wrapper.style.height = '';
                    });
                    return;
                }
                
                const containerRect = container.getBoundingClientRect();
                const containerW = containerRect.width;
                const containerH = containerRect.height || 600;

                // Determine scaleFactor to fit within both width and height constraints
                let scaleFactor = containerW / 1300;
                const scaleFactorH = containerH / 850;
                scaleFactor = Math.min(scaleFactor, scaleFactorH);
                
                // Clamp scaleFactor to keep cards readable and elegant (0.55 to 1.15)
                scaleFactor = Math.min(Math.max(scaleFactor, 0.55), 1.15);

                let minX = Infinity, minY = Infinity;
                let maxX = -Infinity, maxY = -Infinity;

                Object.keys(baseCoords).forEach(className => {
                    const item = baseCoords[className];
                    const w = item.w * scaleFactor;
                    const h = item.h * scaleFactor;
                    const x = item.x * scaleFactor;
                    const y = item.y * scaleFactor;

                    if (x < minX) minX = x;
                    if (y < minY) minY = y;
                    if (x + w > maxX) maxX = x + w;
                    if (y + h > maxY) maxY = y + h;
                });

                const boardW = maxX - minX;
                const boardH = maxY - minY;

                const offsetX = (containerW - boardW) / 2 - minX;
                const offsetY = (containerH - boardH) / 2 - minY;

                Object.keys(baseCoords).forEach(className => {
                    const card = container.querySelector(`.${className}`);
                    if (card) {
                        const wrapper = card.closest('.floating-card-wrapper');
                        if (wrapper) {
                            const item = baseCoords[className];
                            const w = item.w * scaleFactor;
                            const h = item.h * scaleFactor;
                            const left = (item.x * scaleFactor) + offsetX;
                            const top = (item.y * scaleFactor) + offsetY;

                            wrapper.style.width = `${w}px`;
                            wrapper.style.height = `${h}px`;
                            wrapper.style.left = `${left}px`;
                            wrapper.style.top = `${top}px`;
                        }
                    }
                });
            };

            applyPositions();
            window.addEventListener('resize', applyPositions);

            // Update cursor and selection style based on draggable setting
            if (isDraggable) {
                wrappers.forEach(w => {
                    w.style.cursor = 'move';
                    w.style.userSelect = 'none';
                });
            } else {
                wrappers.forEach(w => {
                    w.style.cursor = 'default';
                    w.style.userSelect = 'text'; // Allow text selection when not draggable
                });
            }

            // Handle Drag & Drop
            const handleMouseDown = (e, wrapper) => {
                if (isMobileView() || !isDraggable) return;
                if (e.target.closest('a') || e.target.closest('button')) return;

                activeDraggingCard = wrapper;
                startMouseX = e.clientX;
                startMouseY = e.clientY;
                startCardTop = parseFloat(wrapper.style.top) || 0;
                startCardLeft = parseFloat(wrapper.style.left) || 0;

                wrapper.style.transition = 'none';
                wrapper.style.zIndex = '1000';
                e.preventDefault();
            };

            wrappers.forEach(wrapper => {
                wrapper.addEventListener('mousedown', (e) => handleMouseDown(e, wrapper));
            });

            document.addEventListener('mousemove', (e) => {
                window.mouseX = e.clientX;
                window.mouseY = e.clientY;

                if (activeDraggingCard) {
                    const deltaX = e.clientX - startMouseX;
                    const deltaY = e.clientY - startMouseY;

                    activeDraggingCard.style.top = `${startCardTop + deltaY}px`;
                    activeDraggingCard.style.left = `${startCardLeft + deltaX}px`;
                }
            });

            const logPositions = () => {
                const containerRect = container.getBoundingClientRect();
                const containerW = containerRect.width;
                const containerH = containerRect.height || 600;

                // Re-calculate the exact scaleFactor and offsets used for positioning
                let scaleFactor = containerW / 1300;
                const scaleFactorH = containerH / 850;
                scaleFactor = Math.min(scaleFactor, scaleFactorH);
                scaleFactor = Math.min(Math.max(scaleFactor, 0.55), 1.15);

                let minX = Infinity, minY = Infinity;
                let maxX = -Infinity, maxY = -Infinity;

                Object.keys(baseCoords).forEach(className => {
                    const item = baseCoords[className];
                    const w = item.w * scaleFactor;
                    const h = item.h * scaleFactor;
                    const x = item.x * scaleFactor;
                    const y = item.y * scaleFactor;

                    if (x < minX) minX = x;
                    if (y < minY) minY = y;
                    if (x + w > maxX) maxX = x + w;
                    if (y + h > maxY) maxY = y + h;
                });

                const boardW = maxX - minX;
                const boardH = maxY - minY;

                const offsetX = (containerW - boardW) / 2 - minX;
                const offsetY = (containerH - boardH) / 2 - minY;

                const positions = Array.from(wrappers).map(wrapper => {
                    const className = wrapper.querySelector('.about-card').classList[1];
                    const curLeft = parseFloat(wrapper.style.left) || 0;
                    const curTop = parseFloat(wrapper.style.top) || 0;

                    // Reconstruct base coordinates
                    const baseX = (curLeft - offsetX) / scaleFactor;
                    const baseY = (curTop - offsetY) / scaleFactor;

                    return {
                        class: className,
                        screen_left: curLeft.toFixed(1) + 'px',
                        screen_top: curTop.toFixed(1) + 'px',
                        base_x: parseFloat(baseX.toFixed(3)),
                        base_y: parseFloat(baseY.toFixed(3))
                    };
                });

                console.log("--- Current Card Positions ---");
                console.table(positions);

                // Print copy-pasteable configuration object
                const copyPasteObj = {};
                positions.forEach(pos => {
                    copyPasteObj[pos.class] = {
                        x: pos.base_x,
                        y: pos.base_y,
                        w: baseCoords[pos.class].w,
                        h: baseCoords[pos.class].h
                    };
                });
                console.log("Copy-pasteable baseCoords object:");
                console.log(JSON.stringify(copyPasteObj, null, 4));
            };

            document.addEventListener('mouseup', () => {
                if (!activeDraggingCard) return;
                activeDraggingCard.style.transition = 'all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1)';
                activeDraggingCard.style.zIndex = '5';
                logPositions();
                activeDraggingCard = null;
            });

            // Floating animation removed - Cards are now static

            // Initial reveal animation
            wrappers.forEach((wrapper, index) => {
                wrapper.style.opacity = '0';
                wrapper.style.transform = 'scale(0.8) translateY(30px)';
                setTimeout(() => {
                    wrapper.style.opacity = '1';
                    wrapper.style.transition = 'opacity 1s cubic-bezier(0.165, 0.84, 0.44, 1)';
                    wrapper.style.transform = 'scale(1) translateY(0)';
                }, 200 + (index * 150));
            });
        });
    </script>
</body>

</html>