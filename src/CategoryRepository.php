<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function get_all_categories(): array
{
    $stmt = db()->query('SELECT id, name FROM categories ORDER BY name ASC;');
    return $stmt->fetchAll();
}

function get_category_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();
    return $result ?: null;
}
