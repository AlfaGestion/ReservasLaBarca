(function () {
    'use strict';
    const carousel = document.querySelector('[data-player-carousel]');
    if (!carousel) return;
    const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
    const dots = Array.from(carousel.querySelectorAll('[data-carousel-dot]'));
    const previous = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Math.max(3000, Number(carousel.dataset.interval || 5000));
    let current = 0;
    let timer = null;
    let paused = reducedMotion.matches;
    function show(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach(function (slide, slideIndex) { const active = slideIndex === current; slide.classList.toggle('is-active', active); slide.setAttribute('aria-hidden', active ? 'false' : 'true'); });
        dots.forEach(function (dot, dotIndex) { const active = dotIndex === current; dot.classList.toggle('is-active', active); dot.setAttribute('aria-selected', active ? 'true' : 'false'); });
    }
    function stopTimer() { if (timer !== null) window.clearInterval(timer); timer = null; }
    function startTimer() { stopTimer(); if (!paused && slides.length > 1) timer = window.setInterval(function () { show(current + 1); }, interval); }
    previous?.addEventListener('click', function () { show(current - 1); startTimer(); });
    next?.addEventListener('click', function () { show(current + 1); startTimer(); });
    dots.forEach(function (dot) { dot.addEventListener('click', function () { show(Number(dot.dataset.index || 0)); startTimer(); }); });
    reducedMotion.addEventListener?.('change', function (event) { paused = event.matches; startTimer(); });
    startTimer();
}());
