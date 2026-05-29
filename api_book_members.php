<?php
require_once 'includes/config.php';
header('Content-Type: application/json');
requireLogin();

$user   = currentUser();
$biz_id = $user['business_id'];
$book_id = (int)($_GET['book_id'] ?? 0);

// Verify book belongs to this business
$chk = db()->prepare("SELECT id FROM books WHERE id=? AND business_id=?");
$chk->execute([$book_id, $biz_id]);
if (!$chk->fetch()) { echo json_encode(['error'=>'not found']); exit; }

$stmt = db()->prepare("
    SELECT u.id, u.name, u.role, bm.added_at
    FROM book_members bm
    JOIN users u ON u.id = bm.user_id
    WHERE bm.book_id = ?
    ORDER BY bm.added_at ASC
");
$stmt->execute([$book_id]);
$members = $stmt->fetchAll();

$out = array_map(fn($m) => [
    'id'    => $m['id'],
    'name'  => $m['name'],
    'role'  => ucfirst($m['role']),
    'added' => date('M j', strtotime($m['added_at'])),
], $members);

echo json_encode(['members' => $out]);
