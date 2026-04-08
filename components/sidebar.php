<?php
// Şu anki sayfanın dosya adını al
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="gc_logo_small">
    </div>

    <ul class="nav-links">

        <?php if (isset($_SESSION['role'])): ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li>
                    <a href="/gamecenter/pages/admin_dashboard.php" class="<?= $currentPage == 'admin_dashboard.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-shield"></i>
                        <span>Admin Dashboard</span>
                    </a>
                </li>
            <?php elseif ($_SESSION['role'] === 'developer'): ?>
                <li>
                    <a href="/gamecenter/pages/game_create.php" class="<?= $currentPage == 'game_create.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-plus"></i>
                        <span>Create Game</span>
                    </a>
                </li>
                <li>
                    <a href="/gamecenter/pages/dev_dashboard.php" class="<?= $currentPage == 'dev_dashboard.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-cogs"></i>
                        <span>Dev Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>
        
        <li>
            <a href="/gamecenter/index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>
                <span>Store</span>
            </a>
        </li>
        <li>
            <a href="/gamecenter/pages/game_library.php" class="<?= $currentPage == 'game_library.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gamepad"></i>
                <span>Library</span>
            </a>
        </li>
        <li>
            <a href="/gamecenter/pages/settings.php" class="<?= $currentPage == 'settings.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>
        </li>
        <li class="logout-link">
            <a href="/gamecenter/functions/auth/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>