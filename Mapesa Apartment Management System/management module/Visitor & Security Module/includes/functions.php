<?php

function visitorStoragePath(): string
{
    return __DIR__ . '/../storage/visitors.json';
}

function loadVisitors(): array
{
    $path = visitorStoragePath();

    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function saveVisitors(array $visitors): void
{
    $path = visitorStoragePath();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, json_encode($visitors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function normalizeVisitor(array $input): array
{
    $now = date('Y-m-d H:i:s');

    return [
        'id' => $input['id'] ?? bin2hex(random_bytes(4)),
        'full_name' => trim((string) ($input['full_name'] ?? '')),
        'phone' => trim((string) ($input['phone'] ?? '')),
        'id_number' => trim((string) ($input['id_number'] ?? '')),
        'company' => trim((string) ($input['company'] ?? '')),
        'tenant_name' => trim((string) ($input['tenant_name'] ?? '')),
        'unit_id' => trim((string) ($input['unit_id'] ?? '')),
        'visit_purpose' => trim((string) ($input['visit_purpose'] ?? '')),
        'visitor_type' => in_array(($input['visitor_type'] ?? 'guest'), ['guest', 'family', 'delivery', 'contractor', 'service'], true)
            ? ($input['visitor_type'] ?? 'guest')
            : 'guest',
        'status' => in_array(($input['status'] ?? 'expected'), ['expected', 'checked_in', 'checked_out'], true)
            ? ($input['status'] ?? 'expected')
            : 'expected',
        'scheduled_date' => (string) ($input['scheduled_date'] ?? ''),
        'check_in_time' => (string) ($input['check_in_time'] ?? ''),
        'check_out_time' => (string) ($input['check_out_time'] ?? ''),
        'security_note' => trim((string) ($input['security_note'] ?? '')),
        'registered_by' => trim((string) ($input['registered_by'] ?? 'Front desk')),
        'created_at' => $input['created_at'] ?? $now,
        'history' => is_array($input['history'] ?? null) ? $input['history'] : [],
    ];
}

function addHistoryEvent(array $visitor, string $message): array
{
    $history = is_array($visitor['history'] ?? null) ? $visitor['history'] : [];
    $history[] = [
        'date' => date('Y-m-d H:i:s'),
        'message' => $message,
    ];

    $visitor['history'] = $history;

    return $visitor;
}

function getVisitorSummary(array $visitors): array
{
    $checkedIn = 0;
    $expected = 0;

    foreach ($visitors as $visitor) {
        $status = $visitor['status'] ?? 'expected';

        if ($status === 'checked_in') {
            $checkedIn++;
        } elseif ($status === 'expected') {
            $expected++;
        }
    }

    return [
        'total' => count($visitors),
        'checked_in' => $checkedIn,
        'expected' => $expected,
    ];
}

function getSecurityNotifications(array $visitors): array
{
    $notifications = [];

    foreach ($visitors as $visitor) {
        $status = $visitor['status'] ?? 'expected';
        $name = $visitor['full_name'] ?? 'Visitor';
        $tenant = $visitor['tenant_name'] ?? 'Unknown tenant';

        if ($status === 'expected') {
            $notifications[] = [
                'title' => 'Visitor expected',
                'message' => $name . ' is expected for ' . $tenant . '.',
            ];
        }

        if (($visitor['security_note'] ?? '') !== '') {
            $notifications[] = [
                'title' => 'Security note',
                'message' => $name . ': ' . $visitor['security_note'],
            ];
        }
    }

    return $notifications;
}

function visitorStatusBadge(string $status): string
{
    $classes = [
        'expected' => 'warning',
        'checked_in' => 'success',
        'checked_out' => 'secondary',
    ];

    return $classes[$status] ?? 'secondary';
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
