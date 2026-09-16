/**
 * Knowledge Base Library - Frontend Logic
 */

(function() {
    'use strict';

    // State
    let allEntries = [];
    let currentCategory = 'all';
    let currentSearch = '';
    let currentView = localStorage.getItem('mc_kb_view') || 'list';
    let showArchived = false;
    let currentEntry = null;
    let slugDebounceTimer = null;

    // DOM Elements
    const entriesContainer = document.getElementById('entriesContainer');
    const entryPanel = document.getElementById('entryPanel');
    const categoryBtns = document.querySelectorAll('.category-btn');
    const searchInput = document.getElementById('kbSearch');
    const showArchivedCheckbox = document.getElementById('showArchived');
    const viewBtns = document.querySelectorAll('.view-btn');
    const newEntryBtn = document.getElementById('newEntryBtn');
    const closePanelBtn = document.getElementById('closePanel');
    const entryForm = document.getElementById('entryForm');
    const previewToggle = document.getElementById('previewToggle');
    const contentPreview = document.getElementById('contentPreview');
    const entryContent = document.getElementById('entryContent');
    const entryTitle = document.getElementById('entryTitle');
    const entrySlug = document.getElementById('entrySlug');
    const copySlugBtn = document.getElementById('copySlug');
    const archiveEntryBtn = document.getElementById('archiveEntry');
    const deleteEntryBtn = document.getElementById('deleteEntry');
    const exportEntryBtn = document.getElementById('exportEntry');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    const cancelDeleteBtns = document.querySelectorAll('#cancelDelete, #cancelDeleteBtn');

    // Categories for dropdown
    const categories = [
        'Brand Standards & Voice',
        'Client/Project Profiles',
        'SOPs & Workflows',
        'General',
        'Reference',
        'Memory'
    ];

    // Initialize
    function init() {
        loadEntries();
        setupEventListeners();
        updateViewToggle();
    }

    // Load entries from API
    async function loadEntries() {
        try {
            const status = showArchived ? 'archived' : 'active';
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=list&status=${status}`);
            const data = await response.json();
            
            if (data.success) {
                allEntries = data.entries;
                renderEntries();
            } else {
                showError('Failed to load entries');
            }
        } catch (err) {
            showError('Error loading entries: ' + err.message);
        }
    }

    // Render entries based on current filters
    function renderEntries() {
        let filtered = allEntries;

        // Category filter
        if (currentCategory !== 'all') {
            filtered = filtered.filter(e => e.category === currentCategory);
        }

        // Search filter
        if (currentSearch) {
            const search = currentSearch.toLowerCase();
            filtered = filtered.filter(e => 
                e.title.toLowerCase().includes(search) ||
                (e.tags && e.tags.toLowerCase().includes(search)) ||
                (e.excerpt && e.excerpt.toLowerCase().includes(search))
            );
        }

        if (filtered.length === 0) {
            entriesContainer.innerHTML = '<div class="empty-state">No entries found</div>';
            return;
        }

        if (currentView === 'list') {
            renderListView(filtered);
        } else {
            renderGridView(filtered);
        }
    }

    // Render list view
    function renderListView(entries) {
        const html = `
            <table class="entries-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Category</th>
                        <th>Tags</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    ${entries.map(entry => `
                        <tr class="entry-row" data-slug="${escapeHtml(entry.slug)}">
                            <td class="entry-title-cell">${escapeHtml(entry.title)}</td>
                            <td class="entry-slug-cell"><code class="slug-code">${escapeHtml(entry.slug)}</code></td>
                            <td><span class="category-badge">${escapeHtml(entry.category)}</span></td>
                            <td>${renderTags(entry.tags)}</td>
                            <td class="entry-date">${formatDate(entry.updated_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        entriesContainer.innerHTML = html;
        entriesContainer.className = 'entries-list';

        // Add click handlers
        entriesContainer.querySelectorAll('.entry-row').forEach(row => {
            row.addEventListener('click', () => openEntry(row.dataset.slug));
        });
    }

    // Render grid view
    function renderGridView(entries) {
        const html = entries.map(entry => `
            <div class="entry-card" data-slug="${escapeHtml(entry.slug)}">
                <h3 class="entry-card-title">${escapeHtml(entry.title)}</h3>
                <span class="category-badge">${escapeHtml(entry.category)}</span>
                <div class="entry-card-slug"><code class="slug-code">${escapeHtml(entry.slug)}</code></div>
                <div class="entry-card-tags">${renderTags(entry.tags)}</div>
                <p class="entry-card-excerpt">${escapeHtml(entry.excerpt || '').substring(0, 150)}...</p>
                <div class="entry-card-date">${formatDate(entry.updated_at)}</div>
            </div>
        `).join('');
        
        entriesContainer.innerHTML = html;
        entriesContainer.className = 'entries-grid';

        // Add click handlers
        entriesContainer.querySelectorAll('.entry-card').forEach(card => {
            card.addEventListener('click', () => openEntry(card.dataset.slug));
        });
    }

    // Render tags
    function renderTags(tags) {
        if (!tags) return '';
        return tags.split(',').map(t => t.trim()).filter(t => t).map(t => 
            `<span class="tag">${escapeHtml(t)}</span>`
        ).join('');
    }

    // Open entry for editing
    async function openEntry(slug) {
        try {
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=get&slug=${encodeURIComponent(slug)}`);
            const data = await response.json();
            
            if (data.success) {
                currentEntry = data.entry;
                populateForm(currentEntry);
                entryPanel.classList.remove('hidden');
                document.getElementById('panelTitle').textContent = 'Edit Entry';
                updateArchiveButton();
            } else {
                showError('Failed to load entry');
            }
        } catch (err) {
            showError('Error loading entry: ' + err.message);
        }
    }

    // Create new entry
    function newEntry() {
        currentEntry = null;
        entryForm.reset();
        document.getElementById('entryId').value = '';
        document.getElementById('entrySlug').value = '';
        document.getElementById('entryMetadata').innerHTML = '';
        entryPanel.classList.remove('hidden');
        document.getElementById('panelTitle').textContent = 'New Entry';
        archiveEntryBtn.style.display = 'none';
        deleteEntryBtn.style.display = 'none';
        exportEntryBtn.style.display = 'none';
        entryTitle.focus();
    }

    // Populate form with entry data
    function populateForm(entry) {
        document.getElementById('entryId').value = entry.id;
        document.getElementById('entryTitle').value = entry.title;
        document.getElementById('entrySlug').value = entry.slug;
        document.getElementById('entryCategory').value = entry.category;
        document.getElementById('entryTags').value = entry.tags || '';
        document.getElementById('entryContent').value = entry.content;
        
        const metaHtml = `
            <div class="metadata-row">
                <span>Created:</span> <span>${formatDateTime(entry.created_at)}</span>
            </div>
            <div class="metadata-row">
                <span>Updated:</span> <span>${formatDateTime(entry.updated_at)}</span>
            </div>
            <div class="metadata-row">
                <span>Status:</span> <span class="status-badge status-${entry.status}">${entry.status}</span>
            </div>
        `;
        document.getElementById('entryMetadata').innerHTML = metaHtml;
        
        // Hide preview
        contentPreview.classList.add('hidden');
        entryContent.classList.remove('hidden');
        previewToggle.textContent = 'Preview';
    }

    // Update archive button text
    function updateArchiveButton() {
        if (currentEntry) {
            const isArchived = currentEntry.status === 'archived';
            archiveEntryBtn.textContent = isArchived ? 'Restore' : 'Archive';
            archiveEntryBtn.style.display = 'inline-flex';
            deleteEntryBtn.style.display = 'inline-flex';
            exportEntryBtn.style.display = 'inline-flex';
        }
    }

    // Auto-generate slug from title
    function generateSlug(title) {
        return title
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    // Save entry (create or update)
    async function saveEntry(e) {
        e.preventDefault();
        
        const title = document.getElementById('entryTitle').value.trim();
        const category = document.getElementById('entryCategory').value;
        const content = document.getElementById('entryContent').value;
        
        if (!title || !category || !content) {
            alert('Please fill in all required fields');
            return;
        }

        const payload = {
            title: title,
            category: category,
            tags: document.getElementById('entryTags').value,
            content: content
        };

        // If editing, include slug
        if (currentEntry) {
            payload.slug = currentEntry.slug;
        }

        const action = currentEntry ? 'update' : 'create';
        
        try {
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            
            if (data.success) {
                closePanel();
                loadEntries();
            } else {
                alert('Error: ' + (data.error || 'Failed to save'));
            }
        } catch (err) {
            alert('Error saving: ' + err.message);
        }
    }

    // Archive/Restore entry
    async function toggleArchive() {
        if (!currentEntry) return;
        
        const isArchived = currentEntry.status === 'archived';
        const action = isArchived ? 'restore' : 'archive';
        
        try {
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug: currentEntry.slug })
            });
            
            const data = await response.json();
            
            if (data.success) {
                closePanel();
                loadEntries();
            } else {
                alert('Error: ' + (data.error || 'Failed to archive/restore'));
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    // Delete entry
    async function deleteEntry() {
        if (!currentEntry) return;
        
        deleteModal.classList.remove('hidden');
    }

    async function confirmDelete() {
        if (!currentEntry) return;
        
        try {
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug: currentEntry.slug })
            });
            
            const data = await response.json();
            
            if (data.success) {
                deleteModal.classList.add('hidden');
                closePanel();
                loadEntries();
            } else {
                alert('Error: ' + (data.error || 'Failed to delete'));
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    // Export entry as markdown
    async function exportEntry() {
        if (!currentEntry) return;
        
        try {
            const response = await fetch(`${window.BASE_URL}/api/knowledge.php?action=export&slug=${encodeURIComponent(currentEntry.slug)}`);
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${currentEntry.slug}.md`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            alert('Error exporting: ' + err.message);
        }
    }

    // Close panel
    function closePanel() {
        entryPanel.classList.add('hidden');
        currentEntry = null;
    }

    // Toggle markdown preview
    function togglePreview() {
        const isPreview = !contentPreview.classList.contains('hidden');
        
        if (isPreview) {
            contentPreview.classList.add('hidden');
            entryContent.classList.remove('hidden');
            previewToggle.textContent = 'Preview';
        } else {
            const markdown = entryContent.value;
            contentPreview.innerHTML = renderMarkdown(markdown);
            contentPreview.classList.remove('hidden');
            entryContent.classList.add('hidden');
            previewToggle.textContent = 'Edit';
        }
    }

    // Simple markdown renderer
    function renderMarkdown(text) {
        if (!text) return '';
        
        let html = escapeHtml(text);
        
        // Headers
        html = html.replace(/^###### (.*$)/gim, '<h6>$1</h6>');
        html = html.replace(/^##### (.*$)/gim, '<h5>$1</h5>');
        html = html.replace(/^#### (.*$)/gim, '<h4>$1</h4>');
        html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
        
        // Bold and italic
        html = html.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>');
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');
        html = html.replace(/_(.*?)_/g, '<em>$1</em>');
        
        // Code blocks
        html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Lists
        html = html.replace(/^\s*-\s+(.*$)/gim, '<li>$1</li>');
        html = html.replace(/^\s*\*\s+(.*$)/gim, '<li>$1</li>');
        html = html.replace(/^\s*\d+\.\s+(.*$)/gim, '<li>$1</li>');
        
        // Wrap consecutive li elements in ul
        html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');
        
        // Paragraphs
        html = html.replace(/\n\n/g, '</p><p>');
        html = '<p>' + html + '</p>';
        
        // Clean up empty paragraphs
        html = html.replace(/<p><\/p>/g, '');
        html = html.replace(/<p>(<h[1-6]>)/g, '$1');
        html = html.replace(/(<\/h[1-6]>)<\/p>/g, '$1');
        html = html.replace(/<p>(<ul>)/g, '$1');
        html = html.replace(/(<\/ul>)<\/p>/g, '$1');
        html = html.replace(/<p>(<pre>)/g, '$1');
        html = html.replace(/(<\/pre>)<\/p>/g, '$1');
        
        return html;
    }

    // Copy slug to clipboard
    function copySlug() {
        const slug = document.getElementById('entrySlug').value;
        if (slug) {
            navigator.clipboard.writeText(slug).then(() => {
                copySlugBtn.textContent = '✓';
                setTimeout(() => copySlugBtn.textContent = '📋', 1000);
            });
        }
    }

    // Update view toggle UI
    function updateViewToggle() {
        viewBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === currentView);
        });
    }

    // Setup event listeners
    function setupEventListeners() {
        // Category filter
        categoryBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                categoryBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentCategory = btn.dataset.category;
                renderEntries();
            });
        });

        // Search
        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value;
            renderEntries();
        });

        // Show archived
        showArchivedCheckbox.addEventListener('change', (e) => {
            showArchived = e.target.checked;
            loadEntries();
        });

        // View toggle
        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                currentView = btn.dataset.view;
                localStorage.setItem('mc_kb_view', currentView);
                updateViewToggle();
                renderEntries();
            });
        });

        // New entry
        newEntryBtn.addEventListener('click', newEntry);

        // Close panel
        closePanelBtn.addEventListener('click', closePanel);

        // Form submit
        entryForm.addEventListener('submit', saveEntry);

        // Auto-generate slug on title change (debounced)
        entryTitle.addEventListener('input', () => {
            if (!currentEntry) {
                clearTimeout(slugDebounceTimer);
                slugDebounceTimer = setTimeout(() => {
                    const slug = generateSlug(entryTitle.value);
                    document.getElementById('entrySlug').value = slug;
                }, 300);
            }
        });

        // Preview toggle
        previewToggle.addEventListener('click', togglePreview);

        // Copy slug
        copySlugBtn.addEventListener('click', copySlug);

        // Archive
        archiveEntryBtn.addEventListener('click', toggleArchive);

        // Delete
        deleteEntryBtn.addEventListener('click', deleteEntry);
        confirmDeleteBtn.addEventListener('click', confirmDelete);
        cancelDeleteBtns.forEach(btn => {
            btn.addEventListener('click', () => deleteModal.classList.add('hidden'));
        });

        // Export
        exportEntryBtn.addEventListener('click', exportEntry);
    }

    // Utility: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Utility: Format date
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const utcStr = (dateStr.includes('T') || dateStr.endsWith('Z')) ? dateStr : dateStr.replace(' ', 'T') + 'Z';
        const date = new Date(utcStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'America/New_York' });
    }

    // Utility: Format date and time
    function formatDateTime(dateStr) {
        if (!dateStr) return '';
        const utcStr = (dateStr.includes('T') || dateStr.endsWith('Z')) ? dateStr : dateStr.replace(' ', 'T') + 'Z';
        const date = new Date(utcStr);
        return date.toLocaleString('en-US', { 
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit', timeZone: 'America/New_York'
        });
    }

    // Utility: Show error
    function showError(msg) {
        entriesContainer.innerHTML = `<div class="error-state">${escapeHtml(msg)}</div>`;
    }

    // Start
    init();
})();
