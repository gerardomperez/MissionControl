<?php
$currentPage = $_GET['page'] ?? 'orchestrator';
$navItems = [
    'orchestrator' => 'Orchestrator',
    'calendar'     => 'Calendar',
    'statuslog'    => 'Status Log',
    'team'         => 'Team',
    'skills'       => 'Skills',
    'knowledge'    => 'Library',
    'journal'      => 'Journal',
    'database'     => 'Database',
];
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Control — <?= htmlspecialchars($navItems[$currentPage] ?? 'Dashboard') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <?php $cssVer = filemtime(__DIR__ . '/../public/css/style.css'); ?>
    <link rel="stylesheet" href="<?= $base ?>/css/style.css?v=<?= $cssVer ?>">
    <script>window.BASE_URL = <?= json_encode($base) ?>;</script>
</head>
<body>

<nav class="topnav">
    <div class="topnav-left">
        <span class="nav-badge">MC</span>
        <span class="nav-title">Mission Control</span>
    </div>
    <button class="nav-hamburger" id="navHamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="topnavRight">
        <span></span><span></span><span></span>
    </button>
    <div class="topnav-right" id="topnavRight">
        <?php foreach ($navItems as $key => $label): ?>
            <a href="<?= $base ?>/?page=<?= $key ?>"
               class="nav-link<?= $currentPage === $key ? ' nav-link--active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
<script>
(function(){
    var btn  = document.getElementById('navHamburger');
    var menu = document.getElementById('topnavRight');
    btn.addEventListener('click', function(){
        var open = menu.classList.toggle('topnav-right--open');
        btn.classList.toggle('nav-hamburger--open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // Close when a link is tapped
    menu.addEventListener('click', function(e){
        if (e.target.classList.contains('nav-link')) {
            menu.classList.remove('topnav-right--open');
            btn.classList.remove('nav-hamburger--open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>

<div class="page-wrapper">
