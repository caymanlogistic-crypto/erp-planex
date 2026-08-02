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

        $sql = "SELECT * FROM finance_dds_categories {$where} ORDER BY sort_order ASC, name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchActiveForDirection(PDO $pdo, string $operationType): array
    {
        if ($operationType === 'TRANSFER') {
            return [];
        }
        if ($operationType === 'INCOME') {
            $direction = 'INCOME';
        } elseif ($operationType === 'EXPENSE') {
            $direction = 'EXPENSE';
        } else {
            throw new \InvalidArgumentException('Недопустимый тип операции: ' . $operationType);
        }
        $stmt = $pdo->prepare(
            "SELECT * FROM finance_dds_categories
             WHERE is_active = 1
                AND (direction = :direction OR direction = 'BOTH')
             ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute([':direction' => $direction]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findCategory(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM finance_dds_categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function createCategory(PDO $pdo, array $data, array $user): int
    {
        $code = self::validateCode($data['code'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Название статьи ДДС обязательно.');
        }
        $direction = self::validateDirection($data['direction'] ?? '');
        $sortOrder = (int) ($data['sort_order'] ?? 100);
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

        if ($parentId !== null) {
            $parent = self::findCategory($pdo, $parentId);
            if (!$parent) {
                throw new \InvalidArgumentException('Родительская категория не найдена.');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO finance_dds_categories
                (parent_id, code, name, direction, is_active, sort_order, is_system,
                 created_by_user_id, created_by_role, updated_by_user_id, updated_by_role)
             VALUES
                (:parent_id, :code, :name, :direction, 1, :sort_order, 0,
                 :created_by_user_id, :created_by_role, :updated_by_user_id, :updated_by_role)'
        );
        $stmt->execute([
            ':parent_id' => $parentId,
            ':code' => $code,
            ':name' => $name,
            ':direction' => $direction,
            ':sort_order' => $sortOrder,
            ':created_by_user_id' => $user['id'] ?? null,
            ':created_by_role' => $user['role'] ?? null,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateCategory(PDO $pdo, int $id, array $data, array $user): void
    {
        $category = self::findCategory($pdo, $id);
        if (!$category) {
            throw new \InvalidArgumentException('Статья ДДС не найдена.');
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : $category['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('Название статьи ДДС обязательно.');
        }

        $direction = $category['direction'];
        if (isset($data['direction']) && !$category['is_system']) {
            $direction = self::validateDirection($data['direction']);
        }
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) $category['sort_order'];
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : (int) $category['is_active'];

        $code = $category['code'];
        if (isset($data['code']) && !$category['is_system']) {
            $code = self::validateCode($data['code']);
        }

        $parentId = array_key_exists('parent_id', $data)
            ? (!empty($data['parent_id']) ? (int) $data['parent_id'] : null)
            : $category['parent_id'];

        if ($parentId !== null && (int) $parentId === $id) {
            throw new \InvalidArgumentException('Категория не может быть родителем самой себя.');
        }

        $stmt = $pdo->prepare(
            'UPDATE finance_dds_categories
                SET parent_id = :parent_id, code = :code, name = :name, direction = :direction,
                    sort_order = :sort_order, is_active = :is_active,
                    updated_by_user_id = :updated_by_user_id, updated_by_role = :updated_by_role
              WHERE id = :id'
        );
        $stmt->execute([
            ':parent_id' => $parentId,
            ':code' => $code,
            ':name' => $name,
            ':direction' => $direction,
            ':sort_order' => $sortOrder,
            ':is_active' => $isActive,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    public static function setActive(PDO $pdo, int $id, bool $active, array $user): void
    {
        $category = self::findCategory($pdo, $id);
        if (!$category) {
            throw new \InvalidArgumentException('Статья ДДС не найдена.');
        }

        $stmt = $pdo->prepare(
            'UPDATE finance_dds_categories
                SET is_active = :is_active,
                    updated_by_user_id = :updated_by_user_id,
                    updated_by_role = :updated_by_role
              WHERE id = :id'
        );
        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':updated_by_user_id' => $user['id'] ?? null,
            ':updated_by_role' => $user['role'] ?? null,
            ':id' => $id,
        ]);
    }

    public static function categoryIsUsed(PDO $pdo, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM finance_operations WHERE dds_category_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function validateCode(string $code): string
    {
        $code = trim(strtoupper($code));
        if ($code === '') {
            throw new \InvalidArgumentException('Код статьи ДДС обязателен.');
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) {
            throw new \InvalidArgumentException('Код должен содержать только латинские буквы, цифры и символ подчёркивания.');
        }
        return $code;
    }

    private static function validateDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Направление должно быть INCOME, EXPENSE или BOTH.');
        }
        return $direction;
    }
}
