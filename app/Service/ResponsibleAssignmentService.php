<?php

namespace App\Service;

final class ResponsibleAssignmentService
{
    public const ENTITY_TYPES = [
        'route_executor' => 'crew',
        'contractor' => 'contractor',
        'driver' => 'driver',
        'vehicle_set' => 'vehicle_set',
    ];

    public static function normalizeEntityType(?string $type): ?string
    {
        $type = trim((string) $type);

        if ($type === '') {
            return null;
        }

        return self::ENTITY_TYPES[$type] ?? null;
    }
}
