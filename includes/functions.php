<?php
require_once __DIR__ . '/../db.php';

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base()
{
    return defined('APP_BASE') ? APP_BASE : '';
}

function app_url($path)
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) {
        return $path;
    }

    return app_base() . ltrim($path, '/');
}

function set_flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes()
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function redirect_to($url)
{
    header('Location: ' . $url);
    exit;
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in()
{
    return !empty($_SESSION['user']);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf()
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function old($key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function upload_file($fieldName, $subDir = 'uploads', $allowed = ['jpg', 'jpeg', 'png', 'webp'])
{
    if (empty($_FILES[$fieldName]['name'])) {
        return [true, null];
    }

    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'File upload failed.'];
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return [false, 'File is too large. Max 2MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return [false, 'Invalid file type.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $validMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $validMime, true)) {
        return [false, 'Only image uploads are allowed.'];
    }

    $folderPath = __DIR__ . '/../' . trim($subDir, '/');
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0777, true);
    }

    $newName = uniqid('img_', true) . '.' . $ext;
    $destination = $folderPath . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [false, 'Unable to save uploaded file.'];
    }

    return [true, trim($subDir, '/') . '/' . $newName];
}

function create_slug($text)
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function page_param()
{
    return $_GET['page'] ?? 'dashboard';
}

function per_page()
{
    return 10;
}

function current_page_no()
{
    $p = (int) ($_GET['p'] ?? 1);
    return $p > 0 ? $p : 1;
}

function paginate_clause(&$offset)
{
    $pageNo = current_page_no();
    $limit = per_page();
    $offset = ($pageNo - 1) * $limit;

    return " LIMIT {$limit} OFFSET {$offset} ";
}

function status_badge($status)
{
    $map = [
        'approved' => 'success',
        'active' => 'success',
        'submitted' => 'info',
        'pending' => 'warning',
        'draft' => 'muted',
        'rejected' => 'danger',
        'reject' => 'danger',
        'inactive' => 'danger',
        'suspended' => 'danger',
        'completed' => 'success',
        'scheduled' => 'info',
    ];

    $clean = strtolower((string) $status);
    $class = $map[$clean] ?? 'muted';

    return '<span class="badge badge-' . e($class) . '">' . e(ucfirst($clean)) . '</span>';
}

function log_action($action, $module, $targetType = null, $targetId = null, $oldValues = null, $newValues = null)
{
    try {
        $user = current_user();
        $userId = $user['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        db_execute(
            'INSERT INTO activity_logs (user_id, action, module, target_type, target_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'issssssss',
            [
                (int) ($userId ?? 0),
                (string) $action,
                (string) $module,
                $targetType !== null ? (string) $targetType : null,
                $targetId !== null ? (string) $targetId : null,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $ip,
                $agent,
            ]
        );

        notify_federation_role_users($action, $module, $targetType, $targetId, $user, $newValues);
    } catch (\Throwable $e) {
        // Logging should never block the main operation
    }
}

function notification_message_column()
{
    return has_column('notifications', 'message') ? 'message' : 'body';
}

function notification_type_for_module($module)
{
    $module = strtolower((string) $module);

    if (in_array($module, ['approvals', 'lineups'], true)) {
        return 'approval';
    }
    if (in_array($module, ['teams', 'team'], true)) {
        return 'team';
    }
    if (in_array($module, ['matches', 'match_results'], true)) {
        return 'match';
    }
    if (in_array($module, ['users', 'roles', 'profile'], true)) {
        return 'user';
    }
    if (in_array($module, ['settings', 'auth'], true)) {
        return 'warning';
    }

    return 'info';
}

function humanize_activity_text($value)
{
    $text = str_replace(['_', '-'], ' ', (string) $value);
    $text = preg_replace('/\s+/', ' ', $text);
    return ucwords(trim($text));
}

function activity_notification_content($action, $module, $targetType = null, $targetId = null, $actor = null)
{
    $actionText = humanize_activity_text($action);
    $moduleText = humanize_activity_text($module);
    $actorName = $actor['full_name'] ?? $actor['username'] ?? 'System';
    $title = $actionText;
    $message = $actorName . ' performed ' . strtolower($actionText) . ' in ' . $moduleText . '.';

    if ($targetType || $targetId) {
        $targetParts = [];
        if ($targetType) {
            $targetParts[] = humanize_activity_text($targetType);
        }
        if ($targetId) {
            $targetParts[] = '#' . (int) $targetId;
        }
        $message .= ' Target: ' . implode(' ', $targetParts) . '.';
    }

    return [$title, $message];
}

function federation_role_user_ids()
{
    $rows = db_fetch_all(
        "SELECT DISTINCT u.id
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE u.is_active = 1
           AND (
             r.slug IN ('federation-role', 'federation_role', 'federation-admin', 'federation_admin')
             OR LOWER(r.name) = 'federation role'
           )"
    );

    return array_map(static function ($row) {
        return (int) $row['id'];
    }, $rows);
}

function create_notification($userId, $type, $title, $message = '', $extraData = [])
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $messageColumn = notification_message_column();
    $extraJson = !empty($extraData) ? json_encode($extraData) : null;
    $fields = ['user_id', 'type', 'title', $messageColumn];
    $placeholders = ['?', '?', '?', '?'];
    $types = 'isss';
    $params = [
        $userId,
        (string) $type,
        mb_strimwidth((string) $title, 0, 155, '...'),
        (string) $message,
    ];

    if (has_column('notifications', 'extra_data')) {
        $fields[] = 'extra_data';
        $placeholders[] = '?';
        $types .= 's';
        $params[] = $extraJson;
    }

    return db_execute(
        'INSERT INTO notifications (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')',
        $types,
        $params
    );
}

