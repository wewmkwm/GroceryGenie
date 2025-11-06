<?php
// store_owner/store_owner_sales_report.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$owner_id = (int)$_SESSION['store_owner_id'];

include 'store_owner_header.php';

if (!isset($conn) || $conn->connect_error) {
    $conn = new mysqli('localhost', 'root', '', 'grocerygenie');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
}

$start  = $_GET['start']  ?? date('Y-m-01');
$end    = $_GET['end']    ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$paySt  = $_GET['payment_status'] ?? '';

$allowedStatus = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];
$allowedPaySt  = ['Pending', 'Paid'];

$startTs = $start . ' 00:00:00';
$endTs   = $end   . ' 23:59:59';

$where  = 'gi.owner_id = ? AND o.created_at BETWEEN ? AND ?';
$types  = 'iss';
$params = [$owner_id, $startTs, $endTs];

if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where .= ' AND o.order_status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($paySt !== '' && in_array($paySt, $allowedPaySt, true)) {
    $where .= ' AND o.payment_status = ?';
    $types .= 's';
    $params[] = $paySt;
}

function so_scalar(mysqli $conn, string $sql, string $types, array $params, $default = 0) {
    $st = $conn->prepare($sql);
    if (!$st) {
        return $default;
    }
    $st->bind_param($types, ...$params);
    $st->execute();
    $st->bind_result($val);
    $st->fetch();
    $st->close();
    return $val ?? $default;
}

function so_rows(mysqli $conn, string $sql, string $types, array $params): array {
    $rows = [];
    $st = $conn->prepare($sql);
    if (!$st) {
        return $rows;
    }
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    return $rows;
}

$rev = (float) so_scalar(
    $conn,
    "SELECT COALESCE(SUM(oi.subtotal), 0)
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where",
    $types,
    $params,
    0.0
);

$orders = (int) so_scalar(
    $conn,
    "SELECT COUNT(DISTINCT o.order_id)
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where",
    $types,
    $params,
    0
);

$items = (int) so_scalar(
    $conn,
    "SELECT COALESCE(SUM(oi.quantity), 0)
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where",
    $types,
    $params,
    0
);

$aov = $orders > 0 ? ($rev / $orders) : 0.0;

$topProducts = so_rows(
    $conn,
    "SELECT oi.item_id, oi.item_name, SUM(oi.quantity) AS qty_sold, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where
     GROUP BY oi.item_id, oi.item_name
     ORDER BY revenue DESC
     LIMIT 5",
    $types,
    $params
);

$payBreak = so_rows(
    $conn,
    "SELECT o.payment_method, COUNT(DISTINCT o.order_id) AS orders_count, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where
     GROUP BY o.payment_method
     ORDER BY revenue DESC",
    $types,
    $params
);

$statusBreak = so_rows(
    $conn,
    "SELECT o.order_status, COUNT(DISTINCT o.order_id) AS orders_count
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where
     GROUP BY o.order_status
     ORDER BY orders_count DESC",
    $types,
    $params
);

$daily = so_rows(
    $conn,
    "SELECT DATE(o.created_at) AS day, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN groceryitem gi ON gi.item_id = oi.item_id
     JOIN orders o ON o.order_id = oi.order_id
     WHERE $where
     GROUP BY DATE(o.created_at)
     ORDER BY day ASC",
    $types,
    $params
);
?>

