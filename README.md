# 💰 Expense Tracker

A simple yet powerful PHP-based expense tracking application with SQLite database and interactive analytics.

## Features

- ✅ Track income and expenses
- 📊 Import transactions from CSV files
- 📈 Interactive analytics with charts
- 🏷️ Category management with custom categories
- 🔄 Auto-categorization of imported transactions
- 💡 Click-to-drill down on category expenses
- ✏️ Recategorize expenses directly from analytics
- 📱 Responsive design

## Tech Stack

- **Backend**: PHP 8.x with SQLite
- **Frontend**: HTML5, CSS3, JavaScript
- **Charts**: Chart.js
- **Database**: SQLite (no setup required!)

## Installation

1. Clone this repository:
```bash
git clone https://github.com/YOUR_USERNAME/expense-tracker.git
cd expense-tracker
```

2. Initialize the database:
```bash
sqlite3 data/app.db < data/migrate.sql
```

3. Start the PHP development server:
```bash
php -S localhost:8000 -t public/
```

4. Open your browser to `http://localhost:8000`

## Usage

### Adding Expenses
1. Click "Add New Expense" on the dashboard
2. Fill in date, amount, merchant, category, and optional notes
3. Select "Expense" or "Income" type

### Importing from CSV
1. Click "Import CSV" on the dashboard
2. Upload your bank statement (supports common formats)
3. Transactions are automatically categorized based on merchant names
4. Review and recategorize as needed

### Analytics
1. Click "View Analytics" to see charts and trends
2. Click on any category in the pie chart to view top expenses
3. Select expenses and recategorize them in bulk

### Category Management
1. Click "Manage Categories" to add/edit/delete categories
2. Categories are used for auto-categorization and reporting

## Project Structure

```
expense-tracker/
├── data/
│   ├── migrate.sql       # Database schema
│   └── app.db           # SQLite database (gitignored)
├── public/
│   ├── index.php        # Dashboard
│   ├── add-expense.php  # Add expense form
│   ├── import.php       # CSV import
│   ├── analytics.php    # Analytics & charts
│   ├── manage-categories.php
│   ├── get-category-expenses.php
│   ├── recategorize-expenses.php
│   └── styles.css       # Shared styles
├── src/
│   ├── db.php                    # Database connection
│   ├── ExpenseRepository.php     # Expense CRUD
│   ├── CategoryRepository.php    # Category CRUD
│   ├── AnalyticsService.php      # Analytics queries
│   └── ImportService.php         # CSV import logic
└── views/                        # (Reserved for future templates)
```

## Features in Detail

### Auto-Categorization
The import service automatically categorizes transactions based on merchant names:
- Groceries: Tesco, Sainsbury's, etc.
- Transport: Uber, TFL, etc.
- Eating Out: Restaurants, cafes
- Bills: Subscriptions, utilities
- And more...

### Interactive Analytics
- Monthly income vs expenses trends
- Category spending breakdown
- Top merchants analysis
- Weekday spending patterns
- Click-to-drill down on categories

## Database Schema

The app uses SQLite with two main tables:
- `categories` - Expense/income categories
- `expenses` - Transaction records with foreign key to categories

## Contributing

Feel free to submit issues and pull requests!

## License

MIT License - feel free to use this for personal or commercial projects.

## Author

Built with ❤️ by [Your Name]
