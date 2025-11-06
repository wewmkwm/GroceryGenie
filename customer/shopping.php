<?php include 'customer_header.php'; ?>

<?php
$conn = new mysqli('localhost', 'root', '', 'grocerygenie');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$categories = [];
$categoryResult = $conn->query("SELECT DISTINCT category FROM groceryitem WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($categoryResult) {
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $categories[] = $categoryRow['category'];
    }
    $categoryResult->free();
}

$sql = "SELECT g.item_id, g.item_name, g.brand, g.unit, g.price_per_unit, g.item_image, g.quantity, g.category,
               s.owner_id, s.store_name, s.name AS owner_name
        FROM groceryitem g
        JOIN store_owners s ON g.owner_id = s.owner_id
        ORDER BY g.created_at DESC";
$result = $conn->query($sql);
?>

<style>
  .shopping-wrapper {
    margin-top: 2rem;
    margin-bottom: 4rem;
  }
  .shopping-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-lg);
    color: #fff;
    padding: 2.6rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    justify-content: space-between;
  }
  .shopping-hero h1 {
    font-weight: 700;
    margin-bottom: 0.8rem;
  }
  .shopping-hero p {
    color: rgba(255, 255, 255, 0.85);
    max-width: 520px;
  }
  .shopping-filters,
  .shopping-summary,
  .shopping-filters .card-body {
    padding: 1.6rem;
  }
  .shopping-filters label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--gg-muted);
    font-weight: 600;
  }
  .shopping-filters .form-control,
  .shopping-filters .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .shopping-filters .form-control:focus,
  .shopping-filters .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .shopping-item-card {
    position: relative;
    border-radius: var(--gg-radius-md);
    overflow: hidden;
    box-shadow: var(--gg-shadow-soft);
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
  }
  .shopping-item-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--gg-shadow-hover);
  }
  .shopping-item-thumb {
    position: relative;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(255, 138, 76, 0.08), rgba(255, 61, 113, 0.08));
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .shopping-item-thumb img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: var(--gg-radius-sm);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
  }
  .shopping-item-category {
    position: absolute;
    top: 14px;
    left: 14px;
    background: rgba(255, 138, 76, 0.18);
    color: var(--gg-primary-dark);
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
  }
  .shopping-item-body {
    padding: 1.3rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    flex: 1;
  }
  .shopping-item-body h5 {
    margin: 0;
    font-weight: 700;
    color: var(--gg-secondary);
  }
  .shopping-item-body .text-muted {
    font-size: 0.9rem;
  }
  .shopping-item-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gg-primary-dark);
  }
  .shopping-item-body .form-control {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
    text-align: center;
  }
  .shopping-item-body .form-control:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .shopping-empty {
    display: none;
    text-align: center;
    padding: 2.5rem;
    border-radius: var(--gg-radius-md);
    background: rgba(15, 23, 42, 0.02);
    color: var(--gg-muted);
    font-weight: 500;
  }
  .shopping-summary .card-body {
    padding: 1.8rem;
  }
  .shopping-summary h5 {
    font-weight: 700;
    margin-bottom: 1rem;
  }
  .shopping-summary .list-group-item {
    border: none;
    background: rgba(15, 23, 42, 0.02);
    border-radius: var(--gg-radius-sm);
    margin-bottom: 0.5rem;
  }
  .shopping-summary .list-group-item:last-child {
    margin-bottom: 0;
  }
  .shopping-summary hr {
    margin: 1.2rem 0;
    opacity: 0.15;
  }
</style>

