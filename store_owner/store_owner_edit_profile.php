<?php
// store_owner_edit_profile.php
session_start();
$conn = new mysqli("localhost", "root", "", "grocerygenie");
require_once __DIR__ . '/../includes/upload_helper.php';

// Check if logged in
if (!isset($_SESSION['store_owner_id'])) {
    header("Location: store_owner_login.php");
    exit();
}

$owner_id = $_SESSION['store_owner_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $store_name = trim($_POST['store_name'] ?? '');
    $store_address = trim($_POST['store_address'] ?? '');
    $business_reg_no = trim($_POST['business_reg_no'] ?? '');

    // Handle store logo upload
    $store_logo = null;
    $uploadError = null;
    if (!empty($_FILES['store_logo']['name'])) {
        $uploadResult = gg_secure_upload(
            $_FILES['store_logo'],
            __DIR__ . '/uploads/',
            ['image/jpeg', 'image/png', 'image/webp'],
            3 * 1024 * 1024
        );
        if ($uploadResult['success']) {
            $store_logo = $uploadResult['filename'];
        } else {
            $uploadError = $uploadResult['message'];
        }
    }

    if ($uploadError) {
        $error = $uploadError;
    } else {
        // Update database
        if ($store_logo) {
            $sql = "UPDATE store_owners SET name=?, email=?, phone_number=?, store_name=?, store_address=?, business_reg_no=?, store_logo=? WHERE owner_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $name, $email, $phone_number, $store_name, $store_address, $business_reg_no, $store_logo, $owner_id);
        } else {
            $sql = "UPDATE store_owners SET name=?, email=?, phone_number=?, store_name=?, store_address=?, business_reg_no=? WHERE owner_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $name, $email, $phone_number, $store_name, $store_address, $business_reg_no, $owner_id);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: store_owner_profile.php");
            exit();
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// Fetch existing details
$sql = "SELECT owner_id, name, email, phone_number, store_name, store_address, business_reg_no, store_logo 
        FROM store_owners WHERE owner_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();

// Default logo
$store_logo = !empty($owner['store_logo']) ? "uploads/" . $owner['store_logo'] : "../assets/img/default_store.png";

?>

<?php include 'store_owner_header.php'; ?>

<style>
  .owner-edit-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.15), rgba(255, 145, 77, 0.12));
    border-radius: 28px;
    padding: 2.5rem 1.75rem;
    margin-bottom: 2.5rem;
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.12);
  }
  .owner-edit-hero h2 {
    font-weight: 700;
    color: #1f2937;
  }
  .owner-edit-hero p {
    color: #4b5563;
    max-width: 640px;
  }
  .owner-edit-card {
    border: none;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 30px 55px rgba(15, 23, 42, 0.12);
    overflow: hidden;
  }
  .owner-edit-card .card-body {
    padding: 2.5rem;
  }
  .owner-logo-upload {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
  }
  .owner-logo-upload img {
    width: 140px;
    height: 140px;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid rgba(67, 97, 238, 0.25);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.18);
    background: #ffffff;
  }
  .owner-logo-upload label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #4361ee;
    border: 1px dashed rgba(67, 97, 238, 0.4);
    padding: 0.55rem 1.5rem;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .owner-logo-upload label:hover {
    background: rgba(67, 97, 238, 0.08);
  }
  .owner-logo-upload input[type="file"] {
    display: none;
  }
  .owner-section-title {
    font-size: 0.95rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
    color: #9ca3af;
    margin-bottom: 0.75rem;
  }
  .owner-edit-card .form-label {
    font-weight: 600;
    color: #1f2937;
  }
  .owner-edit-card .form-control,
  .owner-edit-card .form-control:focus,
  .owner-edit-card .form-select {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.55);
    box-shadow: none;
  }
  .owner-edit-card textarea {
    min-height: 120px;
  }
  .owner-edit-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: flex-end;
  }
  .owner-edit-actions .btn {
    border-radius: 999px;
    padding: 0.65rem 1.75rem;
    font-weight: 600;
  }
  @media (max-width: 767.98px) {
    .owner-edit-card .card-body {
      padding: 1.75rem;
    }
    .owner-edit-actions {
      justify-content: center;
    }
  }
</style>

<section class="owner-edit-hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <h2 class="mb-3">Refresh Your Store Profile</h2>
        <p class="mb-0">Keep your contact details, branding, and business information up to date so customers can find and trust your store at a glance.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <div class="badge bg-white text-dark border px-4 py-2 rounded-pill shadow-sm">
          <i class="fas fa-store me-2 text-primary"></i><?php echo htmlspecialchars($owner['store_name']); ?>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-xl-9">
      <div class="card owner-edit-card">
        <div class="card-body">
          <?php if (isset($error)) { ?>
            <div class="alert alert-danger mb-4"><?php echo $error; ?></div>
          <?php } ?>

          <form method="POST" enctype="multipart/form-data">
            <div class="row g-4 align-items-center mb-4">
              <div class="col-md-4 text-center">
                <div class="owner-logo-upload">
                  <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="Store logo preview">
                  <label for="store_logo">
                    <i class="fas fa-upload me-2"></i>Update logo
                  </label>
                  <input type="file" id="store_logo" name="store_logo" accept="image/*">
                  <small class="text-muted d-block">Recommended: square image, max 2MB.</small>
                </div>
              </div>
              <div class="col-md-8">
                <div class="owner-section-title">Owner Details</div>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <label class="form-label" for="owner_name">Owner name</label>
                    <input type="text" id="owner_name" name="name" class="form-control" value="<?php echo htmlspecialchars($owner['name']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="owner_email">Email</label>
                    <input type="email" id="owner_email" name="email" class="form-control" value="<?php echo htmlspecialchars($owner['email']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="owner_phone">Phone number</label>
                    <input type="text" id="owner_phone" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($owner['phone_number']); ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label" for="business_reg_no">Business reg. no.</label>
                    <input type="text" id="business_reg_no" name="business_reg_no" class="form-control" value="<?php echo htmlspecialchars($owner['business_reg_no']); ?>" required>
                  </div>
                </div>
              </div>
            </div>

            <div class="owner-section-title">Store Information</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label" for="store_name">Store name</label>
                <input type="text" id="store_name" name="store_name" class="form-control" value="<?php echo htmlspecialchars($owner['store_name']); ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="store_address">Store address</label>
                <textarea id="store_address" name="store_address" class="form-control" rows="3" required><?php echo htmlspecialchars($owner['store_address']); ?></textarea>
              </div>
            </div>

            <div class="owner-edit-actions">
              <a href="store_owner_profile.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Cancel
              </a>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Save changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'store_owner_footer.php'; ?>
