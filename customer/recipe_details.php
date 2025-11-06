<?php
// recipe_details.php
// Do NOT output anything before potential redirects
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/security.php';

$fatalError = '';
if (!isset($_GET['recipe_id']) || empty($_GET['recipe_id'])) {
    $fatalError = 'Invalid recipe selected.';
}

$recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;

// Fetch recipe details
$hasByoColumn = false;
if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
    if ($colCheck->num_rows > 0) {
        $hasByoColumn = true;
    }
    $colCheck->close();
}

$sql = "SELECT recipe_id, recipe_name, recipe_description, steps, total_cost, dietary_tag, price_range, recipe_image, created_at"
        . ($hasByoColumn ? ", bring_your_own" : "") .
        " FROM recipe 
        WHERE recipe_id = ? AND status = 'approved'";
$recipe = null;
if ($fatalError === '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $fatalError = 'Recipe not found or not available.';
    } else {
        $recipe = $result->fetch_assoc();
        if ($hasByoColumn && !isset($recipe['bring_your_own'])) {
            $recipe['bring_your_own'] = '';
        }
    }
}

$savedRecipe = false;
if (isset($_SESSION['customer_id']) && $fatalError === '') {
    $conn->query("CREATE TABLE IF NOT EXISTS saved_recipes (
        customer_id INT NOT NULL,
        recipe_id INT NOT NULL,
        saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id, recipe_id),
        CONSTRAINT fk_saved_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_saved_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(recipe_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $checkSaved = $conn->prepare('SELECT 1 FROM saved_recipes WHERE customer_id = ? AND recipe_id = ? LIMIT 1');
    if ($checkSaved) {
        $cidCheck = intval($_SESSION['customer_id']);
        $checkSaved->bind_param('ii', $cidCheck, $recipe_id);
        $checkSaved->execute();
        $savedRecipe = $checkSaved->get_result()->num_rows > 0;
        $checkSaved->close();
    }
}
/* -------------------------
   Handle review (rating + comment) POST
   ------------------------- */
$message = '';
if ($fatalError === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit'])) {
    // must be logged in as customer
    if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])) {
        $message = "<div class='alert alert-warning'>Please <a href='customer_login.php'>login</a> to leave a review.</div>";
    } else {
        $customer_id = intval($_SESSION['customer_id']);
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        // validation
        if ($rating < 1 || $rating > 5) {
            $message = "<div class='alert alert-danger'>Please provide a rating between 1 and 5 stars.</div>";
        } elseif (strlen($comment) > 2000) {
            $message = "<div class='alert alert-danger'>Comment is too long (max 2000 characters).</div>";
        } else {
            // Check if user already has a review for this recipe
            $checkStmt = $conn->prepare("SELECT review_id FROM recipe_reviews WHERE recipe_id = ? AND customer_id = ?");
            $checkStmt->bind_param("ii", $recipe_id, $customer_id);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();

            if ($checkRes && $checkRes->num_rows > 0) {
                // update
                $row = $checkRes->fetch_assoc();
                $review_id = intval($row['review_id']);
                $upd = $conn->prepare("UPDATE recipe_reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE review_id = ?");
                $upd->bind_param("isi", $rating, $comment, $review_id);
                if ($upd->execute()) {
                    // redirect to avoid resubmit (safe: no output yet)
                    header("Location: recipe_details.php?recipe_id=" . $recipe_id . "#reviews");
                    exit;
                } else {
                    $message = "<div class='alert alert-danger'>Error updating review: " . htmlspecialchars($upd->error) . "</div>";
                }
                $upd->close();
            } else {
                // insert
                $ins = $conn->prepare("INSERT INTO recipe_reviews (recipe_id, customer_id, rating, comment) VALUES (?, ?, ?, ?)");
                $ins->bind_param("iiis", $recipe_id, $customer_id, $rating, $comment);
                if ($ins->execute()) {
                    header("Location: recipe_details.php?recipe_id=" . $recipe_id . "#reviews");
                    exit;
                } else {
                    $message = "<div class='alert alert-danger'>Error saving review: " . htmlspecialchars($ins->error) . "</div>";
                }
                $ins->close();
            }

            $checkStmt->close();
        }
    }
}

