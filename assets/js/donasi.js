// donasi.js — Validasi dan interaksi halaman donasi Kindnesia
(function () {
  'use strict';

  const MAX_FILE_SIZE = 5 * 1024 * 1024;
  const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

  window.setNominal = function (val, el) {
    const nominalInput = document.getElementById('nominalInput');
    if (nominalInput) nominalInput.value = val;
    document.querySelectorAll('.nominal-btn').forEach(btn => btn.classList.remove('active'));
    el?.classList.add('active');
  };

  window.clearNominalBtn = function () {
    document.querySelectorAll('.nominal-btn').forEach(btn => btn.classList.remove('active'));
  };

  function getExtension(filename) {
    return filename.split('.').pop().toLowerCase();
  }

  function updateFileLabel(fileInput) {
    const label = document.getElementById('fileNameDisplay');
    if (!label) return;
    label.textContent = fileInput.files[0]?.name || 'Pilih file (JPG / PNG / PDF)';
  }

  function validateDonationForm(event) {
    const nominalInput = document.getElementById('nominalInput');
    const fileInput = document.getElementById('bukti');
    const nominal = parseInt(nominalInput?.value || '0', 10);

    if (!nominal || nominal < 10000) {
      event.preventDefault();
      alert('Nominal donasi minimal Rp 10.000');
      nominalInput?.focus();
      return;
    }

    const file = fileInput?.files?.[0];
    if (!file) {
      event.preventDefault();
      alert('Bukti transfer wajib diunggah.');
      fileInput?.focus();
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      event.preventDefault();
      alert('Ukuran bukti transfer maksimal 5 MB.');
      fileInput.value = '';
      updateFileLabel(fileInput);
      return;
    }

    if (!ALLOWED_EXT.includes(getExtension(file.name))) {
      event.preventDefault();
      alert('Format bukti transfer harus JPG, PNG, atau PDF.');
      fileInput.value = '';
      updateFileLabel(fileInput);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('donasiForm');
    const fileInput = document.getElementById('bukti');

    form?.addEventListener('submit', validateDonationForm);
    fileInput?.addEventListener('change', function () { updateFileLabel(fileInput); });
  });
})();
