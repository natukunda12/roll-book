<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rollbook'); // ← change this to your database name
define('DB_USER', 'root');       // ← change this
define('DB_PASS', '');           // ← change this
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'Cashbook Pro');
define('APP_URL', 'http://localhost/cashbook-pro'); // ← change to your domain
define('SESSION_NAME', 'cbpro_session');

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) { die('DB Error: ' . $e->getMessage()); }
    }
    return $pdo;
}

function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function requireLogin(): void { if (!isLoggedIn()) { header('Location: index.php'); exit; } }
function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function isManager(): bool { return in_array($_SESSION['role'] ?? '', ['admin', 'manager']); }

function can(string $perm): bool {
    if (isAdmin()) return true;
    switch ($perm) {
        case 'edit_business':         return isManager() && ($_SESSION['perm_edit_business'] ?? 0) == 1;
        case 'edit_transaction':      return isManager() || ($_SESSION['perm_edit_transaction'] ?? 0) == 1;
        case 'delete_transaction':    return isManager() || ($_SESSION['perm_delete_transaction'] ?? 0) == 1;
        case 'view_all_transactions': return isManager() || ($_SESSION['perm_view_all_transactions'] ?? 0) == 1;
        case 'manage_team':           return isAdmin() || ($_SESSION['admin_access'] ?? 0) == 1;
        case 'manage_book_members':   return isManager();
        case 'manage_clients':        return true; // all logged-in users can add clients to their books
        default: return false;
    }
}

// Check if current user can access a specific book
function canAccessBook(int $book_id): bool {
    if (isAdmin() || isManager()) return true;
    $user = currentUser();
    // Check if user is a member of this book
    $stmt = db()->prepare("SELECT id FROM book_members WHERE book_id=? AND user_id=?");
    $stmt->execute([$book_id, $user['id']]);
    return (bool)$stmt->fetch();
}

function currentUser(): array {
    return [
        'id'                         => $_SESSION['user_id'] ?? null,
        'name'                       => $_SESSION['user_name'] ?? '',
        'email'                      => $_SESSION['user_email'] ?? '',
        'role'                       => $_SESSION['role'] ?? 'user',
        'admin_access'               => $_SESSION['admin_access'] ?? 0,
        'manager_access'             => $_SESSION['manager_access'] ?? 0,
        'perm_edit_transaction'      => $_SESSION['perm_edit_transaction'] ?? 0,
        'perm_delete_transaction'    => $_SESSION['perm_delete_transaction'] ?? 0,
        'perm_edit_business'         => $_SESSION['perm_edit_business'] ?? 0,
        'perm_view_all_transactions' => $_SESSION['perm_view_all_transactions'] ?? 0,
        'business_id'                => $_SESSION['business_id'] ?? null,
    ];
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $amount, string $currency = 'RWF'): string {
    return $currency . ' ' . number_format($amount, 0, '.', ',');
}
