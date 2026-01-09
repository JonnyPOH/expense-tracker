<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function get_all_expenses(): array
{
    $stmt = db()->query('
        SELECT
            e.id,
            e.spent_on,
            e.amount_pennies,
            e.merchant,
            e.note,
            e.transaction_type,
            e.created_at,
            c.name as category_name,
            c.id as category_id
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        ORDER BY e.spent_on DESC, e.created_at DESC
    ');
    return $stmt->fetchAll();
}

function get_expense_by_id(int $id): ?array
{
    $stmt = db()->prepare('
        SELECT
            e.id,
            e.spent_on,
            e.amount_pennies,
            e.merchant,
            e.note,
            e.transaction_type,
            e.created_at,
            c.name as category_name,
            c.id as category_id
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.id = :id
    ');
    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();
    return $result ?: null;
}

function create_expense(string $spent_on, int $amount_pennies, int $category_id, string $merchant, ?string $note = null, string $transaction_type = 'expense'): int
{
    $stmt = db()->prepare('
        INSERT INTO expenses (spent_on, amount_pennies, category_id, merchant, note, transaction_type)
        VALUES (:spent_on, :amount_pennies, :category_id, :merchant, :note, :transaction_type)
    ');

    $stmt->execute([
        'spent_on' => $spent_on,
        'amount_pennies' => $amount_pennies,
        'category_id' => $category_id,
        'merchant' => $merchant,
        'note' => $note,
        'transaction_type' => $transaction_type,
    ]);

    return (int) db()->lastInsertId();
}

function delete_expense(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM expenses WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}

function get_total_expenses(): int
{
    $stmt = db()->query("SELECT COALESCE(SUM(amount_pennies), 0) as total FROM expenses WHERE transaction_type = 'expense'");
    $result = $stmt->fetch();
    return (int) $result['total'];
}

function get_total_income(): int
{
    $stmt = db()->query("SELECT COALESCE(SUM(amount_pennies), 0) as total FROM expenses WHERE transaction_type = 'income'");
    $result = $stmt->fetch();
    return (int) $result['total'];
}

function get_net_balance(): int
{
    return get_total_income() - get_total_expenses();
}

function get_expenses_by_category(): array
{
    $stmt = db()->query('
        SELECT
            c.name as category_name,
            COALESCE(SUM(e.amount_pennies), 0) as total
        FROM categories c
        LEFT JOIN expenses e ON c.id = e.category_id AND e.transaction_type = "expense"
        GROUP BY c.id, c.name
        HAVING total > 0
        ORDER BY total DESC
    ');
    return $stmt->fetchAll();
}

function format_money(int $pennies): string
{
    return '$' . number_format($pennies / 100, 2);
}
