<?php
// customer/customer_profile.php
session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';
if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

// DB connection
$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$customer_id = intval($_SESSION['customer_id']);

// Ensure delivery_address column exists (if not, add it) - helpful for older schemas
$check_col = $conn->query("SHOW COLUMNS FROM customers LIKE 'delivery_address'");
if ($check_col && $check_col->num_rows === 0) {
    // add column (TEXT) - silent attempt
    $conn->query("ALTER TABLE customers ADD COLUMN delivery_address TEXT AFTER phone_number");
}

// Fetch customer details (including delivery_address)
$sql = "SELECT name, email, phone_number, delivery_address, profile_pic FROM customers WHERE customer_id = ?";
$stmtProfile = $conn->prepare($sql);
$stmtProfile->bind_param("i", $customer_id);
$stmtProfile->execute();
$result = $stmtProfile->get_result();

if (!$result || $result->num_rows === 0) {
    die("Customer not found.");
}

$customer = $result->fetch_assoc();

// Default profile picture path
$default_profile_pic = "../assets/img/default_profile.png";

/**
 * Build a browser-friendly URL for a stored profile picture value.
 */
function customer_profile_image_url(?string $stored, string $fallback): string
{
    if (empty($stored)) {
        return $fallback;
    }

    $stored = trim($stored);

    if (preg_match('/^https?:\/\//i', $stored)) {
        return $stored;
    }

    if (str_starts_with($stored, '../') || str_starts_with($stored, './') || str_starts_with($stored, '/')) {
        return $stored;
    }

    $normalized = ltrim($stored, './');
    if (str_starts_with($normalized, 'uploads/')) {
        return '../' . $normalized;
    }

    if (str_starts_with($normalized, 'customer/uploads/')) {
        return '../' . substr($normalized, strlen('customer/'));
    }

    return '../uploads/customers/' . $normalized;
}

$profile_image_url = customer_profile_image_url($customer['profile_pic'] ?? null, $default_profile_pic);