// Only now include header (starts output)
$heroImage = '../assets/img/no_image.png';
if (!empty($recipe['recipe_image'])) {
    $rawImage = trim($recipe['recipe_image']);
    if (preg_match('/^https?:\/\//i', $rawImage)) {
        $heroImage = $rawImage;
    } elseif (str_starts_with($rawImage, '../')) {
        $heroImage = $rawImage;
    } elseif (str_starts_with($rawImage, 'uploads/')) {
        $heroImage = '../' . $rawImage;
    } else {
        $heroImage = '../uploads/recipes/' . ltrim($rawImage, '/');
    }
}
$priceRangeLabel = !empty($recipe['price_range']) ? $recipe['price_range'] : 'Pricing TBD';
$createdLabel = !empty($recipe['created_at']) ? date('d M Y', strtotime($recipe['created_at'])) : 'Recently added';
$fullDescription = trim($recipe['recipe_description'] ?? '');
$shortDescription = $fullDescription;
$stepsRaw = trim((string)($recipe['steps'] ?? ''));
$stepsList = [];
if ($stepsRaw !== '') {
    $normalizedSteps = preg_replace("/\r\n?/", "\n", $stepsRaw);
    $chunks = preg_split('/\n+/', $normalizedSteps);
    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk !== '') {
            $stepsList[] = $chunk;
        }
    }
}
if ($fullDescription !== '') {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($fullDescription) > 220) {
            $shortDescription = mb_substr($fullDescription, 0, 220) . '...';
        }
    } else {
        if (strlen($fullDescription) > 220) {
            $shortDescription = substr($fullDescription, 0, 220) . '...';
        }
    }
}
$csrfToken = null;
if (function_exists('csrf_token')) {
    $csrfToken = csrf_token();
} else {
    $csrfToken = bin2hex(random_bytes(16));
}

include 'customer_header.php';

// If a fatal error occurred, render message and exit cleanly
if ($fatalError !== '') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>" . htmlspecialchars($fatalError) . "</div></div>";
    include 'customer_footer.php';
    exit;
}

/* -------------------------
   Fetch ingredients
   ------------------------- */
$ingredients_sql = "
    SELECT gi.item_name, gi.unit, gi.brand, gi.price_per_unit, gi.item_image, ri.quantity
    FROM recipe_ingredients ri
    JOIN groceryitem gi ON ri.item_id = gi.item_id
    WHERE ri.recipe_id = ?
";
$stmt2 = $conn->prepare($ingredients_sql);
$stmt2->bind_param("i", $recipe_id);
$stmt2->execute();
$ingredients_result = $stmt2->get_result();

/* -------------------------
   Fetch reviews summary & reviews list
   ------------------------- */
// average and count
$avgStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM recipe_reviews WHERE recipe_id = ?");
$avgStmt->bind_param("i", $recipe_id);
$avgStmt->execute();
$avgRes = $avgStmt->get_result()->fetch_assoc();
$avg_rating = $avgRes['avg_rating'] !== null ? floatval($avgRes['avg_rating']) : 0;
$total_reviews = intval($avgRes['total_reviews']);
$avgStmt->close();
$ratingStars = '';
$roundedAvg = round($avg_rating * 2) / 2;
for ($i = 1; $i <= 5; $i++) {
    if ($i <= floor($roundedAvg)) {
        $ratingStars .= '<i class="fas fa-star"></i>';
    } elseif ($i - 0.5 == $roundedAvg) {
        $ratingStars .= '<i class="fas fa-star-half-alt"></i>';
    } else {
        $ratingStars .= '<i class="far fa-star"></i>';
    }
}

// current user's review (if logged in)
$user_review = null;
if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
    $cid = intval($_SESSION['customer_id']);
    $myStmt = $conn->prepare("SELECT review_id, rating, comment FROM recipe_reviews WHERE recipe_id = ? AND customer_id = ? LIMIT 1");
    $myStmt->bind_param("ii", $recipe_id, $cid);
    $myStmt->execute();
    $myRes = $myStmt->get_result();
    if ($myRes && $myRes->num_rows > 0) {
        $user_review = $myRes->fetch_assoc();
    }
    $myStmt->close();
}

