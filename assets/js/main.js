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
    1024: { slidesPerView: 4, spaceBetween: 25 },
    1400: { slidesPerView: 5, spaceBetween: 25 },
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
