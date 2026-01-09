<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

class AnalyticsService
{
    public function getMonthlyTrends(): array
    {
        $stmt = db()->query("
            SELECT
                strftime('%Y-%m', spent_on) as month,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount_pennies ELSE 0 END) as expenses,
                SUM(CASE WHEN transaction_type = 'income' THEN amount_pennies ELSE 0 END) as income,
                COUNT(*) as count
            FROM expenses
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ");

        return array_reverse($stmt->fetchAll());
    }

    public function getCategoryTrends(int $months = 6): array
    {
        $stmt = db()->prepare("
            SELECT
                c.name as category,
                e.transaction_type,
                strftime('%Y-%m', e.spent_on) as month,
                SUM(e.amount_pennies) as total
            FROM categories c
            LEFT JOIN expenses e ON c.id = e.category_id
            WHERE e.spent_on >= date('now', '-' || :months || ' months')
            GROUP BY c.id, c.name, e.transaction_type, month
            HAVING total > 0
            ORDER BY c.name, e.transaction_type, month ASC
        ");

        $stmt->execute(['months' => $months]);
        return $stmt->fetchAll();
    }

    public function getCategoryBreakdown(): array
    {
        $stmt = db()->query("
            SELECT
                c.name as category,
                COUNT(e.id) as transaction_count,
                SUM(e.amount_pennies) as total,
                AVG(e.amount_pennies) as average
            FROM categories c
            LEFT JOIN expenses e ON c.id = e.category_id AND e.transaction_type = 'expense'
            GROUP BY c.id, c.name
            HAVING total > 0
            ORDER BY total DESC
        ");

        return $stmt->fetchAll();
    }

    public function getDailySpending(int $days = 30): array
    {
        $stmt = db()->prepare("
            SELECT
                spent_on as date,
                SUM(amount_pennies) as total
            FROM expenses
            WHERE spent_on >= date('now', '-' || :days || ' days')
            GROUP BY spent_on
            ORDER BY spent_on ASC
        ");

        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll();
    }

    public function getTopMerchants(int $limit = 10): array
    {
        $stmt = db()->prepare("
            SELECT
                merchant,
                COUNT(*) as transaction_count,
                SUM(amount_pennies) as total
            FROM expenses
            GROUP BY merchant
            ORDER BY total DESC
            LIMIT :limit
        ");

        $stmt->execute(['limit' => $limit]);
        return $stmt->fetchAll();
    }

    public function getWeekdayAnalysis(): array
    {
        $stmt = db()->query("
            SELECT
                CASE CAST(strftime('%w', spent_on) AS INTEGER)
                    WHEN 0 THEN 'Sunday'
                    WHEN 1 THEN 'Monday'
                    WHEN 2 THEN 'Tuesday'
                    WHEN 3 THEN 'Wednesday'
                    WHEN 4 THEN 'Thursday'
                    WHEN 5 THEN 'Friday'
                    WHEN 6 THEN 'Saturday'
                END as weekday,
                CAST(strftime('%w', spent_on) AS INTEGER) as day_num,
                COUNT(*) as transaction_count,
                SUM(amount_pennies) as total,
                AVG(amount_pennies) as average
            FROM expenses
            GROUP BY day_num
            ORDER BY day_num
        ");

        return $stmt->fetchAll();
    }

    public function getSpendingStats(): array
    {
        $stmt = db()->query("
            SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount_pennies ELSE 0 END) as total_spent,
                SUM(CASE WHEN transaction_type = 'income' THEN amount_pennies ELSE 0 END) as total_income,
                AVG(CASE WHEN transaction_type = 'expense' THEN amount_pennies END) as average_transaction,
                MIN(CASE WHEN transaction_type = 'expense' THEN amount_pennies END) as smallest_transaction,
                MAX(CASE WHEN transaction_type = 'expense' THEN amount_pennies END) as largest_transaction
            FROM expenses
        ");

        return $stmt->fetch() ?: [];
    }

    public function getMonthlyComparison(): array
    {
        $stmt = db()->query("
            SELECT
                strftime('%Y-%m', spent_on) as month,
                SUM(CASE WHEN transaction_type = 'expense' THEN amount_pennies ELSE 0 END) as total
            FROM expenses
            WHERE spent_on >= date('now', 'start of month', '-1 month')
            GROUP BY month
            ORDER BY month DESC
            LIMIT 2
        ");

        $results = $stmt->fetchAll();

        if (count($results) === 2) {
            $currentMonth = $results[0];
            $previousMonth = $results[1];

            $difference = (int)$currentMonth['total'] - (int)$previousMonth['total'];
            $percentChange = $previousMonth['total'] > 0
                ? ($difference / (int)$previousMonth['total']) * 100
                : 0;

            return [
                'current' => $currentMonth,
                'previous' => $previousMonth,
                'difference' => $difference,
                'percent_change' => $percentChange
            ];
        }

        return [];
    }
}
