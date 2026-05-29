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

    if ($action === 'create' && isManager()) {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            db()->prepare("INSERT INTO books (business_id,created_by,name,description) VALUES (?,?,?,?)")
               ->execute([$biz_id, $user['id'], $name, $desc]);
            $new_id = db()->lastInsertId();
            // Auto-add creator as member
            db()->prepare("INSERT IGNORE INTO book_members (book_id,user_id,added_by) VALUES (?,?,?)")
               ->execute([$new_id, $user['id'], $user['id']]);
            if (!isAdmin()) {
                notifyAdmins($biz_id, $user['id'], 'book_add',
                    "{$user['name']} created a new book",
                    "New cashbook \"{$name}\"" . ($desc ? " — {$desc}" : '') . " was created."
                );
            }
            $msg = 'Book created.';
        } else { $error = 'Book name required.'; }
    }

    if ($action === 'delete' && isManager()) {
        $id = (int)$_POST['book_id'];
        $bk = db()->prepare("SELECT name FROM books WHERE id=? AND business_id=?");
        $bk->execute([$id, $biz_id]); $book = $bk->fetch();
        db()->prepare("DELETE FROM books WHERE id=? AND business_id=?")->execute([$id, $biz_id]);
        if ($book && !isAdmin()) {
            notifyAdmins($biz_id, $user['id'], 'book_delete',
                "{$user['name']} deleted a book",
                "Cashbook \"{$book['name']}\" and all its transactions were deleted."
            );
        }
        $msg = 'Book deleted.';
    }

    if ($action === 'edit' && isManager()) {
        $id   = (int)$_POST['book_id'];
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            db()->prepare("UPDATE books SET name=?,description=? WHERE id=? AND business_id=?")
               ->execute([$name, $desc, $id, $biz_id]);
            if (!isAdmin()) {
                notifyAdmins($biz_id, $user['id'], 'book_edit',
                    "{$user['name']} edited a book", "Cashbook renamed to \"{$name}\"."
                );
            }
            $msg = 'Book updated.';
        }
    }

    // Add member to book
    if ($action === 'add_member' && can('manage_book_members')) {
        $book_id = (int)$_POST['book_id'];
        $uid     = (int)$_POST['member_user_id'];
        // Verify book belongs to business
        $chk = db()->prepare("SELECT id,name FROM books WHERE id=? AND business_id=?");
        $chk->execute([$book_id, $biz_id]);
        if ($chk->fetch()) {
            db()->prepare("INSERT IGNORE INTO book_members (book_id,user_id,added_by) VALUES (?,?,?)")
               ->execute([$book_id, $uid, $user['id']]);
            if (!isAdmin()) {
                $uname = db()->prepare("SELECT name FROM users WHERE id=?");
                $uname->execute([$uid]);
                $added = $uname->fetchColumn();
                notifyAdmins($biz_id, $user['id'], 'book_member_add',
                    "{$user['name']} added a member to a book",
                    "{$added} was added as a member of the book."
                );
            }
            $msg = 'Member added to book.';
        }
    }

    // Remove member from book
    if ($action === 'remove_member' && can('manage_book_members')) {
        $book_id = (int)$_POST['book_id'];
        $uid     = (int)$_POST['member_user_id'];
        db()->prepare("DELETE FROM book_members WHERE book_id=? AND user_id=?")->execute([$book_id, $uid]);
        $msg = 'Member removed.';
    }
}

