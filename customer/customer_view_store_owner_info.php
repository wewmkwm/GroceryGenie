<?php
// customer/customer_view_store_owner_info.php

require_once __DIR__ . '/../db_connect.php';

$ownerId = intval($_GET['id'] ?? 0);
$owner = null;
$storeItems = [];

if ($ownerId > 0) {
    if ($stmt = $conn->prepare("SELECT owner_id, name, email, phone_number, store_name, store_address, business_reg_no, store_logo, created_at FROM store_owners WHERE owner_id = ? LIMIT 1")) {
        $stmt->bind_param('i', $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $owner = $result->fetch_assoc();
        }
        $stmt->close();
    }

    if ($owner) {
        if ($itemStmt = $conn->prepare("SELECT item_id, item_name, unit, price_per_unit, item_image, created_at FROM groceryitem WHERE owner_id = ? ORDER BY created_at DESC")) {
            $itemStmt->bind_param('i', $ownerId);
            $itemStmt->execute();
            $itemRes = $itemStmt->get_result();
            if ($itemRes) {
                while ($row = $itemRes->fetch_assoc()) {
                    $storeItems[] = $row;
                }
            }
            $itemStmt->close();
        }
    }
}

include 'customer_header.php';

if (!$owner):
?>
  <section class="store-owner-empty text-center py-5">
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 520px;">
      <div class="card-body py-5">
        <div class="display-4 mb-3 text-warning"><i class="fas fa-store-slash"></i></div>
        <h4 class="mb-3">Store Owner Not Found</h4>
        <p class="text-muted mb-4">The store owner you are looking for may have been removed or is currently unavailable.</p>
        <a href="shopping.php" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Back to Shopping</a>
      </div>
    </div>
  </section>
<?php
    include 'customer_footer.php';
    return;
endif;

$logoPath = null;
if (!empty($owner['store_logo'])) {
    $logoFile = basename($owner['store_logo']);
    $logoPath = "../store_owner/uploads/" . $logoFile;
}

$itemCount = count($storeItems);
$established = null;
if (!empty($owner['created_at'])) {
    try {
        $establishedDate = new DateTime($owner['created_at']);
        $established = $establishedDate->format('d M Y');
    } catch (Exception $e) {
        $established = null;
    }
}
?>

<style>
  .store-owner-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.18), rgba(255, 145, 77, 0.18));
    border-radius: 28px;
    padding: 2.5rem 2rem;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
    margin-bottom: 2.5rem;
    position: relative;
    overflow: hidden;
  }
  .store-owner-hero::after {
    content: "";
    position: absolute;
    top: -20%;
    right: -10%;
    width: 240px;
    height: 240px;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 50%;
    filter: blur(0);
  }
  .store-owner-hero h2 {
    font-weight: 700;
    color: #0f172a;
  }
  .store-owner-hero p {
    color: #475569;
  }
  .store-owner-logo img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.7);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.18);
    background: #fff;
  }
  .store-owner-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-top: 1.5rem;
  }
  .store-owner-meta .meta-card {
    background: rgba(255, 255, 255, 0.85);
    border-radius: 18px;
    padding: 1rem 1.25rem;
    min-width: 240px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
  }
  .store-owner-meta .meta-card small {
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 600;
  }
  .store-owner-meta .meta-card span {
    display: block;
    font-weight: 600;
    color: #0f172a;
    margin-top: 0.35rem;
  }
  .store-owner-actions {
    margin-top: 1.75rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  .store-owner-actions .btn {
    border-radius: 999px;
    padding: 0.65rem 1.75rem;
    font-weight: 600;
  }
  .store-owner-items {
    margin-bottom: 3rem;
  }
  .store-owner-items h4 {
    font-weight: 700;
    color: #1f2937;
  }
  .store-owner-items .items-summary {
    color: #64748b;
  }
  .store-item-card {
    height: 100%;
    border: none;
    border-radius: 22px;
    box-shadow: 0 22px 45px rgba(15, 23, 42, 0.14);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: #ffffff;
  }
  .store-item-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 28px 55px rgba(15, 23, 42, 0.18);
  }
  .store-item-card img {
    width: 100%;
    height: 180px;
    object-fit: contain;
    background: #f8fafc;
    padding: 1.5rem;
  }
  .store-item-card .card-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
  }
  .store-item-card h6 {
    font-weight: 600;
    color: #1e293b;
  }
  .store-item-card .unit {
    color: #64748b;
    font-size: 0.9rem;
  }
  .store-item-card .price {
    font-weight: 700;
    color: #ef4444;
    font-size: 1.05rem;
  }
  .store-owner-empty {
    margin-top: 3rem;
  }
  .store-owner-empty .card {
    border-radius: 24px;
  }
  @media (max-width: 991.98px) {
    .store-owner-meta {
      gap: 1rem;
    }
    .store-owner-meta .meta-card {
      width: 100%;
    }
  }
