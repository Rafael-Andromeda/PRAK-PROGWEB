// index.js — Interaksi halaman utama Kindnesia
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const scrollToTopBtn = document.getElementById('scrollToTop');
    const header = document.querySelector('header');
    const categoryChips = document.querySelectorAll('.category-chip');
    const searchButton = document.getElementById('searchBtn');
    const searchInput = document.getElementById('campaignSearch');
    const categorySelect = document.getElementById('filterCategory');
    const locationInput = document.getElementById('filterLocation');
    const campaignCards = Array.from(document.querySelectorAll('.campaign-card'));
    const newsletterForm = document.getElementById('newsletterForm');

    function updateScrollButton() {
      if (window.pageYOffset > 300) {
        scrollToTopBtn.classList.add('show');
      } else {
        scrollToTopBtn.classList.remove('show');
      }
    }

    function updateHeader() {
      if (window.pageYOffset > 24) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }

    function filterCampaigns() {
      const keyword = searchInput.value.trim().toLowerCase();
      const category = categorySelect.value;
      const location = locationInput.value.trim().toLowerCase();

      campaignCards.forEach(card => {
        const title = card.dataset.title.toLowerCase();
        const cardCategory = card.dataset.category;
        const cardLocation = card.dataset.location.toLowerCase();

        const matchesKeyword = keyword === '' || title.includes(keyword);
        const matchesCategory = category === '' || cardCategory === category;
        const matchesLocation = location === '' || cardLocation.includes(location);

        if (matchesKeyword && matchesCategory && matchesLocation) {
          card.style.display = 'grid';
        } else {
          card.style.display = 'none';
        }
      });
    }

    function clearChipActive() {
      categoryChips.forEach(chip => chip.classList.remove('active'));
    }

    categoryChips.forEach(chip => {
      chip.addEventListener('click', function () {
        clearChipActive();
        this.classList.add('active');
        categorySelect.value = this.dataset.category;
        filterCampaigns();
      });
    });

    searchButton.addEventListener('click', filterCampaigns);
    searchInput.addEventListener('keyup', filterCampaigns);
    categorySelect.addEventListener('change', function () {
      clearChipActive();
      const activeChip = Array.from(categoryChips).find(chip => chip.dataset.category === this.value);
      if (activeChip) activeChip.classList.add('active');
      filterCampaigns();
    });
    locationInput.addEventListener('keyup', filterCampaigns);

    scrollToTopBtn?.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    function revealOnScroll() {
      const reveals = document.querySelectorAll('.reveal');
      reveals.forEach(item => {
        const rect = item.getBoundingClientRect();
        if (rect.top < window.innerHeight - 80) {
          item.classList.add('visible');
        }
      });
    }

    function initCounters() {
      const metrics = document.querySelectorAll('.metric-card strong');
      metrics.forEach((metric, index) => {
        const text = metric.textContent;
        const value = parseInt(text.replace(/[^0-9]/g, ''), 10) || 0;
        let current = 0;
        const step = Math.max(1, Math.floor(value / 60));

        const interval = setInterval(() => {
          current += step;
          if (current >= value) {
            metric.textContent = text;
            clearInterval(interval);
            return;
          }
          const formatted = current.toLocaleString('id-ID');
          metric.textContent = text.replace(/\d[\d\.]+/, formatted);
        }, 16 + index * 8);
      });
    }

    newsletterForm?.addEventListener('submit', function (event) {
      event.preventDefault();
      const emailInput = this.querySelector('input[type="email"]');
      if (!emailInput.value) return;
      alert(`Terima kasih! Email ${emailInput.value} berhasil didaftarkan.`);
      emailInput.value = '';
    });

    window.addEventListener('scroll', function () {
      updateScrollButton();
      updateHeader();
      revealOnScroll();
    });

    updateScrollButton();
    updateHeader();
    revealOnScroll();
    initCounters();
  });
})();
