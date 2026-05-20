<?php
$hasSidebar = true;
$pageTitle = "Proje Haritası";
include_once 'inc/header.php';

// Referanslar verisini dil dosyasına göre yükle
$referanslar_path = "data/lang/{$lang}/referanslar/referanslar.json";
$referanslar = [];
if (file_exists($referanslar_path)) {
    $referanslar = json_decode(file_get_contents($referanslar_path), true);
}
?>
<!-- Harita İçin Gerekli CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
    .map-main-wrapper {
        display: flex;
        height: calc(100vh - 80px); /* Navbar yüksekliği düşüldü */
        background: #fff;
    }

    #map {
        flex: 1;
        height: 100%;
        z-index: 1;
    }

    .project-list-container {
        width: 400px;
        height: 100%;
        background: #f8f9fa;
        border-right: 1px solid #eee;
        display: flex;
        flex-direction: column;
        z-index: 2;
    }

    .search-container {
        padding: 20px;
        background: #fff;
        border-bottom: 1px solid #eee;
    }

    #searchInput {
        border-radius: 25px;
        padding: 10px 20px;
        border: 1px solid #ddd;
        width: 100%;
    }

    .projects-wrapper {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
    }

    .project-card {
        background: white;
        border-radius: 12px;
        padding: 15px;
        padding-left: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .project-card::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 5px;
        background: url('assets/img/button-bg.png') no-repeat center center / cover;
    }

    .project-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    .project-card h5 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .project-card small {
        color: #888;
    }

    .custom-popup .leaflet-popup-content {
        margin: 0;
        min-width: 200px;
    }

    .popup-content {
        overflow: hidden;
        border-radius: 12px;
        position: relative;
    }

    .popup-img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .popup-content::after {
        content: '';
        position: absolute;
        top: 120px; /* Image height */
        left: 0;
        right: 0;
        height: 3px;
        background: url('assets/img/button-bg.png') no-repeat center center / cover;
        z-index: 10;
    }

    .popup-info {
        padding: 10px;
    }

    .popup-info h6 {
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 14px;
    }

    .popup-btn {
        background: url('assets/img/button-bg.png') no-repeat center center / cover;
        color: white !important;
        padding: 8px 0;
        border-radius: 50px;
        text-decoration: none;
        display: block;
        font-size: 13px;
        font-weight: 600;
        text-align: center;
        margin-top: 10px;
    }

    @media (max-width: 992px) {
        .project-list-container {
            width: 300px;
        }
    }

    @media (max-width: 768px) {
        .map-main-wrapper {
            flex-direction: column;
        }
        .project-list-container {
            width: 100%;
            height: 40%;
        }
        #map {
            height: 60%;
        }
    }
</style>

<body class="materials-page">
    <div class="sidebar">
        <div>
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <h1 class="sidebar-title">Proje Haritası</h1>
        </div>
        <div class="sidebar-footer">
            <?php echo ($lang === 'tr') ? 'Tüm dünyadaki projelerimizi harita üzerinden keşfedin.' : 'Explore our projects all over the world on the map.'; ?>
        </div>
    </div>

    <div class="main-content">
        <?php include_once 'inc/navbar.php'; ?>

        <div class="map-main-wrapper">
            <div class="project-list-container">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="<?= ($lang === 'tr' ? 'Proje veya şehir ara...' : 'Search project or city...') ?>">
                </div>
                <div class="projects-wrapper">
                    <?php 
                    $markerData = [];
                    foreach ($referanslar as $refIndex => $ref): 
                        foreach ($ref['projeler'] as $projIndex => $proj):
                            if (empty($proj['lat']) || empty($proj['lng'])) continue;
                            
                            $firstImg = isset($proj['proje_gorselleri'][0]['gorsel']) ? $proj['proje_gorselleri'][0]['gorsel'] : 'assets/img/logo.png';

                            $markerData[] = [
                                'lat' => $proj['lat'],
                                'lng' => $proj['lng'],
                                'title' => $proj['proje_adi'],
                                'ref_name' => $ref['adi'],
                                'img' => $firstImg,
                                'url' => "referanslar?ref={$refIndex}&proj={$projIndex}"
                            ];
                    ?>
                        <div class="project-card" 
                             data-lat="<?= $proj['lat'] ?>" 
                             data-lng="<?= $proj['lng'] ?>"
                             data-index="<?= count($markerData) - 1 ?>">
                            <h5><?= $proj['proje_adi'] ?></h5>
                            <div class="d-flex justify-content-between align-items-center">
                                <small><i class="bi bi-geo-alt"></i> <?= $ref['adi'] ?></small>
                                <span class="badge bg-secondary" style="font-size: 10px;">PROJE</span>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    endforeach; 
                    ?>
                </div>
            </div>
            <div id="map"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const markersArray = <?= json_encode($markerData) ?>;
            
            // Haritayı başlat (Türkiye merkezli veya URL'den gelen konum)
            const urlParams = new URLSearchParams(window.location.search);
            const targetLat = urlParams.get('lat');
            const targetLng = urlParams.get('lng');
            
            var initialView = [38.9637, 35.2433];
            var initialZoom = 6;

            if (targetLat && targetLng) {
                initialView = [targetLat, targetLng];
                initialZoom = 14;
            }

            var map = L.map('map', {
                zoomControl: true
            }).setView(initialView, initialZoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            var markerClusters = L.markerClusterGroup();
            var leafletMarkers = [];

            // Marker'ları ekle
                markersArray.forEach((data, index) => {
                var popupContent = `
                    <div class="popup-content">
                        <img src="${data.img}" class="popup-img" alt="${data.title}">
                        <div class="popup-info">
                            <h6>${data.title}</h6>
                            <p style="margin-bottom:5px; font-size:12px; color:#666;">${data.ref_name}</p>
                            <a href="${data.url}" target="_blank" class="popup-btn">İncele</a>
                        </div>
                    </div>
                `;

                var marker = L.marker([data.lat, data.lng]);
                marker.bindPopup(popupContent, { className: 'custom-popup' });
                markerClusters.addLayer(marker);
                leafletMarkers[index] = marker;
            });

            map.addLayer(markerClusters);

            // Eğer URL'den konum geldiyse, ilgili popup'ı da aç
            if (targetLat && targetLng) {
                leafletMarkers.forEach(m => {
                    const pos = m.getLatLng();
                    if (Math.abs(pos.lat - targetLat) < 0.0001 && Math.abs(pos.lng - targetLng) < 0.0001) {
                        setTimeout(() => {
                            m.openPopup();
                        }, 1000);
                    }
                });
            }

            // Liste tıklama olayı
            document.querySelectorAll('.project-card').forEach(card => {
                card.addEventListener('click', function() {
                    const lat = this.dataset.lat;
                    const lng = this.dataset.lng;
                    const index = this.dataset.index;
                    
                    map.flyTo([lat, lng], 13);
                    setTimeout(() => {
                        leafletMarkers[index].openPopup();
                    }, 500);
                });
            });

            // Arama filtresi
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.project-card').forEach(card => {
                    const text = card.textContent.toLowerCase();
                    card.style.display = text.includes(term) ? 'block' : 'none';
                });
            });
        });
    </script>
</body>
</html>