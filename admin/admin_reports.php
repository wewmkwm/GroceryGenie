<?php
// admin/admin_reports.php — recipe analytics dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$start  = $_GET['start']  ?? date('Y-m-01');
$end    = $_GET['end']    ?? date('Y-m-t');
$status = $_GET['recipe_status'] ?? '';
$diet   = $_GET['dietary'] ?? '';

$startTs = $start . ' 00:00:00';
$endTs   = $end   . ' 23:59:59';

$rWhere  = 'r.created_at BETWEEN ? AND ?';
$rTypes  = 'ss';
$rParams = [$startTs, $endTs];
if ($status !== '') {
    $rWhere  .= ' AND r.status = ?';
    $rTypes  .= 's';
    $rParams[] = $status;
}
if ($diet !== '') {
    $rWhere  .= ' AND r.dietary_tag = ?';
    $rTypes  .= 's';
    $rParams[] = $diet;
}

$scalar = function (mysqli $conn, string $sql, string $types, array $params, $default = 0) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $default;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return $value ?? $default;
};

$rows = function (mysqli $conn, string $sql, string $types, array $params): array {
    $out = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $out;
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $stmt->close();
    return $out;
};

$totalRecipes  = (int)$scalar($conn, "SELECT COUNT(*) FROM recipe r WHERE $rWhere", $rTypes, $rParams, 0);
$approvedCount = (int)$scalar($conn, "SELECT COUNT(*) FROM recipe r WHERE $rWhere AND r.status='approved'", $rTypes, $rParams, 0);
$pendingCount  = (int)$scalar($conn, "SELECT COUNT(*) FROM recipe r WHERE $rWhere AND r.status='pending'", $rTypes, $rParams, 0);
$rejectedCount = (int)$scalar($conn, "SELECT COUNT(*) FROM recipe r WHERE $rWhere AND r.status='rejected'", $rTypes, $rParams, 0);
$avgRating     = (float)$scalar($conn, "SELECT AVG(rr.rating) FROM recipe_reviews rr JOIN recipe r ON r.recipe_id = rr.recipe_id WHERE rr.created_at BETWEEN ? AND ?", 'ss', [$startTs, $endTs], 0.0);
$totalReviews  = (int)$scalar($conn, "SELECT COUNT(*) FROM recipe_reviews rr WHERE rr.created_at BETWEEN ? AND ?", 'ss', [$startTs, $endTs], 0);

$filterSuffix = '';
$filterParams = array_filter([$status, $diet], fn($v) => $v !== '');

$hotRecipes = $rows(
    $conn,
    "SELECT r.recipe_id, r.recipe_name, AVG(rr.rating) AS avg_rating, COUNT(*) AS review_count
       FROM recipe_reviews rr
       JOIN recipe r ON r.recipe_id = rr.recipe_id
      WHERE rr.created_at BETWEEN ? AND ?
        " . ($status !== '' ? ' AND r.status = ?' : '') . ($diet !== '' ? ' AND r.dietary_tag = ?' : '') . "
   GROUP BY r.recipe_id, r.recipe_name
     HAVING review_count >= 3
   ORDER BY avg_rating DESC, review_count DESC
      LIMIT 5",
    ($filterParams ? 'ss' . str_repeat('s', count($filterParams)) : 'ss'),
    array_values(array_merge([$startTs, $endTs], $filterParams))
);

$mostReviewed = $rows(
    $conn,
    "SELECT r.recipe_id, r.recipe_name, COUNT(*) AS review_count, AVG(rr.rating) AS avg_rating
       FROM recipe_reviews rr
       JOIN recipe r ON r.recipe_id = rr.recipe_id
      WHERE rr.created_at BETWEEN ? AND ?
        " . ($status !== '' ? ' AND r.status = ?' : '') . ($diet !== '' ? ' AND r.dietary_tag = ?' : '') . "
   GROUP BY r.recipe_id, r.recipe_name
   ORDER BY review_count DESC, avg_rating DESC
      LIMIT 5",
    ($filterParams ? 'ss' . str_repeat('s', count($filterParams)) : 'ss'),
    array_values(array_merge([$startTs, $endTs], $filterParams))
);

