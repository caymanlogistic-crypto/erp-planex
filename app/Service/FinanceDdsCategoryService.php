<?php

namespace App\Service;

use PDO;

final class FinanceDdsCategoryService
{
    private const ALLOWED_DIRECTIONS = ['INCOME', 'EXPENSE', 'BOTH'];

    public static function fetchCategories(PDO $pdo, array $filters = []): array
    {
        $conditions = [];
        $params = [];
        if (isset($filters['direction']) && in_array($filters['direction'], self::ALLOWED_DIRECTIONS, true)) {
            $conditions[] = '(direction = :direction OR direction = \'BOTH\')';
            $params[':direction'] = $filters['direction'];
        }
        if (isset($filters['is_active'])) {
            $conditions[] = 'is_active = :is_active';
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }
        if (isset($filters['is_system'])) {
            $conditions[] = 'is_system = :is_system';
            $params[':is_system'] = $filters['is_system'] ? 1 : 0;
        }
        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $pdo->prepare("SELECT * FROM finance_dds_categories {$where} ORDER BY sort_order ASC, name ASC, id ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchActiveForDirection(PDO $pdo, string $operationType): array
    {
        if ($operationType === 'TRANSFER') return [];
        $direction = match ($operationType) {
            'INCOME' => 'INCOME',
            'EXPENSE' => 'EXPENSE',
            default => throw new \InvalidArgumentException('Недопустимый тип операции: ' . $operationType),
        };
        $stmt = $pdo->prepare("SELECT * FROM finance_dds_categories WHERE is_active=1 AND (direction=:direction OR direction='BOTH') ORDER BY sort_order ASC,name ASC,id ASC");
        $stmt->execute([':direction' => $direction]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findCategory(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM finance_dds_categories WHERE id=:id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function createCategory(PDO $pdo, array $data, array $user): int
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \InvalidArgumentException('Название статьи ДДС обязательно.');
        $direction = self::validateDirection($data['direction'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 100);
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId !== null && !self::findCategory($pdo, $parentId)) {
            throw new \InvalidArgumentException('Родительская категория не найдена.');
        }

        // `code` is retained only for compatibility with seeded/system records.
        // Users never type it: relations and UI use the numeric article ID.
        $temporaryCode = 'USR_TMP_' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = $pdo->prepare(
            'INSERT INTO finance_dds_categories
                (parent_id,code,name,direction,is_active,sort_order,is_system,created_by_user_id,created_by_role,updated_by_user_id,updated_by_role)
             VALUES (:parent_id,:code,:name,:direction,1,:sort_order,0,:created_by_user_id,:created_by_role,:updated_by_user_id,:updated_by_role)'
        );
        $stmt->execute([
            ':parent_id' => $parentId,
            ':code' => $temporaryCode,
            ':name' => $name,
            ':direction' => $direction,
            ':sort_order' => $sortOrder,
            ':created_by_user_id' => $user['id'] ?? null,
            ':created_by_role' => $user['role'] ?? null,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE finance_dds_categories SET code=? WHERE id=?')->execute(['USR_' . $id, $id]);
        return $id;
    }

    public static function updateCategory(PDO $pdo, int $id, array $data, array $user): void
    {
        $category = self::findCategory($pdo, $id);
        if (!$category) throw new \InvalidArgumentException('Статья ДДС не найдена.');
        $name = isset($data['name']) ? trim((string)$data['name']) : (string)$category['name'];
        if ($name === '') throw new \InvalidArgumentException('Название статьи ДДС обязательно.');
        $direction = $category['direction'];
        if (isset($data['direction']) && !(int)$category['is_system']) $direction = self::validateDirection($data['direction']);
        $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : (int)$category['sort_order'];
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : (int)$category['is_active'];
        $parentId = array_key_exists('parent_id', $data) ? (!empty($data['parent_id']) ? (int)$data['parent_id'] : null) : $category['parent_id'];
        if ($parentId !== null && $parentId === $id) throw new \InvalidArgumentException('Категория не может быть родителем самой себя.');
        $stmt = $pdo->prepare('UPDATE finance_dds_categories SET parent_id=:parent_id,name=:name,direction=:direction,sort_order=:sort_order,is_active=:is_active,updated_by_user_id=:uid,updated_by_role=:role WHERE id=:id');
        $stmt->execute([
            ':parent_id' => $parentId,
            ':name' => $name,
            ':direction' => $direction,
            ':sort_order' => $sortOrder,
            ':is_active' => $isActive,
            ':uid' => $user['id'] ?? null,
            ':role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    public static function setActive(PDO $pdo, int $id, bool $active, array $user): void
    {
        if (!self::findCategory($pdo, $id)) throw new \InvalidArgumentException('Статья ДДС не найдена.');
        $stmt = $pdo->prepare('UPDATE finance_dds_categories SET is_active=:active,updated_by_user_id=:uid,updated_by_role=:role WHERE id=:id');
        $stmt->execute([':active' => $active ? 1 : 0, ':uid' => $user['id'] ?? null, ':role' => $user['role'] ?? null, ':id' => $id]);
    }

    public static function categoryIsUsed(PDO $pdo, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM finance_operations WHERE dds_category_id=:id');
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function validateDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Выберите направление статьи: поступление, расход или оба направления.');
        }
        return $direction;
    }
}
