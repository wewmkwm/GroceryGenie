<?php
// store_owner/store_owner_profile.php
session_start();

$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['store_owner_id'])) {
    header("Location: store_owner_login.php");
    exit();
}

$owner_id = (int)$_SESSION['store_owner_id'];

$sql = "SELECT owner_id, name, email, phone_number, store_name, store_address, business_reg_no, store_logo, created_at
        FROM store_owners WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Unable to prepare statement.");
}
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Store owner not found.");
}

$owner = $result->fetch_assoc();

$store_logo = !empty($owner['store_logo']) ? "uploads/" . $owner['store_logo'] : "../assets/img/default_store.png";
$createdAt = !empty($owner['created_at']) ? date("d M Y", strtotime($owner['created_at'])) : null;
$storeName = $owner['store_name'] ?? '';
$ownerName = $owner['name'] ?? '';
$email = $owner['email'] ?? '';
$phone = $owner['phone_number'] ?? '';
$businessReg = $owner['business_reg_no'] ?? '';
$storeAddress = $owner['store_address'] ?? '';

$displayOrPlaceholder = function ($value, $isMultiline = false) {
    if (!isset($value) || $value === '') {
        return '<span class="text-muted">Not provided</span>';
    }
    $escaped = htmlspecialchars($value);
    return $isMultiline ? nl2br($escaped) : $escaped;
};
?>

<?php include 'store_owner_header.php'; ?>

<style>
  .so-profile-wrapper {
    margin-top: 2.5rem;
    margin-bottom: 4rem;
  }
  .so-profile-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.5rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    position: relative;
    overflow: hidden;
  }
  .so-profile-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 60%);
    pointer-events: none;
  }
  .so-profile-hero h1 {
    font-weight: 700;
    margin-bottom: 0.6rem;
  }
  .so-profile-hero p {
    color: rgba(255, 255, 255, 0.85);
    max-width: 640px;
    margin-bottom: 1.6rem;
  }
  .so-profile-meta {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    position: relative;
    z-index: 1;
  }
  .so-profile-meta li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
  }
  .so-profile-meta i {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    background: rgba(15, 23, 42, 0.25);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }
  .so-profile-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
    position: relative;
    overflow: hidden;
  }
  .so-profile-card .card-body {
    padding: 2rem;
  }
  .so-profile-summary .so-profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 32px;
    background: var(--gg-muted);
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 4px solid rgba(255, 255, 255, 0.65);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
  }
  .so-profile-summary img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .so-profile-summary h4 {
    font-weight: 700;
    margin-bottom: 0.5rem;
  }
  .so-profile-summary .text-muted {
    font-size: 0.95rem;
  }
  .so-profile-location {
    margin-top: 1.5rem;
  }
  .so-profile-location span.badge {
    background: rgba(255, 255, 255, 0.9);
    color: var(--gg-heading);
  }
  .so-profile-contact {
    list-style: none;
    padding: 0;
    margin: 2rem 0 0;
    display: grid;
    gap: 1rem;
  }
  .so-profile-contact li {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
  }
  .so-profile-contact i {
    color: var(--gg-primary);
    font-size: 1.1rem;
    margin-top: 0.2rem;
  }
  .so-profile-contact span {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--gg-secondary);
  }
  .so-profile-fields {
    margin: 0;
    padding: 0;
    display: grid;
    gap: 1.2rem;
  }
  .so-profile-field {
    display: grid;
    gap: 0.35rem;
  }
  .so-profile-field dt {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--gg-secondary);
    margin: 0;
  }
  .so-profile-field dd {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--gg-heading);
  }
  .so-profile-actions {
    background: linear-gradient(135deg, rgba(255,138,76,0.12), rgba(255,211,130,0.18));
    border-radius: var(--gg-radius-md);
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
  }
  .so-profile-actions h5 {
    margin: 0;
    font-weight: 600;
    color: var(--gg-heading);
  }
  .so-profile-actions p {
    margin: 0;
    color: var(--gg-secondary);
  }
  @media (max-width: 767px) {
    .so-profile-hero {
      padding: 2rem 1.5rem;
    }
    .so-profile-card .card-body {
      padding: 1.6rem;
    }
  }
</style>