<div class="container shopping-wrapper">
  <section class="shopping-hero">
    <div>
      <span class="gg-hero-eyebrow"><i class="fas fa-shopping-basket"></i> Smart Grocery Run</span>
      <h1>Build your pantry with fresh picks and better prices.</h1>
      <p>Search ingredients, compare suppliers, and fill your cart in minutes. We’ll calculate totals and even suggest savings routes before checkout.</p>
    </div>
    <div class="text-nowrap">
      <a href="saved_recipes.php" class="gg-btn-outline text-white"><i class="fas fa-bookmark"></i> View saved recipes</a>
    </div>
  </section>

  <div class="row g-4 mt-4">
    <div class="col-xl-8">
      <div class="card shopping-filters mb-4">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Search</label>
              <input type="text" id="searchInput" class="form-control" placeholder="Search ingredients, brands, or stores">
            </div>
            <div class="col-md-6 col-lg-4">
              <label class="form-label">Category</label>
              <select id="categoryFilter" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($categories as $categoryOption): ?>
                  <option value="<?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 col-lg-4">
              <label class="form-label">Sort</label>
              <select id="sortSelect" class="form-select">
                <option value="default">Recommended</option>
                <option value="name-asc">Name (A - Z)</option>
                <option value="name-desc">Name (Z - A)</option>
                <option value="price-asc">Price (Low to High)</option>
                <option value="price-desc">Price (High to Low)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4" id="ingredientsGrid">
        <?php
        if ($result && $result->num_rows > 0) {
          $position = 0;
          while ($row = $result->fetch_assoc()) {
            $position++;
            $item_id = $row['item_id'];
            $name = htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8');
            $unit = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');
            $priceRaw = (float)$row['price_per_unit'];
            $price = number_format($priceRaw, 2);
            $quantity = (int)$row['quantity'];
            $owner_id = $row['owner_id'];
            $store_name = htmlspecialchars($row['store_name'], ENT_QUOTES, 'UTF-8');
            $owner_name = htmlspecialchars($row['owner_name'], ENT_QUOTES, 'UTF-8');
            $category = htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8');

            $image = "https://via.placeholder.com/120?text=No+Image";
            if (!empty($row['item_image'])) {
              $filename = basename($row['item_image']);
              $possiblePath = "../uploads/items/" . $filename;
              if (file_exists($possiblePath)) {
                $image = $possiblePath;
              }
            }
            ?>
            <div class="col-md-6 col-lg-4 ingredient-item" data-name="<?php echo $name; ?>" data-category="<?php echo $category; ?>" data-price="<?php echo $priceRaw; ?>" data-index="<?php echo $position; ?>">
              <div class="card shopping-item-card h-100">
                <div class="shopping-item-thumb">
                  <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo $name; ?>">
                  <?php if (!empty($category)): ?>
                    <span class="shopping-item-category"><?php echo $category; ?></span>
                  <?php endif; ?>
                </div>
                <div class="shopping-item-body">
                  <div>
                    <h5><?php echo $name; ?></h5>
                    <div class="text-muted">Brand: <?php echo htmlspecialchars($row['brand'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-muted small">Unit: <?php echo $unit; ?></div>
                    <div class="text-muted small">Available: <?php echo $quantity; ?> in stock</div>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="shopping-item-price">RM<?php echo $price; ?></div>
                    <input type="number" class="form-control quantity-box" min="0" max="<?php echo $quantity; ?>" value="0"
                           data-id="<?php echo $item_id; ?>" data-price="<?php echo $priceRaw; ?>" data-name="<?php echo $name; ?>">
                  </div>
                  <div class="text-muted small">
                    Sold by <a href="customer_view_store_owner_info.php?id=<?php echo (int)$owner_id; ?>"><?php echo $store_name; ?></a> · <?php echo $owner_name; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
        }
        ?>
      </div>

      <div class="shopping-empty" id="noResultsMessage">No items match your filters yet. Try adjusting your search.</div>
    </div>

    <div class="col-xl-4">
      <div class="card shopping-summary">
        <div class="card-body">
          <h5><i class="fas fa-basket-shopping text-warning me-2"></i>Your cart</h5>
          <ul class="list-group" id="cartList">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>No items yet.</span>
            </li>
          </ul>
          <hr>
          <div class="d-flex justify-content-between text-muted">
            <span>Items selected</span>
            <span class="fw-semibold" id="cartCount">0</span>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="fw-semibold">Total</span>
            <span class="fs-4 text-warning" id="totalPrice">RM0.00</span>
          </div>
          <button id="checkoutButton" class="gg-btn-primary w-100 mt-3"><i class="fas fa-credit-card me-2"></i>Checkout</button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  const cartList = document.getElementById('cartList');
  const totalPriceElem = document.getElementById('totalPrice');
  const cartCountElem = document.getElementById('cartCount');
  const quantityBoxes = document.querySelectorAll('.quantity-box');
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const sortSelect = document.getElementById('sortSelect');
  const ingredientsGrid = document.getElementById('ingredientsGrid');
  const emptyState = document.getElementById('noResultsMessage');
  const checkoutButton = document.getElementById('checkoutButton');

  let cart = {};

  quantityBoxes.forEach(input => {
    input.addEventListener('input', function() {
      const name = this.dataset.name;
      const price = parseFloat(this.dataset.price);
      const qty = parseInt(this.value) || 0;
      const maxQty = parseInt(this.getAttribute('max'));

      if (qty > maxQty) {
        alert('Only ' + maxQty + ' items available in stock.');
        this.value = maxQty;
        return;
      }

      if (qty > 0) {
        cart[name] = { qty: qty, price: price };
      } else {
        delete cart[name];
      }

      updateCart();
    });
  });

  function updateCart() {
    cartList.innerHTML = '';
    let total = 0;
    let totalQuantity = 0;

    for (const item in cart) {
      const qty = cart[item].qty;
      const subtotal = qty * cart[item].price;
      total += subtotal;
      totalQuantity += qty;

      cartList.innerHTML += `
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong>${item}</strong> × ${qty}<br>
            <small class="text-muted">RM${cart[item].price.toFixed(2)} each</small>
          </div>
          <div class="d-flex align-items-center">
            <span class="fw-semibold me-2">RM${subtotal.toFixed(2)}</span>
            <button type="button" class="btn btn-sm btn-outline-danger remove-item" data-name="${item}" title="Remove">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>
        </li>
      `;
    }

    if (Object.keys(cart).length === 0) {
      cartList.innerHTML = `
        <li class="list-group-item d-flex justify-content-between align-items-center">
          <span>No items yet.</span>
        </li>
      `;
    }

    totalPriceElem.innerText = 'RM' + total.toFixed(2);
    if (cartCountElem) {
      cartCountElem.innerText = totalQuantity;
    }

    document.querySelectorAll('.remove-item').forEach(button => {
      button.addEventListener('click', function() {
        const itemName = this.dataset.name;
        delete cart[itemName];
        const input = Array.from(document.querySelectorAll('.quantity-box')).find(el => el.dataset.name === itemName);
        if (input) {
          input.value = 0;
        }
        updateCart();
      });
    });
  }

  function filterAndSort() {
    if (!ingredientsGrid) return;

    const searchTerm = (searchInput?.value || '').trim().toLowerCase();
    const selectedCategory = categoryFilter?.value || '';
    const sortValue = sortSelect?.value || 'default';
    const items = Array.from(ingredientsGrid.querySelectorAll('.ingredient-item'));

    items.forEach(item => {
      const name = (item.dataset.name || '').toLowerCase();
      const category = item.dataset.category || '';
      const matchesSearch = !searchTerm || name.includes(searchTerm);
      const matchesCategory = !selectedCategory || category === selectedCategory;
      const isVisible = matchesSearch && matchesCategory;
      item.dataset.visible = isVisible ? '1' : '0';
      item.style.display = isVisible ? '' : 'none';
    });

    const sorted = items.slice();
    sorted.sort((a, b) => {
      switch (sortValue) {
        case 'name-asc':
          return (a.dataset.name || '').localeCompare(b.dataset.name || '', undefined, { sensitivity: 'base' });
        case 'name-desc':
          return (b.dataset.name || '').localeCompare(a.dataset.name || '', undefined, { sensitivity: 'base' });
        case 'price-asc':
          return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        case 'price-desc':
          return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
        default:
          return parseInt(a.dataset.index, 10) - parseInt(b.dataset.index, 10);
      }
    });

    sorted.forEach(item => ingredientsGrid.appendChild(item));

    if (emptyState) {
      const anyVisible = items.some(item => item.dataset.visible === '1');
      emptyState.style.display = anyVisible ? 'none' : 'block';
    }
  }

  searchInput?.addEventListener('input', filterAndSort);
  categoryFilter?.addEventListener('change', filterAndSort);
  sortSelect?.addEventListener('change', filterAndSort);
  filterAndSort();
  updateCart();

  checkoutButton?.addEventListener('click', function() {
    if (Object.keys(cart).length === 0) {
      alert('Your cart is empty. Please add some items before checkout.');
      return;
    }

    const cartData = encodeURIComponent(JSON.stringify(cart));
    const total = encodeURIComponent(totalPriceElem.innerText);
    window.location.href = `checkout.php?cart=${cartData}&total=${total}`;
  });

  (function() {
    try {
      const params = new URLSearchParams(window.location.search);
      if (!params.has('cart')) return;
      const fromCart = JSON.parse(decodeURIComponent(params.get('cart')));
      quantityBoxes.forEach(input => {
        const name = input.dataset.name;
        if (fromCart[name]) {
          input.value = parseInt(fromCart[name].qty) || 0;
          input.dispatchEvent(new Event('input'));
        }
      });
    } catch (e) {}
  })();
</script>

<?php include 'customer_footer.php'; ?>
