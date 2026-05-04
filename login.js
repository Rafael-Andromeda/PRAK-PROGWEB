// login.js — Kindnesia Login Handler

(function () {
  'use strict';

  let currentRole = 'donatur';

  const loginBtn   = document.getElementById('loginBtn');
  const usernameEl = document.querySelector('.input-group input[type="text"]');
  const passwordEl = document.querySelector('.input-group input[type="password"]');

  // ── Role toggle ───────────────────────────────────────────────
  window.setRole = function (role, btn) {
    currentRole = role;
    document.querySelectorAll('.role-toggle button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loginBtn.textContent = role === 'donatur'
      ? 'Masuk sebagai Donatur'
      : 'Masuk sebagai Pengelola';
  };

  // ── Pesan UI ──────────────────────────────────────────────────
  function removeMsg() {
    const old = document.getElementById('loginMsg');
    if (old) old.remove();
  }

  function showMsg(msg, isError) {
    removeMsg();
    const div = document.createElement('div');
    div.id = 'loginMsg';
    div.style.cssText = isError
      ? 'background:#FEF2F2;border:1.5px solid #FCA5A5;color:#DC2626;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:0.88rem;font-weight:500;text-align:left;'
      : 'background:#EFF6FF;border:1.5px solid #38BDF8;color:#1E3A8A;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:0.88rem;font-weight:600;text-align:center;';
    div.textContent = msg;
    loginBtn.parentNode.insertBefore(div, loginBtn);
  }

  function setLoading(on) {
    loginBtn.disabled      = on;
    loginBtn.style.opacity = on ? '0.7' : '1';
    loginBtn.textContent   = on
      ? 'Memproses...'
      : (currentRole === 'donatur' ? 'Masuk sebagai Donatur' : 'Masuk sebagai Pengelola');
  }

  // ── Submit login ──────────────────────────────────────────────
  async function doLogin() {
    const username = usernameEl.value.trim();
    const password = passwordEl.value.trim();

    if (!username) { showMsg('Email wajib diisi.', true); usernameEl.focus(); return; }
    if (!password) { showMsg('Password wajib diisi.', true); passwordEl.focus(); return; }

    setLoading(true);
    removeMsg();

    try {
      const resp = await fetch('login.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ username, password, role: currentRole }),
      });

      if (!resp.ok) {
        const text = await resp.text();
        throw new Error('HTTP ' + resp.status + ': ' + text.substring(0, 300));
      }

      let data;
      try {
        data = await resp.json();
      } catch (_) {
        const text = await resp.clone().text();
        throw new Error('Response bukan JSON: ' + text.substring(0, 300));
      }

      if (data.success) {
        sessionStorage.setItem('kindnesia_user', JSON.stringify(data.user));
        showMsg(data.message, false);
        setTimeout(() => { window.location.href = data.redirect || 'index.html'; }, 800);
      } else {
        showMsg(data.message || 'Login gagal.', true);
        setLoading(false);
        passwordEl.value = '';
        passwordEl.focus();
      }

    } catch (err) {
      console.error('Login error:', err);
      showMsg('Tidak dapat terhubung ke server. Detail: ' + err.message, true);
      setLoading(false);
    }
  }

  loginBtn.addEventListener('click', doLogin);

  // Enter key
  [usernameEl, passwordEl].forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
  });

  // ── Cek jika sudah login ──────────────────────────────────────
  (async function checkLoginStatus() {
    try {
      const resp = await fetch('login.php?check=1');
      if (!resp.ok) return;
      const data = await resp.json();
      if (data.logged_in) {
        window.location.href = data.user.role === 'pengelola' ? 'dashboard.html' : 'index.html';
      }
    } catch (_) { /* server belum berjalan */ }
  })();

})();
