// dashboard.js — Interaksi Dashboard Pengelola Kindnesia
(function () {
  'use strict';

  window.showTab = function (tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab)?.classList.add('active');
    btn?.classList.add('active');
  };

  window.openModal = function (id) {
    document.getElementById(id)?.classList.add('open');
  };

  window.closeModal = function (id) {
    document.getElementById(id)?.classList.remove('open');
  };

  window.openEdit = function (id, data) {
    document.getElementById('editId').value = id;
    document.getElementById('editJudul').value = data.judul || '';
    document.getElementById('editKategori').value = data.kategori || '';
    document.getElementById('editLokasi').value = data.lokasi || '';
    document.getElementById('editDeskripsi').value = data.deskripsi || '';
    document.getElementById('editTarget').value = data.target_dana || '';
    document.getElementById('editDeadline').value = data.deadline || '';
    document.getElementById('editMetode').value = data.metode_donasi || '';
    window.openModal('modalEdit');
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(modal => {
      modal.addEventListener('click', event => {
        if (event.target === modal) modal.classList.remove('open');
      });
    });
  });
})();