// fetch all reviews with customer info
$reviews = [];
$revStmt = $conn->prepare("SELECT rr.review_id, rr.rating, rr.comment, rr.created_at, c.customer_id, c.name AS customer_name, c.profile_pic
                           FROM recipe_reviews rr
                           JOIN customers c ON rr.customer_id = c.customer_id
                           WHERE rr.recipe_id = ?
                           ORDER BY rr.created_at DESC");
$revStmt->bind_param("i", $recipe_id);
$revStmt->execute();
$revRes = $revStmt->get_result();
while ($r = $revRes->fetch_assoc()) {
    $reviews[] = $r;
}
$revStmt->close();

$friends = [];
$currentUserId = $_SESSION['customer_id'] ?? null;
if ($currentUserId) {
    $friendStmt = $conn->prepare(
        "SELECT CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END AS friend_id,
                c.name
         FROM friends f
         JOIN customers c ON c.customer_id = CASE WHEN f.requester_id = ? THEN f.addressee_id ELSE f.requester_id END
         WHERE f.status = 'accepted' AND (f.requester_id = ? OR f.addressee_id = ?)
         ORDER BY c.name"
    );
    if ($friendStmt) {
        $friendStmt->bind_param('iiii', $currentUserId, $currentUserId, $currentUserId, $currentUserId);
        $friendStmt->execute();
        $resFriend = $friendStmt->get_result();
        while ($rowFriend = $resFriend->fetch_assoc()) {
            $friends[] = $rowFriend;
        }
        $friendStmt->close();
    }
}
?>

<style>
  .recipe-hero {
    background: linear-gradient(135deg, #fff7f0 0%, #f3f6ff 100%);
    padding: 4rem 0 3rem;
  }
  .recipe-hero__image {
    border-radius: 28px;
    overflow: hidden;
  }
  .recipe-hero__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 28px;
    box-shadow: 0 25px 45px rgba(15, 23, 42, 0.22);
  }
  .recipe-hero__content {
    background: #ffffff;
    border-radius: 28px;
    box-shadow: 0 35px 65px rgba(15, 23, 42, 0.18);
  }
  .recipe-hero__title {
    font-weight: 700;
    font-size: 2.4rem;
    line-height: 1.2;
  }
  .recipe-hero__stars i {
    color: #f59e0b;
    font-size: 1.2rem;
  }
  .recipe-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    background: #eef2ff;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.01em;
  }
  .recipe-chip--amber {
    background: rgba(255, 145, 77, 0.18);
    color: #b45309;
  }
  .recipe-chip--green {
    background: rgba(52, 211, 153, 0.16);
    color: #0f5132;
  }
  .recipe-hero__intro {
    font-size: 1.05rem;
  }
  .recipe-hero__actions .btn {
    border-radius: 999px;
  }
  .recipe-main {
    background: #f8fafc;
  }
  .recipe-section {
    border-radius: 24px;
  }
  .recipe-section .card-body {
    padding: 2.5rem 2.25rem;
  }
  .recipe-section-title {
    font-size: 1.35rem;
    font-weight: 700;
  }
  .recipe-byo {
    border-radius: 18px;
    background: rgba(255, 196, 104, 0.22);
    border: none;
    color: #78350f;
  }
  .recipe-fact {
    border-radius: 18px;
    padding: 1.1rem 1.25rem;
    background: #111827;
    color: #ffffff;
    height: 100%;
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.16);
  }
  .recipe-fact--secondary {
    background: #e0f2fe;
    color: #0c4a6e;
    box-shadow: none;
  }
  .recipe-step-list {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
    counter-reset: recipe-step;
  }
  .recipe-step-item {
    display: flex;
    gap: 1.4rem;
    align-items: flex-start;
    padding: 1.1rem 1.4rem;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 20px 38px rgba(15, 23, 42, 0.08);
  }
  .recipe-step-number {
    flex: 0 0 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(67, 97, 238, 0.12);
    color: #1d4ed8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: inset 0 0 0 1px rgba(67, 97, 238, 0.25);
  }
  .recipe-step-body {
    flex: 1 1 auto;
    color: #1f2937;
    line-height: 1.6;
  }
  .recipe-ingredient-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .recipe-ingredient-item {
    display: flex;
    gap: 1.25rem;
    align-items: center;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 1.1rem 1.4rem;
    background: #ffffff;
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
  }
  .recipe-ingredient-thumb {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    object-fit: cover;
  }
  .recipe-ingredient-meta {
    font-size: 0.85rem;
    color: #64748b;
  }
  .recipe-review-form {
    border-radius: 20px;
    background: #f1f5f9;
  }
  .recipe-review-stars i {
    color: #f59e0b;
  }
  .recipe-review-card {
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 22px 45px rgba(15, 23, 42, 0.12);
  }
  .recipe-review-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
  }
  @media (max-width: 767.98px) {
    .recipe-hero {
      padding: 3rem 0 2rem;
    }
    .recipe-hero__title {
      font-size: 1.85rem;
    }
    .recipe-section .card-body {
      padding: 1.75rem;
    }
    .recipe-ingredient-item {
      flex-direction: column;
      align-items: flex-start;
    }
    .recipe-ingredient-thumb {
      width: 100%;
      height: 200px;
    }
  }