$byDiet = $rows(
    $conn,
    "SELECT COALESCE(r.dietary_tag,'Unspecified') AS diet, COUNT(*) AS cnt
       FROM recipe r
      WHERE $rWhere
   GROUP BY diet
   ORDER BY cnt DESC",
    $rTypes,
    $rParams
);

$dailyRecipes = $rows(
    $conn,
    "SELECT DATE(r.created_at) AS day, COUNT(*) AS cnt
       FROM recipe r
      WHERE $rWhere
   GROUP BY day
   ORDER BY day",
    $rTypes,
    $rParams
);

$dailyReviews = $rows(
    $conn,
    "SELECT DATE(rr.created_at) AS day, COUNT(*) AS cnt
       FROM recipe_reviews rr
       JOIN recipe r ON r.recipe_id = rr.recipe_id
      WHERE rr.created_at BETWEEN ? AND ?" . ($status !== '' ? ' AND r.status = ?' : '') . ($diet !== '' ? ' AND r.dietary_tag = ?' : '') . "
   GROUP BY day
   ORDER BY day",
    ($filterParams ? 'ss' . str_repeat('s', count($filterParams)) : 'ss'),
    array_values(array_merge([$startTs, $endTs], $filterParams))
);

$approvalRate = $totalRecipes > 0 ? ($approvedCount / $totalRecipes) * 100 : 0;
$pendingRate  = $totalRecipes > 0 ? ($pendingCount / $totalRecipes) * 100 : 0;
$rejectedRate = $totalRecipes > 0 ? ($rejectedCount / $totalRecipes) * 100 : 0;
$reviewRate   = $totalRecipes > 0 ? ($totalReviews / $totalRecipes) * 100 : 0;

$dietTotal = array_sum(array_map(fn($r) => (int)$r['cnt'], $byDiet));

