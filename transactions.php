<?php
require_once 'includes/config.php';
require_once 'includes/notifications.php';
requireLogin();

$user     = currentUser();
$biz_id   = $user['business_id'];
$currency = $_SESSION['currency'] ?? 'RWF';
$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $book_id = (int)$_POST['book_id'];
        $type    = $_POST['type'] ?? 'income';
        $amount  = (float)$_POST['amount'];
        $cat_id  = $_POST['category_id'] ?: null;
        $desc    = trim($_POST['description'] ?? '');
        $date    = $_POST['date'] ?? date('Y-m-d');

        $chk = db()->prepare("SELECT id,name FROM books WHERE id=? AND business_id=?");
        $chk->execute([$book_id, $biz_id]);
        $book = $chk->fetch();

        if ($book && $amount > 0) {
            db()->prepare("INSERT INTO transactions (book_id,user_id,type,amount,category_id,description,date) VALUES (?,?,?,?,?,?,?)")
               ->execute([$book_id, $user['id'], $type, $amount, $cat_id, $desc, $date]);

            // Notify admins if actor is not admin
            if (!isAdmin()) {
                $label   = $type === 'income' ? '📈 Income' : '📉 Expense';
                $amt_fmt = formatCurrency($amount, $currency);
                notifyAdmins(
                    $biz_id,
                    $user['id'],
                    'transaction_add',
                    "{$user['name']} added a transaction",
                    "{$label} of {$amt_fmt}" . ($desc ? " — \"{$desc}\"" : '') . " in book \"{$book['name']}\""
                );
            }
            $msg = 'Transaction added.';
        } else { $error = 'Invalid data.'; }
    }

    if ($action === 'delete' && can('delete_transaction')) {
        $id = (int)$_POST['tx_id'];

        // Fetch tx details before deleting for notification
        $tx_info = db()->prepare("SELECT t.*,bk.name AS book_name FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?");
        $tx_info->execute([$id, $biz_id]);
        $tx = $tx_info->fetch();

        db()->prepare("DELETE t FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?")
           ->execute([$id, $biz_id]);

        if ($tx && !isAdmin()) {
            notifyAdmins(
                $biz_id,
                $user['id'],
                'transaction_delete',
                "{$user['name']} deleted a transaction",
                "Deleted " . ucfirst($tx['type']) . " of " . formatCurrency($tx['amount'], $currency)
                . ($tx['description'] ? " — \"{$tx['description']}\"" : '')
                . " from book \"{$tx['book_name']}\""
            );
        }
        $msg = 'Transaction deleted.';
    }

    if ($action === 'edit' && can('edit_transaction')) {
        $id     = (int)$_POST['tx_id'];
        $amount = (float)$_POST['amount'];
        $type   = $_POST['type'] ?? 'income';
        $cat_id = $_POST['category_id'] ?: null;
        $desc   = trim($_POST['description'] ?? '');
        $date   = $_POST['date'] ?? date('Y-m-d');

        // Fetch book name for notification
        $bk_info = db()->prepare("SELECT bk.name FROM transactions t JOIN books bk ON bk.id=t.book_id WHERE t.id=? AND bk.business_id=?");
        $bk_info->execute([$id, $biz_id]);
        $bk = $bk_info->fetch();

        db()->prepare("UPDATE transactions t JOIN books bk ON bk.id=t.book_id SET t.type=?,t.amount=?,t.category_id=?,t.description=?,t.date=? WHERE t.id=? AND bk.business_id=?")
           ->execute([$type, $amount, $cat_id, $desc, $date, $id, $biz_id]);

        if ($bk && !isAdmin()) {
            notifyAdmins(
                $biz_id,
                $user['id'],
                'transaction_edit',
                "{$user['name']} edited a transaction",
                "Updated " . ucfirst($type) . " to " . formatCurrency($amount, $currency)
                . ($desc ? " — \"{$desc}\"" : '')
                . " in book \"{$bk['name']}\""
            );
        }
        $msg = 'Transaction updated.';
    }
}

// Filters
$filter_book = (int)($_GET['book'] ?? 0);
$filter_type = $_GET['type'] ?? '';
$filter_from = $_GET['from'] ?? '';
$filter_to   = $_GET['to'] ?? '';
$search      = trim($_GET['q'] ?? '');

$where  = "bk.business_id=?";
$params = [$biz_id];

if (!can('view_all_transactions')) {
    $where .= " AND t.user_id=?";
    $params[] = $user['id'];
}
if ($filter_book) { $where .= " AND t.book_id=?";         $params[] = $filter_book; }
if ($filter_type) { $where .= " AND t.type=?";             $params[] = $filter_type; }
if ($filter_from) { $where .= " AND t.date >= ?";          $params[] = $filter_from; }
if ($filter_to)   { $where .= " AND t.date <= ?";          $params[] = $filter_to; }
if ($search)      { $where .= " AND t.description LIKE ?"; $params[] = '%'.$search.'%'; }

