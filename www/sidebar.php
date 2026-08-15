<!-- sidebar.php -->
<aside class="sidebar">
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

            <!-- MENU PENGATURAN USER (HANYA MUNCUL JIKA ROLE BENAR-BENAR ADMIN) -->
            <?php 
            $is_admin = false;
            if (isset($_SESSION['role']) && strtoupper(trim($_SESSION['role'])) === 'ADMIN') {
                $is_admin = true;
            }
            if ($is_admin): 
            ?>
            <li>
                <a href="users.php" class="nav-link">
                    <span class="icon">👥</span>
                    <span class="menu-text">Pengaturan User</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <a href="logout.php" class="nav-link text-danger">
            <span class="icon">🚪</span>
            <span class="menu-text">Logout</span>
        </a>
    </div>
</aside>

<style>
.sidebar {
    width: 75px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(12px);
    border-right: 1px solid rgba(255, 255, 255, 0.2);
    padding: 20px 10px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 2px 0 15px rgba(0,0,0,0.1);
    transition: width 0.3s ease;
    overflow-x: hidden;
    overflow-y: auto;
    z-index: 100;
    height: 100vh;
    position: sticky;
    top: 0;
}

.sidebar:hover {
    width: 270px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 10px 20px 10px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 15px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sidebar-menu li {
    width: 100%;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    color: #1e293b;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
    white-space: nowrap;
    width: 100%;
    box-sizing: border-box;
}

.sidebar .nav-link:hover {
    background-color: rgba(0, 121, 107, 0.1);
    color: #00796b;
}

.sidebar .icon {
    font-size: 18px;
    min-width: 24px;
    text-align: center;
}

.sidebar .menu-text,
.sidebar .arrow {
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease;
    white-space: nowrap;
    font-size: 14px;
}

.sidebar:hover .menu-text,
.sidebar:hover .arrow {
    opacity: 1;
    visibility: visible;
}

.sidebar .arrow {
    margin-left: auto;
    font-size: 10px;
}

.sidebar .submenu {
    list-style: none;
    padding-left: 36px;
    margin: 4px 0 0 0;
    display: none;
    flex-direction: column;
    gap: 6px;
}

.sidebar:hover .has-dropdown.active .submenu {
    display: flex;
}

.sidebar .submenu li a {
    font-size: 13px;
    color: #64748b;
    text-decoration: none;
    padding: 4px 0;
    display: block;
    white-space: nowrap;
    transition: color 0.2s;
}

.sidebar .submenu li a:hover {
    color: #00796b;
}
</style>

<script>
function toggleDropdown(e, element) {
    e.preventDefault();
    let parent = element.parentElement;
    document.querySelectorAll('.has-dropdown').forEach(item => {
        if (item !== parent) item.classList.remove('active');
    });
    parent.classList.toggle('active');
}
</script>