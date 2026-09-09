<?php
require_once __DIR__ . "/../config/functions.php";
$isSubdir = (basename(dirname($_SERVER["PHP_SELF"])) !== "HM-Cafe2" && basename(dirname($_SERVER["PHP_SELF"])) !== "htdocs");
$rootPath = $isSubdir ? "../" : "";
?>
<header class="header">
    <a href="<?= $rootPath ?>index.php#home" class="brand">
        <img src="<?= $rootPath ?>assets/logo.png" alt="HM Café Logo">
        <span><strong>HM <em>Café</em></strong><small>COFFEE CONNECT ENJOY</small></span>
    </a>
    <nav class="main-nav" id="mainNav">
        <a href="<?= $rootPath ?>index.php#home">HOME</a>
        <a href="<?= $rootPath ?>index.php#menu">MENU</a>
        <a href="<?= $rootPath ?>index.php#about">ABOUT US</a>
        <a href="<?= $rootPath ?>index.php#gallery">GALLERY</a>
        <a href="<?= $rootPath ?>index.php#events">EVENTS</a>
        <a href="<?= $rootPath ?>index.php#contact">CONTACT</a>
    </nav>
    <div class="nav-account">
        <?php if (is_logged_in()): ?>
            <a href="<?= $rootPath ?>profile.php" class="welcome-user" style="text-decoration:none; color:#00d414; margin-right:10px;">
                👤 <?= e($_SESSION["username"]) ?>
            </a>
            <?php if (is_admin()): ?>
                <a href="<?= $rootPath ?>admin/dashboard.php" class="order" style="border-color:#ffd700; color:#ffd700; margin-right:8px; padding:6px 12px; font-size:11px;">⚡ ADMIN</a>
            <?php endif; ?>
            <a href="<?= $rootPath ?>shop.php" class="order" style="margin-right:8px;">☕ ORDER NOW</a>
            <form action="<?= $rootPath ?>logout.php" method="post" class="logout-form" style="display:inline; margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="order logout-button" style="padding:8px 12px; font-size:11px;">LOGOUT</button>
            </form>
        <?php else: ?>
            <a href="<?= $rootPath ?>shop.php" class="order">☕ ORDER NOW</a>
        <?php endif; ?>
    </div>
    <button class="hamburger" id="hamburger" type="button" aria-label="Open navigation menu" aria-expanded="false">☰</button>
</header>
