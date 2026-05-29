<?php
require_once 'includes/config.php';
requireLogin();
$user=currentUser();$biz_id=$user['business_id'];$currency=$_SESSION['currency']??'RWF';
$stmt=db()->prepare("SELECT SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS inc,SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS exp,COUNT(*) AS total FROM transactions t JOIN books b ON b.id=t.book_id WHERE b.business_id=?");
$stmt->execute([$biz_id]);$stats=$stmt->fetch();
$income=(float)($stats['inc']??0);$expense=(float)($stats['exp']??0);$balance=$income-$expense;
$monthly=db()->prepare("SELECT DATE_FORMAT(t.date,'%b') AS month,DATE_FORMAT(t.date,'%Y-%m') AS ym,SUM(CASE WHEN t.type='income' THEN t.amount ELSE 0 END) AS inc,SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END) AS exp FROM transactions t JOIN books b ON b.id=t.book_id WHERE b.business_id=? AND t.date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) GROUP BY ym,month ORDER BY ym");
$monthly->execute([$biz_id]);$mdata=$monthly->fetchAll();
$recent=db()->prepare("SELECT t.*,c.name AS cat_name,bk.name AS book_name FROM transactions t JOIN books bk ON bk.id=t.book_id LEFT JOIN categories c ON c.id=t.category_id WHERE bk.business_id=? ORDER BY t.created_at DESC LIMIT 8");
$recent->execute([$biz_id]);$recent_tx=$recent->fetchAll();
$bk_cnt=db()->prepare("SELECT COUNT(*) FROM books WHERE business_id=?");$bk_cnt->execute([$biz_id]);$total_books=$bk_cnt->fetchColumn();
include 'includes/header.php';
?>
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:10px">
      <button class="hamburger" onclick="openSidebar()">☰</button>
      <h1>Dashboard</h1>
    </div>
    <div class="actions">
      <a href="transactions.php?action=add" class="btn btn-primary"><span>+</span><span>Add</span></a>
    </div>
  </div>
  <div class="page-content">
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-label">Income</div><div class="stat-value income"><?=formatCurrency($income,$currency)?></div><div class="stat-sub">All time</div></div>
      <div class="stat-card"><div class="stat-label">Expenses</div><div class="stat-value expense"><?=formatCurrency($expense,$currency)?></div><div class="stat-sub">All time</div></div>
      <div class="stat-card"><div class="stat-label">Balance</div><div class="stat-value <?=$balance>=0?'income':'expense'?>"><?=formatCurrency($balance,$currency)?></div><div class="stat-sub">Net</div></div>
      <div class="stat-card"><div class="stat-label">Books</div><div class="stat-value"><?=$total_books?></div><div class="stat-sub">Active</div></div>
    </div>
    <div class="dash-grid" style="margin-bottom:18px">
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
          <div style="font-size:15px;font-weight:500">Income vs Expenses</div>
          <div style="font-size:12px;color:var(--muted)">Last 6 months</div>
        </div>
        <canvas id="mainChart" style="max-height:220px"></canvas>
      </div>
      <div class="card">
        <div style="font-size:15px;font-weight:500;margin-bottom:16px">Overview</div>
        <?php $total=$income+$expense;?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:5px"><span>Income</span><span><?=$total>0?round($income/$total*100):0?>%</span></div>
          <div style="height:6px;background:var(--border2);border-radius:6px"><div style="height:6px;background:var(--income);border-radius:6px;width:<?=$total>0?round($income/$total*100):0?>%"></div></div>
        </div>
        <div style="margin-bottom:18px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:5px"><span>Expenses</span><span><?=$total>0?round($expense/$total*100):0?>%</span></div>
          <div style="height:6px;background:var(--border2);border-radius:6px"><div style="height:6px;background:var(--expense);border-radius:6px;width:<?=$total>0?round($expense/$total*100):0?>%"></div></div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:14px;display:flex;flex-direction:column;gap:8px">
          <a href="books.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;font-size:13px;color:var(--muted2);background:var(--surface2)">📚 <span>All books</span></a>
          <a href="reports.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:9px;font-size:13px;color:var(--muted2);background:var(--surface2)">📤 <span>Generate report</span></a>
        </div>
      </div>
    </div>
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
        <div style="font-size:15px;font-weight:500">Recent Transactions</div>
        <a href="transactions.php" style="font-size:12px;color:var(--accent)">View all →</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Description</th><th>Book</th><th>Date</th><th>Type</th><th style="text-align:right">Amount</th></tr></thead>
          <tbody>
            <?php foreach($recent_tx as $tx):?>
            <tr>
              <td><?=sanitize($tx['description']?:'—')?></td>
              <td style="color:var(--muted)"><?=sanitize($tx['book_name'])?></td>
              <td style="white-space:nowrap;color:var(--muted)"><?=date('M j, Y',strtotime($tx['date']))?></td>
              <td><span class="badge badge-<?=$tx['type']?>"><?=ucfirst($tx['type'])?></span></td>
              <td style="text-align:right;font-weight:500;color:var(--<?=$tx['type']?>);white-space:nowrap"><?=$tx['type']==='income'?'+':'-'?><?=formatCurrency($tx['amount'],$currency)?></td>
            </tr>
            <?php endforeach;?>
            <?php if(empty($recent_tx)):?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No transactions yet</td></tr><?php endif;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
new Chart(document.getElementById('mainChart'),{
  type:'bar',
  data:{
    labels:<?=json_encode(array_column($mdata,'month'))?>,
    datasets:[
      {label:'Income',data:<?=json_encode(array_map(fn($r)=>(float)$r['inc'],$mdata))?>,backgroundColor:'rgba(16,185,129,.7)',borderRadius:6},
      {label:'Expenses',data:<?=json_encode(array_map(fn($r)=>(float)$r['exp'],$mdata))?>,backgroundColor:'rgba(244,63,94,.6)',borderRadius:6}
    ]
  },
  options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{labels:{color:'#94a3b8',font:{size:12}}}},scales:{x:{ticks:{color:'#64748b'},grid:{color:'#252a38'}},y:{ticks:{color:'#64748b'},grid:{color:'#252a38'}}}}
});
</script>
</body></html>