function notify_federation_role_users($action, $module, $targetType = null, $targetId = null, $actor = null, $extraData = [])
{
    $recipientIds = federation_role_user_ids();
    if (empty($recipientIds)) {
        return;
    }

    [$title, $message] = activity_notification_content($action, $module, $targetType, $targetId, $actor);
    $type = notification_type_for_module($module);

    foreach ($recipientIds as $recipientId) {
        create_notification($recipientId, $type, $title, $message, [
            'action' => $action,
            'module' => $module,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'actor_id' => isset($actor['id']) ? (int) $actor['id'] : null,
            'data' => $extraData,
        ]);
    }
}

function fetch_user_notifications($userId, $limit = 8)
{
    $userId = (int) $userId;
    $limit = max(1, min(30, (int) $limit));
    $messageColumn = notification_message_column();

    return db_fetch_all(
        "SELECT id, title, type, {$messageColumn} AS message, created_at, is_read
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT {$limit}",
        'i',
        [$userId]
    );
}

function unread_notification_count($userId)
{
    return db_table_count('notifications', 'user_id = ? AND is_read = 0', 'i', [(int) $userId]);
}

function ensure_default_roles()
{
    $defaultRoles = [
        ['Federation Role', 'federation-role', 'federation', 'Master role with access to all federations'],
        ['Team Role', 'team-role', 'club', 'Master role with access to teams, players, and everything related to the team'],
        ['Federation Admin', 'federation-admin', 'federation', 'Full federation control'],
        ['Team Manager', 'team-manager', 'club', 'Manage team operations'],
        ['Coach', 'coach', 'club', 'Training and lineup access'],
        ['Match Official', 'match-official', 'global', 'Manage officiating tasks'],
        ['Analyst', 'analyst', 'club', 'Performance and ranking analysis'],
        ['Media Officer', 'media-officer', 'federation', 'Manage news/publication'],
    ];

    foreach ($defaultRoles as $role) {
        $existing = db_fetch_one('SELECT id FROM roles WHERE slug = ?', 's', [$role[1]]);
        if (!$existing) {
            db_execute('INSERT INTO roles (name, slug, scope, description) VALUES (?, ?, ?, ?)', 'ssss', $role);
        }
    }
}

