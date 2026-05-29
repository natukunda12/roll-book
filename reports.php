<?php
require_once 'includes/config.php';
requireLogin();

$user     = currentUser();
$biz_id   = $user['business_id'];
$currency = $_SESSION['currency'] ?? 'RWF';

$from    = $_GET['from'] ?? date('Y-m-01');
$to      = $_GET['to']   ?? date('Y-m-d');
$book_id = (int)($_GET['book'] ?? 0);
$export  = $_GET['export'] ?? '';

$books = db()->prepare("SELECT id,name FROM books WHERE business_id=?");
$books->execute([$biz_id]);
$all_books = $books->fetchAll();

$where  = "bk.business_id=? AND t.date BETWEEN ? AND ?";
$params = [$biz_id, $from, $to];
if ($book_id) { $where .= " AND t.book_id=?"; $params[] = $book_id; }

$tx = db()->prepare("
  SELECT t.*, c.name AS cat_name, u.name AS user_name, bk.name AS book_name
  FROM transactions t
  JOIN books bk ON bk.id=t.book_id
  LEFT JOIN categories c ON c.id=t.category_id
  LEFT JOIN users u ON u.id=t.user_id
  WHERE $where ORDER BY t.date
");
$tx->execute($params);
$all_tx = $tx->fetchAll();

$total_inc = array_sum(array_map(fn($t)=>$t['type']==='income'?(float)$t['amount']:0, $all_tx));
$total_exp = array_sum(array_map(fn($t)=>$t['type']==='expense'?(float)$t['amount']:0, $all_tx));

if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report-'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Book','Description','Category','Type','Amount','Added By']);
    foreach ($all_tx as $t)
        fputcsv($out, [$t['date'],$t['book_name'],$t['description'],$t['cat_name']??'',ucfirst($t['type']),$t['amount'],$t['user_name']]);
    fclose($out); exit;
}

if ($export === 'print') {
    $biz = db()->prepare("SELECT * FROM businesses WHERE id=?");
    $biz->execute([$biz_id]);
    $business = $biz->fetch();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report</title>';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>*{box-sizing:border-box}body{font-family:sans-serif;padding:20px;color:#111;max-width:900px;margin:0 auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:7px 10px;border:1px solid #ddd;text-align:left}th{background:#f5f5f5}h1{font-size:20px;margin-bottom:4px}p{margin:2px 0;color:#555;font-size:13px}.totals{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}.tot{padding:10px 16px;border-radius:8px;font-size:13px}.inc{background:#e7faf2;color:#1a7a50}.exp{background:#fff0f2;color:#a02040}.bal{background:#eef3ff;color:#2040a0}@media print{button{display:none}}@media(max-width:600px){.tot{width:100%}}</style></head><body>';
    echo '<h1>'.htmlspecialchars($business['name']).' — Financial Report</h1>';
    echo '<p>Period: '.$from.' to '.$to.'</p><p>Generated: '.date('Y-m-d H:i').'</p>';
    echo '<div class="totals">';
    echo '<div class="tot inc">Income: '.formatCurrency($total_inc,$currency).'</div>';
    echo '<div class="tot exp">Expenses: '.formatCurrency($total_exp,$currency).'</div>';
    echo '<div class="tot bal">Balance: '.formatCurrency($total_inc-$total_exp,$currency).'</div>';
    echo '</div><br>';
    echo '<div style="overflow-x:auto"><table><thead><tr><th>Date</th><th>Book</th><th>Description</th><th>Category</th><th>Type</th><th>Amount</th></tr></thead><tbody>';
    foreach ($all_tx as $t) {
        $c = $t['type']==='income'?'#1a7a50':'#a02040';
        echo '<tr><td>'.htmlspecialchars($t['date']).'</td><td>'.htmlspecialchars($t['book_name']).'</td><td>'.htmlspecialchars($t['description']?:'—').'</td><td>'.htmlspecialchars($t['cat_name']??'—').'</td><td style="color:'.$c.'">'.ucfirst($t['type']).'</td><td style="color:'.$c.';font-weight:500">'.($t['type']==='income'?'+':'-').formatCurrency($t['amount'],$currency).'</td></tr>';
    }
    echo '</tbody></table></div><br><button onclick="window.print()">🖨 Print / Save PDF</button></body></html>';
    exit;
}

