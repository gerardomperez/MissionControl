<?php /* Team — Subagent Roster */ ?>

<div class="team-wrapper">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>The Team</h1>
            <p class="subtitle" id="teamSubtitle">Henry's subagent fleet</p>
        </div>
        <div class="filter-pills" id="filterPills">
            <button class="filter-pill filter-pill--active" data-filter="all">All</button>
            <button class="filter-pill" data-filter="active">Active</button>
            <button class="filter-pill" data-filter="standby">Standby</button>
            <button class="filter-pill" data-filter="idle">Idle</button>
            <button class="filter-pill" data-filter="offline">Offline</button>
        </div>
    </div>

    <div class="team-content">

        <!-- Henry — featured row -->
        <div id="henryRow" class="henry-row"></div>

        <!-- Subagent section label -->
        <div id="subagentLabel" class="team-section-label">Subagent Fleet</div>

        <!-- Subagent grid -->
        <div class="team-grid" id="teamGrid">
            <div class="team-loading">Loading agents…</div>
        </div>

    </div>

</div>
