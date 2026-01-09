<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/AnalyticsService.php';
require_once __DIR__ . '/../src/ExpenseRepository.php';
require_once __DIR__ . '/../src/CategoryRepository.php';

$analytics = new AnalyticsService();

$monthlyTrends = $analytics->getMonthlyTrends();
$categoryBreakdown = $analytics->getCategoryBreakdown();
$categoryTrends = $analytics->getCategoryTrends(12);
$topMerchants = $analytics->getTopMerchants(10);
$weekdayAnalysis = $analytics->getWeekdayAnalysis();
$stats = $analytics->getSpendingStats();
$monthComparison = $analytics->getMonthlyComparison();

// Prepare data for charts
$monthLabels = array_map(fn($m) => $m['month'], $monthlyTrends);
$monthExpenses = array_map(fn($m) => (int)$m['expenses'] / 100, $monthlyTrends);
$monthIncome = array_map(fn($m) => (int)$m['income'] / 100, $monthlyTrends);

$categoryLabels = array_map(fn($c) => $c['category'], $categoryBreakdown);
$categoryValues = array_map(fn($c) => (int)$c['total'] / 100, $categoryBreakdown);

$weekdayLabels = array_map(fn($w) => $w['weekday'], $weekdayAnalysis);
$weekdayValues = array_map(fn($w) => (int)$w['total'] / 100, $weekdayAnalysis);

// Prepare category trends data - separate by income and expense
$categoryExpenseTrends = [];
$categoryIncomeTrends = [];

