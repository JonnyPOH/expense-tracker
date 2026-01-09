PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS expenses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  spent_on TEXT NOT NULL,                 -- store as ISO date string: YYYY-MM-DD
  amount_pennies INTEGER NOT NULL,         -- avoid float bugs
  category_id INTEGER NOT NULL,
  merchant TEXT NOT NULL,
  note TEXT,
  transaction_type TEXT NOT NULL DEFAULT 'expense', -- 'expense' or 'income'
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Seed categories (safe to re-run thanks to INSERT OR IGNORE)
INSERT OR IGNORE INTO categories (name) VALUES
  ('Groceries'),
  ('Transport'),
  ('Eating Out'),
  ('Rent'),
  ('Bills'),
  ('Fun'),
  ('Travel'),
  ('Salary'),
  ('Freelance'),
  ('Investment'),
  ('Gift'),
  ('Other');
