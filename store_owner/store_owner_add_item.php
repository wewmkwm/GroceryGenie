<?php
// store_owner/store_owner_add_item.php

session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';
if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'grocerygenie');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = "<div class='alert alert-danger shadow-sm mb-4'><i class='fas fa-shield-alt me-2'></i>Security validation failed. Please refresh and try again.</div>";
    } else {
        $owner_id = (int)$_SESSION['store_owner_id'];
        $item_name = trim($_POST['item_name'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $price_per_unit = isset($_POST['price_per_unit']) ? (float)$_POST['price_per_unit'] : 0.0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $dietary_tag = trim($_POST['dietary_tag'] ?? '');
        $category = trim($_POST['category'] ?? '');

        $item_image = null;
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

        if ($uploadError) {
            $message = "<div class='alert alert-danger shadow-sm mb-4'><i class='fas fa-exclamation-triangle me-2'></i>" . htmlspecialchars($uploadError) . "</div>";
        } elseif (
            $item_name !== '' &&
            $brand !== '' &&
            $unit !== '' &&
            $price_per_unit >= 0 &&
            $quantity >= 0 &&
            $category !== ''
        ) {
            $stmt = $conn->prepare(
                'INSERT INTO groceryitem
                (owner_id, item_name, brand, unit, price_per_unit, quantity, dietary_tag, category, item_image, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            if ($stmt) {
                $stmt->bind_param(
                    'isssdisss',
                    $owner_id,
                    $item_name,
                    $brand,
                    $unit,
                    $price_per_unit,
                    $quantity,
                    $dietary_tag,
                    $category,
                    $item_image
                );

                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success shadow-sm mb-4'><i class='fas fa-check-circle me-2'></i>Item added successfully!</div>";
                    $_POST = [];
                } else {
                    $message = "<div class='alert alert-danger shadow-sm mb-4'><i class='fas fa-exclamation-circle me-2'></i>Error: " . htmlspecialchars($stmt->error, ENT_QUOTES) . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='alert alert-danger shadow-sm mb-4'><i class='fas fa-exclamation-circle me-2'></i>Unable to prepare statement.</div>";
            }
        } else {
            $message = "<div class='alert alert-warning shadow-sm mb-4'><i class='fas fa-info-circle me-2'></i>Please fill in all required fields.</div>";
        }
    }
}

?>

<?php include 'store_owner_header.php'; ?>

<style>
  .so-add-wrapper {
    margin-top: 2.5rem;
    margin-bottom: 3rem;
  }
  .so-side-card {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.2rem;
    box-shadow: var(--gg-shadow-soft);
    position: relative;
    overflow: hidden;
  }
  .so-side-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.25), transparent 60%);
    pointer-events: none;
  }
  .so-side-card h2 {
    font-weight: 700;
    margin-bottom: 0.75rem;
  }
  .so-side-card p {
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 1.2rem;
  }
  .so-side-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .so-side-card li {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.8rem;
    font-weight: 500;
  }
  .so-side-card li i {
    background: rgba(255, 255, 255, 0.18);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .so-form-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    padding: 2.2rem;
    box-shadow: var(--gg-shadow-soft);
  }
  .so-form-card h3 {
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--gg-secondary);
  }
  .so-form-card .lead {
    color: var(--gg-muted);
    margin-bottom: 1.5rem;
  }
  .so-input-group .input-group-text {
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    border: none;
  }
  .so-input-group .form-control,
  .so-input-group .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .so-input-group .form-control:focus,
  .so-input-group .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  #imagePreview {
    border-radius: var(--gg-radius-sm);
    background: rgba(15, 23, 42, 0.04);
    border: 1px dashed rgba(15, 23, 42, 0.15);
    min-height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  #imagePreview img {
    max-width: 100%;
    max-height: 220px;
    object-fit: cover;
    border-radius: var(--gg-radius-sm);
  }
</style>

