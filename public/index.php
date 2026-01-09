<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/CategoryRepository.php';
require_once __DIR__ . '/../src/ExpenseRepository.php';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        delete_expense($id);
        header('Location: index.php');
        exit;
    }
}

$categories = get_all_categories();
$expenses = get_all_expenses();
$totalExpenses = get_total_expenses();
$totalIncome = get_total_income();
$netBalance = get_net_balance();
$categoryTotals = get_expenses_by_category();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Expense Tracker</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      margin: 0;
      padding: 2rem;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    .card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    h1 {
      margin: 0 0 1rem 0;
      color: white;
      font-size: 2.5rem;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .stat-label {
      font-size: 0.875rem;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: bold;
      color: #667eea;
      margin-top: 0.5rem;
    }
    .btn {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      background: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      border: none;
      cursor: pointer;
      font-size: 1rem;
    }
    .btn:hover {
      background: #5568d3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    .btn-danger {
      background: #ef4444;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
    }
    .btn-danger:hover {
      background: #dc2626;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    th {
      background: #f9fafb;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    tr:hover {
      background: #f9fafb;
    }
    .amount {
      font-weight: 600;
      color: #059669;
    }
    .category-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      background: #e0e7ff;
      color: #4338ca;
      border-radius: 12px;
      font-size: 0.875rem;
      font-weight: 500;
    }
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: #6b7280;
    }
    .empty-state h3 {
      margin: 0 0 1rem 0;
      color: #374151;
    }
    .category-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    .category-item {
      padding: 1rem;
      background: #f9fafb;
      border-radius: 8px;
      border-left: 4px solid #667eea;
    }
    .category-name {
      font-weight: 600;
      color: #374151;
    }
    .category-amount {
      font-size: 1.25rem;
      font-weight: bold;
      color: #059669;
      margin-top: 0.5rem;
    }
    .note {
      color: #6b7280;
      font-size: 0.875rem;
      font-style: italic;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>💰 Expense Tracker</h1>

    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">Total Income</div>
        <div class="stat-value" style="color: #10b981;"><?= format_money($totalIncome) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value" style="color: #ef4444;"><?= format_money($totalExpenses) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Net Balance</div>
        <div class="stat-value" style="color: <?= $netBalance >= 0 ? '#10b981' : '#ef4444' ?>;">
          <?= format_money(abs($netBalance)) ?> <?= $netBalance >= 0 ? '↑' : '↓' ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Transactions</div>
        <div class="stat-value"><?= count($expenses) ?></div>
      </div>
    </div>

    <div class="card">
      <h2>Recent Expenses</h2>
      <a href="add-expense.php" class="btn">+ Add New Expense</a>
      <a href="import.php" class="btn" style="background: #10b981; margin-left: 1rem;">📊 Import CSV</a>
      <a href="analytics.php" class="btn" style="background: #f59e0b; margin-left: 1rem;">📈 View Analytics</a>
      <a href="manage-categories.php" class="btn" style="background: #8b5cf6; margin-left: 1rem;">🏷️ Manage Categories</a>

      <?php if (empty($expenses)): ?>
        <div class="empty-state">
          <h3>No expenses yet</h3>
          <p>Start tracking your spending by adding your first expense!</p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Merchant</th>
              <th>Category</th>
              <th>Amount</th>
              <th>Note</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($expenses as $expense): ?>
              <tr>
                <td><?= htmlspecialchars($expense['spent_on']) ?></td>
                <td>
                  <?php if (($expense['transaction_type'] ?? 'expense') === 'income'): ?>
                    <span style="color: #10b981; font-weight: 600;">💰 Income</span>
                  <?php else: ?>
                    <span style="color: #ef4444; font-weight: 600;">💸 Expense</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($expense['merchant']) ?></td>
                <td><span class="category-badge"><?= htmlspecialchars($expense['category_name']) ?></span></td>
                <td class="amount" style="color: <?= ($expense['transaction_type'] ?? 'expense') === 'income' ? '#10b981' : '#ef4444' ?>;">
                  <?= format_money((int)$expense['amount_pennies']) ?>
                </td>
                <td>
                  <?php if ($expense['note']): ?>
                    <span class="note"><?= htmlspecialchars($expense['note']) ?></span>
                  <?php else: ?>
                    <span class="note">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Spending by Category</h2>
      <div class="category-list">
        <?php foreach ($categoryTotals as $cat): ?>
          <div class="category-item">
            <div class="category-name"><?= htmlspecialchars($cat['category_name']) ?></div>
            <div class="category-amount"><?= format_money((int)$cat['total']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>
</html>
