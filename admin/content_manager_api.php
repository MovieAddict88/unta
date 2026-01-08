<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = connect_db();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_content':
            handle_get_content($pdo);
            break;
        case 'delete_content':
            handle_delete_content($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
}


function handle_get_content($pdo) {
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $sql = "SELECT * FROM content";
    $whereClauses = [];
    $params = [];

    if (!empty($search)) {
        $whereClauses[] = "title LIKE ?";
        $params[] = "%{$search}%";
    }

    if ($type !== 'all') {
        $whereClauses[] = "type = ?";
        $params[] = $type;
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(' AND ', $whereClauses);
    }

    // Get total count for pagination
    $countStmt = $pdo->prepare(str_replace('*', 'COUNT(*)', $sql));
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    // PDO requires integer type for LIMIT and OFFSET
    $stmt->bindValue(count($params) - 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params), $offset, PDO::PARAM_INT);
    // Bind other params
    for ($i = 0; $i < count($params) - 2; $i++) {
        $stmt->bindValue($i + 1, $params[$i]);
    }

    $stmt->execute();
    $content = $stmt->fetchAll();

    echo json_encode([
        'content' => $content,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords
        ]
    ]);
}

function handle_delete_content($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $contentId = $data['id'] ?? null;

    if (!$contentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Content ID is required.']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM content WHERE id = ?");
    if ($stmt->execute([$contentId])) {
        echo json_encode(['success' => true, 'message' => 'Content deleted successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete content.']);
    }
}
?>
