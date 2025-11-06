<?php
// admin/admin_home.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$feedback = '';
$errorMessage = '';

// Ensure the featured flag exists on recipes so admins can curate the homepage.
$featureColumn = $conn->query("SHOW COLUMNS FROM recipe LIKE 'is_featured'");
if ($featureColumn && $featureColumn->num_rows === 0) {
    $conn->query("ALTER TABLE recipe ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
}
if ($featureColumn instanceof mysqli_result) {
    $featureColumn->free();
}

$maxFeatured = 6;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = isset($_POST['featured_recipes']) ? $_POST['featured_recipes'] : [];
    $selected = array_map('intval', (array)$selected);
    $selected = array_values(array_unique($selected));

    if (count($selected) > $maxFeatured) {
        $selected = array_slice($selected, 0, $maxFeatured);
        $warningExceeded = true;
    } else {
        $warningExceeded = false;
    }

    $conn->begin_transaction();
    try {
        if (!$conn->query("UPDATE recipe SET is_featured = 0")) {
            throw new Exception($conn->error);
        }

        if (!empty($selected)) {
            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            $types = str_repeat('i', count($selected));
            $stmt = $conn->prepare("UPDATE recipe SET is_featured = 1 WHERE recipe_id IN ($placeholders)");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param($types, ...$selected);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        }

        $conn->commit();
        $feedback = 'Featured recipes updated successfully.';
        if (!empty($warningExceeded)) {
            $feedback .= ' Only the first ' . $maxFeatured . ' selections were saved.';
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = 'We could not update the featured recipes. ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch dashboard metrics.
$totalApproved = 0;
$featuredCount = 0;
$creatorsCount = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM recipe WHERE status = 'approved'")) {
    $row = $result->fetch_assoc();
    $totalApproved = (int)$row['total'];
    $result->free();
}
if ($result = $conn->query("SELECT COUNT(*) AS total FROM recipe WHERE status = 'approved' AND is_featured = 1")) {
    $row = $result->fetch_assoc();
    $featuredCount = (int)$row['total'];
    $result->free();
}
if ($result = $conn->query("SELECT COUNT(DISTINCT created_by) AS total FROM recipe WHERE status = 'approved'")) {
    $row = $result->fetch_assoc();
    $creatorsCount = (int)$row['total'];
    $result->free();
}

// Load approved recipes for selection.
$recipes = [];
if ($result = $conn->query("
    SELECT recipe_id, recipe_name, recipe_description, price_range, dietary_tag, recipe_image,
           is_featured, COALESCE(last_moderated_at, created_at) AS updated_at
    FROM recipe
    WHERE status = 'approved'
    ORDER BY is_featured DESC, updated_at DESC
    LIMIT 24
")) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }
    $result->free();
}

include 'admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h1>
            <p class="text-muted mb-0">Curate the customer homepage and stay on top of recipe activity.</p>
        </div>
        <div class="text-lg-end">
            <a href="admin_manage_recipes.php" class="btn btn-primary me-2"><i class="fas fa-utensils me-1"></i> Manage Recipes</a>
            <a href="admin_manage_storeowners.php" class="btn btn-outline-secondary"><i class="fas fa-store me-1"></i> Store Owners</a>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $feedback; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <span class="text-uppercase text-muted small">Approved recipes</span>
                    <h3 class="mt-2 mb-0"><?php echo number_format($totalApproved); ?></h3>
                    <p class="text-muted small mb-0">Live and ready to appear in customer discovery.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <span class="text-uppercase text-muted small">Featured slots used</span>
                    <h3 class="mt-2 mb-0"><?php echo number_format($featuredCount); ?> / <?php echo $maxFeatured; ?></h3>
                    <p class="text-muted small mb-0">Select up to <?php echo $maxFeatured; ?> recipes for the customer homepage.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <span class="text-uppercase text-muted small">Active creators</span>
                    <h3 class="mt-2 mb-0"><?php echo number_format($creatorsCount); ?></h3>
                    <p class="text-muted small mb-0">Unique customers with approved recipes.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title mb-2"><i class="fas fa-star me-2 text-warning"></i>Featured Recipe Line-up</h5>
            <p class="text-muted small mb-4">Pick the dishes that should appear on <strong>Customer Home</strong>. You can feature up to <?php echo $maxFeatured; ?> recipes at any time.</p>

            <?php if (empty($recipes)): ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>No approved recipes found yet. Approve a recipe first, then return to feature it.
                </div>
            <?php else: ?>
                <form method="POST" class="featured-recipes-form">
                    <div class="row g-3">
                        <?php foreach ($recipes as $recipe): ?>
                            <?php
                                $isFeatured = (int)$recipe['is_featured'] === 1;
                                $image = $recipe['recipe_image'] ?? '';
                                if ($image === '') {
                                    $imageSrc = '../assets/img/no_image.png';
                                } elseif (preg_match('/^https?:\/\//i', $image)) {
                                    $imageSrc = $image;
                                } elseif (str_starts_with($image, '../')) {
                                    $imageSrc = $image;
                                } elseif (str_starts_with($image, 'uploads/')) {
                                    $imageSrc = '../' . $image;
                                } else {
                                    $imageSrc = '../uploads/recipes/' . $image;
                                }
                                $desc = trim($recipe['recipe_description'] ?? '');
                                if (mb_strlen($desc) > 120) {
                                    $desc = mb_substr($desc, 0, 117) . '...';
                                }
                            ?>
                            <div class="col-md-6 col-xl-4">
                                <label class="card h-100 shadow-sm border <?php echo $isFeatured ? 'border-warning' : 'border-light'; ?> recipe-card-option">
                                    <input
                                        type="checkbox"
                                        class="form-check-input visually-hidden recipe-checkbox"
                                        name="featured_recipes[]"
                                        value="<?php echo (int)$recipe['recipe_id']; ?>"
                                        <?php echo $isFeatured ? 'checked' : ''; ?>
                                    >
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                                        <?php if ($isFeatured): ?>
                                            <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2"><i class="fas fa-star"></i> Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                                        <p class="card-text text-muted small mb-3"><?php echo $desc !== '' ? htmlspecialchars($desc) : 'No description provided.'; ?></p>
                                        <div class="d-flex flex-wrap gap-2 small">
                                            <?php if (!empty($recipe['price_range'])): ?>
                                                <span class="badge rounded-pill bg-light text-dark"><i class="fas fa-coins me-1"></i><?php echo htmlspecialchars($recipe['price_range']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($recipe['dietary_tag'])): ?>
                                                <span class="badge rounded-pill bg-success-subtle text-success"><i class="fas fa-seedling me-1"></i><?php echo htmlspecialchars($recipe['dietary_tag']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
                        <span class="text-muted small"><i class="fas fa-lightbulb me-1 text-warning"></i>Select up to <?php echo $maxFeatured; ?> recipes. Selected cards highlight in gold.</span>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save featured recipes</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const maxFeatured = <?php echo (int)$maxFeatured; ?>;
    const checkboxes = document.querySelectorAll('.recipe-checkbox');

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            if (checked.length > maxFeatured) {
                checkbox.checked = false;
                const toast = document.createElement('div');
                toast.className = 'toast align-items-center text-bg-warning border-0 show position-fixed bottom-0 end-0 m-4';
                toast.setAttribute('role', 'alert');
                toast.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="fas fa-info-circle me-2"></i>You can only feature up to ' + maxFeatured + ' recipes.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3500);
            }

            const card = checkbox.closest('.recipe-card-option');
            if (checkbox.checked) {
                card.classList.remove('border-light');
                card.classList.add('border-warning');
                if (!card.querySelector('.badge.text-dark')) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-warning text-dark position-absolute top-0 end-0 m-2';
                    badge.innerHTML = '<i class="fas fa-star"></i> Featured';
                    card.querySelector('.position-relative').appendChild(badge);
                }
            } else {
                card.classList.remove('border-warning');
                card.classList.add('border-light');
                const badge = card.querySelector('.badge.text-dark');
                if (badge) {
                    badge.remove();
                }
            }
        });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