$cat_stmt = db()->prepare("
  SELECT c.name, t.type, SUM(t.amount) AS total
  FROM transactions t JOIN books bk ON bk.id=t.book_id
  LEFT JOIN categories c ON c.id=t.category_id
  WHERE $where GROUP BY c.name, t.type ORDER BY total DESC
");
$cat_stmt->execute($params);
$cat_data = $cat_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <h1>Reports</h1>
    </div>
    <div class="actions">
      <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&book=<?= $book_id ?>&export=csv" class="btn btn-ghost btn-sm">⬇ CSV</a>
      <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&book=<?= $book_id ?>&export=print" target="_blank" class="btn btn-primary btn-sm">🖨 PDF</a>
    </div>
  </div>

  <div class="page-content">

    <!-- Filter -->
    <form method="GET" style="margin-bottom:18px">
      <div class="filter-bar">
        <div class="fgroup">
          <span class="flabel">From</span>
          <input type="date" name="from" value="<?= $from ?>" class="form-control">
        </div>
        <div class="fgroup">
          <span class="flabel">To</span>
          <input type="date" name="to" value="<?= $to ?>" class="form-control">
        </div>
        <div class="fgroup">
          <span class="flabel">Book</span>
          <select name="book" class="form-control">
            <option value="">All books</option>
            <?php foreach($all_books as $bk): ?>
            <option value="<?= $bk['id'] ?>" <?= $book_id==$bk['id']?'selected':'' ?>><?= sanitize($bk['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Generate</button>
      </div>
    </form>

    <!-- Totals -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Income</div>
        <div class="stat-value income"><?= formatCurrency($total_inc,$currency) ?></div>
        <div class="stat-sub"><?= $from ?> → <?= $to ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Expenses</div>
        <div class="stat-value expense"><?= formatCurrency($total_exp,$currency) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Net Balance</div>
        <div class="stat-value <?= $total_inc-$total_exp>=0?'income':'expense' ?>"><?= formatCurrency($total_inc-$total_exp,$currency) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?= count($all_tx) ?></div>
      </div>
    </div>

    <!-- Table + Category breakdown -->
    <div style="display:grid;grid-template-columns:1fr;gap:18px">

      <!-- On wider screens show side by side -->
      <div style="display:grid;grid-template-columns:1fr 280px;gap:18px" class="report-grid">

        <div class="card" style="padding:0;overflow:hidden">
          <div style="padding:16px 18px;font-size:15px;font-weight:500;border-bottom:1px solid var(--border)">Transaction Details</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Book</th>
                  <th>Description</th>
                  <th>Category</th>
                  <th>Type</th>
                  <th style="text-align:right">Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($all_tx as $t): ?>
                <tr>
                  <td style="white-space:nowrap"><?= date('M j, Y', strtotime($t['date'])) ?></td>
                  <td style="color:var(--muted)"><?= sanitize($t['book_name']) ?></td>
                  <td><?= sanitize($t['description']?:'—') ?></td>
                  <td style="color:var(--muted)"><?= sanitize($t['cat_name']??'—') ?></td>
                  <td><span class="badge badge-<?= $t['type'] ?>"><?= ucfirst($t['type']) ?></span></td>
                  <td style="text-align:right;font-weight:500;color:var(--<?= $t['type'] ?>);white-space:nowrap">
                    <?= $t['type']==='income'?'+':'-' ?><?= formatCurrency($t['amount'],$currency) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($all_tx)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No data for this period</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div style="font-size:15px;font-weight:500;margin-bottom:14px">By Category</div>
          <?php foreach($cat_data as $cat): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);gap:8px">
            <div style="min-width:0">
              <div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= sanitize($cat['name']??'Uncategorized') ?></div>
              <span class="badge badge-<?= $cat['type'] ?>" style="font-size:10px"><?= ucfirst($cat['type']) ?></span>
            </div>
            <div style="font-size:13px;font-weight:500;color:var(--<?= $cat['type'] ?>);white-space:nowrap"><?= formatCurrency($cat['total'],$currency) ?></div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($cat_data)): ?>
          <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">No data</div>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </div>
</div>

<style>
@media(max-width:700px){
  .report-grid{ grid-template-columns:1fr !important }
}
</style>
</body>
</html>
