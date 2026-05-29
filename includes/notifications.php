<?php
require_once __DIR__ . '/mailer.php';

// ── Send in-app + email notification to all admins ────────────
function notifyAdmins(int $biz_id, int $actor_id, string $type, string $title, string $message): void {
    try {
        $admins = db()->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            WHERE u.business_id=? AND u.role='admin' AND u.id!=? AND u.status='active'
        ");
        $admins->execute([$biz_id, $actor_id]);
        $admin_list = $admins->fetchAll();
        if (empty($admin_list)) return;

        // Get business name and app URL for emails
        $biz_stmt = db()->prepare("SELECT name FROM businesses WHERE id=?");
        $biz_stmt->execute([$biz_id]);
        $biz_name = $biz_stmt->fetchColumn() ?: 'Your Business';

        $stmt = db()->prepare("
            INSERT INTO notifications (business_id,user_id,admin_id,type,title,message)
            VALUES (?,?,?,?,?,?)
        ");

        foreach ($admin_list as $admin) {
            // 1. Save in-app notification
            $stmt->execute([$biz_id, $actor_id, $admin['id'], $type, $title, $message]);

            // 2. Send email notification immediately
            $notif = [[
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]];
            sendNotificationEmail(
                $biz_id,
                $admin['email'],
                $admin['name'],
                $biz_name,
                $notif,
                APP_URL
            );
        }
    } catch (Exception $e) {
        // Never break main action due to notification failure
    }
}

// ── Get unread count for admin ────────────────────────────────
function getUnreadCount(int $admin_id): int {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE admin_id=? AND is_read=0");
        $stmt->execute([$admin_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

// ── Get latest notifications for admin ───────────────────────
function getNotifications(int $admin_id): array {
    try {
        $stmt = db()->prepare("
            SELECT n.*, u.name AS actor_name, u.role AS actor_role
            FROM notifications n
            JOIN users u ON u.id=n.user_id
            WHERE n.admin_id=?
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$admin_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

// ── Mark all read ─────────────────────────────────────────────
function markAllRead(int $admin_id): void {
    try {
        db()->prepare("UPDATE notifications SET is_read=1 WHERE admin_id=?")->execute([$admin_id]);
    } catch (Exception $e) {}
}

// ── Mark one read ─────────────────────────────────────────────
function markOneRead(int $notif_id, int $admin_id): void {
    try {
        db()->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND admin_id=?")->execute([$notif_id, $admin_id]);
    } catch (Exception $e) {}
}

function notifIcon(string $type): string {
    return match($type) {
        'transaction_add'    => '💰',
        'transaction_edit'   => '✏️',
        'transaction_delete' => '🗑️',
        'book_add'           => '📚',
        'book_edit'          => '📝',
        'book_delete'        => '🗑️',
        'client_add'         => '👤',
        'book_member_add'    => '👥',
        default              => '🔔',
    };
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff/60).'m ago';
    if ($diff < 86400) return floor($diff/3600).'h ago';
    return date('M j', strtotime($datetime));
}
