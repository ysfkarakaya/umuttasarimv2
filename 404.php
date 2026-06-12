<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/sayfalar/404.json";
$pageData = null;

if (file_exists($filePath)) {
    $pageData = json_decode(file_get_contents($filePath), true);
} else {
    // Fail-safe default (Turkish)
    $pageData = [
        "title" => "404 - Sayfa Bulunamadı | Umut Tasarım",
        "error_code" => "404",
        "heading" => "Sayfa Bulunamadı",
        "message" => "Aradığınız sayfa taşınmış, silinmiş veya geçici olarak kullanım dışı olabilir. Lütfen aradığınız adresi kontrol edin veya ana sayfaya dönün.",
        "button_text" => "Ana Sayfaya Dön"
    ];
}

$hasSidebar = true;
$pageTitle = $pageData['title'];
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

    /* --- 404 Section Styling --- */
    .error-section {
        padding: 40px;
        position: relative;
        height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at 50% 50%, #fdfdfd 0%, #f4f7f6 100%);
        animation: mainContentFadeIn 1s ease-out forwards;
    }

    .error-container {
        text-align: center;
        max-width: 600px;
        z-index: 10;
        position: relative;
        padding: 40px;
        background: rgba(255, 255, 255, 0.45);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.6);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
    }

    .error-icon-wrapper {
        position: relative;
        display: inline-block;
        margin-bottom: 24px;
        animation: floatIcon 4s ease-in-out infinite;
    }

    .error-code-bg {
        font-size: 8.5rem;
        font-weight: 900;
        line-height: 1;
        background: linear-gradient(135deg, #1b355a 0%, #0d1b2a 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0;
        letter-spacing: -2px;
        opacity: 0.95;
    }

    .error-heading {
        font-size: 2rem;
        font-weight: 800;
        color: #1b355a;
        margin-bottom: 16px;
    }

    .error-message {
        font-size: 0.95rem;
        color: #555566;
        line-height: 1.6;
        margin-bottom: 32px;
    }

    .error-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #e30613 0%, #a5030d 100%);
        color: #fff;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 14px 28px;
        border-radius: 50px;
        text-decoration: none;
        box-shadow: 0 10px 20px rgba(227, 6, 19, 0.15);
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .error-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(227, 6, 19, 0.25);
        color: #fff;
    }

    .error-btn i {
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }

    .error-btn:hover i {
        transform: translateX(-4px);
    }

    /* Keyframes */
    @keyframes floatIcon {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-12px);
        }
    }

    @keyframes mainContentFadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .error-section {
            height: auto;
            padding: 80px 20px;
        }
        .error-code-bg {
            font-size: 6.5rem;
        }
        .error-heading {
            font-size: 1.6rem;
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
                <span><?php echo $pageData['error_code']; ?></span>
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

        <section class="error-section">
            <div class="error-container">
                <div class="error-icon-wrapper">
                    <h2 class="error-code-bg"><?php echo $pageData['error_code']; ?></h2>
                </div>
                <h3 class="error-heading"><?php echo htmlspecialchars($pageData['heading'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="error-message"><?php echo htmlspecialchars($pageData['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="<?php echo lang_url('/'); ?>" class="error-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span><?php echo htmlspecialchars($pageData['button_text'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body>
</html>
