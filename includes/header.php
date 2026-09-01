<?php
// includes/header.php - Top Bar Header Komponen Reusable
// Memerlukan: $foto_user, $user_display_name, $nav_role
?>
<!-- Backdrop Overlay untuk Mobile Sidebar -->
<div id="navBackdrop" class="nav-backdrop" onclick="closeSidebar()"></div>

<!-- ===== HEADER UTAMA ===== -->
<header class="header-main">
    <div class="header-accent-line"></div>
    <div class="header-content">
        <div style="display: flex; align-items: center; gap: 14px;">
            <button type="button" class="btn-hamburger" onclick="toggleSidebar()" aria-label="Buka Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="header-left">
                <p data-translate="sub_header">SISTEM INFORMASI AKADEMIK</p>
                <h1 data-translate="main_title">PORTAL UTAMA SEKOLAH</h1>
            </div>
        </div>
        
        <div class="header-right">
            <!-- User Badge (Bisa diklik untuk membuka profil) -->
            <a href="profile.php" style="text-decoration: none;">
                <div class="user-badge">
                    <div class="user-avatar">
                        <?php if (!empty($foto_user) && file_exists('uploads/' . $foto_user)): ?>
                            <img src="uploads/<?php echo htmlspecialchars($foto_user); ?>" alt="Foto">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_display_name ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-role"><?php echo htmlspecialchars($user_display_name ?? ''); ?></span>
                        <span class="user-status">● <?php echo htmlspecialchars(ucfirst($nav_role ?? '')); ?></span>
                    </div>
                </div>
            </a>

            <div class="header-date">
                📅 <?php echo date('d M Y'); ?>
            </div>
        </div>
    </div>
</header>