function default_permissions()
{
    return [
        // Dashboard
        ['Dashboard Access', 'dashboard.view', 'dashboard'],

        // Teams
        ['Manage Teams', 'teams.manage', 'teams'],
        ['Approve Teams', 'teams.approve', 'teams'],
        ['View Teams', 'teams.view', 'teams'],
        ['Create Team', 'teams.create', 'teams'],
        ['Edit Team', 'teams.edit', 'teams'],
        ['Delete Team', 'teams.delete', 'teams'],
        ['Activate Team', 'teams.activate', 'teams'],
        ['Deactivate Team', 'teams.deactivate', 'teams'],

        // Users
        ['Manage Users', 'users.manage', 'users'],
        ['View Users', 'users.view', 'users'],
        ['Create User', 'users.create', 'users'],
        ['Edit User', 'users.edit', 'users'],
        ['Delete User', 'users.delete', 'users'],
        ['Assign Role', 'users.assign_role', 'users'],

        // Roles & Permissions
        ['Manage Roles & Permissions', 'roles.manage', 'roles'],
        ['View Roles', 'roles.view', 'roles'],
        ['Create Role', 'roles.create', 'roles'],
        ['Edit Role', 'roles.edit', 'roles'],
        ['Delete Role', 'roles.delete', 'roles'],
        ['Assign Permissions', 'roles.assign_permissions', 'roles'],

        // Players
        ['Manage Players', 'players.manage', 'players'],
        ['View Players', 'players.view', 'players'],
        ['Register Player', 'players.create', 'players'],
        ['Edit Player', 'players.edit', 'players'],
        ['Delete Player', 'players.delete', 'players'],
        ['Transfer Player', 'players.transfer', 'players'],

        // Matches
        ['Manage Matches', 'matches.manage', 'matches'],
        ['View Matches', 'matches.view', 'matches'],
        ['Create Match', 'matches.create', 'matches'],
        ['Edit Match', 'matches.edit', 'matches'],
        ['Delete Match', 'matches.delete', 'matches'],
        ['Schedule Match', 'matches.schedule', 'matches'],
        ['Assign Match Officials', 'officials.manage', 'matches'],

        // Match Lineups
        ['Manage Lineups', 'lineups.manage', 'lineups'],
        ['View Lineups', 'lineups.view', 'lineups'],
        ['Submit Lineup', 'lineups.submit', 'lineups'],
        ['Approve Lineups', 'lineups.approve', 'approvals'],
        ['Reject Lineup', 'lineups.reject', 'lineups'],

        // Match Results
        ['Manage Results', 'results.manage', 'results'],
        ['View Results', 'results.view', 'results'],
        ['Submit Result', 'results.submit', 'results'],
        ['Approve Match Results', 'results.approve', 'approvals'],
        ['Reject Result', 'results.reject', 'results'],

        // Match Events
        ['Manage Match Events', 'match_events.manage', 'match_events'],
        ['View Match Events', 'match_events.view', 'match_events'],
        ['Record Match Event', 'match_events.create', 'match_events'],
        ['Edit Match Event', 'match_events.edit', 'match_events'],
        ['Delete Match Event', 'match_events.delete', 'match_events'],

        // Competitions
        ['Manage Competitions', 'competitions.manage', 'competitions'],
        ['View Competitions', 'competitions.view', 'competitions'],
        ['Create Competition', 'competitions.create', 'competitions'],
        ['Edit Competition', 'competitions.edit', 'competitions'],
        ['Delete Competition', 'competitions.delete', 'competitions'],
        ['Enroll Teams in Competition', 'competitions.enroll_teams', 'competitions'],

        // Federations
        ['Manage Federations', 'federations.manage', 'federations'],
        ['View Federations', 'federations.view', 'federations'],
        ['Create Federation', 'federations.create', 'federations'],
        ['Edit Federation', 'federations.edit', 'federations'],
        ['Delete Federation', 'federations.delete', 'federations'],

        // Stadiums
        ['Manage Stadiums', 'stadiums.manage', 'stadiums'],
        ['View Stadiums', 'stadiums.view', 'stadiums'],
        ['Create Stadium', 'stadiums.create', 'stadiums'],
        ['Edit Stadium', 'stadiums.edit', 'stadiums'],
        ['Delete Stadium', 'stadiums.delete', 'stadiums'],

        // Seasons
        ['Manage Seasons', 'seasons.manage', 'seasons'],
        ['View Seasons', 'seasons.view', 'seasons'],
        ['Create Season', 'seasons.create', 'seasons'],
        ['Edit Season', 'seasons.edit', 'seasons'],
        ['Delete Season', 'seasons.delete', 'seasons'],

        // News
        ['Manage News', 'news.manage', 'news'],
        ['View News', 'news.view', 'news'],
        ['Create News', 'news.create', 'news'],
        ['Edit News', 'news.edit', 'news'],
        ['Delete News', 'news.delete', 'news'],
        ['Publish News', 'news.publish', 'news'],

        // Media Files
        ['Manage Media', 'media.manage', 'media'],
        ['Upload Media', 'media.upload', 'media'],
        ['View Media', 'media.view', 'media'],
        ['Delete Media', 'media.delete', 'media'],

        // Reports
        ['View Reports', 'reports.view', 'reports'],
        ['Export Reports', 'reports.export', 'reports'],
        ['Generate Reports', 'reports.generate', 'reports'],

        // Activity Logs
        ['View Activity Logs', 'activity_logs.view', 'logs'],
        ['Export Activity Logs', 'activity_logs.export', 'logs'],

        // Settings
        ['Manage Settings', 'settings.manage', 'settings'],
        ['View Settings', 'settings.view', 'settings'],

        // Notifications
        ['View Notifications', 'notifications.view', 'notifications'],
        ['Send Notifications', 'notifications.send', 'notifications'],
        ['Delete Notifications', 'notifications.delete', 'notifications'],

        // Approvals
        ['Approve Rankings', 'rankings.approve', 'approvals'],
        ['Approve Ratings', 'ratings.approve', 'approvals'],
        ['Approve Statistics', 'statistics.approve', 'approvals'],
        ['Approve Player Registrations', 'player_registrations.approve', 'approvals'],

        // Player Ratings
        ['Manage Player Ratings', 'player_ratings.manage', 'player_ratings'],
        ['Rate Player', 'player_ratings.rate', 'player_ratings'],
        ['View Player Ratings', 'player_ratings.view', 'player_ratings'],

        // Player Rankings
        ['Manage Player Rankings', 'player_rankings.manage', 'player_rankings'],
        ['View Player Rankings', 'player_rankings.view', 'player_rankings'],

        // Player Statistics
        ['Manage Player Statistics', 'player_statistics.manage', 'player_statistics'],
        ['View Player Statistics', 'player_statistics.view', 'player_statistics'],

        // Team Standings
        ['View Team Standings', 'standings.view', 'standings'],
        ['Manage Team Standings', 'standings.manage', 'standings'],

        // Team Rankings
        ['View Team Rankings', 'team_rankings.view', 'team_rankings'],
        ['Manage Team Rankings', 'team_rankings.manage', 'team_rankings'],

        // Substitutions
        ['Manage Substitutions', 'substitutions.manage', 'substitutions'],
        ['Record Substitution', 'substitutions.create', 'substitutions'],

        // Formations
        ['Manage Formations', 'formations.manage', 'formations'],
        ['View Formations', 'formations.view', 'formations'],
    ];
}

function ensure_default_permissions()
{
    foreach (default_permissions() as $permission) {
        $existing = db_fetch_one('SELECT id FROM permissions WHERE slug = ?', 's', [$permission[1]]);
        if (!$existing) {
            db_execute(
                'INSERT INTO permissions (name, slug, module) VALUES (?, ?, ?)',
                'sss',
                $permission
            );
        }
    }
}

