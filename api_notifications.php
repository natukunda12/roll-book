<?php
// ============================================================
// Notifications API — called by JS polling every 30 seconds
// ============================================================
require_once 'includes/config.php';
require_once 'includes/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$user   = currentUser();
$action = $_GET['action'] ?? 'count';

// Only admins receive notifications
if (!isAdmin()) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$admin_id = $user['id'];

if ($action === 'count') {
    echo json_encode(['count' => getUnreadCount($admin_id)]);
}

elseif ($action === 'list') {
    $items = getNotifications($admin_id);
    $out = [];
    foreach ($items as $n) {
        $out[] = [
            'id'         => $n['id'],
            'type'       => $n['type'],
            'icon'       => notifIcon($n['type']),
            'title'      => $n['title'],
            'message'    => $n['message'],
            'actor'      => $n['actor_name'],
            'role'       => $n['actor_role'],
            'is_read'    => (bool)$n['is_read'],
            'time'       => timeAgo($n['created_at']),
            'created_at' => $n['created_at'],
        ];
    }
    echo json_encode(['count' => getUnreadCount($admin_id), 'items' => $out]);
}

elseif ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    markAllRead($admin_id);
    echo json_encode(['success' => true]);
}

elseif ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) markOneRead($id, $admin_id);
    echo json_encode(['success' => true]);
}

else {
    echo json_encode(['error' => 'unknown action']);
}