<style>
  .so-report {
    margin-top: 2rem;
    margin-bottom: 4rem;
  }
  .so-report-hero {
    border-radius: var(--gg-radius-md);
    background: var(--gg-gradient);
    color: #fff;
    padding: 2.6rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    position: relative;
    overflow: hidden;
  }
  .so-report-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.2), transparent 55%);
    pointer-events: none;
  }
  .so-report-hero h1 {
    font-weight: 700;
    margin-bottom: 0.8rem;
  }
  .so-report-hero p {
    color: rgba(255, 255, 255, 0.85);
    max-width: 560px;
  }
  .so-report-filter {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 1.5rem;
  }
  .so-report-filter label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .so-report-filter .form-control,
  .so-report-filter .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .so-report-filter .form-control:focus,
  .so-report-filter .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .so-report-metric {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 1.6rem;
  }
  .so-report-metric .icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 16px;
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.1rem;
    font-size: 1.1rem;
  }
  .so-report-metric h3 {
    font-weight: 700;
    margin-bottom: 0.25rem;
  }
  .so-report-metric span {
    color: var(--gg-muted);
    font-size: 0.9rem;
  }
  .so-report-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
  }
  .so-report-card header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.8rem 1.2rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }
  .so-report-card header h4 {
    margin: 0;
    font-weight: 700;
    color: var(--gg-secondary);
  }
  .so-report-card .card-body {
    padding: 1.8rem;
  }
  .so-report-card table {
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(15, 23, 42, 0.02);
  }
  .so-report-card table thead {
    background: rgba(15, 23, 42, 0.05);
  }
  .so-report-card table th {
    border-bottom: none;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .so-report-card table td {
    font-size: 0.9rem;
    vertical-align: middle;
  }
  .so-empty {
    text-align: center;
    padding: 1.5rem;
    color: var(--gg-muted);
    font-style: italic;
  }
</style>

<div class="container so-report">
  <section class="so-report-hero mb-4">
    <span class="gg-hero-eyebrow"><i class="fas fa-chart-line"></i> Sales Insights</span>
    <h1>Understand what’s driving your revenue.</h1>
    <p>Filter by date range, payment status, or delivery progress to uncover trends, top performers, and the health of your orders.</p>
    <div class="d-flex flex-wrap gap-3 mt-4">
      <a href="store_owner_dashboard.php" class="gg-btn-outline text-white"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
      <a href="store_owner_add_item.php" class="gg-btn-primary"><i class="fas fa-plus-circle"></i> Add new product</a>
    </div>
  </section>

  <section class="so-report-filter mb-4">
    <form class="row g-3 align-items-end" method="GET">
      <div class="col-md-3">
        <label class="form-label">Start date</label>
        <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">End date</label>
        <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Order status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach ($allowedStatus as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo $status === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Payment status</label>
        <select name="payment_status" class="form-select">
          <option value="">All</option>
          <?php foreach ($allowedPaySt as $opt): ?>
            <option value="<?php echo $opt; ?>" <?php echo $paySt === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="gg-btn-primary"><i class="fas fa-sync-alt"></i></button>
      </div>
    </form>
  </section>

  <section class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="so-report-metric h-100">
        <div class="icon-wrap"><i class="fas fa-coins"></i></div>
        <h3>RM <?php echo number_format($rev, 2); ?></h3>
        <span>Total revenue</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-report-metric h-100">
        <div class="icon-wrap"><i class="fas fa-receipt"></i></div>
        <h3><?php echo (int)$orders; ?></h3>
        <span>Orders processed</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-report-metric h-100">
        <div class="icon-wrap"><i class="fas fa-shopping-basket"></i></div>
        <h3><?php echo (int)$items; ?></h3>
        <span>Items sold</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-report-metric h-100">
        <div class="icon-wrap"><i class="fas fa-divide"></i></div>
        <h3>RM <?php echo number_format($aov, 2); ?></h3>
        <span>Avg. order value</span>
      </div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-lg-6">
      <article class="so-report-card">
        <header>
          <h4><i class="fas fa-star me-2 text-warning"></i>Top products</h4>
        </header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-end">Qty sold</th>
                  <th class="text-end">Revenue (RM)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($topProducts)) : ?>
                  <?php foreach ($topProducts as $p) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($p['item_name']); ?></td>
                      <td class="text-end"><?php echo (int)$p['qty_sold']; ?></td>
                      <td class="text-end">RM <?php echo number_format((float)$p['revenue'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr><td colspan="3" class="so-empty">No sales recorded for this range.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </article>
    </div>
    <div class="col-lg-6">
      <article class="so-report-card">
        <header>
          <h4><i class="fas fa-credit-card me-2 text-warning"></i>Payment methods</h4>
        </header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead>
                <tr>
                  <th>Method</th>
                  <th class="text-end">Orders</th>
                  <th class="text-end">Revenue (RM)</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($payBreak)) : ?>
                  <?php foreach ($payBreak as $r) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['payment_method']); ?></td>
                      <td class="text-end"><?php echo (int)$r['orders_count']; ?></td>
                      <td class="text-end">RM <?php echo number_format((float)$r['revenue'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr><td colspan="3" class="so-empty">No payment data available.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </article>
    </div>
  </div>

  <div class="row g-4 mt-1">
    <div class="col-lg-6">
      <article class="so-report-card">
        <header>
          <h4><i class="fas fa-clipboard-check me-2 text-warning"></i>Order statuses</h4>
        </header>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead>
                <tr>
                  <th>Status</th>
                  <th class="text-end">Orders</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($statusBreak)) : ?>
                  <?php foreach ($statusBreak as $r) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['order_status']); ?></td>
                      <td class="text-end"><?php echo (int)$r['orders_count']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr><td colspan="2" class="so-empty">No orders for this range.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </article>
    </div>
    <div class="col-lg-6">
      <article class="so-report-card">
        <header>
          <h4><i class="fas fa-chart-area me-2 text-warning"></i>Daily revenue trend</h4>
        </header>
        <div class="card-body">
          <canvas id="revChart" height="160"></canvas>
          <?php if (empty($daily)) : ?>
            <div class="so-empty">No revenue recorded for this selection.</div>
          <?php endif; ?>
        </div>
      </article>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function() {
    const labels = <?php echo json_encode(array_map(static fn($r) => $r['day'], $daily)); ?>;
    const data = <?php echo json_encode(array_map(static fn($r) => (float)$r['revenue'], $daily)); ?>;
    if (!labels.length) return;
    const ctx = document.getElementById('revChart');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Revenue (RM)',
          data,
          borderColor: '#ff6a00',
          backgroundColor: 'rgba(255,106,0,0.16)',
          tension: 0.25,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#ff6a00',
          pointBorderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,23,42,0.9)',
            titleColor: '#fff',
            bodyColor: '#e2e8f0'
          }
        },
        scales: {
          x: {
            ticks: { color: '#64748b' },
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#64748b' },
            grid: { color: 'rgba(148, 163, 184, 0.2)' }
          }
        }
      }
    });
  })();
</script>

<?php include 'store_owner_footer.php'; ?>
