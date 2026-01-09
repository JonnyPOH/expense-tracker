<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['expense_ids']) || !isset($data['new_category_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$expenseIds = $data['expense_ids'];
$newCategoryId = (int) $data['new_category_id'];

if (empty($expenseIds) || !is_array($expenseIds)) {
    echo json_encode(['success' => false, 'error' => 'Invalid expense IDs']);
    exit;
}

if ($newCategoryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid category ID']);
    exit;
}

try {
    // Verify category exists
    $stmt = db()->prepare('SELECT id FROM categories WHERE id = :id');
    $stmt->execute(['id' => $newCategoryId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }

    // Update expenses
    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
    $sql = "UPDATE expenses SET category_id = ? WHERE id IN ($placeholders)";

    $stmt = db()->prepare($sql);
    $params = array_merge([$newCategoryId], $expenseIds);
    $stmt->execute($params);

    $updated = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'updated' => $updated
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