function ensure_master_role_permissions()
{
    ensure_default_roles();
    ensure_default_permissions();

    $masterRoles = db_fetch_all(
        "SELECT id FROM roles WHERE slug IN ('super_admin', 'federation-role', 'federation_role', 'federation-admin', 'federation_admin')"
    );
    $permissions = db_fetch_all('SELECT id FROM permissions');

    foreach ($masterRoles as $role) {
        foreach ($permissions as $permission) {
            $exists = db_fetch_one(
                'SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                'ii',
                [(int) $role['id'], (int) $permission['id']]
            );
            if (!$exists) {
                db_execute(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                    'ii',
                    [(int) $role['id'], (int) $permission['id']]
                );
            }
        }
    }

    // Assign realistic default permissions to other system roles
    $rolePermissionsMapping = [
        'team-role' => [
            'dashboard.view', 'players.view', 'players.create', 'players.edit', 'players.delete',
            'players.transfer', 'players.manage', 'lineups.view', 'lineups.submit', 'lineups.manage',
            'lineups.reject', 'results.view', 'results.submit', 'results.manage', 'results.reject',
            'matches.view', 'matches.manage', 'news.view', 'match_events.view', 'formations.view'
        ],
        'team-manager' => [
            'dashboard.view', 'players.view', 'players.create', 'players.edit', 'players.delete',
            'players.transfer', 'players.manage', 'lineups.view', 'lineups.submit', 'lineups.manage',
            'lineups.reject', 'results.view', 'results.submit', 'results.manage', 'results.reject',
            'matches.view', 'matches.manage', 'news.view', 'match_events.view', 'formations.view'
        ],
        'coach' => [
            'dashboard.view', 'players.view', 'lineups.view', 'lineups.submit', 'lineups.manage',
            'results.view', 'matches.view', 'news.view', 'formations.view', 'formations.manage',
            'player_ratings.view', 'player_ratings.rate', 'player_ratings.manage'
        ],
        'match-official' => [
            'dashboard.view', 'matches.view', 'results.view', 'results.submit', 'results.manage',
            'match_events.view', 'match_events.create', 'match_events.manage',
            'substitutions.manage', 'substitutions.create'
        ],
        'analyst' => [
            'dashboard.view', 'players.view', 'player_rankings.view', 'player_ratings.view',
            'player_statistics.view', 'standings.view', 'team_rankings.view', 'reports.view',
            'reports.export', 'reports.generate'
        ],
        'media-officer' => [
            'dashboard.view', 'news.view', 'news.create', 'news.edit', 'news.delete', 'news.publish'
        ]
    ];

    foreach ($rolePermissionsMapping as $roleSlug => $permSlugs) {
        $roleRow = db_fetch_one('SELECT id FROM roles WHERE slug = ?', 's', [$roleSlug]);
        if ($roleRow) {
            $roleId = (int) $roleRow['id'];
            foreach ($permSlugs as $pSlug) {
                $permRow = db_fetch_one('SELECT id FROM permissions WHERE slug = ?', 's', [$pSlug]);
                if ($permRow) {
                    $permId = (int) $permRow['id'];
                    $exists = db_fetch_one(
                        'SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
                        'ii',
                        [$roleId, $permId]
                    );
                    if (!$exists) {
                        db_execute(
                            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                            'ii',
                            [$roleId, $permId]
                        );
                    }
                }
            }
        }
    }
}

function user_role_slugs($userId)
{
    $rows = db_fetch_all(
        'SELECT r.slug FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
        'i',
        [(int) $userId]
    );

    return array_map(static function ($row) {
        return (string) $row['slug'];
    }, $rows);
}

function user_has_master_role($userId)
{
    $user = current_user();
    if ($user && (int) ($user['id'] ?? 0) === (int) $userId && ($user['user_type'] ?? '') === 'admin') {
        return true;
    }

    $masterSlugs = ['super_admin', 'federation-role', 'federation_role', 'federation-admin', 'federation_admin'];
    return count(array_intersect(user_role_slugs($userId), $masterSlugs)) > 0;
}

function user_permission_slugs($userId)
{
    if (user_has_master_role($userId)) {
        $rows = db_fetch_all('SELECT slug FROM permissions');
    } else {
        $rows = db_fetch_all(
            'SELECT DISTINCT p.slug
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?',
            'i',
            [(int) $userId]
        );
    }

    return array_map(static function ($row) {
        return (string) $row['slug'];
    }, $rows);
}

function user_has_permission($userId, $permissionSlug)
{
    if ($permissionSlug === null || $permissionSlug === '') {
        return true;
    }

    return in_array((string) $permissionSlug, user_permission_slugs((int) $userId), true);
}

function current_user_can($permissionSlug)
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return user_has_permission((int) $user['id'], $permissionSlug);
}

function page_permission_slug($page)
{
    $map = [
        'dashboard' => 'dashboard.view',
        'teams' => 'teams.manage',
        'team_registrations' => 'teams.approve',
        'users' => 'users.manage',
        'roles_permissions' => 'roles.manage',
        'assign_roles' => 'users.assign_role',
        'player_rankings_approval' => 'rankings.approve',
        'player_ratings_approval' => 'ratings.approve',
        'player_statistics_approval' => 'statistics.approve',
        'player_registrations_approval' => 'player_registrations.approve',
        'stadiums' => 'stadiums.manage',
        'seasons' => 'seasons.manage',
        'match_results_approval' => 'results.approve',
        'match_officials' => 'officials.manage',
        'match_lineups_approval' => 'lineups.approve',
        'news' => 'news.manage',
        'activity_logs' => 'activity_logs.view',
        'reports' => 'reports.view',
        'settings' => 'settings.manage',
        'match_scheduling' => 'matches.schedule',
        'competitions' => 'competitions.manage',

        // Team Portal Page Mappings
        'squad' => 'players.view',
        'players' => 'players.view',
        'lineups' => 'lineups.view',
        'matches' => 'matches.view',
        'results' => 'results.view',
    ];

    return $map[$page] ?? null;
}

function current_user_can_page($page)
{
    $alwaysAllowed = ['profile', 'logout', 'notifications', 'news'];
    if (in_array((string) $page, $alwaysAllowed, true)) {
        return true;
    }

    return current_user_can(page_permission_slug($page));
}

