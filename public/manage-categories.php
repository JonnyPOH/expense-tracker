<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/CategoryRepository.php';
require_once __DIR__ . '/../src/db.php';

$message = '';
$messageType = '';

// Handle form submission to update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'recategorize') {
        $expenseIds = $_POST['expense_ids'] ?? [];
        $newCategoryId = (int) ($_POST['new_category_id'] ?? 0);

        if (!empty($expenseIds) && $newCategoryId > 0) {
            $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
            $stmt = db()->prepare("UPDATE expenses SET category_id = ? WHERE id IN ($placeholders)");
            $params = array_merge([$newCategoryId], $expenseIds);
            $stmt->execute($params);

            $message = count($expenseIds) . " transaction(s) recategorized successfully!";
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'recategorize_multiple') {
        $categories = $_POST['categories'] ?? [];
        $totalUpdated = 0;

        foreach ($categories as $expenseIdsStr => $categoryId) {
            $categoryId = (int) $categoryId;
            if ($categoryId > 0) {
                // Convert key back to string and split into IDs
                $expenseIdsStr = (string) $expenseIdsStr;
                $expenseIds = array_map('intval', explode(',', $expenseIdsStr));
                if (!empty($expenseIds)) {
                    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
                    $stmt = db()->prepare("UPDATE expenses SET category_id = ? WHERE id IN ($placeholders)");
                    $params = array_merge([$categoryId], $expenseIds);
                    $stmt->execute($params);
                    $totalUpdated += count($expenseIds);
                }
            }
        }

        if ($totalUpdated > 0) {
            $message = "{$totalUpdated} transaction(s) recategorized successfully!";
            $messageType = 'success';
        } else {
            $message = "No changes were made. Please select categories for the merchants you want to update.";
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'add_category') {
        $newCategoryName = trim($_POST['category_name'] ?? '');

        if (!empty($newCategoryName)) {
            try {
                $stmt = db()->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$newCategoryName]);
                $message = "Category '{$newCategoryName}' created successfully!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: Category might already exist.";
                $messageType = 'error';
            }
        }
    }
}

// Get sorting preference
$sortBy = $_GET['sort'] ?? 'count';
$allowedSorts = ['count', 'total', 'merchant'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'count';
}

// Get all "Other" category expenses grouped by merchant
$otherCategory = get_all_categories();
$otherCategoryId = null;
foreach ($otherCategory as $cat) {
    if ($cat['name'] === 'Other') {
        $otherCategoryId = $cat['id'];
        break;
    }
}

