<?php
// store_owner/store_owner_edit_item.php

session_start();
if (!isset($_SESSION['store_owner_id'])) {
    header("Location: store_owner_login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

require_once __DIR__ . '/../includes/upload_helper.php';

$message = "";
$owner_id = $_SESSION['store_owner_id'];

// ✅ Get item ID
if (!isset($_GET['item_id']) || empty($_GET['item_id'])) {
    header("Location: store_owner_dashboard.php");
    exit();
}
$item_id = intval($_GET['item_id']);

// ✅ Fetch item details
$stmt = $conn->prepare("SELECT * FROM groceryitem WHERE item_id=? AND owner_id=?");
$stmt->bind_param("ii", $item_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $message = "<div class='alert alert-danger'>❌ Item not found or you do not have permission to edit it.</div>";
} else {
    $item = $result->fetch_assoc();
}

// ✅ Handle update form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_name = trim($_POST['item_name']);
    $brand = trim($_POST['brand']);
    $unit = trim($_POST['unit']);
    $price_per_unit = isset($_POST['price_per_unit']) ? (float)$_POST['price_per_unit'] : 0.0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    if ($quantity < 0) { $quantity = 0; }
    $dietary_tag = $_POST['dietary_tag'];
    $category = $_POST['category'];

    // Image handling
    $item_image = $item['item_image'];
    $uploadError = null;
    if (!empty($_FILES['item_image']['name'])) {
        $uploadResult = gg_secure_upload(
            $_FILES['item_image'],
            dirname(__DIR__) . '/uploads/items/'
        );
        if ($uploadResult['success']) {
            $item_image = $uploadResult['filename'];
        } else {
            $uploadError = $uploadResult['message'];
        }
    }

    // ✅ Update DB
    if ($uploadError) {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> " . htmlspecialchars($uploadError) . "</div>";
    } else {
        $update_stmt = $conn->prepare("UPDATE groceryitem 
            SET item_name=?, brand=?, unit=?, price_per_unit=?, quantity=?, dietary_tag=?, category=?, item_image=? 
            WHERE item_id=? AND owner_id=?");
        $update_stmt->bind_param("sssdisssii", $item_name, $brand, $unit, $price_per_unit, $quantity, $dietary_tag, $category, $item_image, $item_id, $owner_id);

        if ($update_stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Item updated successfully!</div>";
            // Refresh item data
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
        } else {
            $message = "<div class='alert alert-danger'>??O Error updating item: " . $update_stmt->error . "</div>";
        }
    }
}
?>

<?php include("store_owner_header.php"); ?>

<?php $itemData = isset($item) && is_array($item) ? $item : null; ?>

<style>
  .so-edit-wrapper {
    margin-top: 2.5rem;
    margin-bottom: 4rem;
  }
  .so-edit-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.4rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    flex-wrap: wrap;
  }
  .so-edit-hero h1 {
    margin-bottom: 0.7rem;
    font-weight: 700;
  }
  .so-edit-hero p {
    color: rgba(255, 255, 255, 0.85);
    max-width: 520px;
  }
  .so-edit-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
  }
  .so-edit-card .card-body {
    padding: 2rem;
  }
  .so-edit-card label {
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .so-edit-card .form-control,
  .so-edit-card .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .so-edit-card .form-control:focus,
  .so-edit-card .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .so-preview-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
    overflow: hidden;
  }
  .so-preview-card img {
    width: 100%;
    height: 220px;
    object-fit: contain;
    background: rgba(15, 23, 42, 0.04);
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    padding: 1rem;
  }
  .so-preview-card .card-body {
    padding: 1.6rem;
  }
  .so-preview-meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-right: 0.5rem;
    color: var(--gg-muted);
    font-size: 0.9rem;
  }
  .so-empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    background: rgba(15, 23, 42, 0.02);
    border-radius: var(--gg-radius-md);
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
    color: var(--gg-muted);
  }
</style>

