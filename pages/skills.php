<?php /* Skills — Skill Registry */ ?>

<div class="skills-wrapper">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>Skills Registry</h1>
            <p class="subtitle">Available skills and capabilities</p>
        </div>
        <div class="page-header-right">
            <div class="skills-filter-wrap">
                <label for="skillsFilter" class="skills-filter-label">Show:</label>
                <select id="skillsFilter" class="skills-filter-select">
                    <option value="all">All Skills</option>
                    <option value="active">Active</option>
                    <option value="unknown">Unknown / Placeholder</option>
                </select>
                <span id="skillsCount" class="skills-count"></span>
            </div>
        </div>
    </div>

    <!-- Skills List -->
    <div class="skills-content">
        <div class="skills-list" id="skillsList">
            <div class="skills-list-loading">Loading skills…</div>
        </div>
    </div>

</div>

<!-- Skill Detail Modal -->
<div class="modal-backdrop hidden" id="skillModal">
    <div class="modal modal--skill" role="dialog" aria-modal="true" aria-labelledby="skillModalTitle">
        <div class="modal-header skill-modal-header">
            <h2 id="skillModalTitle">Skill</h2>
            <button class="modal-close" id="skillModalClose" aria-label="Close">&#10005;</button>
        </div>
        <div class="modal-body skill-modal-body">
            <div id="skillModalContent" class="skill-modal-content"></div>
        </div>
    </div>
</div>