$uncategorizedMerchants = [];
if ($otherCategoryId) {
    $orderClause = match($sortBy) {
        'total' => 'ORDER BY total DESC',
        'merchant' => 'ORDER BY merchant ASC',
        default => 'ORDER BY count DESC'
    };

    $stmt = db()->prepare("
        SELECT
            merchant,
            COUNT(*) as count,
            SUM(amount_pennies) as total,
            GROUP_CONCAT(id) as expense_ids
        FROM expenses
        WHERE category_id = :category_id
        GROUP BY merchant
        {$orderClause}
    ");
    $stmt->execute(['category_id' => $otherCategoryId]);
    $uncategorizedMerchants = $stmt->fetchAll();
}

$categories = get_all_categories();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Categories - Expense Tracker</title>
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
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      margin-bottom: 2rem;
    }
    h1 {
      margin: 0 0 1rem 0;
      color: white;
      font-size: 2.5rem;
    }
    .back-link {
      display: inline-block;
      color: white;
      text-decoration: none;
      margin-bottom: 1rem;
      font-weight: 500;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .message {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }
    .message.success {
      background: #d1fae5;
      border: 2px solid #10b981;
      color: #065f46;
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
    .merchant-name {
      font-weight: 600;
      color: #1f2937;
    }
    .count {
      color: #6b7280;
      font-size: 0.875rem;
    }
    select {
      padding: 0.5rem;
      border: 2px solid #e5e7eb;
      border-radius: 6px;
      font-size: 0.875rem;
      min-width: 150px;
    }
    .btn {
      padding: 0.5rem 1rem;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.875rem;
      transition: all 0.3s;
    }
    .btn:hover {
      background: #5568d3;
      transform: translateY(-1px);
    }
    .btn:disabled {
      background: #9ca3af;
      cursor: not-allowed;
      transform: none;
    }
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: #6b7280;
    }
    .info-box {
      background: #eff6ff;
      border-left: 4px solid #3b82f6;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 2rem;
    }
    .info-box h3 {
      margin: 0 0 0.5rem 0;
      color: #1e40af;
    }
    .stats {
      display: flex;
      gap: 2rem;
      margin-bottom: 1rem;
    }
    .stat {
      font-size: 0.875rem;
      color: #6b7280;
    }
    .stat strong {
      color: #1f2937;
      font-size: 1.25rem;
      display: block;
    }
    .action-form {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    code {
      background: #f3f4f6;
      padding: 0.2rem 0.4rem;
      border-radius: 4px;
      font-size: 0.875rem;
    }
    .sort-controls {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;
      align-items: center;
    }
    .sort-btn {
      padding: 0.5rem 1rem;
      background: white;
      color: #667eea;
      border: 2px solid #667eea;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.875rem;
      text-decoration: none;
      transition: all 0.3s;
    }
    .sort-btn:hover {
      background: #667eea;
      color: white;
    }
    .sort-btn.active {
      background: #667eea;
      color: white;
    }
    .bulk-actions {
      background: #f0f9ff;
      border: 2px solid #0ea5e9;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      display: flex;
      gap: 1rem;
      align-items: center;
      justify-content: space-between;
    }
    .save-all-btn {
      padding: 0.75rem 2rem;
      background: #0ea5e9;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      font-size: 1rem;
      transition: all 0.3s;
    }
    .save-all-btn:hover:not(:disabled) {
      background: #0284c7;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
    }
    .save-all-btn:disabled {
      background: #94a3b8;
      cursor: not-allowed;
    }
    .category-select {
      padding: 0.5rem;
      border: 2px solid #e5e7eb;
      border-radius: 6px;
      font-size: 0.875rem;
      width: 100%;
      transition: border-color 0.2s;
    }
    .category-select:focus {
      outline: none;
      border-color: #667eea;
    }
    .category-select.changed {
      border-color: #10b981;
      background: #f0fdf4;
    }
    }
    .add-category-form {
      background: #f9fafb;
      padding: 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
    }
    .add-category-form h3 {
      margin: 0 0 1rem 0;
    }
    .form-row {
      display: flex;
      gap: 0.5rem;
      align-items: flex-end;
    }
    .form-group {
      flex: 1;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #374151;
    }
    .form-group input {
      width: 100%;
      padding: 0.5rem;
      border: 2px solid #e5e7eb;
      border-radius: 6px;
      font-size: 0.875rem;
    }
    .message.error {
      background: #fee2e2;
      border: 2px solid #ef4444;
      color: #991b1b;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Dashboard</a>
    <h1>🏷️ Manage Categories</h1>

    <div class="card">
      <h2>➕ Add New Category</h2>
      <div class="add-category-form">
        <form method="POST">
          <input type="hidden" name="action" value="add_category">
          <div class="form-row">
            <div class="form-group">
              <label for="category_name">Category Name</label>
              <input
                type="text"
                id="category_name"
                name="category_name"
                placeholder="e.g., Work Income, Freelance, Shopping"
                required
              >
            </div>
            <button type="submit" class="btn">Create Category</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <h2>Uncategorized Transactions (Other)</h2>

      <div class="info-box">
        <h3>💡 How to improve categorization:</h3>
        <p>These merchants are currently categorized as "Other". You can:</p>
        <ul style="margin: 0.5rem 0;">
          <li>Create a new category above (e.g., "Work Income" for salary payments)</li>
          <li>Recategorize them here using the dropdowns</li>
          <li>Add keywords to <code>src/ImportService.php</code> (Lines 11-50) to auto-categorize future imports</li>
        </ul>
        <p style="margin: 0.5rem 0 0 0;"><strong>Example:</strong> Add <code>'DEEL'</code> to a new Work Income category</p>
      </div>

      <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <div class="stats">
        <div class="stat">
          <strong><?= count($uncategorizedMerchants) ?></strong>
          Unique Merchants
        </div>
        <div class="stat">
          <strong><?= array_sum(array_column($uncategorizedMerchants, 'count')) ?></strong>
          Total Transactions
        </div>
      </div>

      <div class="sort-controls">
        <span style="font-weight: 600; color: #374151;">Sort by:</span>
        <a href="?sort=count" class="sort-btn <?= $sortBy === 'count' ? 'active' : '' ?>">
          Most Frequent
        </a>
        <a href="?sort=total" class="sort-btn <?= $sortBy === 'total' ? 'active' : '' ?>">
          Highest Amount
        </a>
        <a href="?sort=merchant" class="sort-btn <?= $sortBy === 'merchant' ? 'active' : '' ?>">
          Merchant Name
        </a>
      </div>

      <?php if (empty($uncategorizedMerchants)): ?>
        <div class="empty-state">
          <h3>🎉 All transactions are categorized!</h3>
          <p>Great job! There are no items in the "Other" category.</p>
        </div>
      <?php else: ?>
        <form method="POST" id="bulkForm">
          <input type="hidden" name="action" value="recategorize_multiple">

          <div class="bulk-actions">
            <span style="font-weight: 600; color: #0369a1;">💡 Select categories for each merchant below, then click "Save All Changes"</span>
            <button type="submit" id="saveAllBtn" class="save-all-btn" disabled>Save All Changes</button>
            <span id="changedCount" style="color: #64748b; font-size: 0.875rem;">0 changes</span>
          </div>

          <table>
            <thead>
              <tr>
                <th>Merchant</th>
                <th>Transactions</th>
                <th>Total Amount</th>
                <th>New Category</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($uncategorizedMerchants as $merchant): ?>
              <tr>
                <td>
                  <div class="merchant-name"><?= htmlspecialchars($merchant['merchant']) ?></div>
                </td>
                <td><?= (int)$merchant['count'] ?> times</td>
                <td>$<?= number_format((int)$merchant['total'] / 100, 2) ?></td>
                <td>
                  <select class="category-select" name="categories[<?= htmlspecialchars($merchant['expense_ids']) ?>]">
                    <option value="">-- Keep as Other --</option>
                    <?php foreach ($categories as $cat): ?>
                      <?php if ($cat['name'] !== 'Other'): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>📝 Quick Reference: Categories</h2>
      <p style="color: #6b7280; margin-bottom: 1rem;">
        To automatically categorize future imports, add keywords to <code>src/ImportService.php</code>
      </p>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <?php foreach ($categories as $cat): ?>
          <div style="padding: 1rem; background: #f9fafb; border-radius: 8px; border-left: 4px solid #667eea;">
            <strong><?= htmlspecialchars($cat['name']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script>
    // Track changes to category selects
    const categorySelects = document.querySelectorAll('.category-select');
    const saveAllBtn = document.getElementById('saveAllBtn');
    const changedCount = document.getElementById('changedCount');

    function updateChangedCount() {
      const changed = Array.from(categorySelects).filter(select => select.value !== '');
      changedCount.textContent = `${changed.length} changes`;
      saveAllBtn.disabled = changed.length === 0;

      // Visual feedback for changed selects
      categorySelects.forEach(select => {
        if (select.value !== '') {
          select.classList.add('changed');
        } else {
          select.classList.remove('changed');
        }
      });
    }

    categorySelects.forEach(select => {
      select.addEventListener('change', updateChangedCount);
    });

    // Form submission validation
    document.getElementById('bulkForm').addEventListener('submit', (e) => {
      const changed = Array.from(categorySelects).filter(select => select.value !== '');
      if (changed.length === 0) {
        e.preventDefault();
        alert('Please select at least one category to update.');
        return false;
      }

      return confirm(`Update ${changed.length} merchant(s) with the selected categories?`);
    });
  </script>
</body>
</html>