function total_pages($totalItems)
{
    return max(1, (int) ceil($totalItems / per_page()));
}

function load_system_settings()
{
    $defaults = [
        'system_name' => 'Football Federation Admin Dashboard',
        'system_email' => 'federation@example.com',
        'primary_color' => '#ff7a00',
        'secondary_color' => '#0b1f3a',
        'logo' => 'assets/images/federation-logo.svg',
    ];

    $file = __DIR__ . '/system_settings.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }

    $raw = file_get_contents($file);
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        return $defaults;
    }

    return array_merge($defaults, $json);
}

function save_system_settings($settings)
{
    $current = load_system_settings();
    $merged = array_merge($current, $settings);
    $file = __DIR__ . '/system_settings.json';

    return file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT)) !== false;
}

function page_title($page)
{
    $titles = [
        'dashboard' => 'Dashboard',
        'teams' => 'Teams Management',
        'team_registrations' => 'Team Registrations',
        'users' => 'Users Management',
        'roles_permissions' => 'Roles & Permissions',
        'assign_roles' => 'Assign Roles',
        'player_rankings_approval' => 'Player Rankings Approval',
        'player_ratings_approval' => 'Player Ratings Approval',
        'player_statistics_approval' => 'Player Statistics Approval',
        'player_registrations_approval' => 'Player Registrations Approval',
        'players' => 'Players',
        'stadiums' => 'Stadium Management',
        'seasons' => 'Seasons Management',
        'match_results_approval' => 'Match Results Approval',
        'match_officials' => 'Match Officials',
        'match_lineups_approval' => 'Match Lineups Approval',
        'news' => 'News Management',
        'activity_logs' => 'Activity Logs',
        'notifications' => 'Notifications',
        'reports' => 'Reports',
        'settings' => 'Settings',
        'profile' => 'Profile',
        'match_scheduling' => 'Match Scheduling',
        'competitions' => 'Competitions Management',
    ];

    return $titles[$page] ?? 'Dashboard';
}

function icon_svg($name)
{
    $icons = [
        'dashboard' => '<svg viewBox=\"0 0 24 24\"><path d=\"M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-18v6h8V3h-8z\"/></svg>',
        'team' => '<svg viewBox=\"0 0 24 24\"><path d=\"M12 2l9 4v6c0 5.5-3.8 10.7-9 12-5.2-1.3-9-6.5-9-12V6l9-4zm0 5a2 2 0 100 4 2 2 0 000-4zm-4.2 9.5h8.4c-.4-1.8-1.9-3-4.2-3s-3.8 1.2-4.2 3z\"/></svg>',
        'users' => '<svg viewBox=\"0 0 24 24\"><path d=\"M16 11c1.7 0 3-1.6 3-3.5S17.7 4 16 4s-3 1.6-3 3.5 1.3 3.5 3 3.5zM8 11c1.7 0 3-1.6 3-3.5S9.7 4 8 4 5 5.6 5 7.5 6.3 11 8 11zm0 2c-2.7 0-8 1.4-8 4v3h16v-3c0-2.6-5.3-4-8-4zm8 0c-.3 0-.7 0-1 .1 1.3.9 2 2.1 2 3.4v3h7v-3c0-2.6-5.3-4-8-4z\"/></svg>',
        'approval' => '<svg viewBox=\"0 0 24 24\"><path d=\"M12 2l7 3v6c0 5-3.2 9.7-7 11-3.8-1.3-7-6-7-11V5l7-3zm-1 12l5-5-1.4-1.4-3.6 3.6-1.6-1.6L8 11l3 3z\"/></svg>',
        'stadium' => '<svg viewBox=\"0 0 24 24\"><path d=\"M3 18h18v2H3v-2zm2-2V8l7-3 7 3v8h-2V9.3l-5-2.1-5 2.1V16H5z\"/></svg>',
        'season' => '<svg viewBox=\"0 0 24 24\"><path d=\"M7 2h2v2h6V2h2v2h3v18H4V4h3V2zm12 8H5v10h14V10z\"/></svg>',
        'news' => '<svg viewBox=\"0 0 24 24\"><path d=\"M4 4h16v16H4V4zm3 3v2h10V7H7zm0 4v2h10v-2H7zm0 4v2h7v-2H7z\"/></svg>',
        'logs' => '<svg viewBox=\"0 0 24 24\"><path d=\"M4 4h16v16H4V4zm2 2v12h12V6H6zm2 2h8v2H8V8zm0 4h8v2H8v-2z\"/></svg>',
        'report' => '<svg viewBox=\"0 0 24 24\"><path d=\"M5 3h14v18H5V3zm3 4v10h2V7H8zm4 3v7h2v-7h-2zm4-2v9h2V8h-2z\"/></svg>',
        'settings' => '<svg viewBox=\"0 0 24 24\"><path d=\"M19.4 13a7.7 7.7 0 000-2l2-1.6-2-3.4-2.4 1a8.8 8.8 0 00-1.7-1L15 2h-4l-.4 3a8.8 8.8 0 00-1.7 1l-2.4-1-2 3.4 2 1.6a7.7 7.7 0 000 2l-2 1.6 2 3.4 2.4-1c.5.4 1.1.8 1.7 1l.4 3h4l.4-3c.6-.2 1.2-.6 1.7-1l2.4 1 2-3.4-2-1.6zM12 15.5A3.5 3.5 0 1112 8a3.5 3.5 0 010 7.5z\"/></svg>',
        'profile' => '<svg viewBox=\"0 0 24 24\"><path d=\"M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.4 0-8 2.2-8 5v3h16v-3c0-2.8-3.6-5-8-5z\"/></svg>',
        'logout' => '<svg viewBox=\"0 0 24 24\"><path d=\"M10 17l1.4-1.4L9.8 14H20v-2H9.8l1.6-1.6L10 9l-4 4 4 4zm-6 4h8v-2H4V5h8V3H4a2 2 0 00-2 2v14a2 2 0 002 2z\"/></svg>',
        'notification' => '<svg viewBox=\"0 0 24 24\"><path d=\"M12 22a2.5 2.5 0 002.5-2.5h-5A2.5 2.5 0 0012 22zm8-6V11a8 8 0 10-16 0v5L2 18v1h20v-1l-2-2z\"/></svg>',
        'search' => '<svg viewBox=\"0 0 24 24\"><path d=\"M10 2a8 8 0 015.3 14l4.3 4.3-1.4 1.4-4.3-4.3A8 8 0 1110 2zm0 2a6 6 0 100 12 6 6 0 000-12z\"/></svg>',
        'add' => '<svg viewBox=\"0 0 24 24\"><path d=\"M19 11h-6V5h-2v6H5v2h6v6h2v-6h6v-2z\"/></svg>',
        'menu' => '<svg viewBox=\"0 0 24 24\"><path d=\"M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z\"/></svg>',
        'chevron-down' => '<svg viewBox=\"0 0 24 24\"><path d=\"M7 10l5 5 5-5z\"/></svg>',
        'chevron-right' => '<svg viewBox=\"0 0 24 24\"><path d=\"M9 6l6 6-6 6z\"/></svg>',
        'calendar' => '<svg viewBox=\"0 0 24 24\"><path d=\"M7 2h2v2h6V2h2v2h3v18H4V4h3V2zm12 8H5v10h14V10z\"/></svg>',
        'mail' => '<svg viewBox=\"0 0 24 24\"><path d=\"M3 6h18v12H3V6zm9 6l9-6H3l9 6z\"/></svg>',
        'upload' => '<svg viewBox=\"0 0 24 24\"><path d=\"M5 20h14v-2H5v2zM11 4v8H8l4 4 4-4h-3V4h-2z\"/></svg>',
        'user-add' => '<svg viewBox=\"0 0 24 24\"><path d=\"M15 12c2.8 0 5-2.2 5-5s-2.2-5-5-5-5 2.2-5 5 2.2 5 5 5zm-8 1v-3H5v3H2v2h3v3h2v-3h3v-2H7zm8 1c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z\"/></svg>',
        'refresh' => '<svg viewBox=\"0 0 24 24\"><path d=\"M17.7 6.3A8 8 0 106.3 17.7l1.4-1.4A6 6 0 1112 18a6 6 0 005.7-8H15V8h7v7h-2V11a8 8 0 00-2.3-4.7z\"/></svg>',
        'dots' => '<svg viewBox=\"0 0 24 24\"><path d=\"M12 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4zm0 8a2 2 0 110-4 2 2 0 010 4z\"/></svg>',
    ];

    return $icons[$name] ?? $icons['dashboard'];
}

