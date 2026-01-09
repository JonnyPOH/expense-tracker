<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/CategoryRepository.php';
require_once __DIR__ . '/../src/ExpenseRepository.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spent_on = trim($_POST['spent_on'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $merchant = trim($_POST['merchant'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // Validation
    if (empty($spent_on)) {
        $errors[] = 'Date is required';
    }
    if (empty($amount) || !is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Valid amount is required';
    }
    if ($category_id <= 0) {
        $errors[] = 'Category is required';
    }
    if (empty($merchant)) {
        $errors[] = 'Merchant is required';
    }

    if (empty($errors)) {
        // Convert dollar amount to pennies
        $amount_pennies = (int) round((float)$amount * 100);

        create_expense(
            $spent_on,
            $amount_pennies,
            $category_id,
            $merchant,
            $note ?: null
        );

        $success = true;
        // Redirect after successful submission
        header('Location: index.php');
        exit;
    }
}

$categories = get_all_categories();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Expense - Expense Tracker</title>
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
      max-width: 600px;
      margin: 0 auto;
    }
    .card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    h1 {
      margin: 0 0 2rem 0;
      color: #1f2937;
      font-size: 2rem;
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
    .form-group {
      margin-bottom: 1.5rem;
    }
    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: #374151;
    }
    input, select, textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 1rem;
      font-family: inherit;
      transition: border-color 0.3s;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #667eea;
    }
    textarea {
      resize: vertical;
      min-height: 100px;
    }
    .btn {
      display: inline-block;
      padding: 0.75rem 2rem;
      background: #667eea;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      width: 100%;
    }
    .btn:hover {
      background: #5568d3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    .errors {
      background: #fee2e2;
      border: 2px solid #ef4444;
      border-radius: 8px;
      padding: 1rem;
      margin-bottom: 1.5rem;
    }
    .errors ul {
      margin: 0;
      padding-left: 1.5rem;
      color: #991b1b;
    }
    .required {
      color: #ef4444;
    }
    .hint {
      font-size: 0.875rem;
      color: #6b7280;
      margin-top: 0.25rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Dashboard</a>

    <div class="card">
      <h1>Add New Expense</h1>

      <?php if (!empty($errors)): ?>
        <div class="errors">
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label for="spent_on">Date <span class="required">*</span></label>
          <input
            type="date"
            id="spent_on"
            name="spent_on"
            value="<?= htmlspecialchars($_POST['spent_on'] ?? date('Y-m-d')) ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="merchant">Merchant <span class="required">*</span></label>
          <input
            type="text"
            id="merchant"
            name="merchant"
            placeholder="e.g., Starbucks, Walmart, Shell"
            value="<?= htmlspecialchars($_POST['merchant'] ?? '') ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="amount">Amount ($) <span class="required">*</span></label>
          <input
            type="number"
            id="amount"
            name="amount"
            step="0.01"
            min="0.01"
            placeholder="0.00"
            value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
            required
          >
          <div class="hint">Enter the amount in dollars (e.g., 25.50)</div>
        </div>

        <div class="form-group">
          <label for="category_id">Category <span class="required">*</span></label>
          <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $cat): ?>
              <option
                value="<?= (int)$cat['id'] ?>"
                <?= (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>
              >
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="note">Note (optional)</label>
          <textarea
            id="note"
            name="note"
            placeholder="Add any additional details..."
          ><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn">Add Expense</button>
      </form>
    </div>
  </div>
</body>
</html>
