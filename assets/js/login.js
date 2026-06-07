// login.js — Kindnesia Login Handler
(function () {
  'use strict';

  let currentRole = 'donatur';

  const loginBtn = document.getElementById('loginBtn');
  const usernameEl = document.querySelector('.input-group input[type="text"]');
  const passwordEl = document.querySelector('.input-group input[type="password"]');
  const roleToggle = document.querySelector('.role-toggle');
  const roleButtons = document.querySelectorAll('.role-toggle button');

  window.setRole = function (role, btn) {
    currentRole = role;
    roleButtons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loginBtn.textContent = role === 'donatur' ? 'Masuk sebagai Donatur' : 'Masuk sebagai Pengelola';
    roleToggle.classList.toggle('pengelola-active', role === 'pengelola');
  };

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
    loginBtn.disabled = on;
    loginBtn.style.opacity = on ? '0.7' : '1';
    loginBtn.textContent = on
      ? 'Memproses...'
      : (currentRole === 'donatur' ? 'Masuk sebagai Donatur' : 'Masuk sebagai Pengelola');
  }

  function safeRedirect(defaultRedirect) {
    const params = new URLSearchParams(window.location.search);
    const redirect = params.get('redirect');

    if (!redirect) return defaultRedirect;
    if (/^https?:\/\//i.test(redirect) || redirect.includes('://')) return defaultRedirect;
    if (redirect.includes('login') || redirect.includes('logout')) return defaultRedirect;

    // Pengelola tetap diarahkan ke dashboard agar tidak masuk ke halaman donasi.
    if (currentRole === 'pengelola') return 'dashboard.php';

    return redirect;
  }

  async function doLogin() {
    const username = usernameEl.value.trim();
    const password = passwordEl.value.trim();

    if (!username) { showMsg('Username atau email wajib diisi.', true); usernameEl.focus(); return; }
    if (!password) { showMsg('Password wajib diisi.', true); passwordEl.focus(); return; }

    setLoading(true);
    removeMsg();

    try {
      const resp = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password, role: currentRole }),
      });

      const rawText = await resp.text();
      if (!resp.ok) throw new Error('HTTP ' + resp.status + ': ' + rawText.substring(0, 300));

      let data;
      try { data = JSON.parse(rawText); }
      catch (_) { throw new Error('Response bukan JSON. Output PHP: ' + rawText.substring(0, 300)); }

      if (data.success) {
        sessionStorage.setItem('kindnesia_user', JSON.stringify(data.user));
        showMsg(data.message, false);
        const target = safeRedirect(data.redirect || 'index.php');
        setTimeout(() => { window.location.href = target; }, 500);
      } else {
        showMsg(data.message || 'Login gagal.', true);
        setLoading(false);
        passwordEl.value = '';
        passwordEl.focus();
      }
    } catch (err) {
      console.error('Login error:', err);
      showMsg('Login gagal. Detail: ' + err.message, true);
      setLoading(false);
    }
  }

  loginBtn.addEventListener('click', doLogin);
  [usernameEl, passwordEl].forEach(el => {
    el.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
  });
})();
