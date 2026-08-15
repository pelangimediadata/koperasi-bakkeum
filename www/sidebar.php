<!-- sidebar.php -->

<!-- Tombol Header & Overlay Mobile (Diposisikan secara independen) -->
<div id="mobileWrapperContainer">
    <div class="mobile-floating-header">
        <button type="button" class="btn-floating-toggle" onclick="toggleFullMenu()">☰ MENU</button>
        <div class="brand-mobile-text">🏢 KELOLA KOPERASI</div>
    </div>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay">
        <div class="overlay-header">
            <span>🏢 KELOLA KOPERASI</span>
            <button type="button" class="btn-close-overlay" onclick="toggleFullMenu()">✕</button>
        </div>
        <ul class="overlay-menu-list">
            <li><a href="dashboard.php">🏠 Dashboard</a></li>
            <li class="has-mobile-sub">
                <a href="#" onclick="toggleMobileSub(event, this)">💰 Simpan Pinjam</a>
                <ul class="mobile-sub-list">
                    <li><a href="anggota.php">Anggota</a></li>
                    <li><a href="simpanan.php">Simpanan</a></li>
                    <li><a href="pinjaman.php">Pinjaman</a></li>
                    <li><a href="bayar.php">Pembayaran</a></li>
                </ul>
            </li>
            <li><a href="toko.php">🛍️ Kelola Toko</a></li>
            <li><a href="operasional.php">📊 Anggaran Operasional</a></li>
            <li class="has-mobile-sub">
                <a href="#" onclick="toggleMobileSub(event, this)">📊 Laporan</a>
                <ul class="mobile-sub-list">
                    <li><a href="laporan.php">Laporan Anggota</a></li>
                    <li><a href="laporan_kas.php">Laporan Kas</a></li>
                    <li><a href="laporan_shu.php">SHU Anggota</a></li>
                </ul>
            </li>
            <?php 
            $is_admin = false;
            if (isset($_SESSION['role']) && strtoupper(trim($_SESSION['role'])) === 'ADMIN') {
                $is_admin = true;
            }
            if ($is_admin): 
            ?>
            <li><a href="users.php">👥 Pengaturan User</a></li>
            <?php endif; ?>
            <li><a href="logout.php" style="color: #ff6b6b !important;">🚪 Logout</a></li>
        </ul>
    </div>
</div>

<!-- Sidebar Asli (Hanya tampil di Desktop/Laptop) -->
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <span class="brand-icon">🏢</span>
            <span class="menu-text" style="font-weight: bold; font-size: 16px;">KELOLA KOPERASI</span>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="nav-link">
                    <span class="icon">🏠</span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>

            <li class="has-dropdown">
                <a href="#" class="nav-link" onclick="toggleDropdown(event, this)">
                    <span class="icon">💰</span>
                    <span class="menu-text">Simpan Pinjam</span>
                    <span class="arrow">▼</span>
                </a>
                <ul class="submenu">
                    <li><a href="anggota.php">Anggota</a></li>
                    <li><a href="simpanan.php">Simpanan</a></li>
                    <li><a href="pinjaman.php">Pinjaman</a></li>
                    <li><a href="bayar.php">Pembayaran</a></li>
                </ul>
            </li>

            <li>
                <a href="toko.php" class="nav-link">
                    <span class="icon">🛍️</span>
                    <span class="menu-text">Kelola Toko</span>
                </a>
            </li>
            
            <li>
                <a href="operasional.php" class="nav-link">
                    <span class="icon">📊</span>
                    <span class="menu-text">Anggaran Operasional</span>
                </a>
            </li>

            <li class="has-dropdown">
                <a href="#" class="nav-link" onclick="toggleDropdown(event, this)">
                    <span class="icon">📊</span>
                    <span class="menu-text">Laporan</span>
                    <span class="arrow">▼</span>
                </a>
                <ul class="submenu">
                    <li><a href="laporan.php">Laporan Anggota</a></li>
                    <li><a href="laporan_kas.php">Laporan Kas</a></li>
                    <li><a href="laporan_shu.php">SHU Anggota</a></li>
                </ul>
            </li>

            <?php if ($is_admin): ?>
            <li>
                <a href="users.php" class="nav-link">
                    <span class="icon">👥</span>
                    <span class="menu-text">Pengaturan User</span>
                </a>
            </li>
            <?php endif; ?>

            <li>
                <a href="logout.php" class="nav-link text-danger">
                    <span class="icon">🚪</span>
                    <span class="menu-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</aside>

<style>
/* ==========================================================
   STYLE KHUSUS MOBILE / HP (<= 768px)
   ========================================================== */
