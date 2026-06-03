// index.js — Interaksi halaman utama Kindnesia
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('input[name="q"]');
    const searchForm = document.querySelector('.filter form');

    searchInput?.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchForm?.submit();
      }
    });
  });
})();
