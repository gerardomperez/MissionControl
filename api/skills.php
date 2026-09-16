<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

// Skills are stored as files in the skills directory
$skillsDir = __DIR__ . '/../../skills';

/**
 * Extract a single-line field from YAML frontmatter (e.g. "status", "name").
 * Returns the trimmed value or '' if not found.
 */
function extractFrontmatterField(string $content, string $field): string {
    if (!preg_match('/^---\s*\n(.*?)\n---/s', $content, $fm)) return '';
    if (!preg_match('/^' . preg_quote($field, '/') . ':\s*(.+)/m', $fm[1], $m)) return '';
    return trim($m[1]);
}

/**
 * Extract description from YAML frontmatter, handling both inline and block scalar (>) styles.
 * Returns a single clean string.
 */
function extractFrontmatterDescription(string $content): string {
    // Pull out the frontmatter block between --- markers
    if (!preg_match('/^---\s*\n(.*?)\n---/s', $content, $fm)) {
        return '';
    }
    $frontmatter = $fm[1];

    // Match description field — either inline or block scalar (> or |)
    if (!preg_match('/^description:\s*(.*)/m', $frontmatter, $m)) {
        return '';
    }

    $firstLine = rtrim($m[1]);

    // Inline value: description: Some text here
    if ($firstLine !== '' && $firstLine !== '>' && $firstLine !== '|') {
        return $firstLine;
    }

    // Block scalar (> or |): collect indented continuation lines
    $afterDesc = substr($frontmatter, strpos($frontmatter, $m[0]) + strlen($m[0]));
    $lines = explode("\n", $afterDesc);
    $parts = [];
    foreach ($lines as $line) {
        // Continuation lines are indented (at least 2 spaces)
        if (preg_match('/^  +(.*)/', $line, $lm)) {
            $parts[] = trim($lm[1]);
        } elseif (empty($parts)) {
            continue; // skip blank lines before first indented line
        } else {
            break; // non-indented line ends the block
        }
    }

    // YAML > folds newlines into spaces
    return implode(' ', array_filter($parts, fn($p) => $p !== ''));
}

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                // Return single skill with full content
                $skillPath = $skillsDir . '/' . basename($id) . '/SKILL.md';
                $metaPath = $skillsDir . '/' . basename($id) . '/_meta.json';
                
                if (!file_exists($skillPath)) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Skill not found']);
                    exit;
                }
                
                $content = file_get_contents($skillPath);
                $name = basename($id);
                $description = '';
                $status = 'active';
                $content = file_get_contents($skillPath);

                // Check frontmatter status — placeholder → unknown
                $fmStatus = extractFrontmatterField($content, 'status');
                if ($fmStatus === 'placeholder') {
                    $status = 'unknown';
                }

                // _meta.json can further override
                if (file_exists($metaPath)) {
                    $meta = json_decode(file_get_contents($metaPath), true);
                    if ($meta) {
                        $name = $meta['name'] ?? $name;
                        $description = $meta['description'] ?? '';
                        $metaStatus = $meta['status'] ?? '';
                        if (in_array($metaStatus, ['active', 'inactive', 'deprecated', 'unknown'])) {
                            $status = $metaStatus;
                        }
                    }
                }
                
                // Try to extract description from SKILL.md frontmatter
                if (empty($description)) {
                    $description = extractFrontmatterDescription($content);
                }
                
                echo json_encode([
                    'id' => basename($id),
                    'name' => $name,
                    'description' => $description,
                    'status' => $status,
                    'content' => $content,
                ]);
            } else {
                // Return list of all skills
                $skills = [];
                
                if (is_dir($skillsDir)) {
                    $dirs = scandir($skillsDir);
                    foreach ($dirs as $dir) {
                        if ($dir === '.' || $dir === '..') continue;
                        
                        $skillPath = $skillsDir . '/' . $dir . '/SKILL.md';
                        $metaPath = $skillsDir . '/' . $dir . '/_meta.json';
                        
                        if (file_exists($skillPath)) {
                            $name = $dir;
                            $description = '';
                            $status = 'active';

                            // Read SKILL.md content once
                            $content = file_get_contents($skillPath);

                            // Check frontmatter status field — placeholder → unknown
                            $fmStatus = extractFrontmatterField($content, 'status');
                            if ($fmStatus === 'placeholder') {
                                $status = 'unknown';
                            }

                            // _meta.json can further override name, description, and status
                            if (file_exists($metaPath)) {
                                $meta = json_decode(file_get_contents($metaPath), true);
                                if ($meta) {
                                    $name = $meta['name'] ?? $name;
                                    $description = $meta['description'] ?? '';
                                    $metaStatus = $meta['status'] ?? '';
                                    if (in_array($metaStatus, ['active', 'inactive', 'deprecated', 'unknown'])) {
                                        $status = $metaStatus;
                                    }
                                }
                            }

                            // Extract description from SKILL.md frontmatter if not set by meta
                            if (empty($description)) {
                                $description = extractFrontmatterDescription($content);
                            }
                            
                            $skills[] = [
                                'id' => $dir,
                                'name' => $name,
                                'description' => $description,
                                'status' => $status,
                            ];
                        }
                    }
                }
                
                // Sort by name
                usort($skills, function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                
                echo json_encode($skills);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}