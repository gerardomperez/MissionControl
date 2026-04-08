<?php
$currentPage = $_GET['page'] ?? 'orchestrator';
$navItems = [
    'orchestrator' => 'Orchestrator',
    'calendar'     => 'Calendar',
    'statuslog'    => 'Status Log',
    'team'         => 'Team',
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
    <link rel="stylesheet" href="<?= $base ?>/css/style.css">
    <script>window.BASE_URL = <?= json_encode($base) ?>;</script>
</head>
<body>

<nav class="topnav">
    <div class="topnav-left">
        <span class="nav-badge">MC</span>
        <span class="nav-title">Mission Control</span>
    </div>
    <div class="topnav-right">
        <?php foreach ($navItems as $key => $label): ?>
            <a href="<?= $base ?>/?page=<?= $key ?>"
               class="nav-link<?= $currentPage === $key ? ' nav-link--active' : '' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<div class="page-wrapper">