foreach ($categoryTrends as $row) {
    $cat = $row['category'];
    $type = $row['transaction_type'] ?? 'expense';
    $month = $row['month'] ?? '';

    if ($type === 'income') {
        if (!isset($categoryIncomeTrends[$cat])) {
            $categoryIncomeTrends[$cat] = [];
        }
        if (!empty($month)) {
            $categoryIncomeTrends[$cat][$month] = (int)$row['total'] / 100;
        }
    } else {
        if (!isset($categoryExpenseTrends[$cat])) {
            $categoryExpenseTrends[$cat] = [];
        }
        if (!empty($month)) {
            $categoryExpenseTrends[$cat][$month] = (int)$row['total'] / 100;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Analytics - Expense Tracker</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
      max-width: 1400px;
      margin: 0 auto;
    }
    h1 {
      margin: 0 0 2rem 0;
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
    .card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .card h2 {
      margin: 0 0 1.5rem 0;
      color: #1f2937;
    }
    .stats-grid {
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
    .stat-change {
      margin-top: 0.5rem;
      font-size: 0.875rem;
      font-weight: 600;
    }
    .stat-change.positive {
      color: #ef4444;
    }
    .stat-change.negative {
      color: #10b981;
    }
    .charts-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
      gap: 2rem;
    }
    .chart-container {
      position: relative;
      height: 300px;
    }
    .merchant-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .merchant-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    .merchant-item:last-child {
      border-bottom: none;
    }
    .merchant-name {
      font-weight: 600;
      color: #374151;
    }
    .merchant-stats {
      text-align: right;
    }
    .merchant-amount {
      font-weight: bold;
      color: #059669;
    }
    .merchant-count {
      font-size: 0.875rem;
      color: #6b7280;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 1000;
      padding: 2rem;
      overflow-y: auto;
    }
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      max-width: 800px;
      width: 100%;
      max-height: 80vh;
      overflow-y: auto;
      position: relative;
    }
    .modal-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: none;
      border: none;
      font-size: 2rem;
      cursor: pointer;
      color: #6b7280;
      padding: 0.5rem;
      line-height: 1;
    }
    .modal-close:hover {
      color: #1f2937;
    }
    .modal h2 {
      margin: 0 0 1.5rem 0;
      color: #1f2937;
    }
    .expense-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .expense-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      border-bottom: 1px solid #e5e7eb;
      gap: 1rem;
    }
    .expense-item:last-child {
      border-bottom: none;
    }
    .expense-item.selected {
      background-color: #f3f4f6;
    }
    .expense-checkbox {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }
    .expense-info {
      flex: 1;
    }
    .expense-merchant {
      font-weight: 600;
      color: #374151;
      margin-bottom: 0.25rem;
    }
    .expense-date {
      font-size: 0.875rem;
      color: #6b7280;
    }
    .expense-amount {
      font-weight: bold;
      color: #ef4444;
      font-size: 1.125rem;
    }
    .clickable-hint {
      font-size: 0.875rem;
      color: #6b7280;
      margin-top: 0.5rem;
      font-style: italic;
    }
    .recategorize-controls {
      display: none;
      padding: 1rem;
      background: #f9fafb;
      border-radius: 8px;
      margin-top: 1rem;
      gap: 1rem;
      align-items: center;
    }
    .recategorize-controls.active {
      display: flex;
    }
    .recategorize-controls select {
      flex: 1;
      padding: 0.5rem;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 1rem;
    }
    .recategorize-controls button {
      padding: 0.5rem 1.5rem;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
    }
    .recategorize-controls button:hover {
      background: #5568d3;
    }
    .recategorize-controls button:disabled {
      background: #9ca3af;
      cursor: not-allowed;
    }
    @media (max-width: 768px) {
      .charts-grid {
        grid-template-columns: 1fr;
      }
      .modal {
        padding: 1rem;
      }
      .recategorize-controls {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Dashboard</a>
    <h1>📈 Analytics & Trends</h1>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Income</div>
        <div class="stat-value" style="color: #10b981;"><?= format_money((int)$stats['total_income']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value" style="color: #ef4444;"><?= format_money((int)$stats['total_spent']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Net Balance</div>
        <?php $netBalance = (int)$stats['total_income'] - (int)$stats['total_spent']; ?>
        <div class="stat-value" style="color: <?= $netBalance >= 0 ? '#10b981' : '#ef4444' ?>;">
          <?= format_money(abs($netBalance)) ?> <?= $netBalance >= 0 ? '↑' : '↓' ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Transactions</div>
        <div class="stat-value"><?= number_format((int)$stats['total_transactions']) ?></div>
      </div>
    </div>

    <?php if (!empty($monthComparison)): ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value"><?= format_money((int)$monthComparison['current']['total']) ?></div>
        <?php
          $change = $monthComparison['percent_change'];
          $changeClass = $change > 0 ? 'positive' : 'negative';
          $changeSymbol = $change > 0 ? '↑' : '↓';
        ?>
        <div class="stat-change <?= $changeClass ?>">
          <?= $changeSymbol ?> <?= abs(round($change, 1)) ?>% vs last month
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Income vs Expenses Over Time</h2>
      <div class="chart-container">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>

    <div class="card">
      <h2>💸 Category Spending Trends (Last 12 Months)</h2>
      <div class="chart-container">
        <canvas id="categoryExpenseTrendsChart"></canvas>
      </div>
    </div>

    <div class="card">
      <h2>💰 Category Income Trends (Last 12 Months)</h2>
      <div class="chart-container">
        <canvas id="categoryIncomeTrendsChart"></canvas>
      </div>
    </div>

    <div class="charts-grid">
      <div class="card">
        <h2>Spending by Category</h2>
        <p class="clickable-hint">💡 Click on a category to see top expenses</p>
        <div class="chart-container">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>

      <div class="card">
        <h2>Spending by Weekday</h2>
        <div class="chart-container">
          <canvas id="weekdayChart"></canvas>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Top 10 Merchants</h2>
      <ul class="merchant-list">
        <?php foreach ($topMerchants as $merchant): ?>
          <li class="merchant-item">
            <div class="merchant-name"><?= htmlspecialchars($merchant['merchant']) ?></div>
            <div class="merchant-stats">
              <div class="merchant-amount"><?= format_money((int)$merchant['total']) ?></div>
              <div class="merchant-count"><?= (int)$merchant['transaction_count'] ?> transactions</div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- Modal for category details -->
  <div id="categoryModal" class="modal">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">&times;</button>
      <h2 id="modalTitle"></h2>
      <ul id="modalExpenseList" class="expense-list"></ul>
      <div id="recategorizeControls" class="recategorize-controls">
        <select id="newCategorySelect">
          <option value="">Select new category...</option>
          <?php foreach (get_all_categories() as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button id="recategorizeBtn" onclick="recategorizeSelected()">Recategorize Selected</button>
      </div>
    </div>
  </div>

  <script>
    // Monthly Income vs Expenses Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [
          {
            label: 'Income',
            data: <?= json_encode($monthIncome) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
          },
          {
            label: 'Expenses',
            data: <?= json_encode($monthExpenses) ?>,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '$' + value.toFixed(0);
              }
            }
          }
        }
      }
    });

    // Category Expense Trends Chart
    const categoryExpenseTrendCtx = document.getElementById('categoryExpenseTrendsChart').getContext('2d');

    <?php
    // Define colors in PHP
    $colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fee140', '#30cfd0', '#ff6b6b', '#51cf66'];

    // Get all unique months across all expense categories
    $allExpenseMonths = [];
    foreach ($categoryExpenseTrends as $cat => $months) {
        $allExpenseMonths = array_merge($allExpenseMonths, array_keys($months));
    }
    $allExpenseMonths = array_unique(array_filter($allExpenseMonths));
    sort($allExpenseMonths);

    // Prepare datasets for each expense category
    $expenseDatasets = [];
    $colorIndex = 0;
    foreach ($categoryExpenseTrends as $cat => $months) {
        $data = [];
        foreach ($allExpenseMonths as $month) {
            $data[] = isset($months[$month]) ? $months[$month] : 0;
        }
        $expenseDatasets[] = [
            'label' => $cat,
            'data' => $data,
            'borderColor' => $colors[$colorIndex % count($colors)],
            'borderWidth' => 2,
            'fill' => false,
            'tension' => 0.4
        ];
        $colorIndex++;
    }
    ?>

    new Chart(categoryExpenseTrendCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($allExpenseMonths) ?>,
        datasets: <?= json_encode($expenseDatasets) ?>
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'right'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '$' + value.toFixed(0);
              }
            }
          }
        }
      }
    });

    // Category Income Trends Chart
    const categoryIncomeTrendCtx = document.getElementById('categoryIncomeTrendsChart').getContext('2d');

    <?php
    // Get all unique months across all income categories
    $allIncomeMonths = [];
    foreach ($categoryIncomeTrends as $cat => $months) {
        $allIncomeMonths = array_merge($allIncomeMonths, array_keys($months));
    }
    $allIncomeMonths = array_unique(array_filter($allIncomeMonths));
    sort($allIncomeMonths);

    // Prepare datasets for each income category
    $incomeDatasets = [];
    $colorIndex = 0;
    foreach ($categoryIncomeTrends as $cat => $months) {
        $data = [];
        foreach ($allIncomeMonths as $month) {
            $data[] = isset($months[$month]) ? $months[$month] : 0;
        }
        $incomeDatasets[] = [
            'label' => $cat,
            'data' => $data,
            'borderColor' => $colors[$colorIndex % count($colors)],
            'borderWidth' => 2,
            'fill' => false,
            'tension' => 0.4
        ];
        $colorIndex++;
    }
    ?>

    new Chart(categoryIncomeTrendCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($allIncomeMonths) ?>,
        datasets: <?= json_encode($incomeDatasets) ?>
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'right'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '$' + value.toFixed(0);
              }
            }
          }
        }
      }
    });

    // Category Breakdown Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
          data: <?= json_encode($categoryValues) ?>,
          backgroundColor: [
            '#667eea', '#764ba2', '#f093fb', '#4facfe',
            '#43e97b', '#fa709a', '#fee140', '#30cfd0'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right'
          }
        },
        onClick: (event, activeElements) => {
          if (activeElements.length > 0) {
            const index = activeElements[0].index;
            const category = categoryChart.data.labels[index];
            showCategoryExpenses(category);
          }
        }
      }
    });

    // Function to show category expenses
    async function showCategoryExpenses(category) {
      try {
        const response = await fetch(`get-category-expenses.php?category=${encodeURIComponent(category)}`);
        const data = await response.json();

        document.getElementById('modalTitle').textContent = `Top Expenses - ${category}`;

        const list = document.getElementById('modalExpenseList');
        list.innerHTML = '';

        if (data.expenses && data.expenses.length > 0) {
          data.expenses.forEach(expense => {
            const li = document.createElement('li');
            li.className = 'expense-item';
            li.innerHTML = `
              <input type="checkbox" class="expense-checkbox" data-expense-id="${expense.id}" onchange="toggleRecategorizeControls()">
              <div class="expense-info">
                <div class="expense-merchant">${expense.merchant}</div>
                <div class="expense-date">${expense.spent_on}${expense.note ? ' • ' + expense.note : ''}</div>
              </div>
              <div class="expense-amount">${expense.amount}</div>
            `;
            list.appendChild(li);
          });
        } else {
          list.innerHTML = '<li class="expense-item">No expenses found</li>';
        }

        // Reset recategorize controls
        document.getElementById('recategorizeControls').classList.remove('active');
        document.getElementById('newCategorySelect').value = '';

        document.getElementById('categoryModal').classList.add('active');
      } catch (error) {
        console.error('Error fetching category expenses:', error);
        alert('Failed to load expenses');
      }
    }

    function toggleRecategorizeControls() {
      const checkboxes = document.querySelectorAll('.expense-checkbox:checked');
      const controls = document.getElementById('recategorizeControls');

      if (checkboxes.length > 0) {
        controls.classList.add('active');
      } else {
        controls.classList.remove('active');
      }
    }

    async function recategorizeSelected() {
      const checkboxes = document.querySelectorAll('.expense-checkbox:checked');
      const newCategoryId = document.getElementById('newCategorySelect').value;

      if (!newCategoryId) {
        alert('Please select a category');
        return;
      }

      if (checkboxes.length === 0) {
        alert('Please select at least one expense');
        return;
      }

      const expenseIds = Array.from(checkboxes).map(cb => cb.dataset.expenseId);

      try {
        const response = await fetch('recategorize-expenses.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            expense_ids: expenseIds,
            new_category_id: newCategoryId
          })
        });

        const result = await response.json();

        if (result.success) {
          alert(`Successfully recategorized ${result.updated} expense(s)`);
          closeModal();
          // Reload the page to refresh the charts
          location.reload();
        } else {
          alert('Error: ' + (result.error || 'Failed to recategorize'));
        }
      } catch (error) {
        console.error('Error recategorizing expenses:', error);
        alert('Failed to recategorize expenses');
      }
    }

    function closeModal() {
      document.getElementById('categoryModal').classList.remove('active');
    }

    // Close modal on background click
    document.getElementById('categoryModal').addEventListener('click', (e) => {
      if (e.target.id === 'categoryModal') {
        closeModal();
      }
    });

    // Weekday Analysis Chart
    const weekdayCtx = document.getElementById('weekdayChart').getContext('2d');
    new Chart(weekdayCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($weekdayLabels) ?>,
        datasets: [{
          label: 'Spending by Day',
          data: <?= json_encode($weekdayValues) ?>,
          backgroundColor: '#667eea',
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '$' + value.toFixed(0);
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