$transactions = db()->prepare("
    SELECT t.*, c.name AS cat_name, u.name AS user_name, bk.name AS book_name
    FROM transactions t
    JOIN books bk ON bk.id = t.book_id
    LEFT JOIN categories c ON c.id = t.category_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE $where
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 200
");
$transactions->execute($params);
$all_tx = $transactions->fetchAll();

$books_q = db()->prepare("SELECT id,name FROM books WHERE business_id=? ORDER BY name");
$books_q->execute([$biz_id]);
$all_books = $books_q->fetchAll();

$cats = db()->prepare("SELECT * FROM categories WHERE business_id=? ORDER BY type,name");
$cats->execute([$biz_id]);
$all_cats = $cats->fetchAll();

$total_inc = array_sum(array_map(fn($t) => $t['type']==='income' ? (float)$t['amount'] : 0, $all_tx));
$total_exp = array_sum(array_map(fn($t) => $t['type']==='expense' ? (float)$t['amount'] : 0, $all_tx));
$auto_open = ($_GET['action'] ?? '') === 'add' ? 'true' : 'false';

include 'includes/header.php';
?>
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <h1>Transactions</h1>
    </div>
    <div class="actions">
      <button class="btn btn-primary" onclick="openModal('addModal')"><span>+</span><span>Add</span></button>
    </div>
  </div>

  <div class="page-content">
    <?php if($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <!-- Filters -->
    <form method="GET">
      <div class="filter-bar">
        <div class="fgroup"><span class="flabel">Search</span><input name="q" value="<?= sanitize($search) ?>" class="form-control" placeholder="Search…" style="width:150px"></div>
        <div class="fgroup"><span class="flabel">Book</span>
          <select name="book" class="form-control">
            <option value="">All books</option>
            <?php foreach($all_books as $bk): ?><option value="<?= $bk['id'] ?>" <?= $filter_book==$bk['id']?'selected':'' ?>><?= sanitize($bk['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fgroup"><span class="flabel">Type</span>
          <select name="type" class="form-control" style="width:110px">
            <option value="">All</option>
            <option value="income" <?= $filter_type==='income'?'selected':'' ?>>Income</option>
            <option value="expense" <?= $filter_type==='expense'?'selected':'' ?>>Expense</option>
          </select>
        </div>
        <div class="fgroup"><span class="flabel">From</span><input type="date" name="from" value="<?= sanitize($filter_from) ?>" class="form-control"></div>
        <div class="fgroup"><span class="flabel">To</span><input type="date" name="to" value="<?= sanitize($filter_to) ?>" class="form-control"></div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-ghost">Filter</button>
          <a href="transactions.php" class="btn btn-ghost">Clear</a>
        </div>
      </div>
    </form>

    <!-- Totals -->
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-label">Income</div><div class="stat-value income"><?= formatCurrency($total_inc,$currency) ?></div></div>
      <div class="stat-card"><div class="stat-label">Expenses</div><div class="stat-value expense"><?= formatCurrency($total_exp,$currency) ?></div></div>
      <div class="stat-card"><div class="stat-label">Net</div><div class="stat-value <?= $total_inc-$total_exp>=0?'income':'expense' ?>"><?= formatCurrency($total_inc-$total_exp,$currency) ?></div></div>
      <div class="stat-card"><div class="stat-label">Records</div><div class="stat-value"><?= count($all_tx) ?></div></div>
    </div>

    <!-- Table -->
    <div class="card" style="padding:0;overflow:hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Date</th><th>Description</th><th>Book</th><th>Category</th>
              <?php if(can('view_all_transactions')): ?><th>By</th><?php endif; ?>
              <th>Type</th><th style="text-align:right">Amount</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($all_tx as $tx): ?>
            <tr>
              <td style="white-space:nowrap"><?= date('M j, Y', strtotime($tx['date'])) ?></td>
              <td><?= sanitize($tx['description'] ?: '—') ?></td>
              <td style="color:var(--muted)"><?= sanitize($tx['book_name']) ?></td>
              <td style="color:var(--muted)"><?= sanitize($tx['cat_name'] ?? '—') ?></td>
              <?php if(can('view_all_transactions')): ?><td style="color:var(--muted)"><?= sanitize($tx['user_name']) ?></td><?php endif; ?>
              <td><span class="badge badge-<?= $tx['type'] ?>"><?= ucfirst($tx['type']) ?></span></td>
              <td style="text-align:right;font-weight:500;color:var(--<?= $tx['type'] ?>);white-space:nowrap">
                <?= $tx['type']==='income'?'+':'-' ?><?= formatCurrency($tx['amount'],$currency) ?>
              </td>
              <td>
                <div style="display:flex;gap:5px">
                  <?php if(can('edit_transaction')): ?>
                  <button class="btn btn-ghost btn-sm" onclick="openEditTx(<?= htmlspecialchars(json_encode($tx)) ?>)">Edit</button>
                  <?php endif; ?>
                  <?php if(can('delete_transaction')): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete this transaction?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Del</button>
                  </form>
                  <?php endif; ?>
                  <?php if(!can('edit_transaction') && !can('delete_transaction')): ?>
                  <span style="font-size:11px;color:var(--muted)">View only</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($all_tx)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">No transactions found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <h2>Add Transaction</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label>Type</label><select name="type" class="form-control" required><option value="income">Income</option><option value="expense">Expense</option></select></div>
        <div class="form-group"><label>Amount (<?= $currency ?>)</label><input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Book</label><select name="book_id" class="form-control" required><option value="">Select book…</option><?php foreach($all_books as $bk):?><option value="<?=$bk['id']?>" <?=$filter_book==$bk['id']?'selected':''?>><?=sanitize($bk['name'])?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Category</label><select name="category_id" class="form-control"><option value="">None</option><?php foreach($all_cats as $cat):?><option value="<?=$cat['id']?>">[<?=ucfirst($cat['type'])?>] <?=sanitize($cat['name'])?></option><?php endforeach;?></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Date</label><input type="date" name="date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
        <div class="form-group"><label>Description</label><input name="description" class="form-control" placeholder="What is this for?"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Transaction</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-handle"></div>
    <h2>Edit Transaction</h2>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="tx_id" id="etx_id">
      <div class="form-row">
        <div class="form-group"><label>Type</label><select name="type" id="etx_type" class="form-control"><option value="income">Income</option><option value="expense">Expense</option></select></div>
        <div class="form-group"><label>Amount (<?= $currency ?>)</label><input type="number" name="amount" id="etx_amount" class="form-control" step="0.01" min="0.01" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Category</label><select name="category_id" id="etx_cat" class="form-control"><option value="">None</option><?php foreach($all_cats as $cat):?><option value="<?=$cat['id']?>">[<?=ucfirst($cat['type'])?>] <?=sanitize($cat['name'])?></option><?php endforeach;?></select></div>
        <div class="form-group"><label>Date</label><input type="date" name="date" id="etx_date" class="form-control" required></div>
      </div>
      <div class="form-group"><label>Description</label><input name="description" id="etx_desc" class="form-control"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
const autoOpen = <?= $auto_open ?>;
if(autoOpen) openModal('addModal');
function openEditTx(tx){
  document.getElementById('etx_id').value=tx.id;
  document.getElementById('etx_type').value=tx.type;
  document.getElementById('etx_amount').value=tx.amount;
  document.getElementById('etx_cat').value=tx.category_id||'';
  document.getElementById('etx_date').value=tx.date;
  document.getElementById('etx_desc').value=tx.description||'';
  openModal('editModal');
}
</script>
</body></html>
<!-- This block is appended and overrides form submit when offline -->
<script>
// ─────────────────────────────────────────────────────────
// Offline-aware transaction form submission
// ─────────────────────────────────────────────────────────

function interceptForms() {
  // Intercept Add Transaction form
  const addForm = document.querySelector('#addModal form');
  if (addForm) {
    addForm.addEventListener('submit', async function(e) {
      if (navigator.onLine) return; // let it submit normally
      e.preventDefault();
      const fd = new FormData(this);
      const payload = {
        book_id:     parseInt(fd.get('book_id')||0),
        type:        fd.get('type'),
        amount:      parseFloat(fd.get('amount')||0),
        category_id: fd.get('category_id') || null,
        description: fd.get('description') || '',
        date:        fd.get('date') || new Date().toISOString().slice(0,10),
      };
      if (!payload.book_id || !payload.amount) {
        alert('Please fill in book and amount.'); return;
      }
      await queueOfflineAction('transaction_add', payload);
      // Show optimistic feedback
      closeModal('addModal');
      showOfflineToast('Transaction saved offline — will sync when online');
      this.reset();
    });
  }

  // Intercept Delete buttons
  document.querySelectorAll('form[data-tx-delete]').forEach(form => {
    form.addEventListener('submit', async function(e) {
      if (navigator.onLine) return;
      e.preventDefault();
      if (!confirm('Delete this transaction? (will sync when online)')) return;
      const tx_id = parseInt(this.querySelector('[name=tx_id]').value);
      await queueOfflineAction('transaction_delete', { tx_id });
      const row = this.closest('tr');
      if (row) { row.style.opacity='0.4'; row.style.transition='opacity .3s'; }
      showOfflineToast('Deletion queued — will sync when online');
    });
  });
}

function showOfflineToast(msg) {
  const t = document.createElement('div');
  t.style.cssText = `
    position:fixed;top:80px;left:50%;transform:translateX(-50%);
    background:#1e2333;border:1px solid #3b82f6;color:#93c5fd;
    padding:10px 20px;border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;
    z-index:900;box-shadow:0 4px 20px rgba(0,0,0,.4);white-space:nowrap;
  `;
  t.textContent = '📵 ' + msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// Mark delete forms
document.querySelectorAll('form').forEach(f => {
  if (f.querySelector('[name=action][value=delete]')) {
    f.setAttribute('data-tx-delete', '1');
  }
});

document.addEventListener('DOMContentLoaded', interceptForms);
</script>