<div class="container so-edit-wrapper">
  <section class="so-edit-hero">
    <div>
      <span class="gg-hero-eyebrow"><i class="fas fa-pen-to-square"></i> Update listing</span>
      <h1><?php echo $itemData ? htmlspecialchars($itemData['item_name']) : 'Edit Item'; ?></h1>
      <p>
        Fine tune your product details, refresh pricing, or swap out imagery to keep customers inspired.
        Changes go live the moment you save.
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="store_owner_dashboard.php" class="gg-btn-outline text-white"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
      <a href="store_owner_add_item.php" class="gg-btn-primary"><i class="fas fa-plus"></i> Add another item</a>
    </div>
  </section>

  <?php if ($message) { echo $message; } ?>

  <?php if ($itemData): ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="so-edit-card">
          <div class="card-body">
            <h4 class="mb-3"><i class="fas fa-wrench text-warning me-2"></i>Edit item details</h4>
            <form method="POST" enctype="multipart/form-data">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Item Name *</label>
                  <input type="text" name="item_name" class="form-control" value="<?php echo htmlspecialchars($itemData['item_name']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Brand *</label>
                  <input type="text" name="brand" class="form-control" value="<?php echo htmlspecialchars($itemData['brand']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Unit *</label>
                  <input type="text" name="unit" class="form-control" value="<?php echo htmlspecialchars($itemData['unit']); ?>" required>
                  <small class="text-muted">How shoppers receive the item (e.g. 1kg, 6-pack).</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Price Per Unit (RM) *</label>
                  <input type="number" step="0.01" min="0" name="price_per_unit" class="form-control" value="<?php echo htmlspecialchars($itemData['price_per_unit']); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Quantity in Stock *</label>
                  <input type="number" min="0" name="quantity" class="form-control" value="<?php echo htmlspecialchars($itemData['quantity'] ?? 0); ?>" required>
                  <small class="text-muted">Update what you currently have ready to sell.</small>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Dietary Tag</label>
                  <select name="dietary_tag" class="form-select">
                    <?php
                      $dietOptions = ['', 'Vegan', 'Vegetarian', 'Halal', 'Gluten-Free', 'None'];
                      foreach ($dietOptions as $opt) {
                        $label = $opt === '' ? '-- Select --' : $opt;
                        $selected = $itemData['dietary_tag'] === $opt ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($opt) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
                      }
                    ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Category *</label>
                  <select name="category" class="form-select" required>
                    <?php
                      $categories = ['Fruits','Vegetables','Dairy','Meat','Snacks','Beverages','Others'];
                      foreach ($categories as $cat) {
                        $selected = $itemData['category'] === $cat ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($cat) . '" ' . $selected . '>' . htmlspecialchars($cat) . '</option>';
                      }
                    ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Upload New Image (optional)</label>
                  <input type="file" name="item_image" id="itemImageInput" class="form-control" accept="image/*">
                  <small class="text-muted">Square images (1200 x 1200px) look best.</small>
                </div>
              </div>
              <button type="submit" class="gg-btn-primary w-100 mt-4 py-3"><i class="fas fa-save me-2"></i>Save changes</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="so-preview-card">
          <?php
            $existingPath = '../uploads/items/' . $itemData['item_image'];
            $fallback = '../assets/default_item.png';
            $displayImage = (!empty($itemData['item_image']) && file_exists($existingPath)) ? $existingPath : $fallback;
          ?>
          <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="Current item image" id="itemPreviewImage">
          <div class="card-body">
            <h5 class="mb-1"><?php echo htmlspecialchars($itemData['item_name']); ?></h5>
            <div class="text-muted mb-3"><?php echo htmlspecialchars($itemData['brand']); ?></div>
            <div class="so-preview-meta mb-3">
              <span><i class="fas fa-box"></i><?php echo htmlspecialchars($itemData['unit']); ?></span>
              <span><i class="fas fa-boxes"></i><?php echo htmlspecialchars((string)($itemData['quantity'] ?? 0)); ?> in stock</span>
              <span><i class="fas fa-tags"></i><?php echo htmlspecialchars($itemData['category']); ?></span>
              <?php if (!empty($itemData['dietary_tag'])): ?>
                <span><i class="fas fa-leaf"></i><?php echo htmlspecialchars($itemData['dietary_tag']); ?></span>
              <?php endif; ?>
            </div>
            <div class="fw-bold text-warning display-6 mb-3">RM <?php echo number_format((float)$itemData['price_per_unit'], 2); ?></div>
            <div class="small text-muted">Created on <?php echo date('d M Y', strtotime($itemData['created_at'])); ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="so-empty-state mt-4">
      <i class="fas fa-triangle-exclamation fa-2x mb-3 d-block"></i>
      Item not found or unavailable. Return to the dashboard to browse your listings.
    </div>
  <?php endif; ?>
</div>

<script>
  (function() {
    const input = document.getElementById('itemImageInput');
    const preview = document.getElementById('itemPreviewImage');
    if (!input || !preview) return;

    input.addEventListener('change', function(event) {
      const [file] = event.target.files || [];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  })();
</script>

<?php include 'store_owner_footer.php'; ?>
