<?php /* Database Browser */ ?>

<div class="db-wrapper">

    <!-- Sidebar: table list -->
    <aside class="db-sidebar">
        <div class="db-sidebar-header">
            <h2>Tables</h2>
        </div>
        <ul class="db-table-list" id="tableList">
            <li class="db-table-item loading">Loading…</li>
        </ul>
    </aside>

    <!-- Main panel -->
    <main class="db-main">
        <div class="db-main-header">
            <div class="db-main-title">
                <h1 id="dbTableTitle">Database</h1>
                <span class="db-row-count" id="dbRowCount"></span>
            </div>
            <div class="db-main-actions">
                <button class="btn btn-outline" id="dbSchemaToggle" disabled>Schema</button>
            </div>
        </div>

        <!-- Schema panel (hidden by default) -->
        <div class="db-schema-panel hidden" id="dbSchemaPanel">
            <table class="db-schema-table">
                <thead>
                    <tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th><th>PK</th></tr>
                </thead>
                <tbody id="dbSchemaBody"></tbody>
            </table>
        </div>

        <!-- Data grid -->
        <div class="db-grid-wrap" id="dbGridWrap">
            <div class="db-empty-state" id="dbEmptyState">
                <p>Select a table to browse its data.</p>
            </div>
            <div class="db-table-scroll hidden" id="dbTableScroll">
                <table class="db-data-table" id="dbDataTable">
                    <thead id="dbDataHead"></thead>
                    <tbody id="dbDataBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="db-pagination hidden" id="dbPagination">
            <button class="btn btn-outline btn-sm" id="dbPrevPage" disabled>← Prev</button>
            <span class="db-page-info" id="dbPageInfo"></span>
            <button class="btn btn-outline btn-sm" id="dbNextPage" disabled>Next →</button>
        </div>
    </main>

</div>