$message = "";

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = "<div class='alert alert-danger'>Security validation failed. Please refresh the page and try again.</div>";
    } else {
        // sanitize inputs
        $name = trim($_POST['name'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $password_raw = $_POST['password'] ?? '';
        $password_hash = !empty($password_raw) ? password_hash($password_raw, PASSWORD_BCRYPT) : null;

        // profile pic handling
        $profile_filename = $customer['profile_pic'] ?? null; // keep old if not uploading
        $uploadError = null;
        if (!empty($_FILES['profile_pic']['name'])) {
            $uploadResult = gg_secure_upload(
                $_FILES['profile_pic'],
                dirname(__DIR__) . '/uploads/customers/',
                ['image/jpeg', 'image/png', 'image/webp'],
                3 * 1024 * 1024
            );
            if ($uploadResult['success']) {
                $profile_filename = $uploadResult['filename'];
            } else {
                $uploadError = $uploadResult['message'];
            }
        }

        if ($uploadError) {
            $message = "<div class='alert alert-danger'>" . htmlspecialchars($uploadError) . "</div>";
        } else {
            // Build update query depending on whether password was supplied
            if ($password_hash) {
                $update_sql = "UPDATE customers SET name = ?, phone_number = ?, delivery_address = ?, password = ?, profile_pic = ? WHERE customer_id = ?";
                $upd = $conn->prepare($update_sql);
                $upd->bind_param("sssssi", $name, $phone_number, $delivery_address, $password_hash, $profile_filename, $customer_id);
            } else {
                $update_sql = "UPDATE customers SET name = ?, phone_number = ?, delivery_address = ?, profile_pic = ? WHERE customer_id = ?";
                $upd = $conn->prepare($update_sql);
                $upd->bind_param("ssssi", $name, $phone_number, $delivery_address, $profile_filename, $customer_id);
            }

            if ($upd->execute()) {
                $message = "<div class='alert alert-success'>Profile updated successfully!</div>";
                // refresh data
                $stmtProfile->execute();
                $customer = $stmtProfile->get_result()->fetch_assoc();
                $profile_image_url = customer_profile_image_url($customer['profile_pic'] ?? null, $default_profile_pic);
            } else {
                $message = "<div class='alert alert-danger'>Error updating profile. " . htmlspecialchars($upd->error) . "</div>";
            }
            if (isset($upd)) $upd->close();
        }
    }
}

// include header (assumes this outputs header HTML and starts <body>)
include("customer_header.php");
?>

<style>
/* Customer profile page enhancements */
.profile-bg {
    background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 55%, #ffffff 100%);
    padding: 3rem 0;
}
.profile-page {
    background: #ffffff;
    border-radius: 24px;
    padding: 2.75rem;
    box-shadow: 0 24px 45px rgba(15, 23, 42, 0.08);
}
@media (max-width: 991.98px) {
    .profile-page {
        padding: 2rem;
    }
}
@media (max-width: 575.98px) {
    .profile-page {
        padding: 1.5rem;
    }
}
.profile-page .card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}
.profile-page .card-header {
    border-bottom: none;
    font-weight: 600;
    letter-spacing: 0.02em;
}
.profile-page .card-body {
    padding: 1.75rem;
}
.profile-page .alert {
    border-radius: 14px;
}
.profile-page .table thead th {
    border-bottom: none;
    color: #475569;
    font-weight: 600;
    background: transparent;
}
.profile-page .table tbody td {
    vertical-align: middle;
}
.profile-summary-card {
    background: linear-gradient(160deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    border-radius: 22px;
    padding: 2.25rem 1.75rem;
    position: relative;
    overflow: hidden;
}
.profile-summary-card::after {
    content: "";
    position: absolute;
    inset: 25% -30% -40% 60%;
    background: rgba(255, 255, 255, 0.12);
    transform: rotate(25deg);
}
.profile-avatar {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    border: 6px solid rgba(255, 255, 255, 0.28);
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.35);
    object-fit: cover;
    background: rgba(255, 255, 255, 0.15);
}
.profile-summary-card h4 {
    font-weight: 600;
}
.profile-summary-subtitle {
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}
.profile-summary-meta {
    background: rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    padding: 1rem 1.2rem;
    margin-top: 1.5rem;
}
.profile-summary-meta p {
    margin-bottom: 0.5rem;
    font-weight: 500;
}
.profile-summary-meta p:last-child {
    margin-bottom: 0;
}
.profile-summary-meta i {
    width: 1.35rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.85);
}
.profile-summary-meta span {
    color: #ffffff;
    word-break: break-word;
    display: inline-block;
}
.profile-summary-address {
    background: rgba(255, 255, 255, 0.18);
    border-radius: 16px;
    padding: 1.1rem 1.2rem;
    margin-top: 1.5rem;
}
.profile-summary-address h6 {
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-size: 0.75rem;
    margin-bottom: 0.4rem;
    color: rgba(255, 255, 255, 0.85);
}
.profile-summary-address p {
    margin-bottom: 0;
    color: #ffffff;
}
.profile-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    padding: 0.35rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 600;
}
.profile-chip i {
    font-size: 0.85rem;
}
.profile-form-card {
    border-radius: 20px;
}
.profile-form-card .card-body {
    padding: 2rem;
}
.profile-form-card .form-label {
    font-weight: 600;
    color: #1f2937;
}
.profile-form-card .form-control,
.profile-form-card .form-control:focus,
.profile-form-card .form-select:focus {
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: none;
}
.btn-profile-save {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: none;
    color: #ffffff;
    padding: 0.65rem 1.6rem;
    border-radius: 999px;
    font-weight: 600;
    box-shadow: 0 18px 35px rgba(99, 102, 241, 0.35);
}
.btn-profile-save:hover,
.btn-profile-save:focus {
    color: #ffffff;
    filter: brightness(1.05);
}
.profile-section-title {
    font-weight: 700;
    letter-spacing: 0.015em;
    color: #1f2937;
}
.profile-section-card {
    border-radius: 20px;
}
.profile-section-card .card-header {
    color: #ffffff;
}
.profile-section-card .card-header.profile-header-warning {
    background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
}
.profile-section-card .card-header.profile-header-success {
    background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
}
.profile-section-card .card-header.profile-header-primary {
    background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
}
.profile-section-card .card-header.profile-header-info {
    background: linear-gradient(135deg, #38bdf8 0%, #0ea5e9 100%);
}
.profile-section-card .card-header.profile-header-danger {
    background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
}
.recipe-card {
    border: 1px solid rgba(148, 163, 184, 0.18);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
}
.recipe-card .card-body {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
}
.recipe-card .card-title {
    font-weight: 600;
}
.recipe-card-image {
    height: 200px;
    object-fit: cover;
    border-top-left-radius: 18px;
    border-top-right-radius: 18px;
}
.profile-order-card {
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 18px;
    overflow: hidden;
}
.profile-order-card .order-head {
    background: rgba(99, 102, 241, 0.08);
}
.profile-order-badge {
    border-radius: 999px;
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
    font-weight: 600;
}
</style>

<div class="profile-bg">
    <div class="container mt-4 mb-5 profile-page">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
            <h2 class="mb-0 profile-section-title"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h2>
            <span class="profile-chip"><i class="fas fa-shield-alt"></i> Account Center</span>
        </div>

        <?php echo $message; ?>

        <div class="row g-4">
            <!-- Profile Summary -->
            <div class="col-lg-4">
                <div class="profile-summary-card text-center h-100">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>"
                         class="profile-avatar mb-3"
                         alt="Profile picture of <?php echo htmlspecialchars($customer['name']); ?>">
                    <h4 class="mb-1"><?php echo htmlspecialchars($customer['name']); ?></h4>
                    <p class="profile-summary-subtitle mb-0">Customer Account</p>

                    <div class="profile-summary-meta text-start">
                        <p><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($customer['email']); ?></span></p>
                        <p><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($customer['phone_number']); ?></span></p>
                    </div>

                    <?php if (!empty($customer['delivery_address'])): ?>
                        <div class="profile-summary-address text-start">
                            <h6><i class="fas fa-map-marker-alt me-2"></i>Delivery Address</h6>
                            <p class="small mb-0"><?php echo nl2br(htmlspecialchars($customer['delivery_address'])); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="profile-summary-address text-center">
                            <h6><i class="fas fa-map-marker-alt me-2"></i>Delivery Address</h6>
                            <p class="small mb-0">Add your delivery address to speed up checkout.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-lg-8">
                <div class="card shadow profile-form-card h-100">
                    <div class="card-header border-0 bg-white px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="profile-section-title mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Profile</span>
                        <span class="profile-chip"><i class="fas fa-user-cog"></i> Personal Info</span>
                    </div>
                        <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" novalidate>
                            <?php echo csrf_field(); ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($customer['phone_number']); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Delivery Address</label>
                                    <textarea name="delivery_address" class="form-control" rows="3" placeholder="Enter your delivery address"><?php echo htmlspecialchars($customer['delivery_address']); ?></textarea>
                                    <small class="text-muted d-block mt-2">This address will be sent to store owners when you place an order.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter new password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Profile Picture</label>
                                    <input type="file" name="profile_pic" class="form-control" accept="image/*">
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-profile-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipes Created by Customer -->
        <div class="mt-5">
            <h3 class="profile-section-title"><i class="fas fa-utensils me-2 text-warning"></i>My Recipes</h3>

            <!-- Pending Recipes -->
            <div class="card shadow mt-3 profile-section-card">
                <div class="card-header profile-header-warning">
                    <i class="fas fa-hourglass-half me-2"></i>Pending Recipes
            </div>
            <div class="card-body">
                <?php
                $pending_recipes = $conn->query("SELECT * FROM recipe WHERE created_by='" . intval($customer_id) . "' AND status='pending' ORDER BY created_at DESC");
                if ($pending_recipes && $pending_recipes->num_rows > 0) {
                ?>
                    <div class="row g-3">
                        <?php while ($row = $pending_recipes->fetch_assoc()) { ?>
                            <div class="col-md-4">
                                <div class="card recipe-card h-100">
                                        <?php if (!empty($row['recipe_image'])) { ?>
                                            <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="card-img-top recipe-card-image" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                        <?php } else { ?>
                                            <img src="../assets/img/no_image.png" class="card-img-top recipe-card-image" alt="Recipe placeholder image" loading="lazy">
                                        <?php } ?>
                                    <div class="card-body">
                                        <div>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <p class="text-muted small mb-3"><i class="far fa-clock me-1"></i>Submitted <?php echo htmlspecialchars(!empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : 'Pending'); ?></p>
                                            <?php if (!empty($row['bring_your_own'])): ?>
                                                <div class="alert alert-warning bg-opacity-25 py-2 px-3 small text-dark mb-3">
                                                    <strong>Bring Your Own:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($row['bring_your_own'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill align-self-start mt-auto" data-bs-toggle="modal" data-bs-target="#recipeModalPending<?php echo $row['recipe_id']; ?>">View Details</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for Pending Recipe Details -->
                            <div class="modal fade" id="recipeModalPending<?php echo $row['recipe_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-warning text-white border-0">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if (!empty($row['recipe_image'])) { ?>
                                                <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                            <?php } ?>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($row['recipe_description']); ?></p>
                                            <p><strong>Dietary Tag:</strong> <?php echo htmlspecialchars($row['dietary_tag']); ?></p>
                                            <p><strong>Total Cost:</strong> RM <?php echo number_format($row['total_cost'], 2); ?></p>
                                            <p><strong>Price Range:</strong> <?php echo htmlspecialchars($row['price_range']); ?></p>
                                            <p><strong>Created At:</strong> <?php echo htmlspecialchars($row['created_at']); ?></p>
                                            <?php if (!empty($row['bring_your_own'])): ?>
                                                <div class="alert alert-warning bg-opacity-25 py-2 px-3 text-dark">
                                                    <strong>Bring Your Own:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($row['bring_your_own'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                        <p class="text-muted mb-0">No pending recipes.</p>
                    <?php } ?>
            </div>
        </div>

        <!-- Needs Revision -->
        <div class="card shadow mt-4 profile-section-card">
            <div class="card-header profile-header-info">
                <i class="fas fa-edit me-2"></i>Action Required: Needs Revision
            </div>
            <div class="card-body">
                <?php
                $revision_recipes = $conn->query("SELECT * FROM recipe WHERE created_by='" . intval($customer_id) . "' AND status='needs_revision' ORDER BY COALESCE(last_moderated_at, created_at) DESC");
                if ($revision_recipes && $revision_recipes->num_rows > 0) {
                ?>
                    <div class="row g-3">
                        <?php while ($row = $revision_recipes->fetch_assoc()) { ?>
                            <?php
                                $moderatedDate = !empty($row['last_moderated_at']) ? date('d M Y, h:i A', strtotime($row['last_moderated_at'])) : 'Awaiting admin note';
                                $comment = trim($row['last_moderation_comment'] ?? '');
                            ?>
                            <div class="col-md-4">
                                <div class="card recipe-card h-100 border-info">
                                    <?php if (!empty($row['recipe_image'])) { ?>
                                        <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="card-img-top recipe-card-image" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                    <?php } else { ?>
                                        <img src="../assets/img/no_image.png" class="card-img-top recipe-card-image" alt="Recipe placeholder image" loading="lazy">
                                    <?php } ?>
                                    <div class="card-body">
                                        <div>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <p class="text-muted small mb-2"><i class="far fa-clock me-1"></i>Updated <?php echo htmlspecialchars($moderatedDate); ?></p>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-info bg-opacity-50 py-2 px-3 small text-dark mb-3">
                                                    <strong>Admin feedback:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['bring_your_own'])): ?>
                                                <div class="alert alert-warning bg-opacity-25 py-2 px-3 small text-dark mb-3">
                                                    <strong>Bring Your Own:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($row['bring_your_own'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-auto">
                                            <button class="btn btn-sm btn-outline-info rounded-pill" data-bs-toggle="modal" data-bs-target="#recipeModalRevision<?php echo $row['recipe_id']; ?>">View feedback</button>
                                            <a class="btn btn-sm btn-primary rounded-pill" href="edit_recipe.php?recipe_id=<?php echo $row['recipe_id']; ?>">
                                                Edit &amp; resubmit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for Needs Revision -->
                            <div class="modal fade" id="recipeModalRevision<?php echo $row['recipe_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-white border-0">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($row['recipe_name']); ?> &mdash; Admin feedback</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if (!empty($row['recipe_image'])) { ?>
                                                <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                            <?php } ?>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($row['recipe_description']); ?></p>
                                            <p><strong>Dietary Tag:</strong> <?php echo htmlspecialchars($row['dietary_tag']); ?></p>
                                            <p><strong>Total Cost:</strong> RM <?php echo number_format($row['total_cost'], 2); ?></p>
                                            <p><strong>Price Range:</strong> <?php echo htmlspecialchars($row['price_range']); ?></p>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-info bg-opacity-75 py-2 px-3 text-dark">
                                                    <strong>Latest comment from admin:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                    <div class="small text-muted mt-2">Left on <?php echo htmlspecialchars($moderatedDate); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <p class="small text-muted mb-0">Make the requested updates and resubmit so our team can approve your recipe.</p>
                                        </div>
                                        <div class="modal-footer border-0 justify-content-between flex-wrap gap-2">
                                            <a class="btn btn-primary" href="edit_recipe.php?recipe_id=<?php echo $row['recipe_id']; ?>">
                                                Continue editing
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No recipes currently need revisions.</p>
                <?php } ?>
            </div>
        </div>

        <!-- Rejected Recipes -->
        <div class="card shadow mt-4 profile-section-card">
            <div class="card-header profile-header-danger">
                <i class="fas fa-times-circle me-2"></i>Rejected Recipes
            </div>
            <div class="card-body">
                <?php
                $rejected_recipes = $conn->query("SELECT * FROM recipe WHERE created_by='" . intval($customer_id) . "' AND status='rejected' ORDER BY COALESCE(last_moderated_at, created_at) DESC");
                if ($rejected_recipes && $rejected_recipes->num_rows > 0) {
                ?>
                    <div class="row g-3">
                        <?php while ($row = $rejected_recipes->fetch_assoc()) { ?>
                            <?php
                                $moderatedDate = !empty($row['last_moderated_at']) ? date('d M Y, h:i A', strtotime($row['last_moderated_at'])) : 'Decision pending';
                                $comment = trim($row['last_moderation_comment'] ?? '');
                            ?>
                            <div class="col-md-4">
                                <div class="card recipe-card h-100 border-danger">
                                    <?php if (!empty($row['recipe_image'])) { ?>
                                        <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="card-img-top recipe-card-image" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                    <?php } else { ?>
                                        <img src="../assets/img/no_image.png" class="card-img-top recipe-card-image" alt="Recipe placeholder image" loading="lazy">
                                    <?php } ?>
                                    <div class="card-body">
                                        <div>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <p class="text-muted small mb-2"><i class="far fa-clock me-1"></i>Reviewed <?php echo htmlspecialchars($moderatedDate); ?></p>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-danger bg-opacity-75 py-2 px-3 small text-white mb-3">
                                                    <strong>Admin feedback:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-auto">
                                            <button class="btn btn-sm btn-outline-danger rounded-pill" data-bs-toggle="modal" data-bs-target="#recipeModalRejected<?php echo $row['recipe_id']; ?>">View details</button>
                                            <a class="btn btn-sm btn-danger rounded-pill" href="edit_recipe.php?recipe_id=<?php echo $row['recipe_id']; ?>">
                                                Revise &amp; resubmit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for Rejected Recipe -->
                            <div class="modal fade" id="recipeModalRejected<?php echo $row['recipe_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white border-0">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($row['recipe_name']); ?> &mdash; Rejected</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if (!empty($row['recipe_image'])) { ?>
                                                <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                            <?php } ?>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($row['recipe_description']); ?></p>
                                            <p><strong>Dietary Tag:</strong> <?php echo htmlspecialchars($row['dietary_tag']); ?></p>
                                            <p><strong>Total Cost:</strong> RM <?php echo number_format($row['total_cost'], 2); ?></p>
                                            <p><strong>Price Range:</strong> <?php echo htmlspecialchars($row['price_range']); ?></p>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-danger py-2 px-3 text-white">
                                                    <strong>Admin explanation:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                    <div class="small text-light mt-2">Shared on <?php echo htmlspecialchars($moderatedDate); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted small mb-0">Contact support if you need clarification or wish to resubmit with changes.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer border-0 justify-content-between flex-wrap gap-2">
                                            <a class="btn btn-danger" href="edit_recipe.php?recipe_id=<?php echo $row['recipe_id']; ?>">
                                                Update and resubmit
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No recipes have been rejected.</p>
                <?php } ?>
            </div>
        </div>

        <!-- Approved Recipes -->
        <div class="card shadow mt-4 profile-section-card">
            <div class="card-header profile-header-success">
                <i class="fas fa-check-circle me-2"></i>Approved Recipes
            </div>
            <div class="card-body">
                <?php
                $approved_recipes = $conn->query("SELECT * FROM recipe WHERE created_by='" . intval($customer_id) . "' AND status='approved' ORDER BY created_at DESC");
                if ($approved_recipes && $approved_recipes->num_rows > 0) {
                ?>
                    <div class="row g-3">
                        <?php while ($row = $approved_recipes->fetch_assoc()) { ?>
                            <?php
                                $moderatedDate = !empty($row['last_moderated_at']) ? date('d M Y, h:i A', strtotime($row['last_moderated_at'])) : (!empty($row['created_at']) ? date('d M Y, h:i A', strtotime($row['created_at'])) : 'Awaiting processing');
                                $comment = trim($row['last_moderation_comment'] ?? '');
                            ?>
                            <div class="col-md-4">
                                <div class="card recipe-card h-100">
                                        <?php if (!empty($row['recipe_image'])) { ?>
                                            <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="card-img-top recipe-card-image" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                        <?php } else { ?>
                                            <img src="../assets/img/no_image.png" class="card-img-top recipe-card-image" alt="Recipe placeholder image" loading="lazy">
                                        <?php } ?>
                                    <div class="card-body">
                                        <div>
                                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <p class="text-muted small mb-3"><i class="far fa-clock me-1"></i>Approved <?php echo htmlspecialchars($moderatedDate); ?></p>
                                            <?php if (!empty($row['bring_your_own'])): ?>
                                                <div class="alert alert-warning bg-opacity-25 py-2 px-3 small text-dark mb-3">
                                                    <strong>Bring Your Own:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($row['bring_your_own'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-info bg-opacity-50 py-2 px-3 small text-dark mb-3">
                                                    <strong>Admin note:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-success rounded-pill align-self-start mt-auto" data-bs-toggle="modal" data-bs-target="#recipeModalApproved<?php echo $row['recipe_id']; ?>">View Details</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal for Approved Recipe Details -->
                            <div class="modal fade" id="recipeModalApproved<?php echo $row['recipe_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header bg-success text-white border-0">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($row['recipe_name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php if (!empty($row['recipe_image'])) { ?>
                                                <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($row['recipe_name']); ?> image" loading="lazy">
                                            <?php } ?>
                                            <p><strong>Description:</strong> <?php echo htmlspecialchars($row['recipe_description']); ?></p>
                                            <p><strong>Dietary Tag:</strong> <?php echo htmlspecialchars($row['dietary_tag']); ?></p>
                                            <p><strong>Total Cost:</strong> RM <?php echo number_format($row['total_cost'], 2); ?></p>
                                            <p><strong>Price Range:</strong> <?php echo htmlspecialchars($row['price_range']); ?></p>
                                            <p><strong>Created At:</strong> <?php echo htmlspecialchars($row['created_at']); ?></p>
                                            <p><strong>Approved At:</strong> <?php echo htmlspecialchars($moderatedDate); ?></p>
                                            <?php if (!empty($row['bring_your_own'])): ?>
                                                <div class="alert alert-warning bg-opacity-25 py-2 px-3 text-dark">
                                                    <strong>Bring Your Own:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($row['bring_your_own'])); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($comment !== ''): ?>
                                                <div class="alert alert-info bg-opacity-75 py-2 px-3 text-dark">
                                                    <strong>Admin note:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($comment)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php } ?>
                    </div>
                    <?php } else { ?>
                        <p class="text-muted mb-0">No approved recipes yet.</p>
                    <?php } ?>
            </div>
        </div>

    </div>

        <!-- My Orders -->
        <div class="mt-5">
            <h3 class="profile-section-title"><i class="fas fa-receipt me-2 text-primary"></i>My Orders</h3>
            <div class="card shadow mt-3 profile-section-card">
                <div class="card-header profile-header-primary">
                    <i class="fas fa-clock me-2"></i>Recent Orders
                </div>
                <div class="card-body">
                <?php
                $stmtOrders = $conn->prepare("SELECT order_id, total_amount, payment_method, payment_status, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
                if ($stmtOrders) {
                    $stmtOrders->bind_param("i", $customer_id);
                    $stmtOrders->execute();
                    $ordersRes = $stmtOrders->get_result();

                    if ($ordersRes && $ordersRes->num_rows > 0) {
                        while ($order = $ordersRes->fetch_assoc()) {
                            $oid = (int)$order['order_id'];
                            ?>
                            <div class="profile-order-card mb-3">
                                <div class="p-3 order-head d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                    <div>
                                        <strong>Order #<?php echo $oid; ?></strong>
                                        <div class="small text-muted">Placed on <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($order['created_at']))); ?></div>
                                    </div>
                                    <div class="text-md-end d-flex d-md-block flex-wrap gap-2">
                                        <span class="badge bg-info text-dark profile-order-badge"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                                        <span class="badge bg-warning text-dark profile-order-badge"><?php echo htmlspecialchars($order['payment_status']); ?></span>
                                        <span class="badge bg-secondary profile-order-badge"><?php echo htmlspecialchars($order['order_status']); ?></span>
                                        <div class="fw-bold text-primary mt-1">Total: RM <?php echo number_format((float)$order['total_amount'], 2); ?></div>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="text-end">Unit Price (RM)</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Subtotal (RM)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $stmtOrderItems = $conn->prepare("SELECT item_name, unit_price, quantity, subtotal FROM order_items WHERE order_id = ?");
                                                if ($stmtOrderItems) {
                                                    $stmtOrderItems->bind_param("i", $oid);
                                                    $stmtOrderItems->execute();
                                                    $itemsRes = $stmtOrderItems->get_result();
                                                    // Build reorder cart structure
                                                    $reCart = [];
                                                    $reTotal = 0.0;
                                                    if ($itemsRes && $itemsRes->num_rows > 0) {
                                                        while ($itemRow = $itemsRes->fetch_assoc()) {
                                                            $reCart[$itemRow['item_name']] = [
                                                                'qty'   => (int)$itemRow['quantity'],
                                                                'price' => (float)$itemRow['unit_price']
                                                            ];
                                                            $reTotal += (float)$itemRow['subtotal'];
                                                            ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($itemRow['item_name']); ?></td>
                                                                <td class="text-end"><?php echo number_format((float)$itemRow['unit_price'], 2); ?></td>
                                                                <td class="text-end"><?php echo (int)$itemRow['quantity']; ?></td>
                                                                <td class="text-end"><?php echo number_format((float)$itemRow['subtotal'], 2); ?></td>
                                                            </tr>
                                                        <?php }
                                                    } else { ?>
                                                        <tr><td colspan="4" class="text-muted">No items found for this order.</td></tr>
                                                    <?php }
                                                    $stmtOrderItems->close();
                                                } else { ?>
                                                    <tr><td colspan="4" class="text-danger">Unable to load order items.</td></tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php
                                      // Reorder button (prefill cart and go to checkout)
                                      if (!empty($reCart)) {
                                        $reCartJson = urlencode(json_encode($reCart));
                                        $reTotalStr = urlencode('RM' . number_format((float)$reTotal, 2));
                                        echo '<div class="mt-3 d-flex flex-wrap gap-2 justify-content-end">'
                                           . '<a class="btn btn-sm btn-outline-primary rounded-pill" href="checkout.php?cart=' . $reCartJson . '&total=' . $reTotalStr . '"><i class="fas fa-redo"></i> Reorder</a>'
                                           . '<a class="btn btn-sm btn-outline-secondary rounded-pill" href="order_receipt.php?order_id=' . (int)$oid . '"><i class="fas fa-receipt"></i> View Invoice</a>'
                                           . '</div>';
                                      }
                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='text-muted'>You have not placed any orders yet.</div>";
                    }
                } else {
                    echo "<div class='text-danger'>Unable to load orders.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php
// close and include footer if any
if (isset($stmtProfile) && $stmtProfile instanceof mysqli_stmt) { $stmtProfile->close(); }
if (isset($stmtOrders) && $stmtOrders instanceof mysqli_stmt) { $stmtOrders->close(); }
$conn->close();

include("customer_footer.php");
?>



