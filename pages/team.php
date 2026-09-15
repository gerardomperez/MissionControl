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

    <!-- Model Tier Legend -->
    <div class="model-tier-legend">
        <div class="legend-title">AI Model Tiers</div>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-badge tier-1">T1</span>
                <span class="legend-text"><strong>Opus 4.6</strong> — High-stakes (Legal, Strategy)</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge tier-2">T2</span>
                <span class="legend-text"><strong>Sonnet 4.6</strong> — Workhorse (80% of tasks)</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge tier-3">T3</span>
                <span class="legend-text"><strong>Haiku 4.5</strong> — Efficient (Structured tasks)</span>
            </div>
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