$dateSummary = sprintf(
    '%s — %s',
    date('d M Y', strtotime($start)),
    date('d M Y', strtotime($end))
);
?>
<?php include 'admin_header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
  .admin-report-wrapper {
    margin-top: 1rem;
    margin-bottom: 3rem;
  }
  .report-hero {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.18), rgba(45, 212, 191, 0.18), rgba(56, 189, 248, 0.22));
    border-radius: var(--gg-radius-md, 20px);
    padding: 2.8rem 2.4rem;
    position: relative;
    overflow: hidden;
    box-shadow: var(--gg-shadow-soft, 0 22px 50px rgba(15, 23, 42, 0.25));
    color: #0f172a;
  }
  .report-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.35), transparent 60%);
    pointer-events: none;
  }
  .report-hero h1 {
    font-weight: 700;
    margin-bottom: 0.7rem;
  }
  .report-hero p {
    color: rgba(15, 23, 42, 0.7);
    max-width: 520px;
  }
  .report-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1.8rem;
  }
  .report-hero-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 1.25rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(6px);
    font-weight: 600;
    color: #0f172a;
  }
  .report-filters {
    margin-top: 2rem;
    margin-bottom: 1.5rem;
    border-radius: var(--gg-radius-md, 18px);
    box-shadow: var(--gg-shadow-soft, 0 18px 40px rgba(15, 23, 42, 0.18));
    border: none;
  }
  .report-filters .card-body {
    padding: 1.75rem 2rem;
  }
  .report-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.2rem;
  }
  .report-filter-grid label {
    font-size: 0.82rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--gg-muted, #64748b);
    margin-bottom: 0.35rem;
  }
  .report-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.1rem;
  }
  .report-kpi {
    background: var(--gg-surface, #fff);
    border-radius: var(--gg-radius-md, 18px);
    padding: 1.6rem;
    box-shadow: var(--gg-shadow-soft, 0 18px 30px rgba(15, 23, 42, 0.12));
    display: grid;
    gap: 0.65rem;
  }
  .report-kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    background: rgba(14, 165, 233, 0.18);
    color: var(--gg-primary-dark, #ff6a00);
  }
  .report-kpi-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--gg-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .report-kpi-value {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--gg-heading, #0f172a);
  }
  .report-kpi-sub {
    font-size: 0.8rem;
    font-weight: 600;
    color: rgba(14, 165, 233, 0.85);
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .report-card {
    border: none;
    border-radius: var(--gg-radius-md, 18px);
    box-shadow: var(--gg-shadow-soft, 0 18px 30px rgba(15, 23, 42, 0.1));
    overflow: hidden;
  }
  .report-card .card-header {
    background: #fff;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .report-card table {
    font-size: 0.94rem;
  }
  .report-card table thead {
    background: rgba(15, 23, 42, 0.04);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  .report-card table tbody tr {
    transition: background 0.2s ease;
  }
  .report-card table tbody tr:hover {
    background: rgba(14, 165, 233, 0.06);
  }
  .report-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--gg-muted, #64748b);
    font-weight: 500;
  }
  .dietary-progress .progress {
    height: 10px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.22);
  }
  .dietary-progress .progress-bar {
    border-radius: 999px;
  }
  .chart-card canvas {
    max-height: 260px;
  }
  @media (max-width: 767.98px) {
    .report-hero {
      padding: 2.1rem 1.8rem;
    }
    .report-hero-meta span {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<div class="container admin-report-wrapper">
  <section class="report-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <span class="gg-hero-eyebrow text-uppercase fw-semibold opacity-75"><i class="fas fa-chart-line me-2"></i>Recipe analytics</span>
        <h1 class="mb-2">Performance overview</h1>
        <p>Review recipe output, approval health, and review momentum for the selected period. Use the filters below to narrow the insights to specific criteria.</p>
      </div>
      <div class="text-md-end">
        <span class="badge bg-light text-dark fw-semibold px-3 py-2">
          <i class="fas fa-calendar-alt me-2 text-warning"></i><?php echo htmlspecialchars($dateSummary); ?>
        </span>
        <div class="mt-3 text-muted small">Applied filters: 
          <?php echo $status ? htmlspecialchars(ucfirst($status)) : 'All statuses'; ?> · 
          <?php echo $diet ? htmlspecialchars($diet) : 'All dietary tags'; ?>
        </div>
      </div>
    </div>
    <div class="report-hero-meta mt-4">
      <span><i class="fas fa-seedling text-success"></i><?php echo (int)$totalRecipes; ?> total recipes</span>
      <span><i class="fas fa-star text-warning"></i><?php echo number_format($avgRating, 1); ?> average rating</span>
      <span><i class="fas fa-message text-primary"></i><?php echo (int)$totalReviews; ?> reviews</span>
    </div>
  </section>

  <div class="card report-filters">
    <div class="card-body">
      <form method="GET" class="report-filter-grid">
        <div>
          <label for="startInput">Start</label>
          <input type="date" id="startInput" name="start" value="<?php echo htmlspecialchars($start); ?>" class="form-control">
        </div>
        <div>
          <label for="endInput">End</label>
          <input type="date" id="endInput" name="end" value="<?php echo htmlspecialchars($end); ?>" class="form-control">
        </div>
        <div>
          <label for="statusSelect">Recipe status</label>
          <select id="statusSelect" name="recipe_status" class="form-select">
            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
          </select>
        </div>
        <div>
          <label for="dietSelect">Dietary tag</label>
          <select id="dietSelect" name="dietary" class="form-select">
            <option value="" <?php echo $diet === '' ? 'selected' : ''; ?>>All</option>
            <?php
              $dietOptions = ['None','Vegetarian','Vegan','Halal','Gluten-Free'];
              foreach ($dietOptions as $option) {
                $selected = $diet === $option ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
              }
            ?>
          </select>
        </div>
        <div class="d-flex align-items-end gap-2">
          <button class="btn btn-primary px-4"><i class="fas fa-filter me-2"></i>Apply filters</button>
          <a href="admin_reports.php" class="btn btn-outline-secondary px-3">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <section class="report-kpis mb-4">
    <div class="report-kpi">
      <div class="report-kpi-icon bg-primary-subtle text-primary"><i class="fas fa-book"></i></div>
      <div class="report-kpi-title">Total recipes</div>
      <div class="report-kpi-value"><?php echo number_format($totalRecipes); ?></div>
      <div class="report-kpi-sub">Across selected window</div>
    </div>
    <div class="report-kpi">
      <div class="report-kpi-icon bg-success-subtle text-success"><i class="fas fa-circle-check"></i></div>
      <div class="report-kpi-title">Approved</div>
      <div class="report-kpi-value"><?php echo number_format($approvedCount); ?></div>
      <div class="report-kpi-sub"><?php echo number_format($approvalRate, 1); ?>% approval rate</div>
    </div>
    <div class="report-kpi">
      <div class="report-kpi-icon bg-warning-subtle text-warning"><i class="fas fa-hourglass-half"></i></div>
      <div class="report-kpi-title">Pending</div>
      <div class="report-kpi-value"><?php echo number_format($pendingCount); ?></div>
      <div class="report-kpi-sub"><?php echo number_format($pendingRate, 1); ?>% awaiting review</div>
    </div>
    <div class="report-kpi">
      <div class="report-kpi-icon bg-danger-subtle text-danger"><i class="fas fa-times-circle"></i></div>
      <div class="report-kpi-title">Rejected</div>
      <div class="report-kpi-value"><?php echo number_format($rejectedCount); ?></div>
      <div class="report-kpi-sub"><?php echo number_format($rejectedRate, 1); ?>% expired</div>
    </div>
    <div class="report-kpi">
      <div class="report-kpi-icon bg-warning-subtle text-warning"><i class="fas fa-star"></i></div>
      <div class="report-kpi-title">Average rating</div>
      <div class="report-kpi-value"><?php echo number_format($avgRating, 1); ?></div>
      <div class="report-kpi-sub"><?php echo number_format($reviewRate, 1); ?>% recipes reviewed</div>
    </div>
    <div class="report-kpi">
      <div class="report-kpi-icon bg-info-subtle text-info"><i class="fas fa-comments"></i></div>
      <div class="report-kpi-title">Total reviews</div>
      <div class="report-kpi-value"><?php echo number_format($totalReviews); ?></div>
      <div class="report-kpi-sub">All ratings received</div>
    </div>
  </section>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card report-card">
        <div class="card-header">
          <span><i class="fas fa-fire text-danger me-2"></i>Top-rated recipes (min 3 reviews)</span>
        </div>
        <div class="card-body p-0">
          <?php if ($hotRecipes) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Recipe</th>
                    <th class="text-end">Avg rating</th>
                    <th class="text-end">Reviews</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($hotRecipes as $recipe) { ?>
                    <tr>
                      <td><?php echo htmlspecialchars($recipe['recipe_name']); ?></td>
                      <td class="text-end fw-semibold"><?php echo number_format((float)$recipe['avg_rating'], 1); ?></td>
                      <td class="text-end text-muted"><?php echo (int)$recipe['review_count']; ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="report-empty">No qualifying recipes during this period.</div>
          <?php } ?>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card report-card">
        <div class="card-header">
          <span><i class="fas fa-list-check text-primary me-2"></i>Most reviewed recipes</span>
        </div>
        <div class="card-body p-0">
          <?php if ($mostReviewed) { ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Recipe</th>
                    <th class="text-end">Reviews</th>
                    <th class="text-end">Avg rating</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($mostReviewed as $recipe) { ?>
                    <tr>
                      <td><?php echo htmlspecialchars($recipe['recipe_name']); ?></td>
                      <td class="text-end fw-semibold"><?php echo (int)$recipe['review_count']; ?></td>
                      <td class="text-end text-muted"><?php echo number_format((float)$recipe['avg_rating'], 1); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="report-empty">No review activity recorded for this range.</div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card report-card chart-card">
        <div class="card-header"><span><i class="fas fa-calendar-plus me-2 text-primary"></i>New recipes by day</span></div>
        <div class="card-body">
          <canvas id="recipesChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card report-card chart-card">
        <div class="card-header"><span><i class="fas fa-comment-dots me-2 text-warning"></i>Reviews by day</span></div>
        <div class="card-body">
          <canvas id="reviewsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-6">
      <div class="card report-card">
        <div class="card-header"><span><i class="fas fa-leaf me-2 text-success"></i>Dietary distribution</span></div>
        <div class="card-body">
          <?php if ($byDiet) { ?>
            <div class="dietary-progress d-grid gap-3">
              <?php
                $colors = ['#0ea5e9', '#22c55e', '#f97316', '#6366f1', '#ef4444', '#14b8a6'];
                $index = 0;
                foreach ($byDiet as $dietRow) {
                  $label = $dietRow['diet'] ?: 'Unspecified';
                  $count = (int)$dietRow['cnt'];
                  $share = $dietTotal > 0 ? ($count / $dietTotal) * 100 : 0;
                  $color = $colors[$index % count($colors)];
                  $index++;
              ?>
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
                    <small class="text-muted"><?php echo number_format($share, 1); ?>%</small>
                  </div>
                  <div class="progress" role="progressbar" aria-valuenow="<?php echo number_format($share, 1); ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?php echo $share; ?>%; background: <?php echo $color; ?>;"></div>
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } else { ?>
            <div class="report-empty">No dietary data available for this filter.</div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const recipeLabels = <?php echo json_encode(array_map(fn($row) => $row['day'], $dailyRecipes)); ?>;
  const recipeData   = <?php echo json_encode(array_map(fn($row) => (int)$row['cnt'], $dailyRecipes)); ?>;
  const reviewLabels = <?php echo json_encode(array_map(fn($row) => $row['day'], $dailyReviews)); ?>;
  const reviewData   = <?php echo json_encode(array_map(fn($row) => (int)$row['cnt'], $dailyReviews)); ?>;

  const buildGradient = (ctx, color) => {
    const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
    gradient.addColorStop(0, color);
    gradient.addColorStop(1, 'rgba(255,255,255,0)');
    return gradient;
  };

  if (recipeLabels.length) {
    const ctx = document.getElementById('recipesChart').getContext('2d');
    const primary = '#0ea5e9';
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: recipeLabels,
        datasets: [{
          label: 'New recipes',
          data: recipeData,
          borderColor: primary,
          borderWidth: 2.5,
          backgroundColor: buildGradient(ctx, 'rgba(14, 165, 233, 0.25)'),
          tension: 0.3,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#fff',
          pointBorderColor: primary
        }]
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          },
          x: {
            ticks: { maxRotation: 0 }
          }
        },
        plugins: {
          legend: { display: false }
        }
      }
    });
  }

  if (reviewLabels.length) {
    const ctx = document.getElementById('reviewsChart').getContext('2d');
    const accent = '#f97316';
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: reviewLabels,
        datasets: [{
          label: 'Reviews',
          data: reviewData,
          borderColor: accent,
          borderWidth: 2.5,
          backgroundColor: buildGradient(ctx, 'rgba(249, 115, 22, 0.22)'),
          tension: 0.35,
          fill: true,
          pointRadius: 4,
          pointBackgroundColor: '#fff',
          pointBorderColor: accent
        }]
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          },
          x: {
            ticks: { maxRotation: 0 }
          }
        },
        plugins: {
          legend: { display: false }
        }
      }
    });
  }
</script>

<?php include 'admin_footer.php'; ?>
