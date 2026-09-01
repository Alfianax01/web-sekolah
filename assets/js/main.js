// ============================================================
// MAIN.JS - Portal Akademik Sekolah Shared Scripts
// ============================================================

// ===== LIVE DIGITAL CLOCK =====
function updateLiveClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeStr = `${hours}:${minutes}:${seconds}`;

    document.querySelectorAll('.live-clock-time, #liveDigitalClock, .header-live-clock').forEach(el => {
        el.innerText = timeStr;
    });

    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const dateStr = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;

    document.querySelectorAll('.live-clock-date, #liveDigitalDate').forEach(el => {
        el.innerText = dateStr;
    });
}

// ===== MOBILE SIDEBAR DRAWER =====
function toggleSidebar() {
    const menu = document.getElementById('navbarMenu');
    const backdrop = document.getElementById('navBackdrop');
    if (menu) menu.classList.toggle('active');
    if (backdrop) backdrop.classList.toggle('active');
}

function closeSidebar() {
    const menu = document.getElementById('navbarMenu');
    const backdrop = document.getElementById('navBackdrop');
    if (menu) menu.classList.remove('active');
    if (backdrop) backdrop.classList.remove('active');
}

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
        // Also close any active modals
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// ===== MULTILANGUAGE SWITCHER =====
function changeLanguage(lang) {
    if (typeof translations === 'undefined' || !translations[lang]) return;

    const elements = document.querySelectorAll('[data-translate]');
    elements.forEach(el => {
        const key = el.getAttribute('data-translate');
        if (translations[lang][key] !== undefined) {
            if (el.tagName === 'INPUT' && el.getAttribute('placeholder') !== null) {
                el.placeholder = translations[lang][key];
            } else {
                el.innerText = translations[lang][key];
            }
        }
    });

    // Save preference
    localStorage.setItem('selected_lang', lang);

    // Sync all dropdowns on current page
    document.querySelectorAll('.hero-lang-select, #langSelect').forEach(sel => {
        sel.value = lang;
    });
}

// ===== UNIVERSAL MODAL HELPERS =====
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

// ===== DOM INITIALIZATION =====
window.addEventListener('DOMContentLoaded', () => {
    // Start Live Clock
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Apply Saved Language
    const savedLang = localStorage.getItem('selected_lang') || 'id';
    document.querySelectorAll('.hero-lang-select, #langSelect').forEach(sel => {
        sel.value = savedLang;
    });
    changeLanguage(savedLang);
});