@media screen and (max-width: 768px) {
    /* Sembunyikan sidebar bawaan layout desktop */
    #mainSidebar.sidebar {
        display: none !important;
    }

    /* Bar Header Tombol Menu di HP */
    .mobile-floating-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        background: #004d40 !important;
        color: #ffffff !important;
        padding: 12px 16px !important;
        width: 100% !important;
        box-sizing: border-box !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 99998 !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
    }

    .brand-mobile-text {
        font-weight: bold;
        font-size: 15px;
    }

    .btn-floating-toggle {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        border: none !important;
        padding: 6px 12px !important;
        border-radius: 6px !important;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
    }

    /* Berikan jarak pada body agar konten utama tidak tertutup header fixed */
    body {
        padding-top: 55px !important;
    }

    /* Fullscreen Overlay Menu */
    .mobile-menu-overlay {
        position: fixed !important;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: #004d40;
        z-index: 999999;
        display: none;
        flex-direction: column;
        padding: 20px;
        box-sizing: border-box;
        overflow-y: auto;
    }

    .mobile-menu-overlay.active {
        display: flex !important;
    }

    .overlay-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #ffffff;
        font-weight: bold;
        font-size: 18px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .btn-close-overlay {
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        font-size: 16px;
        cursor: pointer;
        font-weight: bold;
    }

    .overlay-menu-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .overlay-menu-list li a {
        display: block;
        color: #ffffff;
        text-decoration: none;
        padding: 12px 15px;
        background: rgba(255,255,255,0.08);
        border-radius: 8px;
        font-size: 15px;
    }

    .overlay-menu-list li a:hover {
        background: rgba(255,255,255,0.2);
    }

    .mobile-sub-list {
        list-style: none;
        padding-left: 20px;
        margin-top: 6px;
        display: none;
        flex-direction: column;
        gap: 6px;
    }

    .has-mobile-sub.active .mobile-sub-list {
        display: flex !important;
    }

    .mobile-sub-list li a {
        background: rgba(0,0,0,0.2) !important;
        font-size: 14px !important;
        color: #e2e8f0 !important;
    }
}

/* ==========================================================
   STYLE KHUSUS DESKTOP / LAPTOP (> 768px)
   ========================================================== */
@media screen and (min-width: 769px) {
    .mobile-floating-header,
    .mobile-menu-overlay {
        display: none !important;
    }

    #mainSidebar.sidebar {
        width: 75px !important;
        min-width: 75px !important;
        max-width: 75px !important;
        height: 100vh !important;
        position: sticky !important;
        top: 0 !important;
        left: unset !important;
        padding: 20px 10px !important;
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(12px);
        border-right: 1px solid rgba(255, 255, 255, 0.2);
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        box-shadow: 2px 0 15px rgba(0,0,0,0.1) !important;
        transition: width 0.3s ease;
        overflow-x: hidden;
        overflow-y: auto;
        z-index: 100;
    }

    #mainSidebar.sidebar:hover {
        width: 270px !important;
        min-width: 270px !important;
        max-width: 270px !important;
    }

    #mainSidebar .sidebar-brand {
        display: flex !important;
        align-items: center;
        gap: 12px;
        padding: 10px 10px 20px 10px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 15px;
    }

    #mainSidebar .sidebar-menu {
        display: flex !important;
        flex-direction: column !important;
        gap: 6px;
        margin: 0 !important;
        list-style: none !important;
        padding: 0 !important;
    }

    #mainSidebar .nav-link {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        background: transparent !important;
        color: #1e293b !important;
        padding: 10px 12px !important;
        border-radius: 8px;
        text-decoration: none !important;
    }

    #mainSidebar .nav-link:hover {
        background-color: rgba(0, 121, 107, 0.1) !important;
        color: #00796b !important;
    }

    #mainSidebar .menu-text,
    #mainSidebar .arrow {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease;
        color: inherit !important;
    }

    #mainSidebar.sidebar:hover .menu-text,
    #mainSidebar.sidebar:hover .arrow {
        opacity: 1 !important;
        visibility: visible !important;
    }

    #mainSidebar .icon {
        font-size: 18px !important;
        min-width: 24px !important;
        text-align: center !important;
    }

    #mainSidebar .arrow {
        margin-left: auto !important;
        font-size: 10px !important;
    }

    #mainSidebar .submenu {
        list-style: none !important;
        display: none !important;
        flex-direction: column !important;
        padding-left: 36px !important;
        padding-top: 4px !important;
        padding-bottom: 4px !important;
        margin-top: 4px !important;
    }

    #mainSidebar.sidebar:hover .has-dropdown.active .submenu {
        display: flex !important;
    }

    #mainSidebar .submenu li a {
        color: #64748b !important;
        padding: 6px 0;
        display: block;
        text-decoration: none;
        font-size: 14px;
    }

    #mainSidebar .submenu li a:hover {
        color: #00796b !important;
    }
}
</style>

<script>
/* Script JavaScript untuk Memindahkan Menu ke Luar Kontainer Pembatas Layout */
document.addEventListener("DOMContentLoaded", function() {
    if (window.innerWidth <= 768) {
        let wrapper = document.getElementById('mobileWrapperContainer');
        if (wrapper) {
            document.body.prepend(wrapper);
        }
    }
});

function toggleFullMenu() {
    let overlay = document.getElementById('mobileMenuOverlay');
    overlay.classList.toggle('active');
}

function toggleMobileSub(e, element) {
    e.preventDefault();
    let parent = element.parentElement;
    parent.classList.toggle('active');
}

function toggleDropdown(e, element) {
    e.preventDefault();
    let parent = element.parentElement;
    document.querySelectorAll('.has-dropdown').forEach(item => {
        if (item !== parent) item.classList.remove('active');
    });
    parent.classList.toggle('active');
}
</script>