<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';
requireLogin();

$user     = currentUser();
$biz_id   = $user['business_id'];
$currency = $_SESSION['currency'] ?? 'RWF';
$book_id  = (int)($_GET['book'] ?? 0);
$msg = ''; $error = '';

// Verify book access
if ($book_id && !canAccessBook($book_id)) {
    header('Location: books.php'); exit;
}

// Fetch book info
$book = null;
if ($book_id) {
    $bk = db()->prepare("SELECT * FROM books WHERE id=? AND business_id=?");
    $bk->execute([$book_id, $biz_id]);
    $book = $bk->fetch();
    if (!$book) { header('Location: books.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_client') {
        $bid  = (int)$_POST['book_id'];
        $name = trim($_POST['name'] ?? '');
        $phone= trim($_POST['phone'] ?? '');
        $email= trim($_POST['email'] ?? '');
        $notes= trim($_POST['notes'] ?? '');
        if ($name && canAccessBook($bid)) {
            db()->prepare("INSERT INTO clients (book_id,business_id,name,phone,email,notes,created_by) VALUES (?,?,?,?,?,?,?)")
               ->execute([$bid, $biz_id, $name, $phone, $email, $notes, $user['id']]);
            if (!isAdmin()) {
                notifyAdmins($biz_id, $user['id'], 'client_add',
                    "{$user['name']} added a client",
                    "New client \"{$name}\" added to book \"{$book['name']}\"."
                );
            }
            $msg = "Client {$name} added.";
        } else { $error = 'Client name required.'; }
    }

    if ($action === 'delete_client') {
        $cid = (int)$_POST['client_id'];
        // Verify ownership
        $c = db()->prepare("SELECT c.id FROM clients c JOIN books b ON b.id=c.book_id WHERE c.id=? AND b.business_id=?");
        $c->execute([$cid, $biz_id]);
        if ($c->fetch()) {
            db()->prepare("UPDATE transactions SET client_id=NULL WHERE client_id=?")->execute([$cid]);
            db()->prepare("DELETE FROM clients WHERE id=?")->execute([$cid]);
            $msg = 'Client deleted.';
        }
    }

    if ($action === 'edit_client') {
        $cid  = (int)$_POST['client_id'];
        $name = trim($_POST['name'] ?? '');
        $phone= trim($_POST['phone'] ?? '');
        $email= trim($_POST['email'] ?? '');
        $notes= trim($_POST['notes'] ?? '');
        $c = db()->prepare("SELECT c.id FROM clients c JOIN books b ON b.id=c.book_id WHERE c.id=? AND b.business_id=?");
        $c->execute([$cid, $biz_id]);
        if ($c->fetch() && $name) {
            db()->prepare("UPDATE clients SET name=?,phone=?,email=?,notes=? WHERE id=?")
               ->execute([$name,$phone,$email,$notes,$cid]);
            $msg = 'Client updated.';
        }
    }

    // Add transaction for a client
    if ($action === 'add_client_tx') {
        $cid     = (int)$_POST['client_id'];
        $bid     = (int)$_POST['book_id'];
        $type    = $_POST['type'] ?? 'income';
        $amount  = (float)$_POST['amount'];
        $desc    = trim($_POST['description'] ?? '');
        $date    = $_POST['date'] ?? date('Y-m-d');
        if ($amount > 0 && canAccessBook($bid)) {
            db()->prepare("INSERT INTO transactions (book_id,user_id,type,amount,client_id,description,date) VALUES (?,?,?,?,?,?,?)")
               ->execute([$bid, $user['id'], $type, $amount, $cid, $desc, $date]);
            $cname = db()->prepare("SELECT name FROM clients WHERE id=?");
            $cname->execute([$cid]);
            $cn = $cname->fetchColumn();
            if (!isAdmin()) {
                notifyAdmins($biz_id, $user['id'], 'transaction_add',
                    "{$user['name']} added a client transaction",
                    ucfirst($type)." of ".formatCurrency($amount,$currency)." for client \"{$cn}\"" .($desc?" — \"{$desc}\"":"")
                );
            }
            $msg = 'Transaction added for client.';
        }
    }
}

// Fetch clients — filter by book or all books the user can access
if ($book_id) {
    $clients = db()->prepare("
        SELECT c.*,
          COUNT(t.id) AS tx_count,
          SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS total_in,
          SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS total_out
        FROM clients c
        LEFT JOIN transactions t ON t.client_id=c.id
        WHERE c.book_id=? AND c.business_id=?
        GROUP BY c.id ORDER BY c.name
    ");
    $clients->execute([$book_id, $biz_id]);
} else {
    // All accessible books
    if (isAdmin() || isManager()) {
        $clients = db()->prepare("
            SELECT c.*, b.name AS book_name,
              COUNT(t.id) AS tx_count,
              SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS total_in,
              SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS total_out
            FROM clients c
            JOIN books b ON b.id=c.book_id
            LEFT JOIN transactions t ON t.client_id=c.id
            WHERE c.business_id=?
            GROUP BY c.id ORDER BY c.name
        ");
        $clients->execute([$biz_id]);
    } else {
        $clients = db()->prepare("
            SELECT c.*, b.name AS book_name,
              COUNT(t.id) AS tx_count,
              SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS total_in,
              SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS total_out
            FROM clients c
            JOIN books b ON b.id=c.book_id
            JOIN book_members bm ON bm.book_id=b.id AND bm.user_id=?
            LEFT JOIN transactions t ON t.client_id=c.id
            WHERE c.business_id=?
            GROUP BY c.id ORDER BY c.name
        ");
        $clients->execute([$user['id'], $biz_id]);
    }
}
$all_clients = $clients->fetchAll();

// Books for dropdown (accessible only)
if (isAdmin() || isManager()) {
    $bq = db()->prepare("SELECT id,name FROM books WHERE business_id=? ORDER BY name");
    $bq->execute([$biz_id]);
} else {
    $bq = db()->prepare("SELECT b.id,b.name FROM books b JOIN book_members bm ON bm.book_id=b.id WHERE b.business_id=? AND bm.user_id=? ORDER BY b.name");
    $bq->execute([$biz_id, $user['id']]);
}
$accessible_books = $bq->fetchAll();

// Active client for transaction modal
$active_client = (int)($_GET['client'] ?? 0);

include 'includes/header.php';
?>
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <h1><?= $book ? sanitize($book['name']).' — ' : '' ?>Clients</h1>
    </div>
    <div class="actions">
      <?php if($book_id): ?>
      <a href="books.php" class="btn btn-ghost btn-sm">← Books</a>
      <a href="transactions.php?book=<?= $book_id ?>" class="btn btn-ghost btn-sm">💰 Transactions</a>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="openModal('addClientModal')"><span>+</span><span>Add Client</span></button>
    </div>
  </div>

  <div class="page-content">
    <?php if($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <?php if(empty($all_clients)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--muted)">
      <div style="font-size:40px;margin-bottom:16px">👤</div>
      <div style="font-size:16px;margin-bottom:8px">No clients yet</div>
      <div style="font-size:13px">Add your first client to start tracking their transactions.</div>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px">
      <?php foreach($all_clients as $cl):
        $balance = (float)$cl['total_in'] - (float)$cl['total_out'];
      ?>
      <div class="card" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:44px;height:44px;border-radius:50%;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:500;color:var(--accent);flex-shrink:0">
            <?= strtoupper(substr($cl['name'],0,2)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($cl['name']) ?></div>
            <?php if(!empty($cl['phone'])): ?>
            <div style="font-size:12px;color:var(--muted)">📞 <?= sanitize($cl['phone']) ?></div>
            <?php endif; ?>
            <?php if(isset($cl['book_name'])): ?>
            <div style="font-size:11px;color:var(--accent)">📚 <?= sanitize($cl['book_name']) ?></div>
            <?php endif; ?>
          </div>
          <span style="font-size:11px;background:var(--surface2);color:var(--muted);padding:2px 8px;border-radius:20px;flex-shrink:0"><?= $cl['tx_count'] ?> txns</span>
        </div>

        <?php if(!empty($cl['notes'])): ?>
        <div style="font-size:12px;color:var(--muted);background:var(--surface2);padding:8px 12px;border-radius:8px;line-height:1.5"><?= sanitize($cl['notes']) ?></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:var(--income-dim);border-radius:9px;padding:8px 12px">
            <div style="font-size:10px;color:var(--income);margin-bottom:2px;text-transform:uppercase;font-weight:500">Received</div>
            <div style="font-size:13px;font-weight:500;color:var(--income)"><?= formatCurrency((float)$cl['total_in'],$currency) ?></div>
          </div>
          <div style="background:var(--expense-dim);border-radius:9px;padding:8px 12px">
            <div style="font-size:10px;color:var(--expense);margin-bottom:2px;text-transform:uppercase;font-weight:500">Paid out</div>
            <div style="font-size:13px;font-weight:500;color:var(--expense)"><?= formatCurrency((float)$cl['total_out'],$currency) ?></div>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid var(--border);flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-size:11px;color:var(--muted)">Balance</div>
            <div style="font-size:14px;font-weight:500;color:<?= $balance>=0?'var(--income)':'var(--expense)' ?>"><?= formatCurrency($balance,$currency) ?></div>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" onclick="openAddTx(<?= $cl['id'] ?>,'<?= addslashes($cl['name']) ?>',<?= $cl['book_id'] ?>)">+ Transaction</button>
            <button class="btn btn-ghost btn-sm" onclick="openEditClient(<?= htmlspecialchars(json_encode($cl)) ?>)">Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete client <?= addslashes($cl['name']) ?>?')">
              <input type="hidden" name="action" value="delete_client">
              <input type="hidden" name="client_id" value="<?= $cl['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Del</button>
            </form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Client Modal -->
<div class="modal-overlay" id="addClientModal">
  <div class="modal"><div class="modal-handle"></div><h2>Add Client</h2>
    <form method="POST"><input type="hidden" name="action" value="add_client">
      <div class="form-group"><label>Book</label>
        <select name="book_id" class="form-control" required>
          <option value="">Select book…</option>
          <?php foreach($accessible_books as $bk): ?>
          <option value="<?= $bk['id'] ?>" <?= $book_id==$bk['id']?'selected':'' ?>><?= sanitize($bk['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Full Name *</label><input name="name" class="form-control" placeholder="e.g. John Doe" required></div>
      <div class="form-row">
        <div class="form-group"><label>Phone</label><input name="phone" class="form-control" placeholder="+250 7XX XXX XXX"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="john@email.com"></div>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Any notes about this client…" style="resize:vertical"></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addClientModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Client</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Client Modal -->
<div class="modal-overlay" id="editClientModal">
  <div class="modal"><div class="modal-handle"></div><h2>Edit Client</h2>
    <form method="POST"><input type="hidden" name="action" value="edit_client"><input type="hidden" name="client_id" id="ec_id">
      <div class="form-group"><label>Full Name *</label><input name="name" id="ec_name" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label>Phone</label><input name="phone" id="ec_phone" class="form-control"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" id="ec_email" class="form-control"></div>
      </div>
      <div class="form-group"><label>Notes</label><textarea name="notes" id="ec_notes" class="form-control" rows="2" style="resize:vertical"></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editClientModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Transaction for Client Modal -->
<div class="modal-overlay" id="addTxModal">
  <div class="modal"><div class="modal-handle"></div>
    <h2>Add Transaction</h2>
    <div id="addTxClientName" style="font-size:13px;color:var(--accent);margin-bottom:16px"></div>
    <form method="POST"><input type="hidden" name="action" value="add_client_tx">
      <input type="hidden" name="client_id" id="atx_client_id">
      <input type="hidden" name="book_id" id="atx_book_id">
      <div class="form-row">
        <div class="form-group"><label>Type</label>
          <select name="type" class="form-control">
            <option value="income">Income (received from client)</option>
            <option value="expense">Expense (paid to client)</option>
          </select>
        </div>
        <div class="form-group"><label>Amount (<?= $currency ?>)</label>
          <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Date</label><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="form-group"><label>Description</label><input name="description" class="form-control" placeholder="What is this for?"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addTxModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Transaction</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditClient(cl){
  document.getElementById('ec_id').value    = cl.id;
  document.getElementById('ec_name').value  = cl.name;
  document.getElementById('ec_phone').value = cl.phone || '';
  document.getElementById('ec_email').value = cl.email || '';
  document.getElementById('ec_notes').value = cl.notes || '';
  openModal('editClientModal');
}

function openAddTx(clientId, clientName, bookId){
  document.getElementById('atx_client_id').value = clientId;
  document.getElementById('atx_book_id').value   = bookId;
  document.getElementById('addTxClientName').textContent = '👤 ' + clientName;
  openModal('addTxModal');
}

<?php if($active_client): ?>
openAddTx(<?= $active_client ?>, '', <?= $book_id ?>);
<?php endif; ?>
</script>
</body></html>
