<?php
// includes/hero_banner.php - Hero Banner dengan Language Switcher & Live Clock
$h_icon = $hero_icon ?? '📋';
$h_title_key = $hero_title_key ?? '';
$h_title_text = $hero_title_text ?? 'Portal Akademik';
$h_desc_key = $hero_desc_key ?? '';
$h_desc_text = $hero_desc_text ?? '';
?>
<div class="hero-banner">
    <div class="hero-left">
        <h2>
            <?php echo $h_icon; ?>
            <span <?php echo $h_title_key ? 'data-translate="' . htmlspecialchars($h_title_key) . '"' : ''; ?>>
                <?php echo htmlspecialchars($h_title_text); ?>
            </span>
        </h2>
        <?php if ($h_desc_text || $h_desc_key): ?>
            <p <?php echo $h_desc_key ? 'data-translate="' . htmlspecialchars($h_desc_key) . '"' : ''; ?>>
                <?php echo htmlspecialchars($h_desc_text); ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="hero-right">
        <!-- Action Button Ganti Bahasa -->
        <div class="hero-lang-box">
            <label for="langSelect" style="font-size: 11.5px; font-weight: 700; color: #94a3b8; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                🌐 <span data-translate="lang_label">Bahasa:</span>
            </label>
            <select id="langSelect" onchange="changeLanguage(this.value)" class="hero-lang-select">
                <option value="id" selected>🇮🇩 Indonesia</option>
                <option value="en">🇬🇧 English</option>
                <option value="jp">🇯🇵 日本語</option>
                <option value="kr">🇰🇷 한국어</option>
            </select>
        </div>

        <div class="hero-badge" data-translate="semester_badge">
            📅 T.A. 2025/2026 Semester Ganjil
        </div>
        <div class="hero-badge live-clock-badge">
            🟢 <span class="live-clock-time">00:00:00</span> WIB
        </div>
    </div>
</div>
