<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$owner_id = (int)$_SESSION['store_owner_id'];
$query = trim($_GET['q'] ?? '');
$results = [];
$suggestions = [];

include 'store_owner_header.php';

if (!isset($conn) || $conn->connect_error) {
    $conn = new mysqli('localhost', 'root', '', 'grocerygenie');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
}

if ($query !== '') {
    $like = '%' . $query . '%';
    $stmt = $conn->prepare(
        'SELECT item_id, item_name, brand, category, price_per_unit, quantity, unit, item_image, created_at
         FROM groceryitem
         WHERE owner_id = ?
           AND (item_name LIKE ? OR brand LIKE ? OR category LIKE ?)
         ORDER BY item_name ASC'
    );
    if ($stmt) {
        $stmt->bind_param('isss', $owner_id, $like, $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
}

if ($query !== '' && empty($results)) {
    $normalize = function ($value) {
        if (!isset($value) || $value === '') {
            return '';
        }
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };
    $queryNorm = $normalize($query);

    $stmt = $conn->prepare(
        'SELECT item_id, item_name, brand, category, price_per_unit, quantity, unit, item_image, created_at
         FROM groceryitem
         WHERE owner_id = ?
         ORDER BY created_at DESC
         LIMIT 120'
    );
    if ($stmt) {
        $stmt->bind_param('i', $owner_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $candidates = [];
        while ($row = $res->fetch_assoc()) {
            $highest = 0.0;
            foreach (['item_name', 'brand', 'category'] as $field) {
                if (empty($row[$field])) {
                    continue;
                }
                $valueNorm = $normalize($row[$field]);
                if ($valueNorm === '') {
                    continue;
                }
                if (strpos($valueNorm, $queryNorm) !== false) {
                    $highest = 100.0;
                    break;
                }
                similar_text($queryNorm, $valueNorm, $percent);
                if ($percent > $highest) {
                    $highest = $percent;
                }
            }
            $row['match_score'] = $highest;
            $candidates[] = $row;
        }
        $stmt->close();

        if (!empty($candidates)) {
            usort($candidates, function ($a, $b) {
                return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
            });
            $threshold = 30.0;
            $filtered = array_values(array_filter($candidates, function ($item) use ($threshold) {
                return ($item['match_score'] ?? 0) >= $threshold;
            }));
            $suggestions = !empty($filtered)
                ? array_slice($filtered, 0, 4)
                : array_slice($candidates, 0, 3);
        }
    }
}
?>

<style>
  .so-search-page {
    margin-top: 2.5rem;
    margin-bottom: 4rem;
  }
  .so-search-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.4rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    row-gap: 1.5rem;
  }
  .so-search-hero h1 {
    font-weight: 700;
    margin-bottom: 0.8rem;
  }
  .so-search-summary {
    color: rgba(255, 255, 255, 0.85);
  }
  .so-search-results {
    margin-top: 2.5rem;
  }
  .so-result-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  .so-result-card img {
    width: 100%;
    height: 160px;
    object-fit: contain;
    background: rgba(15, 23, 42, 0.04);
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    padding: 0.75rem;
  }
  .so-result-card .card-body {
    padding: 1.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }
  .so-match-hint {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--gg-secondary);
    margin-top: 0.35rem;
    opacity: 0.85;
  }
  .so-empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    background: rgba(15, 23, 42, 0.02);
    border-radius: var(--gg-radius-md);
    color: var(--gg-muted);
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.05);
  }
</style>

<div class="container so-search-page">
  <section class="so-search-hero">
    <div>
      <span class="gg-hero-eyebrow"><i class="fas fa-search"></i> Inventory lookup</span>
      <h1>Search your store catalogue.</h1>
      <p class="so-search-summary">
        <?php if ($query === ''): ?>
          Start typing to find items by name, brand, or category.
        <?php else: ?>
          We found <?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?> for <strong>"<?php echo htmlspecialchars($query); ?>"</strong>.
        <?php endif; ?>
      </p>
    </div>
    <div>
      <a href="store_owner_add_item.php" class="gg-btn-outline text-white"><i class="fas fa-plus"></i> Add another item</a>
    </div>
  </section>

  <section class="so-search-results">
    <?php if ($query === ''): ?>
      <div class="so-empty-state">
        <i class="fas fa-lightbulb fa-2x mb-3 d-block"></i>
        Enter a keyword to search items by name, brand, or category.
      </div>
    <?php elseif (empty($results)): ?>
      <div class="so-empty-state">
        <i class="fas fa-box-open fa-2x mb-3 d-block"></i>
        No matches found for "<strong><?php echo htmlspecialchars($query); ?></strong>".
      </div>
      <?php if (!empty($suggestions)): ?>
        <div class="mt-4">
          <h5 class="text-center mb-3">Closest matches you might be looking for</h5>
          <div class="row g-4">
            <?php foreach ($suggestions as $item): ?>
              <?php
                $imagePath = '../uploads/items/' . $item['item_image'];
                $fallback = '../assets/default_item.png';
                $displayImage = (!empty($item['item_image']) && file_exists($imagePath)) ? $imagePath : $fallback;
                $score = isset($item['match_score']) ? (float)$item['match_score'] : null;
              ?>
              <div class="col-md-4">
                <div class="so-result-card">
                  <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                      <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                      </div>
                      <div class="text-end">
                        <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php if ($score !== null && $score > 0): ?>
                          <div class="so-match-hint"><?php echo round($score); ?>% match</div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                      <span>Unit: <?php echo htmlspecialchars($item['unit']); ?></span>
                      <span>Stock: <?php echo (int)$item['quantity']; ?></span>
                    </div>
                    <div class="fw-semibold text-warning">RM <?php echo number_format((float)$item['price_per_unit'], 2); ?></div>
                    <div class="d-flex gap-2 mt-auto">
                      <a href="store_owner_edit_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="fas fa-pen"></i> Edit</a>
                      <a href="store_owner_delete_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?');"><i class="fas fa-trash"></i></a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($results as $item): ?>
          <?php
            $imagePath = '../uploads/items/' . $item['item_image'];
            $fallback = '../assets/default_item.png';
            $displayImage = (!empty($item['item_image']) && file_exists($imagePath)) ? $imagePath : $fallback;
          ?>
          <div class="col-md-4">
            <div class="so-result-card">
              <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                    <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                  </div>
                  <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($item['category']); ?></span>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                  <span>Unit: <?php echo htmlspecialchars($item['unit']); ?></span>
                  <span>Stock: <?php echo (int)$item['quantity']; ?></span>
                </div>
                <div class="fw-semibold text-warning">RM <?php echo number_format((float)$item['price_per_unit'], 2); ?></div>
                <div class="d-flex gap-2 mt-auto">
                  <a href="store_owner_edit_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="fas fa-pen"></i> Edit</a>
                  <a href="store_owner_delete_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?');"><i class="fas fa-trash"></i></a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php include 'store_owner_footer.php'; ?>