<div class="container so-add-wrapper">
  <div class="row g-4 align-items-stretch">
    <div class="col-lg-4">
      <div class="so-side-card h-100">
        <div class="mb-4">
          <span class="gg-hero-eyebrow mb-3 d-inline-flex align-items-center gap-2"><i class="fas fa-store"></i> New Inventory</span>
          <h2>Showcase fresh stock with flair.</h2>
          <p>High quality photos and clear details help GroceryGenie customers discover and trust your products faster.</p>
        </div>
        <ul>
          <li><i class="fas fa-camera"></i><span>Add bright, well-lit images.</span></li>
          <li><i class="fas fa-tag"></i><span>Keep pricing transparent.</span></li>
          <li><i class="fas fa-box"></i><span>Update stock so alerts stay accurate.</span></li>
        </ul>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="so-form-card">
        <h3><i class="fas fa-plus-circle text-warning me-2"></i>Add a new item</h3>
        <p class="lead">Complete the details below and we’ll surface your product across GroceryGenie shopping and recipes.</p>

        <?php if ($message !== '') { echo $message; } ?>

        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <?php echo csrf_field(); ?>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Item Name *</label>
            <div class="input-group so-input-group">
              <span class="input-group-text"><i class="fas fa-shopping-bag"></i></span>
              <input type="text" name="item_name" class="form-control" placeholder="e.g. Organic Spinach" value="<?php echo htmlspecialchars($_POST['item_name'] ?? '', ENT_QUOTES); ?>" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Brand *</label>
            <div class="input-group so-input-group">
              <span class="input-group-text"><i class="fas fa-copyright"></i></span>
              <input type="text" name="brand" class="form-control" placeholder="Enter brand" value="<?php echo htmlspecialchars($_POST['brand'] ?? '', ENT_QUOTES); ?>" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Unit *</label>
            <div class="input-group so-input-group">
              <span class="input-group-text"><i class="fas fa-balance-scale"></i></span>
              <input type="text" name="unit" class="form-control" placeholder="e.g. 1 kg, 500 ml, 12 pcs" value="<?php echo htmlspecialchars($_POST['unit'] ?? '', ENT_QUOTES); ?>" required>
            </div>
            <small class="text-muted">Describe how you sell this item (weight, volume, pack size).</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Price Per Unit (RM) *</label>
            <div class="input-group so-input-group">
              <span class="input-group-text"><i class="fas fa-money-bill-wave"></i></span>
              <input type="number" step="0.01" min="0" name="price_per_unit" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($_POST['price_per_unit'] ?? '', ENT_QUOTES); ?>" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Quantity in Stock *</label>
            <div class="input-group so-input-group">
              <span class="input-group-text"><i class="fas fa-boxes"></i></span>
              <input type="number" min="0" name="quantity" class="form-control" placeholder="Available quantity" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '', ENT_QUOTES); ?>" required>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Dietary Tag</label>
            <?php $dietSel = $_POST['dietary_tag'] ?? ''; ?>
            <div class="so-input-group">
              <select name="dietary_tag" class="form-select">
                <option value="" <?php echo $dietSel === '' ? 'selected' : ''; ?>>-- Select --</option>
                <option value="Vegan" <?php echo $dietSel === 'Vegan' ? 'selected' : ''; ?>>Vegan</option>
                <option value="Vegetarian" <?php echo $dietSel === 'Vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                <option value="Halal" <?php echo $dietSel === 'Halal' ? 'selected' : ''; ?>>Halal</option>
                <option value="Gluten-Free" <?php echo $dietSel === 'Gluten-Free' ? 'selected' : ''; ?>>Gluten-Free</option>
                <option value="None" <?php echo $dietSel === 'None' ? 'selected' : ''; ?>>None</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category *</label>
            <?php $catSel = $_POST['category'] ?? ''; ?>
            <div class="so-input-group">
              <select name="category" class="form-select" required>
                <option value="" <?php echo $catSel === '' ? 'selected' : ''; ?>>-- Select --</option>
                <option value="Fruits" <?php echo $catSel === 'Fruits' ? 'selected' : ''; ?>>Fruits</option>
                <option value="Vegetables" <?php echo $catSel === 'Vegetables' ? 'selected' : ''; ?>>Vegetables</option>
                <option value="Dairy" <?php echo $catSel === 'Dairy' ? 'selected' : ''; ?>>Dairy</option>
                <option value="Meat" <?php echo $catSel === 'Meat' ? 'selected' : ''; ?>>Meat</option>
                <option value="Snacks" <?php echo $catSel === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                <option value="Beverages" <?php echo $catSel === 'Beverages' ? 'selected' : ''; ?>>Beverages</option>
                <option value="Others" <?php echo $catSel === 'Others' ? 'selected' : ''; ?>>Others</option>
              </select>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Item Image *</label>
            <div class="so-input-group">
              <input type="file" id="itemImageInput" name="item_image" class="form-control" accept="image/*" required>
            </div>
            <small class="text-muted d-block mt-1">Recommended: 1200 x 1200px, bright background.</small>
            <div id="imagePreview" class="mt-3">
              <span class="text-muted"><i class="fas fa-image me-2"></i>Preview will appear here</span>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="gg-btn-primary w-100 py-3"><i class="fas fa-save me-2"></i>Save Item</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    const fileInput = document.getElementById('itemImageInput');
    const preview = document.getElementById('imagePreview');
    if (!fileInput || !preview) return;

    fileInput.addEventListener('change', function (event) {
      preview.innerHTML = '';
      const [file] = event.target.files || [];
      if (!file) {
        preview.innerHTML = '<span class="text-muted"><i class="fas fa-image me-2"></i>Preview will appear here</span>';
        return;
      }
      const reader = new FileReader();
      reader.onload = function (e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  })();
</script>

<?php include 'store_owner_footer.php'; ?>
