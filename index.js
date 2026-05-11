// index.js — Kindnesia Index Handler

(function () {
  'use strict';

  // ── Cek session login dari sessionStorage ─────────────────────
  const userData = sessionStorage.getItem('kindnesia_user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      // Update tombol Login di navbar jadi nama user
      const loginBtn = document.querySelector('nav a.login-btn');
      if (loginBtn && user.nama) {
        loginBtn.textContent = '👤 ' + user.nama;
        loginBtn.href = '#';
        loginBtn.title = 'Role: ' + user.role;
      }
    } catch (_) {}
  }

  // ── Filter / Search kampanye ───────────────────────────────────
  const searchInput  = document.querySelector('.filter input[type="text"]');
  const categorySelect = document.querySelector('.filter select');
  const searchBtn    = document.querySelector('.filter button');
  const cards        = document.querySelectorAll('.campaigns .card');

  function filterCards() {
    const keyword  = searchInput ? searchInput.value.toLowerCase() : '';
    const category = categorySelect ? categorySelect.value.toLowerCase() : '';

    cards.forEach(card => {
      const title    = card.querySelector('h3')?.textContent.toLowerCase() || '';
      const org      = card.querySelector('.org')?.textContent.toLowerCase() || '';
      const badge    = card.querySelector('.badge')?.textContent.toLowerCase() || '';

      const matchKeyword  = !keyword  || title.includes(keyword) || org.includes(keyword);
      const matchCategory = !category || category === 'kategori' || badge.includes(category);

      card.style.display = (matchKeyword && matchCategory) ? '' : 'none';
    });
  }

  if (searchBtn) searchBtn.addEventListener('click', filterCards);
  if (searchInput) searchInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') filterCards();
  });

})();