// Fetch books — regular users only see books they're members of
if (isAdmin() || isManager()) {
    $books = db()->prepare("
        SELECT b.*, u.name AS creator,
          COUNT(DISTINCT t.id) AS tx_count,
          COUNT(DISTINCT bm.user_id) AS member_count,
          SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS total_income,
          SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS total_expense
        FROM books b
        LEFT JOIN transactions t ON t.book_id=b.id
        LEFT JOIN users u ON u.id=b.created_by
        LEFT JOIN book_members bm ON bm.book_id=b.id
        WHERE b.business_id=?
        GROUP BY b.id ORDER BY b.created_at DESC
    ");
    $books->execute([$biz_id]);
} else {
    $books = db()->prepare("
        SELECT b.*, u.name AS creator,
          COUNT(DISTINCT t.id) AS tx_count,
          COUNT(DISTINCT bm2.user_id) AS member_count,
          SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS total_income,
          SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS total_expense
        FROM books b
        JOIN book_members bm ON bm.book_id=b.id AND bm.user_id=?
        LEFT JOIN transactions t ON t.book_id=b.id
        LEFT JOIN users u ON u.id=b.created_by
        LEFT JOIN book_members bm2 ON bm2.book_id=b.id
        WHERE b.business_id=?
        GROUP BY b.id ORDER BY b.created_at DESC
    ");
    $books->execute([$user['id'], $biz_id]);
}
$all_books = $books->fetchAll();

// All team users (for adding members)
$team = db()->prepare("SELECT id,name,role FROM users WHERE business_id=? AND status='active' ORDER BY name");
$team->execute([$biz_id]);
$all_users = $team->fetchAll();

include 'includes/header.php';
?>
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <h1>Books</h1>
    </div>
    <?php if(isManager()): ?>
    <div class="actions">
      <button class="btn btn-primary" onclick="openModal('createModal')"><span>+</span><span>New Book</span></button>
    </div>
    <?php endif; ?>
  </div>

  <div class="page-content">
    <?php if($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-error"><?= sanitize($error) ?></div><?php endif; ?>

    <?php if(empty($all_books)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--muted)">
      <div style="font-size:40px;margin-bottom:16px">📚</div>
      <div style="font-size:16px;margin-bottom:8px">No books yet</div>
      <div style="font-size:13px">You haven't been added to any books yet, or no books exist.</div>
    </div>
    <?php else: ?>
    <div class="books-grid">
      <?php foreach($all_books as $bk):
        $inc = (float)$bk['total_income'];
        $exp = (float)$bk['total_expense'];
        $bal = $inc - $exp;
      ?>
      <div class="card" style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
          <div style="min-width:0">
            <div style="font-size:15px;font-weight:500;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($bk['name']) ?></div>
            <div style="font-size:12px;color:var(--muted)"><?= sanitize($bk['description'] ?: 'No description') ?></div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
            <span style="font-size:11px;background:var(--surface2);color:var(--muted);padding:2px 8px;border-radius:20px"><?= $bk['tx_count'] ?> txns</span>
            <span style="font-size:11px;background:var(--accent-dim);color:var(--accent);padding:2px 8px;border-radius:20px">👥 <?= $bk['member_count'] ?> members</span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:var(--income-dim);border-radius:9px;padding:10px 12px">
            <div style="font-size:10px;color:var(--income);margin-bottom:3px;text-transform:uppercase;font-weight:500">Income</div>
            <div style="font-size:13px;font-weight:500;color:var(--income)"><?= formatCurrency($inc,$currency) ?></div>
          </div>
          <div style="background:var(--expense-dim);border-radius:9px;padding:10px 12px">
            <div style="font-size:10px;color:var(--expense);margin-bottom:3px;text-transform:uppercase;font-weight:500">Expense</div>
            <div style="font-size:13px;font-weight:500;color:var(--expense)"><?= formatCurrency($exp,$currency) ?></div>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:8px;border-top:1px solid var(--border);flex-wrap:wrap;gap:8px">
          <div>
            <div style="font-size:11px;color:var(--muted)">Balance</div>
            <div style="font-size:14px;font-weight:500;color:<?= $bal>=0?'var(--income)':'var(--expense)' ?>"><?= formatCurrency($bal,$currency) ?></div>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="transactions.php?book=<?= $bk['id'] ?>" class="btn btn-ghost btn-sm">💰 Transactions</a>
            <a href="clients.php?book=<?= $bk['id'] ?>" class="btn btn-ghost btn-sm">👤 Clients</a>
            <?php if(can('manage_book_members')): ?>
            <button class="btn btn-ghost btn-sm" onclick="openMembers(<?= $bk['id'] ?>,'<?= addslashes($bk['name']) ?>')">👥 Members</button>
            <?php endif; ?>
            <?php if(isManager()): ?>
            <button class="btn btn-ghost btn-sm" onclick="openEdit(<?= $bk['id'] ?>,'<?= addslashes($bk['name']) ?>','<?= addslashes($bk['description']??'') ?>')">Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this book and ALL its data?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="book_id" value="<?= $bk['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Del</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal">
  <div class="modal"><div class="modal-handle"></div><h2>New Cashbook</h2>
    <form method="POST"><input type="hidden" name="action" value="create">
      <div class="form-group"><label>Book Name</label><input name="name" class="form-control" placeholder="e.g. April 2026 Operations" required></div>
      <div class="form-group"><label>Description</label><input name="description" class="form-control" placeholder="What is this for?"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Book</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
  <div class="modal"><div class="modal-handle"></div><h2>Edit Book</h2>
    <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="book_id" id="edit_book_id">
      <div class="form-group"><label>Book Name</label><input name="name" id="edit_name" class="form-control" required></div>
      <div class="form-group"><label>Description</label><input name="description" id="edit_desc" class="form-control"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Members Modal -->
<div class="modal-overlay" id="membersModal">
  <div class="modal" style="max-width:500px"><div class="modal-handle"></div>
    <h2>Book Members</h2>
    <div id="membersBookName" style="font-size:13px;color:var(--muted);margin-bottom:16px"></div>

    <!-- Add member form -->
    <form method="POST" style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
      <input type="hidden" name="action" value="add_member">
      <input type="hidden" name="book_id" id="members_book_id">
      <select name="member_user_id" class="form-control" style="flex:1;min-width:160px" required>
        <option value="">Select team member…</option>
        <?php foreach($all_users as $u): ?>
        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= ucfirst($u['role']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Add</button>
    </form>

    <!-- Current members list (loaded via JS) -->
    <div id="membersList" style="display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto"></div>

    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('membersModal')">Close</button>
    </div>
  </div>
</div>

<script>
function openEdit(id,name,desc){
  document.getElementById('edit_book_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_desc').value=desc;
  openModal('editModal');
}

function openMembers(bookId, bookName){
  document.getElementById('members_book_id').value = bookId;
  document.getElementById('membersBookName').textContent = '📚 ' + bookName;
  openModal('membersModal');
  loadMembers(bookId);
}

function loadMembers(bookId){
  fetch('api_book_members.php?book_id='+bookId)
    .then(r=>r.json())
    .then(data=>{
      const list = document.getElementById('membersList');
      if(!data.members || data.members.length===0){
        list.innerHTML='<div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">No members yet</div>';
        return;
      }
      list.innerHTML = data.members.map(m=>`
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface2);border-radius:9px">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:500;color:var(--accent);flex-shrink:0">${m.name.substring(0,2).toUpperCase()}</div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:500">${m.name}</div>
            <div style="font-size:11px;color:var(--muted)">${m.role} · Added ${m.added}</div>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="remove_member">
            <input type="hidden" name="book_id" value="${bookId}">
            <input type="hidden" name="member_user_id" value="${m.id}">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove ${m.name}?')">Remove</button>
          </form>
        </div>
      `).join('');
    });
}
</script>
</body></html>
<script>
// Offline-aware book creation
const bkForm = document.querySelector('#createModal form');
if (bkForm) {
  bkForm.addEventListener('submit', async function(e) {
    if (navigator.onLine) return;
    e.preventDefault();
    const fd = new FormData(this);
    const name = (fd.get('name')||'').trim();
    if (!name) { alert('Book name required.'); return; }
    await queueOfflineAction('book_add', { name, description: fd.get('description')||'' });
    closeModal('createModal');
    // Show placeholder card
    const grid = document.querySelector('.books-grid');
    if (grid) {
      const div = document.createElement('div');
      div.className = 'card';
      div.style.opacity = '0.5';
      div.innerHTML = `<div style="font-size:15px;font-weight:500">${name}</div><div style="font-size:12px;color:var(--muted);margin-top:4px">📵 Queued offline — syncing soon</div>`;
      grid.prepend(div);
    }
    showOfflineToast('Book queued offline — will sync when online');
  });
}

function showOfflineToast(msg) {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;top:80px;left:50%;transform:translateX(-50%);background:#1e2333;border:1px solid #3b82f6;color:#93c5fd;padding:10px 20px;border-radius:10px;font-size:13px;font-family:'DM Sans',sans-serif;z-index:900;box-shadow:0 4px 20px rgba(0,0,0,.4);white-space:nowrap;`;
  t.textContent = '📵 ' + msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>
