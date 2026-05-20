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

$hasSidebar = true;
?>
<?php
include_once 'inc/header.php';
?>

<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 2.5rem;
        line-height: 1.1;
        margin-top: 50px;
    }

    /* Clear/Minimalist Quality Styles to match image */
    .materials-section {
        padding: 30px 50px;
        position: relative;
        height: calc(100vh - 80px);
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        overflow: hidden;
        background: #fff;
    }

    .quality-header {
        max-width: 1000px;
        margin-bottom: 20px;
    }

    .quality-header p {
        font-size: 0.65rem;
        line-height: 1.2;
        color: #777;
        margin-bottom: 2px;
    }

    .quality-header strong {
        color: #444;
    }

    .quality-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 30px;
        row-gap: 5px;
        max-width: 1200px;
        flex: 1;
        align-content: flex-start;
    }

    .quality-group {
        margin-bottom: 8px;
    }

    .quality-group h3 {
        font-size: 0.8rem;
        font-weight: 700;
        color: #f39c12;
        /* Exact Orange */
        margin-bottom: 2px;
        text-transform: none;
    }

    .quality-entry {
        /* margin-bottom: 6px; */
        line-height: 1;
    }

    .quality-entry strong {
        font-size: 0.65rem;
        color: #666;
        font-weight: 700;
    }

    .quality-entry span {
        font-size: 0.65rem;
        color: #888;
        line-height: 1;
    }

    .quality-footer {
        margin-top: auto;
        padding-top: 10px;
        padding-bottom: 15px;
        max-width: 1100px;
    }

    .quality-footer p {
        font-size: 0.63rem;
        color: #888;
        line-height: 1.2;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .materials-section {
            height: auto;
            overflow: visible;
            padding: 40px 20px;
        }

        .quality-grid {
            grid-template-columns: 1fr;
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

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <?php echo $pageContent; ?>

    </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // JS functionality removed as requested.
        });
    </script>
</body>

</html>