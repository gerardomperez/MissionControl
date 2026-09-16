<?php
/**
 * Knowledge Base API
 * Handles all CRUD operations for knowledge entries
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = getDB();
$action = $_GET['action'] ?? '';

/**
 * Generate a slug from a title
 * - lowercase
 * - replace spaces/special chars with hyphens
 * - strip non-alphanumeric except hyphens
 */
function generateSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Ensure slug is unique by appending a number if needed
 */
function ensureUniqueSlug($pdo, $slug, $excludeId = null) {
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM knowledge_entries WHERE slug = :slug";
        if ($excludeId) {
            $sql .= " AND id != :excludeId";
        }
        $stmt = $pdo->prepare($sql);
        $params = [':slug' => $slug];
        if ($excludeId) {
            $params[':excludeId'] = $excludeId;
        }
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            return $slug;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
}

try {
    switch ($action) {
        // GET ?action=list — List entries with optional filtering
        case 'list':
            $category = $_GET['category'] ?? null;
            $tag = $_GET['tag'] ?? null;
            $search = $_GET['search'] ?? null;
            $status = $_GET['status'] ?? 'active';
            
            $where = ["status = :status"];
            $params = [':status' => $status];
            
            if ($category) {
                $where[] = "category = :category";
                $params[':category'] = $category;
            }
            
            if ($tag) {
                $where[] = "tags LIKE :tag";
                $params[':tag'] = '%' . $tag . '%';
            }
            
            if ($search) {
                $where[] = "(title LIKE :search OR tags LIKE :search OR content LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            
            $sql = "SELECT id, slug, title, category, tags, status, updated_at, 
                           substr(content, 1, 200) as excerpt 
                    FROM knowledge_entries 
                    WHERE " . implode(' AND ', $where) . " 
                    ORDER BY updated_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'entries' => $entries,
                'count' => count($entries)
            ]);
            break;

        // GET ?action=get&slug=X — Get full entry by slug
        case 'get':
            $slug = $_GET['slug'] ?? null;
            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM knowledge_entries WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
            $entry = $stmt->fetch();
            
            if (!$entry) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'entry not found']);
                exit;
            }
            
            echo json_encode(['success' => true, 'entry' => $entry]);
            break;

        // GET ?action=export&slug=X — Export entry as markdown
        case 'export':
            $slug = $_GET['slug'] ?? null;
            if (!$slug) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM knowledge_entries WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
            $entry = $stmt->fetch();
            
            if (!$entry) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'entry not found']);
                exit;
            }
            
            header('Content-Type: text/markdown');
            header('Content-Disposition: attachment; filename="' . $slug . '.md"');
            
            $yaml = "---\n";
            $yaml .= "title: " . $entry['title'] . "\n";
            $yaml .= "slug: " . $entry['slug'] . "\n";
            $yaml .= "category: " . $entry['category'] . "\n";
            $yaml .= "tags: " . $entry['tags'] . "\n";
            $yaml .= "status: " . $entry['status'] . "\n";
            $yaml .= "created_at: " . $entry['created_at'] . "\n";
            $yaml .= "updated_at: " . $entry['updated_at'] . "\n";
            $yaml .= "---\n\n";
            
            echo $yaml . $entry['content'];
            break;

        // GET ?action=bulk_export&category=X — Export all entries as JSON array of markdown
        case 'bulk_export':
            $category = $_GET['category'] ?? null;
            
            $where = ["status = 'active'"];
            $params = [];
            
            if ($category) {
                $where[] = "category = :category";
                $params[':category'] = $category;
            }
            
            $sql = "SELECT * FROM knowledge_entries WHERE " . implode(' AND ', $where) . " ORDER BY category, title";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();
            
            $markdownEntries = [];
            foreach ($entries as $entry) {
                $yaml = "---\n";
                $yaml .= "title: " . $entry['title'] . "\n";
                $yaml .= "slug: " . $entry['slug'] . "\n";
                $yaml .= "category: " . $entry['category'] . "\n";
                $yaml .= "tags: " . $entry['tags'] . "\n";
                $yaml .= "status: " . $entry['status'] . "\n";
                $yaml .= "created_at: " . $entry['created_at'] . "\n";
                $yaml .= "updated_at: " . $entry['updated_at'] . "\n";
                $yaml .= "---\n\n";
                
                $markdownEntries[] = [
                    'slug' => $entry['slug'],
                    'title' => $entry['title'],
                    'markdown' => $yaml . $entry['content']
                ];
            }
            
            echo json_encode(['success' => true, 'entries' => $markdownEntries]);
            break;

        // POST ?action=create — Create new entry
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'title is required']);
                exit;
            }
            if (empty($data['category'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'category is required']);
                exit;
            }
            if (!isset($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'content is required']);
                exit;
            }
            
            $title = $data['title'];
            $slug = !empty($data['slug']) ? $data['slug'] : generateSlug($title);
            $slug = ensureUniqueSlug($pdo, $slug);
            
            $stmt = $pdo->prepare("INSERT INTO knowledge_entries 
                (slug, title, category, tags, content, status, created_at, updated_at) 
                VALUES (:slug, :title, :category, :tags, :content, :status, datetime('now'), datetime('now'))");
            
            $stmt->execute([
                ':slug' => $slug,
                ':title' => $title,
                ':category' => $data['category'],
                ':tags' => $data['tags'] ?? '',
                ':content' => $data['content'],
                ':status' => $data['status'] ?? 'active'
            ]);
            
            $id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => $id,
                'slug' => $slug
            ]);
            break;

        // POST ?action=update — Update existing entry
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['slug'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $slug = $data['slug'];
            
            // Get current entry
            $stmt = $pdo->prepare("SELECT * FROM knowledge_entries WHERE slug = :slug");
            $stmt->execute([':slug' => $slug]);
            $entry = $stmt->fetch();
            
            if (!$entry) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'entry not found']);
                exit;
            }
            
            $updates = [];
            $params = [':slug' => $slug];
            
            if (isset($data['title'])) {
                $updates[] = "title = :title";
                $params[':title'] = $data['title'];
            }
            if (isset($data['category'])) {
                $updates[] = "category = :category";
                $params[':category'] = $data['category'];
            }
            if (isset($data['tags'])) {
                $updates[] = "tags = :tags";
                $params[':tags'] = $data['tags'];
            }
            if (isset($data['content'])) {
                $updates[] = "content = :content";
                $params[':content'] = $data['content'];
            }
            
            // Always update updated_at
            $updates[] = "updated_at = datetime('now')";
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'no fields to update']);
                exit;
            }
            
            $sql = "UPDATE knowledge_entries SET " . implode(', ', $updates) . " WHERE slug = :slug";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'slug' => $slug]);
            break;

        // POST ?action=archive — Archive an entry
        case 'archive':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['slug'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE knowledge_entries SET status = 'archived', updated_at = datetime('now') WHERE slug = :slug");
            $stmt->execute([':slug' => $data['slug']]);
            
            echo json_encode(['success' => true]);
            break;

        // POST ?action=restore — Restore an archived entry
        case 'restore':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['slug'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE knowledge_entries SET status = 'active', updated_at = datetime('now') WHERE slug = :slug");
            $stmt->execute([':slug' => $data['slug']]);
            
            echo json_encode(['success' => true]);
            break;

        // POST ?action=delete — Hard delete an entry
        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['slug'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'slug is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM knowledge_entries WHERE slug = :slug");
            $stmt->execute([':slug' => $data['slug']]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'invalid action']);
    }
} catch (Exception $e) {
    error_log("[Knowledge API] ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
