const swiper = new Swiper(".swiper", {
  slidesPerView: 1,
  spaceBetween: 20,
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev",
  },
  breakpoints: {
    640: { slidesPerView: 2, spaceBetween: 20 },
    768: { slidesPerView: 3, spaceBetween: 20 },
    992: { slidesPerView: 3, spaceBetween: 20 },  /* Tablet, sidebar hidden: show 3 slides */
    1200: { slidesPerView: 3, spaceBetween: 25 }, /* Desktop, sidebar visible: show 3 slides */
    1500: { slidesPerView: 5, spaceBetween: 25 }, /* Large desktop: show 5 slides */
  },
});

// Hero Section Background Auto Switcher
document.addEventListener("DOMContentLoaded", function () {
  const heroSection = document.getElementById("hero-section");
  const bgImages = window.heroBackgrounds || [];

  if (heroSection && bgImages.length > 0) {
    // Preload images
    bgImages.forEach((bg) => {
      const img = new Image();
      img.src = bg.image;
    });

    let currentIndex = 0;

    setInterval(function () {
      currentIndex = (currentIndex + 1) % bgImages.length;
      heroSection.style.backgroundImage = `url('${bgImages[currentIndex].image}')`;
    }, 5000);
  }
});
