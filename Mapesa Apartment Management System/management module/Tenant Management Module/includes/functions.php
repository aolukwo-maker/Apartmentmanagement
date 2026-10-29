<?php

function formatDate(?string $value): string
{
    if (empty($value)) {
        return 'Not set';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    if ($date === false) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    return $date->format('d M Y');
}

function groupTenantsByUnit(array $tenants): array
{
    $groups = [];

    foreach ($tenants as $tenant) {
        $unit = $tenant['unit_id'] ?: 'Unassigned';
        $groups[$unit][] = $tenant;
    }

    ksort($groups);

    return $groups;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
