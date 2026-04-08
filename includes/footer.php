<?php
$pageScripts = [
    'orchestrator' => 'kanban.js',
    'calendar'     => 'calendar.js',
    'statuslog'    => 'statuslog.js',
    'team'         => 'team.js',
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
if ($script): ?>
<script src="<?= $base ?>/js/<?= htmlspecialchars($script) ?>"></script>
<?php endif; ?>
</body>
</html>
