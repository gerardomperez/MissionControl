<?php /* Journal */ ?>

<div class="journal-wrapper">

    <div class="page-header">
        <div class="page-header-left">
            <h1>Journal</h1>
            <p class="subtitle">Quick notes, timestamped</p>
        </div>
    </div>

    <div class="journal-content">
        <form id="journalForm" class="journal-form">
            <textarea id="journalEntry" class="form-control" rows="6" placeholder="Write something..." required></textarea>
            <div class="journal-form-actions">
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>

        <div class="report-list" id="journalList">
            <div class="report-list-loading">Loading entries…</div>
        </div>
    </div>

</div>

<script src="<?= $base ?>/js/journal.js?v=<?= filemtime(__DIR__ . '/../public/js/journal.js') ?>"></script>