</style>

<section class="recipe-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="recipe-hero__image">
          <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
        </div>
      </div>
      <div class="col-lg-6">
        <div class="recipe-hero__content p-4 p-lg-5">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="recipe-chip recipe-chip--amber"><i class="fas fa-coins me-1"></i><?php echo htmlspecialchars($priceRangeLabel); ?></span>
            <?php if (!empty($recipe['dietary_tag'])): ?>
              <span class="recipe-chip recipe-chip--green"><i class="fas fa-seedling me-1"></i><?php echo htmlspecialchars($recipe['dietary_tag']); ?></span>
            <?php endif; ?>
            <span class="recipe-chip"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($createdLabel); ?></span>
          </div>
          <h1 class="recipe-hero__title mb-3"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h1>
          <div class="recipe-hero__rating d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="recipe-hero__stars"><?php echo $ratingStars; ?></span>
            <span class="recipe-hero__score fw-semibold">
              <?php echo $avg_rating ? number_format($avg_rating, 1) : '0.0'; ?> / 5
              <small class="text-muted">(<?php echo $total_reviews; ?> review<?php echo $total_reviews !== 1 ? 's' : ''; ?>)</small>
            </span>
          </div>
          <?php if ($shortDescription !== ''): ?>
            <p class="recipe-hero__intro text-muted mb-4"><?php echo htmlspecialchars($shortDescription); ?></p>
          <?php endif; ?>
          <div class="recipe-hero__actions d-flex flex-wrap gap-2">
            <a href="generate_shopping_list.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="btn btn-success btn-lg px-4 shadow-sm" id="openShoppingList">
              <i class="fas fa-list-ul me-2"></i>Shopping list
            </a>
            <?php if (isset($_SESSION['customer_id'])): ?>
              <form method="post" action="save_recipe_toggle.php" class="d-inline">
                <input type="hidden" name="recipe_id" value="<?php echo (int)$recipe_id; ?>">
                <input type="hidden" name="action" value="<?php echo $savedRecipe ? 'remove' : 'save'; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                <button type="submit" class="btn btn-outline-primary btn-lg px-4">
                  <i class="fas <?php echo $savedRecipe ? 'fa-heart-broken' : 'fa-heart'; ?> me-2"></i>
                  <?php echo $savedRecipe ? 'Remove from saved' : 'Save recipe'; ?>
                </button>
              </form>
            <?php endif; ?>
            <?php if ($currentUserId): ?>
              <?php if ($friends): ?>
                <button type="button" class="btn btn-outline-primary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#shareRecipeModal">
                  <i class="fas fa-share-alt me-2"></i>Share
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-toggle="tooltip" data-bs-title="Add friends to share recipes" disabled>
                  <i class="fas fa-share-alt me-2"></i>Share
                </button>
              <?php endif; ?>
            <?php endif; ?>
            <a href="all_recipes.php" class="btn btn-outline-secondary btn-lg px-4">
              <i class="fas fa-arrow-left me-2"></i>Back to recipes
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="recipe-main py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-8 order-2 order-lg-1">
        <div class="card recipe-section border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <h2 class="recipe-section-title mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>About this recipe</h2>
            </div>
            <?php if ($fullDescription !== ''): ?>
              <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($fullDescription)); ?></p>
            <?php else: ?>
              <p class="text-muted mb-0">The creator has not added a description yet.</p>
            <?php endif; ?>
            <?php if ($hasByoColumn && !empty($recipe['bring_your_own'])): ?>
              <div class="recipe-byo alert mt-4 mb-0">
                <i class="fas fa-exclamation-circle me-2 text-warning"></i>
                <strong>Bring your own:</strong> <?php echo nl2br(htmlspecialchars($recipe['bring_your_own'])); ?>
              </div>
            <?php endif; ?>
            <div class="row g-3 mt-4">
              <div class="col-sm-6">
                <div class="recipe-fact">
                  <span class="text-uppercase small text-white-50 d-block">Estimated cost</span>
                  <div class="fs-4 fw-semibold mt-1">RM <?php echo number_format($recipe['total_cost'], 2); ?></div>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="recipe-fact recipe-fact--secondary">
                  <span class="text-uppercase small d-block">Price guide</span>
                  <div class="fs-5 fw-semibold mt-1"><?php echo htmlspecialchars($priceRangeLabel); ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card recipe-section border-0 shadow-sm mt-4" id="steps">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="recipe-section-title mb-0"><i class="fas fa-utensils me-2 text-danger"></i>Cooking steps</h2>
              <?php if (!empty($stepsList)): ?>
                <span class="badge bg-light text-dark rounded-pill"><?php echo count($stepsList); ?> step<?php echo count($stepsList) === 1 ? '' : 's'; ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($stepsList)): ?>
              <div class="recipe-step-list">
                <?php foreach ($stepsList as $index => $step): ?>
                  <div class="recipe-step-item">
                    <div class="recipe-step-number"><?php echo $index + 1; ?></div>
                    <div class="recipe-step-body"><?php echo nl2br(htmlspecialchars($step)); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted mb-0">The creator has not shared step-by-step instructions yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card recipe-section border-0 shadow-sm mt-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h2 class="recipe-section-title mb-0"><i class="fas fa-carrot me-2 text-success"></i>Ingredients</h2>
              <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
                <span class="badge bg-light text-dark rounded-pill"><?php echo $ingredients_result->num_rows; ?> items</span>
              <?php endif; ?>
            </div>
            <?php if ($ingredients_result && $ingredients_result->num_rows > 0): ?>
              <div class="recipe-ingredient-list">
                <?php while ($ingredient = $ingredients_result->fetch_assoc()): ?>
                  <?php
                    $ingredientImage = '../assets/img/no_image.png';
                    if (!empty($ingredient['item_image'])) {
                        $ingImg = trim($ingredient['item_image']);
                        if (preg_match('/^https?:\/\//i', $ingImg)) {
                            $ingredientImage = $ingImg;
                        } elseif (str_starts_with($ingImg, '../')) {
                            $ingredientImage = $ingImg;
                        } elseif (str_starts_with($ingImg, 'uploads/')) {
                            $ingredientImage = '../' . $ingImg;
                        } else {
                            $ingredientImage = '../uploads/items/' . ltrim($ingImg, '/');
                        }
                    }
                  ?>
                  <div class="recipe-ingredient-item" data-item-name="<?php echo htmlspecialchars($ingredient['item_name']); ?>" data-item-price="<?php echo htmlspecialchars($ingredient['price_per_unit']); ?>" data-item-qty="<?php echo (int)$ingredient['quantity']; ?>">
                    <img src="<?php echo htmlspecialchars($ingredientImage); ?>" alt="<?php echo htmlspecialchars($ingredient['item_name']); ?>" class="recipe-ingredient-thumb">
                    <div class="flex-grow-1">
                      <h6 class="mb-1"><?php echo htmlspecialchars($ingredient['item_name']); ?></h6>
                      <div class="recipe-ingredient-meta">
                        <?php if (!empty($ingredient['brand'])): ?>
                          <span class="me-2"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($ingredient['brand']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-balance-scale me-1"></i><?php echo htmlspecialchars($ingredient['unit']); ?></span>
                      </div>
                    </div>
                    <div class="text-end">
                      <div class="fw-semibold mb-1">x<?php echo htmlspecialchars($ingredient['quantity']); ?></div>
                      <div class="text-muted small">RM <?php echo number_format($ingredient['price_per_unit'], 2); ?> / <?php echo htmlspecialchars($ingredient['unit']); ?></div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
              <button type="button" id="checkoutIngredientsBtn" class="btn btn-success btn-lg w-100 mt-4">
                <i class="fas fa-shopping-basket me-2"></i>Checkout ingredients
              </button>
            <?php else: ?>
              <p class="text-muted mb-0">No ingredients listed for this recipe.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card recipe-section border-0 shadow-sm mt-4" id="reviews">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
              <div>
                <h2 class="recipe-section-title mb-1"><i class="fas fa-comments me-2 text-primary"></i>Community reviews</h2>
                <p class="text-muted mb-0">Share your tips or read what others loved about this dish.</p>
              </div>
              <span class="badge bg-light text-dark rounded-pill">Avg <?php echo $avg_rating ? number_format($avg_rating, 1) : '0.0'; ?>/5</span>
            </div>
            <?php if (!empty($message)) echo $message; ?>
            <div class="recipe-review-form p-3 p-md-4 mb-4">
              <?php if (!isset($_SESSION['customer_id']) || empty($_SESSION['customer_id'])): ?>
                <p class="mb-0">Please <a href="customer_login.php">log in</a> to leave a review.</p>
              <?php else: ?>
                <form method="POST" action="">
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Your rating</label>
                    <div id="starWrap" class="mb-2" style="font-size:1.4rem;">
                      <?php
                        $myRating = $user_review['rating'] ?? 0;
                        for ($i=1;$i<=5;$i++) {
                            $class = ($i <= $myRating) ? 'fas fa-star text-warning star selected' : 'far fa-star text-warning star';
                            echo "<i data-value='{$i}' class='{$class}' style='cursor:pointer;margin-right:6px;'></i>";
                        }
                      ?>
                    </div>
                    <input type="hidden" id="ratingInput" name="rating" value="<?php echo intval($myRating); ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label fw-semibold">Your comment</label>
                    <textarea name="comment" class="form-control" rows="3" maxlength="2000" placeholder="What stood out for you?"><?php echo isset($user_review['comment']) ? htmlspecialchars($user_review['comment']) : ''; ?></textarea>
                  </div>
                  <div class="text-end">
                    <button type="submit" name="review_submit" class="btn btn-primary">
                      <?php echo $user_review ? 'Update review' : 'Submit review'; ?>
                    </button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
            <div class="recipe-review-list">
              <?php if (count($reviews) === 0): ?>
                <p class="text-muted mb-0">No reviews yet. Be the first to share your experience!</p>
              <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                  <?php
                    $pic = isset($r['profile_pic']) ? trim((string)$r['profile_pic']) : '';
                    $fname = $pic !== '' ? basename($pic) : '';
                    $fsUserUploads = __DIR__ . '/uploads/' . $fname;
                    $fsCustomers   = __DIR__ . '/../uploads/customers/' . $fname;
                    $fsUploadsRoot = __DIR__ . '/../uploads/' . $fname;
                    if ($fname && file_exists($fsUserUploads)) {
                      $avatar = 'uploads/' . htmlspecialchars($fname);
                    } elseif ($fname && file_exists($fsCustomers)) {
                      $avatar = '../uploads/customers/' . htmlspecialchars($fname);
                    } elseif ($fname && file_exists($fsUploadsRoot)) {
                      $avatar = '../uploads/' . htmlspecialchars($fname);
                    } else {
                      $avatar = '../assets/img/default_profile.png';
                    }
                  ?>
                  <div class="recipe-review-card p-3 p-md-4 mb-3">
                    <div class="d-flex gap-3 align-items-start">
                      <img src="<?php echo $avatar; ?>" alt="Reviewer avatar" class="recipe-review-avatar">
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                          <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($r['customer_name']); ?></h6>
                            <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?></small>
                          </div>
                          <div class="recipe-review-stars text-warning">
                            <?php
                              $rt = intval($r['rating']);
                              for ($s=1;$s<=5;$s++) {
                                  echo $s <= $rt ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                              }
                            ?>
                          </div>
                        </div>
                        <?php if (!empty($r['comment'])): ?>
                          <p class="mt-3 mb-0 text-muted"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 order-1 order-lg-2">
        <div class="card recipe-section border-0 shadow-sm mb-4">
          <div class="card-body text-center">
            <h2 class="recipe-section-title mb-3"><i class="fas fa-gauge me-2 text-info"></i>Quick snapshot</h2>
            <div class="row g-3">
              <div class="col-6">
                <div class="recipe-fact recipe-fact--secondary h-100">
                  <span class="text-uppercase small d-block">Total cost</span>
                  <div class="fs-5 fw-semibold mt-1">RM <?php echo number_format($recipe['total_cost'], 2); ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="recipe-fact h-100">
                  <span class="text-uppercase small text-white-50 d-block">Reviews</span>
                  <div class="fs-4 fw-semibold mt-1"><?php echo $total_reviews; ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php if ($friends): ?>
        <div class="card recipe-section border-0 shadow-sm mb-4">
          <div class="card-body">
            <h2 class="recipe-section-title mb-3"><i class="fas fa-user-friends me-2 text-primary"></i>Share with friends</h2>
            <p class="text-muted mb-3">Send this recipe to your GroceryGenie friends directly from here.</p>
            <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#shareRecipeModal">
              <i class="fas fa-share-alt me-2"></i>Share now
            </button>
          </div>
        </div>
        <?php endif; ?>
        <div class="card recipe-section border-0 shadow-sm">
          <div class="card-body">
            <h2 class="recipe-section-title mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Tips</h2>
            <p class="text-muted mb-0">Add this recipe to your weekly plan, generate a shopping list with one tap, and keep notes in the review section to remember your tweaks.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Share Recipe Modal -->
<div class="modal fade" id="shareRecipeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-share-alt"></i> Share Recipe</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($friends): ?>
        <div id="shareAlert" class="alert d-none" role="alert"></div>
        <form id="shareForm">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
          <div class="mb-3">
            <label class="form-label">Select Friend</label>
            <select name="friend_id" class="form-select" required>
              <option value="">-- Choose friend --</option>
              <?php foreach ($friends as $f): ?>
                <option value="<?php echo (int)$f['friend_id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Message (optional)</label>
            <textarea name="note" class="form-control" rows="2" placeholder="Add a short note"></textarea>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Send Notification</button>
            <button type="button" id="shareToChatBtn" class="btn btn-outline-primary">Share via Chat</button>
          </div>
        </form>
        <?php else: ?>
        <div class="alert alert-info mb-0">You need at least one friend to share recipes.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- JS to make star rating interactive and auto-add ingredients to cart -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
  const stars = document.querySelectorAll('#starWrap .star');
  const ratingInput = document.getElementById('ratingInput');

  function setStars(value) {
    stars.forEach(s => {
      const v = parseInt(s.getAttribute('data-value'));
      if (v <= value) {
        s.classList.remove('far');
        s.classList.add('fas', 'selected');
      } else {
        s.classList.remove('fas', 'selected');
        s.classList.add('far');
      }
    });
    ratingInput.value = value;
  }

  stars.forEach(star => {
    star.addEventListener('click', function() {
      const v = parseInt(this.getAttribute('data-value'));
      setStars(v);
    });
  });

  // keep initial state from DB
  setStars(parseInt(ratingInput.value) || 0);


    const checkoutBtn = document.getElementById('checkoutIngredientsBtn');
    if (checkoutBtn) {
      checkoutBtn.addEventListener('click', function() {
        const items = document.querySelectorAll('.recipe-ingredient-item[data-item-name]');
        const cart = {};
        let total = 0;
        items.forEach(li => {
          const name = li.getAttribute('data-item-name');
          const price = parseFloat(li.getAttribute('data-item-price')) || 0;
          const qty = parseInt(li.getAttribute('data-item-qty')) || 0;
          if (name && qty > 0 && price >= 0) {
            cart[name] = { qty: qty, price: price };
            total += qty * price;
          }
        });
        if (Object.keys(cart).length === 0) {
          alert('No ingredients to add to cart.');
          return;
        }
        const cartParam = encodeURIComponent(JSON.stringify(cart));
        const totalParam = encodeURIComponent('RM' + total.toFixed(2));
        window.location.href = 'checkout.php?cart=' + cartParam + '&total=' + totalParam;
      });
    }

    const openShoppingListBtn = document.getElementById('openShoppingList');
    function buildShoppingListText() {
      const items = document.querySelectorAll('.recipe-ingredient-item[data-item-name]');
      const lines = [];
      let total = 0;
      lines.push('Shopping List for: ' + (<?php echo json_encode($recipe['recipe_name']); ?>));
      lines.push('');
      items.forEach(li => {
        const name = li.getAttribute('data-item-name');
        const price = parseFloat(li.getAttribute('data-item-price')) || 0;
        const qty = parseInt(li.getAttribute('data-item-qty')) || 0;
        const subtotal = qty * price;
        total += subtotal;
        lines.push(`- ${name} x ${qty} @ RM${price.toFixed(2)} each  (RM${subtotal.toFixed(2)})`);
      });
      lines.push('');
      lines.push('Estimated Total: RM' + total.toFixed(2));
      return lines.join('\n');
    }

    if (openShoppingListBtn) {
      openShoppingListBtn.addEventListener('click', function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const txt = buildShoppingListText();
        const ta = document.getElementById('shoppingListText');
        if (ta) { ta.value = txt; }
        if (window.bootstrap) {
          const modal = new bootstrap.Modal(document.getElementById('shoppingListModal'));
          modal.show();
        } else {
          alert(txt);
        }
      });
    }

    const copyBtn = document.getElementById('copyShoppingList');
    if (copyBtn) {
      copyBtn.addEventListener('click', async function() {
        const ta = document.getElementById('shoppingListText');
        try {
          await navigator.clipboard.writeText(ta.value || '');
          this.innerText = 'Copied!';
          setTimeout(()=>{ this.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1200);
        } catch(_) {
          ta.select();
          document.execCommand('copy');
        }
      });
    }

    const printBtn = document.getElementById('printShoppingList');
    if (printBtn) {
      printBtn.addEventListener('click', function() {
        const txt = document.getElementById('shoppingListText').value || '';
        const w = window.open('', '_blank');
        if (!w) { alert('Please allow popups to print.'); return; }
        w.document.write('<!DOCTYPE html><html><head><title>Shopping List</title>');
        w.document.write('<style>body{font-family:Arial, sans-serif; padding:20px;} pre{white-space:pre-wrap;}</style>');
        w.document.write('</head><body><h3>Shopping List</h3><pre>' + (txt.replace(/[&<>]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[s])) ) + '</pre></body></html>');
        w.document.close();
        w.focus();
        w.print();
        w.close();
      });
    }

    if (window.bootstrap) {
      document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    }

    const shareConfig = {
      recipeId: <?php echo (int)$recipe_id; ?>,
      csrf: <?php echo json_encode($csrfToken); ?>
    };
    const shareForm = document.getElementById('shareForm');
    const shareAlert = document.getElementById('shareAlert');
    const csrfField = shareForm ? shareForm.querySelector('input[name="csrf_token"]') : null;
    const shareModalEl = document.getElementById('shareRecipeModal');
    const shareModal = shareForm && shareModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(shareModalEl) : null;
    const shareToChatBtn = document.getElementById('shareToChatBtn');

    function showShareAlert(type, message) {
      if (!shareAlert) return;
      shareAlert.classList.remove('d-none', 'alert-success', 'alert-danger');
      shareAlert.classList.add('alert', 'alert-' + type);
      shareAlert.textContent = message;
    }

    if (shareForm) {
      shareForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (shareAlert) {
          shareAlert.classList.add('d-none');
          shareAlert.textContent = '';
        }
        const friendId = parseInt(shareForm.friend_id.value || '0', 10);
        if (!friendId) {
          showShareAlert('danger', 'Please choose a friend.');
          return;
        }
        const note = shareForm.note.value.trim();
        const submitBtn = shareForm.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sharing...';
        }
        try {
          const resp = await fetch('../api/share_recipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              recipe_id: shareConfig.recipeId,
              friend_id: friendId,
              note: note,
              csrf_token: csrfField ? csrfField.value : shareConfig.csrf
            })
          });
          const data = await resp.json();
          if (data.ok) {
            showShareAlert('success', data.message || 'Recipe shared successfully.');
            shareForm.reset();
            if (shareModal) {
              setTimeout(() => shareModal.hide(), 1200);
            }
          } else {
            showShareAlert('danger', data.message || 'Failed to share recipe.');
          }
        } catch (err) {
          showShareAlert('danger', 'Network error. Please try again.');
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        }
      });
    }

    if (shareToChatBtn && shareForm) {
      shareToChatBtn.addEventListener('click', async function() {
        if (shareAlert) {
          shareAlert.classList.add('d-none');
          shareAlert.textContent = '';
        }
        const friendId = parseInt(shareForm.friend_id.value || '0', 10);
        if (!friendId) {
          showShareAlert('danger', 'Please choose a friend.');
          return;
        }
        const note = shareForm.note.value.trim();
        const originalText = shareToChatBtn.innerHTML;
        shareToChatBtn.disabled = true;
        shareToChatBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sharing...';

        try {
          const resp = await fetch('../api/chat_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'send',
              peer_id: friendId,
              recipe_id: shareConfig.recipeId,
              content: note
            })
          });
          const data = await resp.json();
          if (data.ok) {
            showShareAlert('success', 'Recipe shared via chat.');
            if (shareModal) {
              setTimeout(() => shareModal.hide(), 1200);
            }
          } else {
            showShareAlert('danger', data.message || 'Failed to share via chat.');
          }
        } catch (err) {
          showShareAlert('danger', 'Network error. Please try again.');
        } finally {
          shareToChatBtn.disabled = false;
          shareToChatBtn.innerHTML = originalText;
        }
      });
    }
  });
</script>

<!-- Shopping List Modal -->
<div class="modal fade" id="shoppingListModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> Shopping List</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea id="shoppingListText" class="form-control" rows="10" readonly></textarea>
        <div class="small text-muted mt-2">Tip: Use Copy to place into notes or messaging, or Print for a paper copy.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-primary" id="copyShoppingList"><i class="fas fa-copy"></i> Copy</button>
        <button type="button" class="btn btn-primary" id="printShoppingList"><i class="fas fa-print"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<?php include 'customer_footer.php'; ?>



