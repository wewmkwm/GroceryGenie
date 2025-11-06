<?php
// customer/checkout.php
require_once __DIR__ . '/../includes/security.php';
include 'customer_header.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$customer_id = $_SESSION['customer_id'] ?? 0;

// Retrieve cart data from URL (from shopping.php)
$cart  = isset($_GET['cart'])  ? json_decode(urldecode($_GET['cart']), true) : [];
$total = isset($_GET['total']) ? preg_replace('/[^0-9.\-]/', '', $_GET['total']) : "0.00"; // sanitize to numbers

// DB
$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// If logged in, fetch delivery address snapshot to show (optional)
$delivery_address = '';
if ($customer_id) {
  $stmt = $conn->prepare("SELECT delivery_address FROM customers WHERE customer_id=?");
  $stmt->bind_param("i", $customer_id);
  $stmt->execute();
  $stmt->bind_result($delivery_address);
  $stmt->fetch();
  $stmt->close();
}
$hasDeliveryAddress = trim((string)$delivery_address) !== '';
?>

<style>
  .checkout-container { padding: 50px 20px; position: relative; }
  .back-chip {
    position: absolute; top: 12px; right: 12px;
    background:#fff; border:1px solid #ddd; border-radius:999px; padding:8px 14px;
    display:inline-flex; align-items:center; gap:8px; text-decoration:none; color:#333;
  }
  .back-chip:hover { background:#f7f7f7; }
  .checkout-summary {
    background: #ffffff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px;
  }
  .checkout-header { color: #ff6a00; font-weight: 700; margin-bottom: 25px; }
  .item-card { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #eee; }
  .item-card:last-child { border-bottom: none; }
  .item-left { display:flex; align-items:center; gap:12px; }
  .item-image { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; }
  .item-name { font-size: 16px; font-weight: 600; margin-bottom: 2px; }
  .item-qty { font-size: 13px; color: #555; }
  .item-subtotal { font-weight: bold; color: #ff6a00; }
  .total-box { background: #fff8f0; border: 1px solid #ffd7b5; padding: 20px; border-radius: 10px; margin-top: 25px; }
  .total-box h4 { margin:0; color: #333; display:flex; justify-content:space-between; align-items:center; }
  .total-box h4 span { color: #ff6a00; font-weight: bold; }
  .section-title { font-weight: 700; margin-top: 25px; margin-bottom: 10px; color:#333; }
  .pay-card { background:#fafafa; border:1px solid #eee; border-radius:10px; padding:16px; }
  .card-box { margin-top:15px; background:#fff8f1; border:1px solid #fdd9ba; border-radius:12px; padding:16px; }
  .card-box .form-label { font-weight:600; }
  .card-inline { display:flex; gap:12px; }
  .card-inline .form-control { flex:1; }
  .card-hint { font-size:0.85rem; color:#7c6a58; }
  .btn-confirm { background-color: #ff914d; border: none; color: white; font-weight: bold; padding: 12px 0; border-radius: 8px; width: 100%; margin-top: 18px; transition: 0.3s; }
  .btn-confirm:hover { background-color: #ff6a00; }
  .btn-confirm:disabled { background-color: #d1d5db; color: #9ca3af; cursor: not-allowed; }
  .empty-cart { text-align: center; padding: 60px 20px; color: #777; }
  .empty-cart img { width: 150px; margin-bottom: 20px; }
  .addr-box { white-space: pre-wrap; background:#f7f7f9; border:1px dashed #ddd; border-radius:10px; padding:12px; }
</style>

<div class="container checkout-container">

  <!-- Back to shopping without clearing cart -->
  <a class="back-chip" href="shopping.php?cart=<?php echo urlencode(json_encode($cart)); ?>&total=<?php echo urlencode("RM".number_format((float)$total,2)); ?>">
    <i class="fas fa-arrow-left"></i> Back to Shopping
  </a>

  <div class="checkout-summary">
    <h3 class="checkout-header">🛒 Checkout Summary</h3>

    <?php if (!empty($cart)) : ?>

      <!-- Items -->
      <?php
      foreach ($cart as $itemName => $details) {
        $qty = (int)$details['qty'];
        $price = (float)$details['price'];
        $subtotal = $qty * $price;

        // We ALSO want item_id snapshot if possible (safer key than name)
        $q = $conn->prepare("SELECT item_id, item_image FROM groceryitem WHERE item_name = ? LIMIT 1");
        $q->bind_param("s", $itemName);
        $q->execute();
        $q->bind_result($item_id, $item_image);
        $q->fetch();
        $q->close();

        // Image path
        if (!empty($item_image)) {
          $filename = basename($item_image);
          $imagePath = "../uploads/items/" . $filename;
        } else {
          $imagePath = "https://via.placeholder.com/80?text=No+Image";
        }

        echo "
          <div class='item-card'>
            <div class='item-left'>
              <img src='".htmlspecialchars($imagePath)."' class='item-image' alt='".htmlspecialchars($itemName)."'>
              <div>
                <div class='item-name'>".htmlspecialchars($itemName)."</div>
                <div class='item-qty'>Quantity: $qty</div>
              </div>
            </div>
            <div class='item-subtotal'>RM".number_format($subtotal, 2)."</div>
          </div>
        ";
      }
      ?>

      <!-- Total -->
      <div class="total-box">
        <h4>
          <span>Total Amount</span>
          <span>RM<?php echo number_format((float)$total, 2); ?></span>
        </h4>
      </div>

      <!-- Delivery Address snapshot -->
      <div class="section-title"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
      <?php if (!empty($delivery_address)) : ?>
        <div class="addr-box"><?php echo htmlspecialchars($delivery_address); ?></div>
        <small class="text-muted d-block mt-1">This address is saved from your profile. <a href="customer_profile.php">Edit Address</a></small>
      <?php else: ?>
        <div class="addr-box">No delivery address set. Please <a href="customer_profile.php">add your delivery address</a> before confirming.</div>
      <?php endif; ?>

      <!-- Payment Method -->
      <div class="section-title"><i class="fas fa-credit-card"></i> Payment Method</div>
      <div class="pay-card">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="pmethod" id="pmCOD" value="COD" checked>
          <label class="form-check-label" for="pmCOD">Cash on Delivery (Pay when item arrives)</label>
        </div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="radio" name="pmethod" id="pmBT" value="Bank Transfer">
        <label class="form-check-label" for="pmBT">Bank Transfer (Upload invoice)</label>
      </div>
      <div class="form-check mt-2">
        <input class="form-check-input" type="radio" name="pmethod" id="pmCard" value="Card">
        <label class="form-check-label" for="pmCard">Credit / Debit <Card></Card></label>
      </div>

      <!-- Bank Transfer details (shown when selected) -->
      <div id="bankTransferBox" class="mt-3" style="display:none;">
        <div class="alert alert-info py-2">
          <strong>Bank Details (Demo):</strong><br>
          Bank: Maybank<br>
          Acc No: 1234 5678 9012<br>
          Name: GroceryGenie Sdn Bhd
        </div>
        <label class="form-label">Upload Payment Proof (screenshot / invoice)</label>
        <input type="file" id="proofInput" class="form-control" accept="image/*">
          <small class="text-muted">Please upload your payment proof when choosing Bank Transfer.</small>
      </div>

      <!-- Card details -->
      <div id="cardBox" class="card-box" style="display:none;">

        <div class="mb-3">
          <label class="form-label" for="cardName">Cardholder Name</label>
          <input type="text" id="cardName" class="form-control" placeholder="e.g. John Doe">
        </div>
        <div class="mb-3">
          <label class="form-label" for="cardNumber">Card Number</label>
          <input type="text" id="cardNumber" class="form-control" maxlength="19" placeholder="1111 2222 3333 4444">
        </div>
        <div class="card-inline">
          <div>
            <label class="form-label" for="cardExpiry">Expiry (MM/YY)</label>
            <input type="text" id="cardExpiry" class="form-control" maxlength="5" placeholder="08/28">
          </div>
          <div>
            <label class="form-label" for="cardCvv">CVV</label>
            <input type="password" id="cardCvv" class="form-control" maxlength="4" placeholder="123">
          </div>
        </div>
        <div class="card-hint mt-2">We do not store card details. CVV is used for demo validation only.</div>
      </div>
      </div>

      <!-- Confirm (posts to processor) -->
      <button class="btn-confirm" id="confirmBtn" <?php echo $hasDeliveryAddress ? '' : 'disabled'; ?>>Confirm Purchase</button>

      <!-- Hidden form we’ll submit via JS -->
      <form id="placeOrderForm" action="process_order_demo.php" method="POST" enctype="multipart/form-data" style="display:none;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cart_json" value="<?php echo htmlspecialchars(json_encode($cart)); ?>">
        <input type="hidden" name="total_amount" value="<?php echo htmlspecialchars(number_format((float)$total,2,'.','')); ?>">
        <input type="hidden" name="payment_method" id="paymentMethodField" value="COD">
        <!-- We’ll append proof file via JS by copying the file input into a new form-data entry -->
      </form>

    <?php else: ?>
      <div class="empty-cart">
        <img src="https://cdn-icons-png.flaticon.com/512/11329/11329060.png" alt="Empty Cart">
        <h5>Your cart is empty!</h5>
        <p>Browse ingredients and add them to your cart before checking out.</p>
        <a href="shopping.php" class="btn btn-primary mt-3">Go Shopping</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
  // Show/hide Bank Transfer box
  const pmCOD = document.getElementById('pmCOD');
  const pmBT  = document.getElementById('pmBT');
  const pmCard = document.getElementById('pmCard');
  const bankBox = document.getElementById('bankTransferBox');
  const cardBox = document.getElementById('cardBox');
  const cardNameInput = document.getElementById('cardName');
  const cardNumberInput = document.getElementById('cardNumber');
  const cardExpiryInput = document.getElementById('cardExpiry');
  const cardCvvInput = document.getElementById('cardCvv');
  const paymentMethodField = document.getElementById('paymentMethodField');
  const proofInput = document.getElementById('proofInput');
  const confirmBtn = document.getElementById('confirmBtn');
  const form = document.getElementById('placeOrderForm');
  const hasDeliveryAddress = <?php echo $hasDeliveryAddress ? 'true' : 'false'; ?>;

  function refreshBox() {
    if (pmBT.checked) {
      bankBox.style.display = 'block';
      if (cardBox) cardBox.style.display = 'none';
      paymentMethodField.value = 'Bank Transfer';
    } else if (pmCard && pmCard.checked) {
      bankBox.style.display = 'none';
      if (cardBox) cardBox.style.display = 'block';
      paymentMethodField.value = 'Card';
    } else {
      bankBox.style.display = 'none';
      if (cardBox) cardBox.style.display = 'none';
      paymentMethodField.value = 'COD';
    }
  }
  pmCOD.addEventListener('change', refreshBox);
  pmBT.addEventListener('change', refreshBox);
  pmCard?.addEventListener('change', refreshBox);
  refreshBox();

  // Submit with FormData (to include file if chosen)
  confirmBtn?.addEventListener('click', function() {
    if (!hasDeliveryAddress) {
      alert('Please add your delivery address in your profile before confirming the purchase.');
      return;
    }
    if (pmBT.checked) {
      if (!proofInput || proofInput.files.length === 0) {
        alert('Please upload your payment proof before confirming a bank transfer order.');
        return;
      }
    }
    const fd = new FormData(form);
    if (pmBT.checked && proofInput && proofInput.files.length > 0) {
      fd.append('proof_image', proofInput.files[0]);
    }
    if (pmCard && pmCard.checked) {
      const cardName = (cardNameInput?.value || '').trim();
      const cardNumberDigits = (cardNumberInput?.value || '').replace(/\D/g, '');
      const cardExpiry = (cardExpiryInput?.value || '').trim();
      const cardCvv = (cardCvvInput?.value || '').trim();

      const expiryValid = /^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(cardExpiry);
      const cvvValid = /^\d{3,4}$/.test(cardCvv);

      if (!cardName || cardNumberDigits.length < 12 || cardNumberDigits.length > 19 || !expiryValid || !cvvValid) {
        alert('Please enter valid card details (name, number, expiry, CVV).');
        return;
      }

      fd.append('card_name', cardName);
      fd.append('card_number', cardNumberDigits);
      fd.append('card_expiry', cardExpiry);
    }

    fetch('process_order_demo.php', {
      method: 'POST',
      body: fd
    }).then(r => r.json())
      .then(res => {
        if (res.ok) {
          // success -> redirect to a simple success page (or order details)
          window.location.href = 'order_success.php?order_id=' + res.order_id;
        } else {
          alert(res.message || 'Failed to place order.');
        }
      })
      .catch(() => alert('Network error. Please try again.'));
  });
</script>

<?php
$conn->close();
include 'customer_footer.php';
?>
