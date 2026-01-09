<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/ExpenseRepository.php';
require_once __DIR__ . '/../src/CategoryRepository.php';

header('Content-Type: application/json');

$category = $_GET['category'] ?? '';

if (empty($category)) {
    echo json_encode(['error' => 'Category is required']);
    exit;
}

// Get category ID
$categories = get_all_categories();
$categoryId = null;
foreach ($categories as $cat) {
    if ($cat['name'] === $category) {
        $categoryId = (int) $cat['id'];
        break;
    }
}

if (!$categoryId) {
    echo json_encode(['error' => 'Category not found']);
    exit;
}

// Get top 10 expenses for this category
$stmt = db()->prepare('
    SELECT
        e.id,
        e.spent_on,
        e.amount_pennies,
        e.merchant,
        e.note,
        e.transaction_type
    FROM expenses e
    WHERE e.category_id = :category_id
    AND e.transaction_type = "expense"
    ORDER BY e.amount_pennies DESC
    LIMIT 10
');

$stmt->execute(['category_id' => $categoryId]);
$expenses = $stmt->fetchAll();

// Format the expenses
$formattedExpenses = array_map(function($expense) {
    return [
        'id' => $expense['id'],
        'spent_on' => $expense['spent_on'],
        'merchant' => htmlspecialchars($expense['merchant']),
        'note' => $expense['note'] ? htmlspecialchars($expense['note']) : null,
        'amount' => format_money((int) $expense['amount_pennies'])
    ];
}, $expenses);

echo json_encode([
    'category' => $category,
    'expenses' => $formattedExpenses
]);
