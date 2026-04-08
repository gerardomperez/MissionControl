<?php /* Status Log — Agent Reports */ ?>

<div class="statuslog-wrapper">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Status Reports</h1>
            <p class="subtitle">Agent-generated reports and diagnostics</p>
        </div>
    </div>

    <!-- Report List -->
    <div class="statuslog-content">
        <div class="report-list" id="reportList">
            <div class="report-list-loading">Loading reports…</div>
        </div>
    </div>

</div>

<!-- Report Detail Modal -->
<div class="modal-backdrop hidden" id="reportModal">
    <div class="modal modal--report" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
        <div class="modal-header report-modal-header">
            <div class="report-modal-meta">
                <h2 id="reportModalTitle">Report</h2>
                <div class="report-modal-submeta" id="reportModalMeta"></div>
            </div>
            <button class="modal-close" id="reportModalClose" aria-label="Close">&#10005;</button>
        </div>
        <div class="modal-body report-modal-body" id="reportModalBody"></div>
    </div>
</div>
