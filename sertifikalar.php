<?php
$hasSidebar = true;
?>
<?php
$pageTitle = "Sertifikalar";
include_once 'inc/header.php';

// Fetch dynamic content from JSON
$lang = active_lang();
$jsonPath = __DIR__ . "/data/lang/{$lang}/kartlar/sertifikalar.json";
$sertifikalar = [];
if (file_exists($jsonPath)) {
    $sertifikalar = json_decode(file_get_contents($jsonPath), true);
}

// Convert certificate details for JS
$sertifikalarJSData = [];
foreach ($sertifikalar as $item) {
    if (!isset($sertifikalarJSData[$item['kart_adi']])) {
        $sertifikalarJSData[$item['kart_adi']] = [];
    }
    foreach ($item['detaylar'] as $detay) {
        $sertifikalarJSData[$item['kart_adi']][] = [
            'title' => $detay['kart_detay_adi'],
            'desc' => $detay['kart_detay_aciklama'],
            'highlight' => ($detay['kart_detay_etiket'] === 'Highlight')
        ];
    }
}
?>
<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        line-height: 1.1;
        margin: 0;
    }

    .materials-section {
        padding: 40px;
        position: relative;
        min-height: 80vh;
        height: 100%;
        overflow: hidden;
    }

    .materials-header h2 {
        color: #999;
        font-size: 1.5rem;
        font-weight: 400;
        margin-bottom: 0;
    }

    .materials-header h1 {
        font-size: 3.5rem;
        font-weight: 900;
        color: #1e40af;
        /* Deep Blue */
        margin-top: -5px;
        text-transform: uppercase;
        line-height: 1;
        transition: all 0.5s ease;
    }

    /* Sphere Carousel */
    .sphere-container {
        position: relative;
        height: 250px;
        /* margin-top: 20px; */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sphere-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sphere-item {
        position: absolute;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
        background-size: cover;
        background-position: center;
    }

    /* Sphere Shadows for Grounding */
    .sphere-item::after {
        content: '';
        position: absolute;
        bottom: -30px;
        left: 10%;
        width: 80%;
        height: 20px;
        background: radial-gradient(ellipse at center, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0) 70%);
        border-radius: 50%;
        filter: blur(5px);
        z-index: -1;
    }

    .sphere-item.active {
        width: 180px;
        height: 180px;
        z-index: 20;
        transform: translateX(0) translateY(20px) scale(1.1);
        filter: brightness(1.1);
    }

    .sphere-item.prev {
        transform: translateX(-100px) translateY(-10px) scale(0.85);
        z-index: 15;
        filter: brightness(0.9);
    }

    .sphere-item.hidden {
        transform: translateX(-180px) translateY(-30px) scale(0.7);
        opacity: 0.9;
        z-index: 10;
        filter: brightness(0.8);
    }

    .sphere-item.next {
        transform: translateX(90px) translateY(5px) scale(0.8);
        z-index: 12;
        filter: brightness(0.9);
    }

    /* Material Gradients - More Realistic */
    .sphere-metal {
        background-size: cover !important;
        background: url('assets/img/sertifika1.png');
        /* box-shadow: inset -10px -10px 30px rgba(0, 0, 0, 0.5), 0 20px 40px rgba(0, 0, 0, 0.2); */
    }

    .sphere-wood {
        /* background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, rgba(0, 0, 0, 0) 50%),
                url('https://www.transparenttextures.com/patterns/wood-pattern.png'),
                #8b5a2b; */
        background: url('assets/img/sertifika2.png');
        background-size: cover !important;
        background-blend-mode: overlay;
        /* box-shadow: inset -10px -10px 40px rgba(0, 0, 0, 0.4), 0 20px 40px rgba(0, 0, 0, 0.2); */
    }

    .sphere-plastic {
        background-size: cover !important;
        background: url('assets/img/sertifika3.png');
        /* box-shadow: inset -15px -15px 40px rgba(0, 0, 0, 0.3), 0 20px 40px rgba(59, 130, 246, 0.3); */
        opacity: 0.95;
    }

    .sphere-glass {
        background-size: cover !important;
        background: url('assets/img/sertifika4.png');
        /* backdrop-filter: blur(8px); */
        border: 1px solid rgba(255, 255, 255, 0.4);
        /* box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.5), 0 20px 40px rgba(0, 0, 0, 0.1); */
    }

    /* Floating Cards Section */
    .cards-section {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 5;
        overflow: visible;
    }

    .floating-card-wrapper {
        position: absolute;
        width: 250px;
        pointer-events: all;
        transition: transform 0.1s ease-out;
    }

    .material-card-premium {
        background: white;
        /* background: transparent; */
        padding: 10px;
        transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        display: flex;
        flex-direction: column;
    }

    /* The Special Blue Highlight Card */
    .material-card-premium.highlight-card {
        background: #eef6ff;
        border-radius: 15px;
        padding: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        width: 260px;
        border: 1px solid rgba(30, 64, 175, 0.08);
    }

    .material-card-premium h4 {
        font-weight: 700;
        font-size: 0.8rem;
        color: #1e40af;
        /* Deep Blue */
        margin-bottom: 4px;
        line-height: 1.2;
    }

    .material-card-premium.highlight-card h4 {
        font-size: 1rem;
        color: #4b5563;
        /* Darker gray for highlight text */
    }

    .material-card-premium.highlight-card h4 b {
        color: #1e40af;
    }

    .material-card-premium p {
        font-size: 0.7rem;
        color: #4b5563;
        line-height: 1.4;
        margin-bottom: 0;
    }

    .material-card-premium b,
    .material-card-premium strong {
        color: #1e40af;
        font-weight: 700;
    }

    /* Connection Lines SVG */
    .connection-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        pointer-events: none;
    }

    .line-path {
        fill: none;
        stroke: #1e40af;
        stroke-width: 1.5;
        opacity: 0.3;
    }

    @keyframes dashMove {
        from {
            stroke-dashoffset: 1000;
        }

        to {
            stroke-dashoffset: 0;
        }
    }

    .specs-badge {
        position: absolute;
        top: 30px;
        right: 30px;
        width: 105px;
        height: 105px;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        border-radius: 9px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 19px;
        color: #fff;
        z-index: 20;
        box-shadow: 0 11px 26px rgba(233, 30, 99, 0.25);
        transition: all 0.4s ease;
    }

    .specs-badge::before {
        content: "";
        position: absolute;
        top: 15px;
        right: 15px;
        border-right: 5px solid #fff;
        border-top: 5px solid #fff;
        border-left: 5px solid transparent;
        border-bottom: 5px solid transparent;
        opacity: 0.8;
    }

    .specs-badge .badge-top {
        font-size: 0.71rem;
        font-weight: 300;
        margin-bottom: -1px;
        opacity: 0.9;
    }

    .specs-badge .badge-bottom {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -1px;
    }

    /* Responsive Design */
    @media (max-width: 991px) {
        .materials-section {
            padding: 20px;
        }

        .materials-header h1 {
            font-size: 3.5rem;
        }

        .sphere-container {
            height: 200px;
        }

        .cards-section {
            padding: 20px 0;
        }

        .sidebar-title {
            font-size: 1.8rem !important;
        }
    }

    @media (max-width: 768px) {
        .materials-header-wrapper {
            display: grid;
            grid-template-columns: 9fr 3fr;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .materials-header h1 {
            font-size: 2rem !important;
            margin-top: 0;
        }

        .materials-header h2 {
            font-size: 0.9rem !important;
        }

        .specs-badge {
            position: relative !important;
            top: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 1/1;
            padding: 12px !important;
            min-width: 70px;
            border-radius: 8px !important;
            box-shadow: 0 10px 20px rgba(233, 30, 99, 0.2);
        }

        .specs-badge .badge-top {
            font-size: 0.6rem !important;
        }

        .specs-badge .badge-bottom {
            font-size: 0.9rem !important;
        }

        .specs-badge::before {
            top: 10px !important;
            right: 10px !important;
            border-right: 4px solid #fff !important;
            border-top: 4px solid #fff !important;
            border-left: 4px solid transparent !important;
            border-bottom: 4px solid transparent !important;
        }

        /* Sphere Smaller on Mobile */
        .sphere-item {
            width: 70px !important;
            height: 70px !important;
        }

        .sphere-item.active {
            width: 90px !important;
            height: 90px !important;
        }

        .materials-section {
            overflow: visible;
        }

        .connection-canvas {
            display: none;
        }

        .cards-section {
            position: relative;
            inset: auto;
            height: auto;
            margin-top: 18px;
            display: flex;
            gap: 14px;
            overflow-x: auto;
            overflow-y: hidden;
            pointer-events: auto;
            padding: 6px 2px 12px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .cards-section::-webkit-scrollbar {
            display: none;
        }

        .floating-card-wrapper {
            position: relative;
            width: min(280px, 78vw);
            min-width: min(280px, 78vw);
            top: auto !important;
            left: auto !important;
            transform: none !important;
            opacity: 1 !important;
            transition: none !important;
            z-index: 5 !important;
            scroll-snap-align: start;
        }

        .material-card-premium:hover {
            transform: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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
                <?= statik('sidebar_title_certificates') ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="materials-header-wrapper">
                <div class="materials-header">
                    <h2><?= statik('certificates_header_h2') ?></h2>
                    <h1 id="active-material-name">ISO 10002</h1>
                </div>


            </div>

            <svg class="connection-canvas" id="connection-svg">
                <defs>
                    <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:rgba(233, 30, 99, 0.8);stop-opacity:1" />
                        <stop offset="100%" style="stop-color:rgba(123, 31, 162, 0.2);stop-opacity:0.2" />
                    </linearGradient>
                </defs>
            </svg>

            <div class="sphere-container">
                <div class="sphere-wrapper">
                    <?php
                    $sphereClasses = [
                        'ISO 10002' => 'sphere-metal',
                        'ISO 9001' => 'sphere-wood',
                        'ISO 14001' => 'sphere-plastic',
                        'ISO 45001' => 'sphere-glass'
                    ];

                    foreach ($sertifikalar as $index => $item):
                        $catName = $item['kart_adi'];
                        $sphereClass = isset($sphereClasses[$catName]) ? $sphereClasses[$catName] : 'sphere-generic';

                        $statusClass = '';
                        if ($index === 0)
                            $statusClass = 'active';
                        elseif ($index === 1)
                            $statusClass = 'next';
                        elseif ($index === count($sertifikalar) - 1)
                            $statusClass = 'prev';
                        else
                            $statusClass = 'hidden';

                        $imgUrl = !empty($item['kart_gorsel']) ? $item['kart_gorsel'] : 'assets/img/placeholder.png';
                        ?>
                        <div class="sphere-item <?php echo $statusClass; ?> <?php echo $sphereClass; ?>"
                            data-name="<?php echo htmlspecialchars($catName); ?>"
                            style="background-image: url('<?php echo $imgUrl; ?>');">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cards-section" id="floating-cards-container">
                <!-- Floating cards will be injected here via JS -->
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const spheres = Array.from(document.querySelectorAll('.sphere-item'));
            const materialName = document.getElementById('active-material-name');
            const cardsContainer = document.getElementById('floating-cards-container');
            const svg = document.getElementById('connection-svg');
            const mobileMediaQuery = window.matchMedia('(max-width: 768px)');
            let currentActiveIndex = 0;

            const isMobileView = () => mobileMediaQuery.matches;

            // Certificates Data
            // Materials Data (Dynamically loaded from JSON)
            const materialsData = <?php echo json_encode($sertifikalarJSData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            const drawLines = () => {
                if (isMobileView()) {
                    const existingPaths = svg.querySelectorAll('.line-path');
                    existingPaths.forEach(p => p.remove());
                    return;
                }

                const activeSphere = document.querySelector('.sphere-item.active');
                if (!activeSphere) return;

                const sphereRect = activeSphere.getBoundingClientRect();
                const containerRect = svg.getBoundingClientRect();

                const startX = sphereRect.left + sphereRect.width / 2 - containerRect.left;
                const startY = sphereRect.top + sphereRect.height / 2 - containerRect.top;

                // Clear existing lines but keep defs
                const existingPaths = svg.querySelectorAll('.line-path');
                existingPaths.forEach(p => p.remove());

                const cards = document.querySelectorAll('.floating-card-wrapper');
                cards.forEach(card => {
                    const cardRect = card.getBoundingClientRect();
                    const endX = cardRect.left + cardRect.width / 2 - containerRect.left;
                    const endY = cardRect.top + cardRect.height / 2 - containerRect.top;

                    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    const cp1x = startX + (endX - startX) * 0.5;
                    const cp1y = startY;
                    const cp2x = startX + (endX - startX) * 0.5;
                    const cp2y = endY;

                    path.setAttribute("d", `M ${startX} ${startY} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${endX} ${endY}`);
                    path.setAttribute("class", "line-path");
                    svg.appendChild(path);
                });
            };

            // Drag and Drop Management
            let activeDraggingCard = null;
            let startMouseX, startMouseY, startCardTop, startCardLeft;

            const handleMouseDown = (e, wrapper) => {
                if (isMobileView()) return;
                // Ensure we're not clicking a link or button inside
                if (e.target.closest('a') || e.target.closest('button')) return;

                activeDraggingCard = wrapper;

                const rect = wrapper.getBoundingClientRect();
                const containerRect = cardsContainer.getBoundingClientRect();

                // Store initial mouse and card positions
                startMouseX = e.clientX;
                startMouseY = e.clientY;
                startCardTop = parseFloat(wrapper.style.top);
                startCardLeft = parseFloat(wrapper.style.left);

                wrapper.style.transition = 'none';
                wrapper.style.zIndex = '1000';

                e.preventDefault();
                e.stopPropagation();
            };

            const logAllPositions = () => {
                const cards = document.querySelectorAll('.floating-card-wrapper');
                const positions = Array.from(cards).map(card => ({
                    top: Math.round(parseFloat(card.style.top) * 10) / 10,
                    left: Math.round(parseFloat(card.style.left) * 10) / 10
                }));
                console.log("--- COPY THESE TO safeSlots ARRAY ---");
                console.log(JSON.stringify(positions, null, 2));
            };

            document.addEventListener('mousemove', (e) => {
                if (!activeDraggingCard) return;

                const containerRect = cardsContainer.getBoundingClientRect();

                // Calculate movement delta in percentage
                const deltaX = ((e.clientX - startMouseX) / containerRect.width) * 100;
                const deltaY = ((e.clientY - startMouseY) / containerRect.height) * 100;

                activeDraggingCard.style.top = `${startCardTop + deltaY}%`;
                activeDraggingCard.style.left = `${startCardLeft + deltaX}%`;

                drawLines();
            });

            document.addEventListener('mouseup', () => {
                if (!activeDraggingCard) return;
                activeDraggingCard.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                activeDraggingCard.style.zIndex = '5';
                logAllPositions();
                activeDraggingCard = null;
            });

            const renderCards = (catName) => {
                const items = materialsData[catName] || [];
                cardsContainer.innerHTML = '';

                if (isMobileView()) {
                    items.forEach((item, index) => {
                        const cardWrapper = document.createElement('div');
                        cardWrapper.className = 'floating-card-wrapper';

                        cardWrapper.innerHTML = `
                        <div class="material-card-premium ${item.highlight ? 'highlight-card' : ''}">
                            <div class="card-content">
                                <h4>${item.title}</h4>
                                <p>${item.desc}</p>
                            </div>
                        </div>
                    `;

                        cardsContainer.appendChild(cardWrapper);
                    });
                    drawLines();
                    return;
                }

                // Fixed slots according to the image layout
                const safeSlots = [
                    {
                        "top": 5,
                        "left": 1
                    },
                    {
                        "top": 5,
                        "left": 68
                    },
                    {
                        "top": 46.5,
                        "left": 4.5
                    },
                    {
                        "top": 59,
                        "left": 32.8
                    },
                    {
                        "top": 53.8,
                        "left": 63.4
                    }
                ];

                items.forEach((item, index) => {
                    const slot = safeSlots[index];
                    if (!slot) return;

                    const cardWrapper = document.createElement('div');
                    cardWrapper.className = 'floating-card-wrapper';
                    cardWrapper.style.top = `${slot.top}%`;
                    cardWrapper.style.left = `${slot.left}%`;
                    cardWrapper.style.width = item.highlight ? '280px' : '250px';
                    cardWrapper.style.opacity = '0';
                    cardWrapper.style.transform = 'scale(0.8) translateY(20px)';
                    cardWrapper.style.cursor = 'move';

                    cardWrapper.innerHTML = `
                        <div class="material-card-premium ${item.highlight ? 'highlight-card' : ''}">
                            <div class="card-content">
                                <h4>${item.title}</h4>
                                <p>${item.desc}</p>
                            </div>
                        </div>
                    `;

                    cardWrapper.addEventListener('mousedown', (e) => handleMouseDown(e, cardWrapper));
                    cardsContainer.appendChild(cardWrapper);

                    setTimeout(() => {
                        cardWrapper.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                        cardWrapper.style.opacity = '1';
                        cardWrapper.style.transform = 'scale(1) translateY(0)';
                    }, index * 100);
                });

                setTimeout(drawLines, 200);
            };

            const updateSlider = (activeIndex) => {
                const total = spheres.length;
                const prevIndex = (activeIndex - 1 + total) % total;
                const nextIndex = (activeIndex + 1) % total;

                spheres.forEach((sphere, i) => {
                    sphere.classList.remove('active', 'prev', 'next', 'hidden');
                    if (i === activeIndex) sphere.classList.add('active');
                    else if (i === prevIndex) sphere.classList.add('prev');
                    else if (i === nextIndex) sphere.classList.add('next');
                    else sphere.classList.add('hidden');
                });

                const activeSphere = spheres[activeIndex];
                const catName = activeSphere.getAttribute('data-name');
                currentActiveIndex = activeIndex;
                materialName.textContent = catName;

                renderCards(catName);
            };

            spheres.forEach((sphere, index) => {
                sphere.addEventListener('click', () => updateSlider(index));
            });

            // Parallax Effect
            document.addEventListener('mousemove', (e) => {
                if (isMobileView()) return;
                if (activeDraggingCard) return; // Pause parallax while dragging
                const amount = 15;
                const x = (e.clientX - window.innerWidth / 2) / (window.innerWidth / 2);
                const y = (e.clientY - window.innerHeight / 2) / (window.innerHeight / 2);

                const cards = document.querySelectorAll('.floating-card-wrapper');
                cards.forEach((card, index) => {
                    const depth = (index + 1) * 0.5;
                    const moveX = x * amount * depth;
                    const moveY = y * amount * depth;
                    card.style.transform = `translate(${moveX}px, ${moveY}px)`;
                });

                drawLines();
            });

            window.addEventListener('resize', drawLines);
            mobileMediaQuery.addEventListener('change', () => {
                updateSlider(currentActiveIndex);
            });

            // Initial Load
            updateSlider(0);
        });
    </script>
</body>

</html>