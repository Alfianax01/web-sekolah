<?php
// includes/navbar.php - Navbar Menu / Mobile Sidebar Drawer
// Memerlukan: $nav_role, $active_page
?>
<nav id="navbarMenu" class="navbar-menu">
    <div class="navbar-inner">
        <div class="sidebar-drawer-header">
            <div class="sidebar-brand">🎓 Portal Akademik</div>
            <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup Menu">✕</button>
        </div>
        <ul>
            <li><a href="index.php" class="nav-link <?php echo ($active_page === 'index') ? 'active' : ''; ?>" data-translate="nav_home">Beranda</a></li>
            <?php if ($nav_role !== 'siswa'): ?>
                <li><a href="siswa.php" class="nav-link <?php echo ($active_page === 'siswa') ? 'active' : ''; ?>" data-translate="nav_siswa">Data Siswa</a></li>
                <li><a href="guru.php" class="nav-link <?php echo ($active_page === 'guru') ? 'active' : ''; ?>" data-translate="nav_guru">Data Guru</a></li>
                <li><a href="mapel.php" class="nav-link <?php echo ($active_page === 'mapel') ? 'active' : ''; ?>" data-translate="nav_mapel">Data Mapel</a></li>
                <li><a href="jurusan.php" class="nav-link <?php echo ($active_page === 'jurusan') ? 'active' : ''; ?>" data-translate="nav_jurusan">Data Jurusan</a></li>
                <li><a href="bahan_ajar.php" class="nav-link <?php echo ($active_page === 'bahan_ajar') ? 'active' : ''; ?>" data-translate="nav_bahan_ajar">Bahan Ajar</a></li>
            <?php endif; ?>
            <li><a href="tugas.php" class="nav-link <?php echo ($active_page === 'tugas') ? 'active' : ''; ?>" data-translate="nav_tugas">Tugas</a></li>
            <?php if ($nav_role === 'siswa'): ?>
                <li><a href="pelajaran.php" class="nav-link <?php echo ($active_page === 'pelajaran') ? 'active' : ''; ?>" data-translate="nav_pelajaran">Pelajaran</a></li>
            <?php endif; ?>
            <li><a href="profile.php" class="nav-link <?php echo ($active_page === 'profile') ? 'active' : ''; ?>" data-translate="nav_profile">Profil Saya</a></li>
            <li><a href="logout.php" class="nav-link nav-logout" data-translate="nav_logout">Logout</a></li>
        </ul>
    </div>
</nav>