function render_pagination($totalItems)
{
    $totalPages = total_pages($totalItems);
    $current = current_page_no();
    if ($totalPages <= 1) {
        return;
    }

    echo '<div class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $current ? 'active' : '';
        $query = $_GET;
        $query['p'] = $i;
        $url = '?' . http_build_query($query);
        echo '<a class="page-link ' . $active . '" href="' . e($url) . '">' . $i . '</a>';
    }
    echo '</div>';
}

function get_default_federation_id()
{
    $row = db_fetch_one('SELECT id FROM federations ORDER BY id ASC LIMIT 1');
    if ($row) {
        return (int) $row['id'];
    }

    db_execute(
        'INSERT INTO federations (name, slug, country, is_active) VALUES (?, ?, ?, 1)',
        'sss',
        ['Rwanda Football Federation', 'rwanda-football-federation', 'Rwanda']
    );

    return (int) db_last_id();
}

function get_default_season_id()
{
    $row = db_fetch_one('SELECT id FROM seasons ORDER BY id DESC LIMIT 1');
    if ($row) {
        return (int) $row['id'];
    }

    db_execute(
        'INSERT INTO seasons (name, start_date, end_date, is_active) VALUES (?, ?, ?, 1)',
        'sss',
        ['2026/2027', date('Y-m-d'), date('Y-m-d', strtotime('+10 months'))]
    );

    return (int) db_last_id();
}

function get_default_competition_id()
{
    $row = db_fetch_one('SELECT id FROM competitions ORDER BY id ASC LIMIT 1');
    if ($row) {
        return (int) $row['id'];
    }

    $federationId = get_default_federation_id();
    $seasonId = get_default_season_id();
    db_execute(
        'INSERT INTO competitions (federation_id, season_id, name, slug, type, is_active) VALUES (?, ?, ?, ?, ?, 1)',
        'iisss',
        [$federationId, $seasonId, 'National League', 'national-league', 'league']
    );

    return (int) db_last_id();
}

function set_status_value($table, $id, $valueType = 'approved')
{
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $statusCol = pick_status_column($tableSafe);
    if (!$statusCol || $id <= 0) {
        return false;
    }

    if ($valueType === 'rejected') {
        $sql = "UPDATE `{$tableSafe}` SET `{$statusCol}` = 3 WHERE id = ?";
        return db_execute($sql, 'i', [$id]);
    }

    $value = $valueType === 'pending' ? 'pending' : 'approved';
    $sql = "UPDATE `{$tableSafe}` SET `{$statusCol}` = ? WHERE id = ?";
    return db_execute($sql, 'si', [$value, $id]);
}

