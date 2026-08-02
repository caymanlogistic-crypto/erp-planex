<?php

namespace App\Service;

use PDO;

final class BankStatementSettingsService
{
    public static function getSettings(PDO $localPdo): array
    {
        $stmt = $localPdo->query(
            'SELECT * FROM `bank_statement_settings` ORDER BY `id` ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['has_password'] = $row['password_encrypted'] !== '';
            unset($row['password_encrypted']);
        }
        return $rows;
    }

    public static function getActiveSettings(PDO $localPdo): array
    {
        $stmt = $localPdo->prepare(
            'SELECT * FROM `bank_statement_settings` WHERE `is_active` = 1 ORDER BY `id` ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function upsertSettings(PDO $localPdo, array $data): int
    {
        $id = isset($data['id']) && $data['id'] > 0 ? (int)$data['id'] : null;

        if ($id) {
            $existing = self::getById($localPdo, $id);
        } else {
            $existing = null;
        }

        $passwordEncrypted = '';
        if (!empty($data['password'])) {
            $passwordEncrypted = cryptoEncrypt($data['password']);
        } elseif ($existing) {
            $passwordEncrypted = $existing['password_encrypted'];
        }

        if ($id) {
            $stmt = $localPdo->prepare(
                'UPDATE `bank_statement_settings` SET
                    `bank_code` = :bank_code,
                    `imap_host` = :imap_host,
                    `imap_port` = :imap_port,
                    `imap_ssl` = :imap_ssl,
                    `username` = :username,
                    `password_encrypted` = :password_encrypted,
                    `sender_filter` = :sender_filter,
                    `subject_filter` = :subject_filter,
                    `is_active` = :is_active
                 WHERE `id` = :id'
            );
            $stmt->execute([
                ':bank_code' => $data['bank_code'] ?? 'vtb',
                ':imap_host' => $data['imap_host'] ?? '',
                ':imap_port' => (int)($data['imap_port'] ?? 993),
                ':imap_ssl' => !empty($data['imap_ssl']) ? 1 : 0,
                ':username' => $data['username'] ?? '',
                ':password_encrypted' => $passwordEncrypted,
                ':sender_filter' => $data['sender_filter'] ?? 'vtb-inform@vtb.ru',
                ':subject_filter' => $data['subject_filter'] ?? 'Регулярная выписка',
                ':is_active' => !empty($data['is_active']) ? 1 : 0,
                ':id' => $id,
            ]);
            return $id;
        }

        $stmt = $localPdo->prepare(
            'INSERT INTO `bank_statement_settings`
                (`bank_code`, `imap_host`, `imap_port`, `imap_ssl`, `username`, `password_encrypted`, `sender_filter`, `subject_filter`, `is_active`)
             VALUES
                (:bank_code, :imap_host, :imap_port, :imap_ssl, :username, :password_encrypted, :sender_filter, :subject_filter, :is_active)'
        );
        $stmt->execute([
            ':bank_code' => $data['bank_code'] ?? 'vtb',
            ':imap_host' => $data['imap_host'] ?? '',
            ':imap_port' => (int)($data['imap_port'] ?? 993),
            ':imap_ssl' => !empty($data['imap_ssl']) ? 1 : 0,
            ':username' => $data['username'] ?? '',
            ':password_encrypted' => $passwordEncrypted,
            ':sender_filter' => $data['sender_filter'] ?? 'vtb-inform@vtb.ru',
            ':subject_filter' => $data['subject_filter'] ?? 'Регулярная выписка',
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int)$localPdo->lastInsertId();
    }

    public static function getById(PDO $localPdo, int $id): ?array
    {
        $stmt = $localPdo->prepare('SELECT * FROM `bank_statement_settings` WHERE `id` = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }
}
