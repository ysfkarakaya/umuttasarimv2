<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sadece Puzzle Yapısı (Görsel Tabanlı)</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <style>
        /* Sayfa Yerleşimi (Tamamen Arındırılmış & Şeffaf) */
        body {
            margin: 0;
            padding: 0;
            background: transparent; /* Web sitenize doğrudan entegre edilebilmesi için tamamen şeffaf arka plan */
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            font-family: ui-sans-serif, system-ui, sans-serif;
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
            pointer-events: none; /* Yazının altındaki tıklamaları engellememesi için */
            z-index: 20;
        }
    </style>
</head>
<body>

    <!-- Sadece Puzzle Alanı (Ekranda Tam Ortalanmış HTML Yapısı) -->
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

    <!-- SCRIPT VE MATEMATİKSEL HİZALAMA MOTORU -->
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
            "3": { "x": 233, "y": 32 },
            "4": { "x": 480, "y": -36 },
            "5": { "x": 231, "y": 224 },
            "6": { "x": 482, "y": 286 },
            "7": { "x": 670, "y": 286 }
        };

        // AYARLADIĞINIZ METİN YERLEŞİM VE STİL KONFİGÜRASYONLARI (KİLİTLENDİ 🔒)
        const textConfigs = {
            "1": {
                "text": "Customer Focus\nand Satisfaction",
                "x": 99,
                "y": 104,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "left",
                "width": 150
            },
            "2": {
                "text": "Continuous\nImprovement\n& Innovation",
                "x": 90,
                "y": 165,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 140
            },
            "3": {
                "text": "Legal Compliance\nand Ethical Values",
                "x": 93,
                "y": 109,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 160
            },
            "4": {
                "text": "Environmental\nAwareness and\nSustainability",
                "x": 86,
                "y": 162,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 150
            },
            "5": {
                "text": "Employee\nDevelopment\nand Safety",
                "x": 75,
                "y": 164,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 140
            },
            "6": {
                "text": "Stakeholder\nCollaboration and\nSocial Responsibility",
                "x": 64,
                "y": 166,
                "fontSize": 13,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 150
            },
            "7": {
                "text": "Conclusion",
                "x": 84,
                "y": 108,
                "fontSize": 17,
                "color": "#ffffff",
                "textAlign": "center",
                "width": 135
            }
        };

        // Ölçekli Hizalama, Metin Çizimi ve Sınır Hesaplayıcı
        function alignPuzzle() {
            let minX = Infinity, minY = Infinity;
            let maxX = -Infinity, maxY = -Infinity;

            // Her bir parçanın doğal görsel boyutunu scaleFactor ile çarparak alanı ayarlar
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

                // Animasyon transform merkezini ölçeklenmiş görselin tam ortası yapıyoruz
                $wrapper.css('transform-origin', `${w / 2}px ${h / 2}px`);
            });

            const margin = 20; 
            const boardW = maxX - minX + (margin * 2);
            const boardH = maxY - minY + (margin * 2);

            $('.puzzle-board').css({
                'width': `${boardW}px`,
                'height': `${boardH}px`
            });

            // Parçaları ve üzerlerindeki metinleri ölçeklendirerek yerleştirir
            Object.keys(userCoords).forEach(id => {
                const coords = userCoords[id];
                const x = (coords.x * scaleFactor) - minX + margin;
                const y = (coords.y * scaleFactor) - minY + margin;

                $(`#piece-${id}`).css({
                    'left': `${x}px`,
                    'top': `${y}px`
                });

                // Metin katmanını ölçeklendirerek çizer
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

            $('.puzzle-piece-wrapper').hover(
                function() {
                    if (isScattered || $(this).hasClass('active')) return;
                    $(this).css({
                        'transform': 'translate(0px, -6px) rotate(0deg)'
                    });
                },
                function() {
                    if (isScattered || $(this).hasClass('active')) return;
                    $(this).css({
                        'transform': 'translate(0px, 0px) rotate(0deg)'
                    });
                }
            );
        });
    </script>
</body>
</html>