const form = document.getElementById('donasiForm');
const fileInput = document.getElementById('bukti');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const successMsg = document.getElementById('successMsg');

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
const ALLOWED_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

function updateFileLabel() {
    const file = fileInput.files[0];
    if (file) {
        fileNameDisplay.textContent = `${file.name}`;
    } else {
        fileNameDisplay.textContent = 'Pilih file (PDF / JPG / PNG)';
    }
}

function showSuccess(message) {
    successMsg.textContent = message;
    successMsg.style.display = 'block';
    setTimeout(() => {
        successMsg.style.display = 'none';
    }, 5000);
}

function validateFile(file) {
    if (!file) {
        return 'Silakan unggah bukti transfer.';
    }
    if (!ALLOWED_TYPES.includes(file.type)) {
        return 'Format file tidak didukung. Gunakan PDF, JPG, atau PNG.';
    }
    if (file.size > MAX_FILE_SIZE) {
        return 'Ukuran file terlalu besar. Maksimal 5 MB.';
    }
    return '';
}

function buildRequestPayload() {
    const nama = document.getElementById('nama').value.trim();
    const email = document.getElementById('email').value.trim();
    const nominal = document.getElementById('nominal').value;
    const metode = document.getElementById('metode').value;
    const pesan = document.getElementById('pesan').value.trim();
    const bukti = fileInput.files[0];

    const formData = new FormData();
    formData.append('nama', nama);
    formData.append('email', email);
    formData.append('nominal', nominal);
    formData.append('metode', metode);
    formData.append('pesan', pesan);
    formData.append('bukti', bukti);

    return formData;
}

function saveDonasiDraft(data) {
    const draft = {
        nama: data.get('nama'),
        email: data.get('email'),
        nominal: data.get('nominal'),
        metode: data.get('metode'),
        pesan: data.get('pesan'),
        tanggal: new Date().toISOString(),
    };
    localStorage.setItem('draftDonasi', JSON.stringify(draft));
}

function clearDraft() {
    localStorage.removeItem('draftDonasi');
}

function restoreDraft() {
    const draftString = localStorage.getItem('draftDonasi');
    if (!draftString) return;

    try {
        const draft = JSON.parse(draftString);
        if (draft.nama) document.getElementById('nama').value = draft.nama;
        if (draft.email) document.getElementById('email').value = draft.email;
        if (draft.nominal) document.getElementById('nominal').value = draft.nominal;
        if (draft.metode) document.getElementById('metode').value = draft.metode;
        if (draft.pesan) document.getElementById('pesan').value = draft.pesan;
    } catch (error) {
        console.warn('Gagal memulihkan draft donasi:', error);
    }
}

function submitToBackend(formData) {
    return fetch('api/donasi.php', {
        method: 'POST',
        body: formData,
    })
    .then((response) => {
        if (!response.ok) {
            throw new Error('Terjadi kesalahan saat mengirim donasi.');
        }
        return response.json();
    });
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const buktiFile = fileInput.files[0];
    const validationError = validateFile(buktiFile);
    if (validationError) {
        alert(validationError);
        return;
    }

    const payload = buildRequestPayload();
    saveDonasiDraft(payload);

    try {
        // Jika belum ada backend/database, gunakan fallback lokal.
        const response = await submitToBackend(payload);
        showSuccess(response.message || 'Donasi Anda berhasil dikirim!');
        form.reset();
        fileNameDisplay.textContent = 'Pilih file (PDF / JPG / PNG)';
        clearDraft();
    } catch (error) {
        console.warn('Backend belum tersedia, menyimpan data mock di browser.', error);
        saveDonasiDraft(payload);
        showSuccess('Donasi berhasil disimpan sementara. Backend belum aktif.');
    }
});

fileInput.addEventListener('change', updateFileLabel);
window.addEventListener('DOMContentLoaded', restoreDraft);
