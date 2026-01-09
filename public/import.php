<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/ImportService.php';

$message = '';
$messageType = '';
$stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['csv_file']['tmp_name'];

        try {
            $importService = new ImportService();

            // Parse CSV
            $transactions = $importService->parseCSV($tmpPath);

            // Import transactions
            $stats = $importService->importTransactions($transactions);

            $message = "Import completed! {$stats['imported']} transactions imported, {$stats['skipped']} skipped as duplicates.";
            $messageType = 'success';

        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Please select a CSV file to upload.";
        $messageType = 'error';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Import CSV - Expense Tracker</title>
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
      max-width: 800px;
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
    .upload-area {
      border: 3px dashed #cbd5e1;
      border-radius: 12px;
      padding: 3rem;
      text-align: center;
      transition: all 0.3s;
      cursor: pointer;
      margin: 2rem 0;
    }
    .upload-area:hover {
      border-color: #667eea;
      background: #f8fafc;
    }
    .upload-area.dragging {
      border-color: #667eea;
      background: #eef2ff;
    }
    .upload-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
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
    }
    .btn:hover {
      background: #5568d3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
    .message.error {
      background: #fee2e2;
      border: 2px solid #ef4444;
      color: #991b1b;
    }
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }
    .stat-box {
      padding: 1rem;
      background: #f9fafb;
      border-radius: 8px;
      text-align: center;
    }
    .stat-number {
      font-size: 2rem;
      font-weight: bold;
      color: #667eea;
    }
    .stat-label {
      font-size: 0.875rem;
      color: #6b7280;
      text-transform: uppercase;
      margin-top: 0.5rem;
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
    .info-box ul {
      margin: 0.5rem 0;
      padding-left: 1.5rem;
    }
    input[type="file"] {
      display: none;
    }
    .file-name {
      margin-top: 1rem;
      color: #059669;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-link">← Back to Dashboard</a>

    <div class="card">
      <h1>📊 Import Bank Statement</h1>

      <div class="info-box">
        <h3>How it works:</h3>
        <ul>
          <li>Upload your Lloyds Bank CSV statement</li>
          <li>Transactions are automatically categorized using smart keyword matching</li>
          <li>Duplicate transactions are detected and skipped</li>
          <li>Only debit transactions (expenses) are imported</li>
        </ul>
      </div>

      <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <?php if ($stats): ?>
        <div class="stats">
          <div class="stat-box">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Found</div>
          </div>
          <div class="stat-box">
            <div class="stat-number"><?= $stats['imported'] ?></div>
            <div class="stat-label">Imported</div>
          </div>
          <div class="stat-box">
            <div class="stat-number"><?= $stats['skipped'] ?></div>
            <div class="stat-label">Skipped</div>
          </div>
        </div>

        <div style="margin-top: 2rem; text-align: center;">
          <a href="analytics.php" class="btn">📈 View Analytics</a>
          <a href="index.php" class="btn" style="background: #6b7280;">Go to Dashboard</a>
        </div>
      <?php else: ?>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <label for="csv_file" class="upload-area" id="uploadArea">
            <div class="upload-icon">📁</div>
            <h3>Click to upload or drag and drop</h3>
            <p>Lloyds Bank CSV statements only</p>
            <p class="file-name" id="fileName"></p>
          </label>
          <input type="file" id="csv_file" name="csv_file" accept=".csv" required>

          <div style="text-align: center; margin-top: 2rem;">
            <button type="submit" class="btn">Import Transactions</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const fileInput = document.getElementById('csv_file');
    const uploadArea = document.getElementById('uploadArea');
    const fileName = document.getElementById('fileName');

    uploadArea.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
      if (e.target.files.length > 0) {
        fileName.textContent = `Selected: ${e.target.files[0].name}`;
      }
    });

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadArea.classList.add('dragging');
    });

    uploadArea.addEventListener('dragleave', () => {
      uploadArea.classList.remove('dragging');
    });

    uploadArea.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadArea.classList.remove('dragging');

      if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileName.textContent = `Selected: ${e.dataTransfer.files[0].name}`;
      }
    });
  </script>
</body>
</html>
