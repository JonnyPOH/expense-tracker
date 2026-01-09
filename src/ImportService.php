<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ExpenseRepository.php';
require_once __DIR__ . '/CategoryRepository.php';

class ImportService
{
    // Keyword-based categorization rules
    private const CATEGORY_RULES = [
        'Groceries' => [
            'TESCO', 'SAINSBURY', 'ASDA', 'MORRISONS', 'ALDI', 'LIDL',
            'WAITROSE', 'M&S', 'MARKS & SPENCER', 'MARKS&SPENCER', 'CO-OP', 'ICELAND',
            'SUPERMARKET', 'GROCERY', 'WH SMITH', 'WHSMITH', 'SPAR', 'CONVENIENCE'
        ],
        'Transport' => [
            'UBER', 'TFL', 'TRANSPORT', 'TRAINLINE', 'NATIONAL RAIL',
            'OYSTER', 'PETROL', 'SHELL', 'BP', 'ESSO', 'PARKING',
            'BUS', 'TUBE', 'TAXI', 'CAR', 'HEATHROW', 'AIRPORT'
        ],
        'Eating Out' => [
            'RESTAURANT', 'CAFE', 'COFFEE', 'STARBUCKS', 'COSTA', 'PRET', 'LEON',
            'MCDONALD', 'KFC', 'SUBWAY', 'PIZZA', 'NANDO', 'BURGER',
            'GREGGS', 'DELIVEROO', 'JUST EAT', 'UBER EATS',
            'TENPERCENTCOFFEE', 'BOOCHON', 'SEOULDIJAINJ', 'TOTT', 'MADDON',
            'SEONGSU', 'CHICKEN', 'BAR', 'GROCER', 'EATERY', 'EATS',
            'TERRACE', 'JACKS BAR', 'JONES THE GROCER', 'BEANBERRY',
            'ANTHRAC', 'IJOOMAK', 'GAMSUNG', 'KOOKLIBJOONGA', 'YO ',
            'ZETTLE', 'STARFIELD', 'MALL', 'FOOD', 'DINING'
        ],
        'Bills' => [
            'COUNCIL TAX', 'WATER', 'ELECTRIC', 'GAS', 'INTERNET',
            'BROADBAND', 'PHONE', 'MOBILE', 'TV LICENCE', 'SKY',
            'NETFLIX', 'SPOTIFY', 'AMAZON PRIME', 'GYM', 'THE GYM',
            'DATACAMP', 'COURSERA', 'GITHUB', 'MEDIUM', 'SUBSCRIPTION'
        ],
        'Rent' => [
            'RENT', 'MORTGAGE', 'ESTATE', 'LETTING', 'PROPERTY'
        ],
        'Travel' => [
            'HOTEL', 'AIRBNB', 'BOOKING.COM', 'EXPEDIA', 'AIRLINE',
            'RYANAIR', 'EASYJET', 'BRITISH AIRWAYS', 'HOLIDAY',
            'KICC_HAEOI', 'EXIMBAY', 'HYUNDAI', 'DEPARTMENT'
        ],
        'Fun' => [
            'CINEMA', 'THEATRE', 'GAME', 'STEAM', 'PLAYSTATION',
            'XBOX', 'NINTENDO', 'APPLE MUSIC',
            'GOOGLE PLAY', 'PLAY APPS', 'TATE', 'MUSEUM', 'GALLERY',
            'KYOBO BOOKS', 'BOOKS', 'ENTERTAINMENT'
        ]
    ];

    public function categorizeTransaction(string $description): int
    {
        $description = strtoupper(trim($description));

        // Try to match with category rules
        foreach (self::CATEGORY_RULES as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, strtoupper($keyword)) !== false) {
                    $category = $this->getCategoryByName($categoryName);
                    if ($category) {
                        return (int) $category['id'];
                    }
                }
            }
        }

        // Default to "Other" if no match found
        $other = $this->getCategoryByName('Other');
        return $other ? (int) $other['id'] : 1;
    }

    private function getCategoryByName(string $name): ?array
    {
        $stmt = db()->prepare('SELECT id, name FROM categories WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function parseCSV(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $transactions = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new Exception("Could not open file: $filePath");
        }

        // Skip header row
        fgetcsv($handle, 0, ',', '"', '');

        while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($data) < 8) {
                continue; // Skip malformed rows
            }

            // Process both debit (expenses) and credit (income) transactions
            $debitAmount = trim($data[5]);
            $creditAmount = trim($data[6]);

            // Determine if it's expense or income based on which column has data
            $isExpense = !empty($debitAmount) && empty($creditAmount);
            $isIncome = !empty($creditAmount);

            if (!$isExpense && !$isIncome) {
                continue;
            }

            $transactions[] = [
                'date' => $this->parseDate($data[0]),
                'type' => $data[1],
                'description' => $data[4],
                'amount' => $isExpense ? (float) $debitAmount : (float) $creditAmount,
                'transaction_type' => $isExpense ? 'expense' : 'income',
            ];
        }

        fclose($handle);
        return $transactions;
    }

    private function parseDate(string $date): string
    {
        // Convert DD/MM/YYYY to YYYY-MM-DD
        $parts = explode('/', $date);
        if (count($parts) === 3) {
            return sprintf('%s-%s-%s', $parts[2], $parts[1], $parts[0]);
        }
        return $date;
    }

    public function importTransactions(array $transactions): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($transactions as $transaction) {
            try {
                // Check for duplicate
                if ($this->isDuplicate($transaction)) {
                    $skipped++;
                    continue;
                }

                // Auto-categorize
                $categoryId = $this->categorizeTransaction($transaction['description']);

                // Convert to pennies
                $amountPennies = (int) round($transaction['amount'] * 100);

                // Create expense or income
                create_expense(
                    $transaction['date'],
                    $amountPennies,
                    $categoryId,
                    $transaction['description'],
                    "Imported from CSV",
                    $transaction['transaction_type']
                );

                $imported++;
            } catch (Exception $e) {
                $errors[] = "Error importing {$transaction['description']}: " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($transactions)
        ];
    }

    private function isDuplicate(array $transaction): bool
    {
        $stmt = db()->prepare('
            SELECT COUNT(*) as count
            FROM expenses
            WHERE spent_on = :date
            AND amount_pennies = :amount
            AND merchant = :merchant
        ');

        $stmt->execute([
            'date' => $transaction['date'],
            'amount' => (int) round($transaction['amount'] * 100),
            'merchant' => $transaction['description']
        ]);

        $result = $stmt->fetch();
        return (int) $result['count'] > 0;
    }
}
