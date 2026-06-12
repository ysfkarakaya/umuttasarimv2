<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$password = 'umut123';
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['project_authenticated']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = $_POST['password'] ?? '';
    if ($submittedPassword === $password) {
        $_SESSION['project_authenticated'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Geçersiz şifre girdiniz.';
    }
}

$isAuthenticated = isset($_SESSION['project_authenticated']) && $_SESSION['project_authenticated'] === true;

// If authenticated, scan for directories under projeler/
$projects = [];
if ($isAuthenticated) {
    $currentDir = __DIR__;
    foreach (scandir($currentDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $currentDir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($fullPath)) {
            // Human readable titles and descriptions
            $title = $entry;
            $description = 'Geliştirme aracı ve proje dizini.';
            $icon = '📁';

            if ($entry === 'ceviri-otomasyon') {
                $title = 'Çeviri Otomasyonu';
                $description = 'Dil JSON dosyalarını Gemini, Vertex veya Claude yapay zeka servisleri aracılığıyla toplu veya tekli olarak otomatik çevirme paneli.';
                $icon = '🤖';
            } elseif ($entry === 'json-editor') {
                $title = 'JSON Dil Editörü';
                $description = 'Projedeki JSON yerelleştirme dosyalarını görsel form ağacı veya CodeMirror kod editörüyle, referans dil desteğiyle düzenleme aracı.';
                $icon = '📝';
            } elseif ($entry === 'get-data') {
                $title = 'API Kontrol Merkezi';
                $description = 'UmutAPP API veri senkronizasyonu ve görsel aktarımı yönetim paneli (get_apiv2).';
                $icon = '⚡';
            }

            $projects[] = [
                'dir' => $entry,
                'title' => $title,
                'description' => $description,
                'icon' => $icon
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proje Yönetim Paneli</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-color: #111827;
            --border-color: #1f2937;
            --line-color: #374151;
            --text-color: #f3f4f6;
            --muted-color: #9ca3af;
            --primary-color: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --error-color: #ef4444;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        /* Container grid for Dashboard */
        .dashboard-container {
            width: 100%;
            max-width: 900px;
            padding: 40px 24px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* Login Card */
        .login-card {
            background-color: var(--panel-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 400px;
            padding: 36px 30px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            animation: fadeIn 0.4s ease-out;
        }

        .login-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-header p {
            font-size: 0.88rem;
            color: var(--muted-color);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--muted-color);
        }

        input[type="password"] {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 11px 14px;
            font-size: 0.95rem;
            border-radius: var(--radius-sm);
            outline: none;
            transition: var(--transition);
        }

        input[type="password"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .btn {
            width: 100%;
            background-color: var(--primary-color);
            border: 1px solid var(--primary-color);
            color: #fff;
            padding: 11px 16px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            outline: none;
        }

        .btn:hover {
            background-color: #4f46e5;
            border-color: #4f46e5;
            box-shadow: 0 0 12px var(--primary-glow);
        }

        .error-msg {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            text-align: center;
        }

        /* Dashboard Header */
        .dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 18px;
        }

        .dash-header-title h2 {
            font-size: 1.6rem;
            font-weight: 600;
            background: linear-gradient(135deg, #818cf8, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .dash-header-title p {
            font-size: 0.88rem;
            color: var(--muted-color);
            margin-top: 4px;
        }

        .logout-link {
            color: var(--muted-color);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .logout-link:hover {
            color: var(--error-color);
            border-color: rgba(239, 68, 68, 0.3);
            background-color: rgba(239, 68, 68, 0.05);
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .project-card {
            background-color: var(--panel-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: var(--text-color);
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #818cf8, #6366f1);
            opacity: 0;
            transition: var(--transition);
        }

        .project-card:hover {
            transform: translateY(-4px);
            border-color: var(--line-color);
            box-shadow: 0 10px 20px -10px rgba(0, 0, 0, 0.5);
        }

        .project-card:hover::before {
            opacity: 1;
        }

        .project-icon {
            font-size: 2.2rem;
            margin-bottom: 4px;
        }

        .project-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .project-desc {
            font-size: 0.88rem;
            color: var(--muted-color);
            line-height: 1.5;
            flex-grow: 1;
        }

        .project-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #818cf8;
            margin-top: 4px;
            transition: var(--transition);
        }

        .project-card:hover .project-footer {
            color: #a5b4fc;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <?php if (!$isAuthenticated): ?>
        <!-- Login Screen -->
        <form class="login-card" method="POST">
            <div class="login-header">
                <h2>Proje Paneli</h2>
                <p>Projeleri listelemek için lütfen şifreyi girin.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">Giriş Şifresi</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autofocus>
            </div>

            <button class="btn" type="submit">Giriş Yap</button>
        </form>
    <?php else: ?>
        <!-- Dashboard Screen -->
        <div class="dashboard-container">
            <header class="dash-header">
                <div class="dash-header-title">
                    <h2>Geliştirici Araçları</h2>
                    <p>Yönetmek istediğiniz otomasyon veya düzenleme aracını seçin.</p>
                </div>
                <a href="?logout=1" class="logout-link">Güvenli Çıkış</a>
            </header>

            <main class="projects-grid">
                <?php if (empty($projects)): ?>
                    <div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--muted-color);">
                        Henüz tanımlanmış bir proje dizini bulunamadı.
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <a href="<?php echo htmlspecialchars($project['dir'], ENT_QUOTES, 'UTF-8'); ?>/" class="project-card">
                            <div class="project-icon"><?php echo $project['icon']; ?></div>
                            <h3 class="project-title"><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="project-desc"><?php echo htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="project-footer">
                                Projeyi Aç ➔
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>

</body>
</html>
