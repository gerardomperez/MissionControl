<?php
/**
 * Knowledge Base Library Page
 */
?>

<div class="knowledge-wrapper">
    <!-- Left Sidebar -->
    <aside class="knowledge-sidebar">
        <div class="sidebar-section">
            <h3>Categories</h3>
            <ul class="category-list">
                <li><button class="category-btn active" data-category="all">All</button></li>
                <li><button class="category-btn" data-category="Brand Standards & Voice">Brand Standards & Voice</button></li>
                <li><button class="category-btn" data-category="Client/Project Profiles">Client/Project Profiles</button></li>
                <li><button class="category-btn" data-category="SOPs & Workflows">SOPs & Workflows</button></li>
                <li><button class="category-btn" data-category="General">General</button></li>
                <li><button class="category-btn" data-category="Reference">Reference</button></li>
                <li><button class="category-btn" data-category="Memory">Memory</button></li>
            </ul>
        </div>
        
        <div class="sidebar-section">
            <h3>Search</h3>
            <input type="text" id="kbSearch" class="form-control" placeholder="Search entries...">
        </div>
        
        <div class="sidebar-section">
            <label class="toggle-label">
                <input type="checkbox" id="showArchived">
                <span>Show archived</span>
            </label>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="knowledge-main">
        <!-- Top Bar -->
        <div class="knowledge-header">
            <h1>Library</h1>
            <div class="knowledge-header-actions">
                <div class="view-toggle">
                    <button class="view-btn active" data-view="list" title="List view">☰</button>
                    <button class="view-btn" data-view="grid" title="Grid view">⊞</button>
                </div>
                <button class="btn btn-primary" id="newEntryBtn">+ New Entry</button>
            </div>
        </div>

        <!-- Entry List/Grid Container -->
        <div class="knowledge-content">
            <div id="entriesContainer" class="entries-list">
                <!-- Entries loaded via JS -->
                <div class="loading-state">Loading entries...</div>
            </div>
        </div>

        <!-- Entry Detail/Edit Panel -->
        <div id="entryPanel" class="entry-panel hidden">
            <div class="entry-panel-header">
                <h2 id="panelTitle">Edit Entry</h2>
                <button class="panel-close" id="closePanel">&times;</button>
            </div>
            
            <div class="entry-panel-body">
                <form id="entryForm">
                    <input type="hidden" id="entryId" value="">
                    
                    <div class="form-group">
                        <label for="entryTitle">Title</label>
                        <input type="text" id="entryTitle" class="form-control" placeholder="Entry title...">
                    </div>
                    
                    <div class="form-group">
                        <label for="entrySlug">
                            Slug
                            <button type="button" class="copy-btn" id="copySlug" title="Copy slug">📋</button>
                        </label>
                        <input type="text" id="entrySlug" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="entryCategory">Category</label>
                        <select id="entryCategory" class="form-control">
                            <option value="Brand Standards & Voice">Brand Standards & Voice</option>
                            <option value="Client/Project Profiles">Client/Project Profiles</option>
                            <option value="SOPs & Workflows">SOPs & Workflows</option>
                            <option value="General">General</option>
                            <option value="Reference">Reference</option>
                            <option value="Memory">Memory</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="entryTags">Tags (comma-separated)</label>
                        <input type="text" id="entryTags" class="form-control" placeholder="tag1, tag2, tag3...">
                    </div>
                    
                    <div class="form-group">
                        <label for="entryContent">
                            Content
                            <button type="button" class="preview-toggle" id="previewToggle">Preview</button>
                        </label>
                        <textarea id="entryContent" class="form-control" rows="15" placeholder="Markdown content..."></textarea>
                        <div id="contentPreview" class="content-preview hidden"></div>
                    </div>
                    
                    <div class="entry-metadata" id="entryMetadata">
                        <!-- Populated via JS -->
                    </div>
                    
                    <div class="entry-actions">
                        <button type="submit" class="btn btn-primary" id="saveEntry">Save</button>
                        <button type="button" class="btn btn-outline" id="archiveEntry">Archive</button>
                        <button type="button" class="btn btn-outline" id="exportEntry">Export .md</button>
                        <button type="button" class="btn btn-outline btn-danger" id="deleteEntry">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-backdrop hidden">
    <div class="modal modal--confirm">
        <div class="modal-header">
            <h2>Confirm Delete</h2>
            <button class="modal-close" id="cancelDelete">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this entry? This action cannot be undone.</p>
            <div class="form-actions">
                <button class="btn btn-outline" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-primary btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/knowledge.js?v=<?= filemtime(__DIR__ . '/../public/js/knowledge.js') ?>"></script>