<div class="container so-profile-wrapper">
  <section class="so-profile-hero">
    <span class="gg-hero-eyebrow"><i class="fas fa-store me-2"></i>Store owner spotlight</span>
    <h1><?php echo htmlspecialchars($storeName !== '' ? $storeName : 'Your Store'); ?></h1>
    <p>Keep your store story and contact information up to date so shoppers and administrators always have the right details.</p>
    <ul class="so-profile-meta">
      <li>
        <i class="fas fa-user-tie"></i>
        <div>
          <small class="d-block text-uppercase opacity-75">Owner</small>
          <?php echo htmlspecialchars($ownerName); ?>
        </div>
      </li>
      <li>
        <i class="fas fa-clock"></i>
        <div>
          <small class="d-block text-uppercase opacity-75">Member since</small>
          <?php echo $createdAt ? htmlspecialchars($createdAt) : '<span class="text-light opacity-75">Not recorded</span>'; ?>
        </div>
      </li>
      <li>
        <i class="fas fa-id-card"></i>
        <div>
          <small class="d-block text-uppercase opacity-75">Business reg.</small>
          <?php echo !empty($businessReg) ? htmlspecialchars($businessReg) : '<span class="text-light opacity-75">Not provided</span>'; ?>
        </div>
      </li>
    </ul>
  </section>

  <div class="row g-4 mt-4">
    <div class="col-lg-4">
      <div class="card so-profile-card so-profile-summary">
        <div class="card-body">
          <div class="so-profile-avatar">
            <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="Store logo">
          </div>
          <h4><?php echo htmlspecialchars($ownerName); ?></h4>
          <div class="text-muted"><?php echo htmlspecialchars($storeName !== '' ? $storeName : 'Store name not provided'); ?></div>
          <div class="so-profile-location">
            <span class="badge rounded-pill px-3 py-2"><i class="fas fa-map-marker-alt me-2 text-warning"></i>Primary location</span>
            <div class="mt-2"><?php echo $displayOrPlaceholder($storeAddress, true); ?></div>
          </div>
          <ul class="so-profile-contact">
            <li>
              <i class="fas fa-envelope"></i>
              <div>
                <span>Email</span>
                <?php if (!empty($email)): ?>
                  <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="text-decoration-none"><?php echo htmlspecialchars($email); ?></a>
                <?php else: ?>
                  <span class="text-muted">Not provided</span>
                <?php endif; ?>
              </div>
            </li>
            <li>
              <i class="fas fa-phone"></i>
              <div>
                <span>Phone</span>
                <?php echo !empty($phone) ? htmlspecialchars($phone) : '<span class="text-muted">Not provided</span>'; ?>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card so-profile-card mb-3">
        <div class="card-body">
          <h4 class="mb-4"><i class="fas fa-clipboard-list text-warning me-2"></i>Business information</h4>
          <dl class="so-profile-fields">
            <div class="so-profile-field">
              <dt>Store name</dt>
              <dd><?php echo $displayOrPlaceholder($storeName); ?></dd>
            </div>
            <div class="so-profile-field">
              <dt>Business address</dt>
              <dd><?php echo $displayOrPlaceholder($storeAddress, true); ?></dd>
            </div>
            <div class="so-profile-field">
              <dt>Business registration number</dt>
              <dd><?php echo $displayOrPlaceholder($businessReg); ?></dd>
            </div>
            <div class="so-profile-field">
              <dt>Contact email</dt>
              <dd>
                <?php if (!empty($email)): ?>
                  <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="text-decoration-none"><?php echo htmlspecialchars($email); ?></a>
                <?php else: ?>
                  <span class="text-muted">Not provided</span>
                <?php endif; ?>
              </dd>
            </div>
            <div class="so-profile-field">
              <dt>Contact phone</dt>
              <dd><?php echo $displayOrPlaceholder($phone); ?></dd>
            </div>
            <div class="so-profile-field">
              <dt>Account created</dt>
              <dd><?php echo $createdAt ? htmlspecialchars($createdAt) : '<span class="text-muted">Not recorded</span>'; ?></dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="so-profile-actions">
        <div>
          <h5>Need to make a change?</h5>
          <p>Keep your business details fresh so customers always have the right information.</p>
        </div>
        <a href="store_owner_edit_profile.php" class="gg-btn-primary">
          <i class="fas fa-pen-to-square me-2"></i> Update profile
        </a>
      </div>
    </div>
  </div>
</div>

<?php include 'store_owner_footer.php'; ?>
