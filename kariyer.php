<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/kariyer/kariyer.json";
$careerData = null;

if (file_exists($filePath)) {
    $json = json_decode(file_get_contents($filePath), true);
    $careerData = $json[0] ?? null;
}

$hasSidebar = true;
$pageTitle = $careerData['page_title'] ?? "Kariyer";
include_once 'inc/header.php';
?>
<style>
    /* Premium Career Page Styles */
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        line-height: 1.1;
        margin: 0;
    }

    .career-hero-section {
        position: relative;
        min-height: 85vh;
        height: 100%;
        background-image: url('assets/img/ref-page-bg.png');
        background-size: cover;
        background-position: center;
        overflow: hidden;
        display: flex;
        align-items: center;
    }

    .career-hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 100%);
        z-index: 1;
    }

    .career-content-wrapper {
        position: relative;
        z-index: 5;
        width: 100%;
        padding: 32px;
    }

    .hero-text-col {
        color: #fff;
        opacity: 0;
        transform: translateX(-30px);
        animation: fadeInLeft 1s forwards;
    }

    .hero-subtitle {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 3.2px;
        color: #e91e63;
        margin-bottom: 12px;
        display: block;
    }

    .hero-title {
        font-size: 2.4rem;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 16px;
        text-transform: uppercase;
    }

    .hero-desc {
        font-size: 0.8rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.7);
        max-width: 400px;
        margin-bottom: 32px;
    }

    /* Benefit Cards */
    .benefits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 40px;
    }

    .benefit-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 20px;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .benefit-card:hover {
        background: rgba(233, 30, 99, 0.1);
        border-color: rgba(233, 30, 99, 0.3);
        transform: translateY(-10px);
    }

    .benefit-card i {
        font-size: 1.6rem;
        color: #e91e63;
        margin-bottom: 12px;
        display: block;
    }

    .benefit-card h4 {
        font-size: 0.88rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }

    .benefit-card p {
        font-size: 0.68rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
        line-height: 1.4;
    }

    /* Form Column Styling */
    .form-col-wrapper {
        opacity: 0;
        transform: translateX(30px);
        animation: fadeInRight 1s 0.3s forwards;
    }

    .premium-form-card {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 24px;
        padding: 28px;
        box-shadow: 0 32px 64px rgba(0, 0, 0, 0.25);
        border: 1px solid #eee;
    }

    .form-header {
        margin-bottom: 16px;
    }

    .form-header h3 {
        font-size: 1.44rem;
        font-weight: 800;
        color: #333;
        margin-bottom: 4px;
    }

    .form-header p {
        font-size: 0.72rem;
        color: #888;
    }

    .form-dark .form-label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #999;
        margin-bottom: 6px;
    }

    .form-dark .form-control,
    .form-dark .form-select {
        background: #f8f9fa;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 11px 16px;
        font-size: 0.72rem;
        transition: all 0.3s;
    }

    .form-dark .form-control:focus {
        background: #fff;
        border-color: #e91e63;
        box-shadow: 0 10px 25px rgba(233, 30, 99, 0.08);
    }

    .cv-upload-area {
        position: relative;
        border: 1.6px dashed #ddd;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        background: #fafafa;
        transition: all 0.3s;
        cursor: pointer;
    }

    .cv-upload-area:hover {
        border-color: #e91e63;
        background: #fffafa;
    }

    .cv-upload-area i {
        font-size: 1.6rem;
        color: #bbb;
        margin-bottom: 8px;
        display: block;
    }

    .cv-upload-area span {
        font-size: 0.68rem;
        color: #777;
        font-weight: 600;
    }

    .submit-premium-btn {
        width: 100%;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 10px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        box-shadow: 0 10px 24px rgba(233, 30, 99, 0.2);
        transition: all 0.4s;
        margin-top: 12px;
    }

    .submit-premium-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 45px rgba(233, 30, 99, 0.35);
    }

    @keyframes fadeInLeft {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes fadeInRight {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Mobile Responsiveness */
    @media (max-width: 991px) {
        .career-hero-section {
            padding: 100px 0 50px;
            height: auto;
        }

        .hero-title {
            font-size: 2.8rem;
        }

        .career-content-wrapper {
            padding: 20px;
        }

        .form-col-wrapper {
            margin-top: 50px;
        }

        .premium-form-card {
            padding: 30px 20px;
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
                <?= statik('sidebar_title_career') ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik Area -->
    <div class="main-content">

        <!-- Üst Navigasyon -->
        <?php include_once 'inc/navbar.php'; ?>

        <!-- Career Content Section -->
        <section class="career-hero-section">
            <div class="container-fluid career-content-wrapper">
                <div class="row align-items-center">
                    <!-- Intro Col -->
                    <div class="col-lg-6 hero-text-col">
                        <span class="hero-subtitle"><?php echo $careerData['intro_subtitle']; ?></span>
                        <h2 class="hero-title"><?php echo $careerData['intro_title']; ?></h2>
                        <p class="hero-desc"><?php echo $careerData['intro_desc']; ?></p>

                        <div class="benefits-grid">
                            <?php foreach ($careerData['benefits'] as $benefit): ?>
                                <div class="benefit-card">
                                    <i class="bi <?php echo $benefit['icon']; ?>"></i>
                                    <h4><?php echo $benefit['title']; ?></h4>
                                    <p><?php echo $benefit['desc']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Form Col -->
                    <div class="col-lg-5 offset-lg-1 form-col-wrapper">
                        <div class="premium-form-card">
                            <div class="form-header">
                                <h3><?php echo $careerData['form_title']; ?></h3>
                                <p><?= statik('career_form_subtitle') ?></p>
                            </div>

                            <form id="careerForm" action="kariyer_islem.php" method="POST" enctype="multipart/form-data"
                                class="form-dark">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label
                                            class="form-label"><?php echo $careerData['form_fields']['name_label']; ?></label>
                                        <input type="text" class="form-control" name="ad_soyad" required
                                            placeholder="<?= statik('career_form_placeholder_name') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label
                                            class="form-label"><?php echo $careerData['form_fields']['email_label']; ?></label>
                                        <input type="email" class="form-control" name="eposta" required
                                            placeholder="<?= statik('career_form_placeholder_email') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label
                                            class="form-label"><?php echo $careerData['form_fields']['phone_label']; ?></label>
                                        <input type="tel" class="form-control" name="telefon"
                                            placeholder="<?= statik('career_form_placeholder_phone') ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label
                                            class="form-label"><?php echo $careerData['form_fields']['position_label']; ?></label>
                                        <select class="form-select" name="pozisyon" required>
                                            <option value="" disabled selected><?= statik('career_form_select_position') ?></option>
                                            <?php foreach ($careerData['positions'] as $pos): ?>
                                                <option value="<?php echo htmlspecialchars($pos); ?>">
                                                    <?php echo htmlspecialchars($pos); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-4">
                                        <label
                                            class="form-label"><?php echo $careerData['form_fields']['cv_label']; ?></label>
                                        <input type="file" name="cv_dosya" id="cv_input" style="display:none"
                                            accept=".pdf,.doc,.docx" required>
                                        <label for="cv_input" class="cv-drop-zone">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <span id="file_name"><?= statik('career_form_cv_select') ?></span>
                                        </label>
                                    </div>
                                </div>
                                <button type="submit"
                                    class="submit-premium-btn"><?= statik('career_form_submit') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Scripts -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cvInput = document.getElementById('cv_input');
            const fileNameDisplay = document.getElementById('file_name');

            cvInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    fileNameDisplay.textContent = e.target.files[0].name;
                    fileNameDisplay.style.color = '#e91e63';
                }
            });
        });
    </script>
</body>

</html>
