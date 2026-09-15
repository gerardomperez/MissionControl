<?php
$pageScripts = [
    'orchestrator' => 'kanban.js',
    'calendar'     => 'calendar.js',
    'statuslog'    => 'statuslog.js',
    'team'         => 'team.js',
    'skills'       => 'skills.js',
    'database'     => 'database.js',
];
$currentPage = $_GET['page'] ?? 'orchestrator';
$script = $pageScripts[$currentPage] ?? null;
?>
</div><!-- /.page-wrapper -->

<footer class="site-footer">
    <span>Mission Control — OpenClaw Agent Operations</span>
</footer>

<?php
$base = defined('BASE_URL') ? BASE_URL : '';
if ($script):
    $scriptPath = __DIR__ . '/../public/js/' . $script;
    $ver = file_exists($scriptPath) ? filemtime($scriptPath) : time();
?>
<script src="<?= $base ?>/js/<?= htmlspecialchars($script) ?>?v=<?= $ver ?>"></script>
<?php endif; ?>
</body>
</html>
