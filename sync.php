<?php
// ============================================================
// Cashbook Pro — Offline Sync Endpoint
// POST /sync.php  with JSON body: { items: [...] }
// ============================================================
require_once 'includes/config.php';
require_once 'includes/notifications.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['error'=>'unauthorized']); exit; }

$user   = currentUser();
$biz_id = $user['business_id'];
$currency = $_SESSION['currency'] ?? 'RWF';

$body  = json_decode(file_get_contents('php://input'), true);
$items = $body['items'] ?? [];

if (empty($items)) { echo json_encode(['synced'=>0,'errors'=>[]]); exit; }

$synced = 0;
$errors = [];

foreach ($items as $item) {
    $client_key = $item['client_key'] ?? '';
    $action     = $item['action'] ?? '';
    $payload    = $item['payload'] ?? [];

    if (!$client_key || !$action) continue;

    // Already synced? skip (idempotent)
    $exists = db()->prepare("SELECT id,synced FROM sync_queue WHERE client_key=?");
    $exists->execute([$client_key]);
    $row = $exists->fetch();
    if ($row && $row['synced']) { $synced++; continue; }

    // Log to queue first
    if (!$row) {
        db()->prepare("INSERT IGNORE INTO sync_queue (business_id,user_id,client_key,action_type,payload) VALUES (?,?,?,?,?)")
           ->execute([$biz_id, $user['id'], $client_key, $action, json_encode($payload)]);
    }

    try {
        $ok = false;

        // ── Transaction Add ────────────────────────────────
        if ($action === 'transaction_add') {
            $book_id = (int)($payload['book_id'] ?? 0);
            $type    = in_array($payload['type']??'', ['income','expense']) ? $payload['type'] : 'income';
            $amount  = max(0, (float)($payload['amount'] ?? 0));
            $cat_id  = !empty($payload['category_id']) ? (int)$payload['category_id'] : null;
            $client_id = !empty($payload['client_id']) ? (int)$payload['client_id'] : null;
            $desc    = substr(trim($payload['description'] ?? ''), 0, 500);
            $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date']??'') ? $payload['date'] : date('Y-m-d');

            // Verify book belongs to business and user can access it
            $chk = db()->prepare("SELECT id,name FROM books WHERE id=? AND business_id=?");
            $chk->execute([$book_id, $biz_id]);
            $book = $chk->fetch();

            if ($book && $amount > 0) {
                db()->prepare("INSERT INTO transactions (book_id,user_id,type,amount,category_id,client_id,description,date) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$book_id, $user['id'], $type, $amount, $cat_id, $client_id, $desc, $date]);
                if (!isAdmin()) {
                    $label = $type === 'income' ? '📈 Income' : '📉 Expense';
                    notifyAdmins($biz_id, $user['id'], 'transaction_add',
                        "{$user['name']} synced a transaction (offline)",
                        "{$label} of ".formatCurrency($amount,$currency).($desc?" — \"{$desc}\"":'')." in \"{$book['name']}\" [synced from offline]"
                    );
                }
                $ok = true;
            } else {
                throw new Exception("Invalid book or amount");
            }
        }

        // ── Transaction Delete ─────────────────────────────
        elseif ($action === 'transaction_delete') {
            $tx_id = (int)($payload['tx_id'] ?? 0);
            $tx = db()->prepare("SELECT t.*,bk.name AS book_name FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?");
            $tx->execute([$tx_id, $biz_id]);
            $t = $tx->fetch();
            if ($t) {
                db()->prepare("DELETE t FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?")
                   ->execute([$tx_id, $biz_id]);
                if (!isAdmin()) {
                    notifyAdmins($biz_id, $user['id'], 'transaction_delete',
                        "{$user['name']} deleted a transaction (offline sync)",
                        "Deleted ".ucfirst($t['type'])." of ".formatCurrency($t['amount'],$currency)." from \"{$t['book_name']}\" [synced]"
                    );
                }
            }
            $ok = true;
        }

        // ── Transaction Edit ───────────────────────────────
        elseif ($action === 'transaction_edit') {
            $tx_id  = (int)($payload['tx_id'] ?? 0);
            $type   = in_array($payload['type']??'', ['income','expense']) ? $payload['type'] : 'income';
            $amount = max(0, (float)($payload['amount'] ?? 0));
            $cat_id = !empty($payload['category_id']) ? (int)$payload['category_id'] : null;
            $desc   = substr(trim($payload['description'] ?? ''), 0, 500);
            $date   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['date']??'') ? $payload['date'] : date('Y-m-d');

            $bk = db()->prepare("SELECT bk.name FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?");
            $bk->execute([$tx_id, $biz_id]);
            $b = $bk->fetch();
            if ($b) {
                db()->prepare("UPDATE transactions t JOIN books bk ON bk.id=t.book_id SET t.type=?,t.amount=?,t.category_id=?,t.description=?,t.date=? WHERE t.id=? AND bk.business_id=?")
                   ->execute([$type,$amount,$cat_id,$desc,$date,$tx_id,$biz_id]);
                if (!isAdmin()) {
                    notifyAdmins($biz_id, $user['id'], 'transaction_edit',
                        "{$user['name']} edited a transaction (offline sync)",
                        "Updated ".ucfirst($type)." to ".formatCurrency($amount,$currency)." in \"{$b['name']}\" [synced]"
                    );
                }
            }
            $ok = true;
        }

        if ($ok) {
            db()->prepare("UPDATE sync_queue SET synced=1,synced_at=NOW() WHERE client_key=?")
               ->execute([$client_key]);
            $synced++;
        }

    } catch (Exception $ex) {
        db()->prepare("UPDATE sync_queue SET conflict=1,conflict_reason=? WHERE client_key=?")
           ->execute([substr($ex->getMessage(),0,255), $client_key]);
        $errors[] = ['key' => $client_key, 'reason' => $ex->getMessage()];
    }
}

echo json_encode([
    'synced'   => $synced,
    'errors'   => $errors,
    'total'    => count($items),
]);