if (!function_exists('notif_time_ago')) {
    function notif_time_ago($datetime)
    {
        $timestamp = strtotime((string) $datetime);
        if (!$timestamp) {
            return 'Just now';
        }

        $diff = max(1, time() - $timestamp);
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = (int) floor($diff / 60);
            return $mins . 'm ago';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . 'h ago';
        }
        $days = (int) floor($diff / 86400);
        if ($days < 7) {
            return $days . 'd ago';
        }
        return date('M d', $timestamp);
    }
}

function ensure_sample_data() {
    // 1. Check if we already have teams
    $teamCount = db_table_count('teams');
    if ($teamCount > 0) {
        return; // Already seeded
    }

    // 2. Seed Stadiums
    $stadiums = [
        ['Amahoro National Stadium', 'Kigali', 'Rwanda', 45000, 'Remera, Kigali'],
        ['Kigali Pelé Stadium', 'Kigali', 'Rwanda', 22000, 'Nyamirambo, Kigali'],
        ['Musanze Stadium', 'Musanze', 'Rwanda', 12000, 'Musanze Town'],
        ['Rubavu Stadium', 'Rubavu', 'Rwanda', 15000, 'Gisenyi, Rubavu']
    ];
    foreach ($stadiums as $s) {
        db_execute('INSERT INTO stadiums (name, city, country, capacity, address) VALUES (?, ?, ?, ?, ?)', 'sssis', $s);
    }

    // Get Stadium IDs
    $stadRows = db_fetch_all('SELECT id, name FROM stadiums');
    $stadMap = [];
    foreach ($stadRows as $row) {
        $stadMap[$row['name']] = (int) $row['id'];
    }

    $fedId = get_default_federation_id();
    $seasonId = get_default_season_id();
    $compId = get_default_competition_id();

    // 3. Seed Teams
    $teamsData = [
        ['Rayon Sports', 'rayon-sports', 'RS', 'https://images.unsplash.com/photo-1551958219-acbc595d9e15?w=80&q=80', 'Kigali', 'Amahoro National Stadium', 1968, 'Christian Harrington'],
        ['APR FC', 'apr-fc', 'APR', 'https://images.unsplash.com/photo-1587329310690-91114ac008f0?w=80&q=80', 'Kigali', 'Kigali Pelé Stadium', 1993, 'Darko Novic'],
        ['Kiyovu SC', 'kiyovu-sc', 'KSC', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=80&q=80', 'Kigali', 'Kigali Pelé Stadium', 1964, 'Alain Landeut'],
        ['Police FC', 'police-fc', 'PFC', 'https://images.unsplash.com/photo-1606925797300-0b35e9d1794e?w=80&q=80', 'Kigali', 'Kigali Pelé Stadium', 2003, 'Vincent Mashami'],
        ['AS Kigali', 'as-kigali', 'ASK', 'https://images.unsplash.com/photo-1560272564-c83b66b1ad12?w=80&q=80', 'Kigali', 'Kigali Pelé Stadium', 1999, 'Eric Nshimiyimana'],
        ['Musanze FC', 'musanze-fc', 'MFC', 'https://images.unsplash.com/photo-1543326727-cf6c39e8f84c?w=80&q=80', 'Musanze', 'Musanze Stadium', 1989, 'Sosthene Habimana'],
        ['Etincelles FC', 'etincelles-fc', 'EFC', 'https://images.unsplash.com/photo-1519766304817-4f37bda74a26?w=80&q=80', 'Rubavu', 'Rubavu Stadium', 1972, 'Radjab Abdul'],
        ['Gorilla FC', 'gorilla-fc', 'GFC', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=80&q=80', 'Rubavu', 'Rubavu Stadium', 2012, 'Musa Gatera']
    ];

    foreach ($teamsData as $t) {
        $stadiumId = $stadMap[$t[5]] ?? null;
        db_execute(
            'INSERT INTO teams (federation_id, home_stadium_id, name, slug, short_name, logo, city, country, founded_year, coach_name, is_active, activated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            'iissssssis',
            [$fedId, $stadiumId, $t[0], $t[1], $t[2], $t[3], $t[4], 'Rwanda', $t[6], $t[7]]
        );
        $teamId = db_last_id();

        // Enroll team in competition
        db_execute('INSERT INTO competition_teams (competition_id, team_id) VALUES (?, ?)', 'ii', [$compId, $teamId]);
    }

    // Get Team IDs
    $teamRows = db_fetch_all('SELECT id, name FROM teams');
    $teamMap = [];
    foreach ($teamRows as $row) {
        $teamMap[$row['name']] = (int) $row['id'];
    }

    // 4. Seed Standings
    $standingsData = [
        ['Rayon Sports', 1, 22, 14, 5, 3, 34, 15, 47],
        ['APR FC', 2, 22, 13, 3, 6, 29, 15, 42],
        ['Kiyovu SC', 3, 22, 11, 5, 6, 25, 16, 38],
        ['Police FC', 4, 22, 10, 6, 6, 22, 16, 36],
        ['AS Kigali', 5, 22, 9, 7, 6, 18, 14, 34],
        ['Musanze FC', 6, 22, 8, 5, 9, 16, 18, 29],
        ['Etincelles FC', 7, 22, 6, 7, 9, 14, 20, 25],
        ['Gorilla FC', 8, 22, 4, 6, 12, 12, 26, 18]
    ];
    foreach ($standingsData as $st) {
        $teamId = $teamMap[$st[0]] ?? null;
        if ($teamId) {
            db_execute(
                'INSERT INTO team_standings (team_id, competition_id, position, matches_played, wins, draws, losses, goals_for, goals_against, goal_difference, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiiiiiiiiii',
                [$teamId, $compId, $st[1], $st[2], $st[3], $st[4], $st[5], $st[6], $st[7], $st[6] - $st[7], $st[8]]
            );
        }
    }

    // 5. Seed Players
    $playersData = [
        ['APR FC', 'Eusebe', 'Nshuti', 1, 'goalkeeper', 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=100&q=80', 94],
        ['APR FC', 'Jean', 'Ndayishimiye', 9, 'forward', 'https://images.unsplash.com/photo-1459865264687-595d652de67e?w=100&q=80', 91],
        ['APR FC', 'Patrick', 'Habimana', 10, 'midfielder', 'https://images.unsplash.com/photo-1606925797300-0b35e9d1794e?w=100&q=80', 89],
        ['APR FC', 'Michel', 'Hakizimana', 5, 'defender', 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=100&q=80', 87],
        ['Rayon Sports', 'Cedric', 'Munyaneza', 11, 'forward', 'https://images.unsplash.com/photo-1543326727-cf6c39e8f84c?w=100&q=80', 86],
        ['Kiyovu SC', 'Blaise', 'Niyibizi', 8, 'midfielder', 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=100&q=80', 84]
    ];
    foreach ($playersData as $pl) {
        $teamId = $teamMap[$pl[0]] ?? null;
        if ($teamId) {
            db_execute(
                'INSERT INTO players (team_id, first_name, last_name, jersey_number, position, photo_pl, contract_start, contract_end, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 2 YEAR), "active")',
                'ississ',
                [$teamId, $pl[1], $pl[2], $pl[3], $pl[4], $pl[5]]
            );
            $playerId = db_last_id();

            // Seed Rating / Statistics for rankings
            db_execute(
                'INSERT INTO player_statistics (player_id, competition_id, matches_played, goals, assists, average_rating, statuss) VALUES (?, ?, 22, ?, ?, ?, "approved")',
                'iiidi',
                [$playerId, $compId, $pl[4] === 'forward' ? 14 : 2, $pl[4] === 'forward' ? 5 : 8, $pl[6]]
            );
        }
    }

    // 6. Seed Matches
    $matchesData = [
        ['Rayon Sports', 'APR FC', 'Amahoro National Stadium', '2026-05-30', '15:00:00', 22, 'Regular Season', 'in_progress'],
        ['Kiyovu SC', 'Police FC', 'Kigali Pelé Stadium', '2026-05-31', '15:00:00', 23, 'Regular Season', 'scheduled'],
        ['AS Kigali', 'Musanze FC', 'Musanze Stadium', '2026-05-28', '15:00:00', 21, 'Regular Season', 'completed']
    ];
    foreach ($matchesData as $m) {
        $homeId = $teamMap[$m[0]] ?? null;
        $awayId = $teamMap[$m[1]] ?? null;
        $stadiumId = $stadMap[$m[2]] ?? null;
        if ($homeId && $awayId) {
            db_execute(
                'INSERT INTO matches (federation_id, competition_id, home_team_id, away_team_id, stadium_id, match_date, match_time, matchday, round, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiiiississ',
                [$fedId, $compId, $homeId, $awayId, $stadiumId, $m[3], $m[4], $m[5], $m[6], $m[7]]
            );
            $matchId = db_last_id();

            if ($m[7] === 'completed') {
                db_execute(
                    'INSERT INTO match_results (match_id, home_score, away_score, status) VALUES (?, 0, 0, "approved")',
                    'i',
                    [$matchId]
                );
            } elseif ($m[7] === 'in_progress') {
                // Insert a temporary result for display
                db_execute(
                    'INSERT INTO match_results (match_id, home_score, away_score, status) VALUES (?, 2, 1, "approved")',
                    'i',
                    [$matchId]
                );
            }
        }
    }

    // 7. Seed News
    $newsData = [
        ['Federation confirms 16 teams registered for 2026/27 Rwanda Premier League season', 'federation-confirms-16-teams', 'The official federation has approved all 16 premier league teams following full documentation reviews. Regular updates will follow.', 'https://images.unsplash.com/photo-1522778119026-d647f0596c20?w=700&q=80'],
        ['APR FC lineup confirmed for clash vs Police FC', 'apr-fc-lineup-confirmed', 'Tactics and starting squads have been submitted by the coach Vincent Mashami.', 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=150&q=70'],
        ['Rayon Sports extend lead with 2–1 victory over Musanze', 'rayon-sports-extend-lead', 'An amazing header in the 88th minute secured Rayon Sports three crucial points.', 'https://images.unsplash.com/photo-1519766304817-4f37bda74a26?w=150&q=70'],
        ['Nshuti rated top GK with 94/100 season average', 'nshuti-rated-top-gk', 'With 12 clean sheets, Eusebe Nshuti holds the best average score this season.', 'https://images.unsplash.com/photo-1627483297886-49710ae1fc22?w=150&q=70']
    ];
    foreach ($newsData as $n) {
        db_execute(
            'INSERT INTO news (author_id, title, slug, content, cover_image, is_published, published_at) VALUES (1, ?, ?, ?, ?, 1, NOW())',
            'ssss',
            $n
        );
    }
}


