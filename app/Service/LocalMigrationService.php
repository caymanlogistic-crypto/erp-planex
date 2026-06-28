<?php

namespace App\Service;

use PDO;

final class LocalMigrationService
{
    public static function ensureDocumentTypeRecord(PDO $localPdo, string $name, string $code, string $entityType, string $category = 'predefined'): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        try {
            $insert = $localPdo->prepare(
                'INSERT IGNORE INTO document_types (name, code, entity_type, category, created_by_user_id, created_by_role)
                 VALUES (:name, :code, :entity_type, :category, :user_id, :role)'
            );
            $insert->execute([
                ':name' => $name,
                ':code' => $code !== '' ? $code : null,
                ':entity_type' => $entityType !== '' ? $entityType : null,
                ':category' => $category,
                ':user_id' => (int) ($_SESSION['user_id'] ?? 0),
                ':role' => (string) ($_SESSION['role_code'] ?? 'system'),
            ]);
        } catch (\Throwable $e) {
        }

        $lookup = $localPdo->prepare(
            'SELECT id
               FROM document_types
              WHERE name = :name
                AND ((entity_type = :entity_type_value) OR (entity_type IS NULL AND :entity_type_null IS NULL))
              ORDER BY id DESC
              LIMIT 1'
        );
        $lookup->execute([
            ':name' => $name,
            ':entity_type_value' => $entityType !== '' ? $entityType : null,
            ':entity_type_null' => $entityType !== '' ? $entityType : null,
        ]);

        $id = $lookup->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public static function apply(PDO $localPdo): void
    {
        for ($i = 1; $i <= 39; $i++) {
            $pattern = base_path('database/migrations-local/' . sprintf('%03d', $i) . '_*.sql');
            $files = glob($pattern);
            if (!$files) {
                continue;
            }

            $file = $files[0];
            $fileName = basename($file);

            try {
                $sql = file_get_contents($file);
                if ($sql !== false && trim($sql) !== '') {
                    $localPdo->exec($sql);
                }
            } catch (\Exception $e) {
                error_log('Local migration ' . $fileName . ': ' . $e->getMessage());
            }
        }
    }
}