</style>

<section class="store-owner-hero">
  <div class="row align-items-center g-4">
    <div class="col-md-auto text-center text-md-start">
      <div class="store-owner-logo mb-3 mb-md-0">
        <?php if ($logoPath): ?>
          <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Store logo">
        <?php else: ?>
          <div class="d-inline-flex align-items-center justify-content-center bg-white shadow rounded-circle" style="width: 120px; height: 120px;">
            <i class="fas fa-store text-primary" style="font-size: 2rem;"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md">
      <h2 class="mb-2"><?php echo htmlspecialchars($owner['store_name']); ?></h2>
      <p class="mb-0">Owned by <strong><?php echo htmlspecialchars($owner['name']); ?></strong>. Reach out using the contact details below or explore the curated items from this store.</p>

      <div class="store-owner-meta">
        <div class="meta-card">
          <small>Contact</small>
          <span><i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($owner['email']); ?></span>
          <span><i class="fas fa-phone me-2 text-success"></i><?php echo htmlspecialchars($owner['phone_number']); ?></span>
        </div>
        <div class="meta-card">
          <small>Store Address</small>
          <span><i class="fas fa-location-dot me-2 text-danger"></i><?php echo htmlspecialchars($owner['store_address']); ?></span>
        </div>
        <div class="meta-card">
          <small>Business Details</small>
          <span><i class="fas fa-briefcase me-2 text-warning"></i><?php echo htmlspecialchars($owner['business_reg_no']); ?></span>
          <?php if ($established): ?>
            <span><i class="fas fa-calendar-check me-2 text-info"></i>Since <?php echo htmlspecialchars($established); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="store-owner-actions">
        <a href="shopping.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Shopping</a>
        <?php if ($itemCount > 0): ?>
          <a href="shopping.php?store=<?php echo $ownerId; ?>" class="btn btn-primary"><i class="fas fa-basket-shopping me-2"></i>Shop This Store</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="store-owner-items">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="fas fa-box-open text-primary me-2"></i>Items from this store</h4>
      <div class="items-summary"><?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?> available</div>
    </div>
    <?php if ($itemCount > 0): ?>
      <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
        <i class="fas fa-clock me-2 text-primary"></i>Updated recently
      </div>
    <?php endif; ?>
  </div>

  <?php if ($itemCount > 0): ?>
    <div class="row g-4">
      <?php foreach ($storeItems as $item): ?>
        <?php
          $itemImage = !empty($item['item_image']) ? basename($item['item_image']) : null;
          $itemImagePath = $itemImage ? "../uploads/items/" . $itemImage : "https://via.placeholder.com/280x180?text=No+Image";
        ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <article class="card store-item-card">
            <img src="<?php echo htmlspecialchars($itemImagePath); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
            <div class="card-body">
              <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
              <?php if (!empty($item['unit'])): ?>
                <div class="unit"><i class="fas fa-weight-hanging me-2"></i><?php echo htmlspecialchars($item['unit']); ?></div>
              <?php endif; ?>
              <div class="price">RM<?php echo number_format((float)$item['price_per_unit'], 2); ?></div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="store-owner-empty text-center">
      <div class="card border-0 shadow-sm mx-auto" style="max-width: 520px;">
        <div class="card-body py-5">
          <div class="display-5 mb-3 text-primary"><i class="fas fa-couch"></i></div>
          <h5 class="mb-3">This store hasn't listed any items yet</h5>
          <p class="text-muted mb-4">Check back soon to discover fresh additions curated by this store owner.</p>
          <a href="shopping.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Browse other stores</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php include 'customer_footer.php'; ?>
