<?php
// customer/customer_home.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';

$placeholderImage = '../assets/img/no_image.png';
$maxFeatured = 6;

// Ensure featured flag exists (in case admin hasn't opened their dashboard yet).
$featureColumn = $conn->query("SHOW COLUMNS FROM recipe LIKE 'is_featured'");
if ($featureColumn && $featureColumn->num_rows === 0) {
    $conn->query("ALTER TABLE recipe ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
}
if ($featureColumn instanceof mysqli_result) {
    $featureColumn->free();
}

$categoryColumn = $conn->query("SHOW COLUMNS FROM recipe LIKE 'recipe_category'");
if ($categoryColumn && $categoryColumn->num_rows === 0) {
    $conn->query("ALTER TABLE recipe ADD COLUMN recipe_category VARCHAR(100) DEFAULT NULL AFTER price_range");
}
if ($categoryColumn instanceof mysqli_result) {
    $categoryColumn->free();
}

/**
 * Normalise recipe image paths.
 */
function recipe_image_url(?string $value, string $placeholder): string
{
    if (empty($value)) {
        return $placeholder;
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    if (str_starts_with($value, '../')) {
        return $value;
    }
    if (str_starts_with($value, 'uploads/')) {
        return '../' . $value;
    }
    return '../uploads/recipes/' . ltrim($value, '/');
}

/**
 * Fetch recipes for a specific section.
 *
 * @param mysqli $conn
 * @param string $where Additional WHERE clause (without the leading AND).
 * @param string $order Order by clause.
 * @param int    $limit Number of rows to retrieve.
 * @param array  $excludeIds Recipe IDs to exclude.
 * @return array<int, array<string, mixed>>
 */
function fetch_recipe_set(mysqli $conn, string $where, string $order, int $limit, array $excludeIds = []): array
{
    $sql = "
        SELECT recipe_id, recipe_name, recipe_description, price_range, dietary_tag,
               recipe_image, recipe_category, total_cost,
               COALESCE(last_moderated_at, created_at) AS updated_at
        FROM recipe
        WHERE status = 'approved'
    ";

    if ($where !== '') {
        $sql .= " AND ({$where})";
    }

    if (!empty($excludeIds)) {
        $ids = implode(',', array_map('intval', $excludeIds));
        $sql .= " AND recipe_id NOT IN ($ids)";
    }

    $sql .= " ORDER BY {$order} LIMIT " . (int)$limit;

    $data = [];
    if ($result = $conn->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $row['recipe_id'] = (int)$row['recipe_id'];
            $row['total_cost'] = isset($row['total_cost']) ? (float)$row['total_cost'] : 0.0;
            $data[] = $row;
        }
        $result->free();
    }

    return $data;
}

/**
 * Create a shortened description for card layouts.
 */
function recipe_summary(string $description, int $length = 110): string
{
    $description = trim($description);
    if ($description === '') {
        return 'No description provided yet.';
    }
    if (mb_strlen($description) <= $length) {
        return $description;
    }
    return mb_substr($description, 0, $length - 3) . '...';
}

function price_chip_class(?string $priceRange): string
{
    if (empty($priceRange)) {
        return 'gg-chip';
    }

    $priceRange = strtolower($priceRange);
    if (str_contains($priceRange, 'cheap')) {
        return 'gg-chip gg-chip--success';
    }
    if (str_contains($priceRange, 'moderate')) {
        return 'gg-chip gg-chip--primary';
    }
    if (str_contains($priceRange, 'premium') || str_contains($priceRange, 'expensive')) {
        return 'gg-chip gg-chip--warning';
    }
    return 'gg-chip';
}

$homeAiPromptInput = '';
$homeAiIngredientsInput = '';
$homeAiOutput = '';
$homeAiError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['home_ai_action']) && $_POST['home_ai_action'] === 'suggest') {
    $homeAiPromptInput = trim($_POST['home_ai_prompt'] ?? '');
    $homeAiIngredientsInput = trim($_POST['home_ai_ingredients'] ?? '');

    try {
        require_once __DIR__ . '/../includes/huggingface_client.php';

        $aiRecipeSet = fetch_recipe_set($conn, '', 'updated_at DESC', 40);
        $recipeNames = array_filter(array_map(static fn($row) => trim((string)($row['recipe_name'] ?? '')), $aiRecipeSet));

        $parts = [];
        if ($homeAiPromptInput !== '') {
            $parts[] = 'Meal preferences: ' . $homeAiPromptInput;
        }
        if ($homeAiIngredientsInput !== '') {
            $parts[] = 'Key ingredients on hand: ' . $homeAiIngredientsInput;
        }
        if (!empty($recipeNames)) {
            $sample = array_slice($recipeNames, 0, 25);
            $summary = 'Reference recipes available: ' . implode(', ', $sample);
            if (count($recipeNames) > 25) {
                $summary .= ', ...';
            }
            $parts[] = $summary;
        }
        $parts[] = 'Craft a concise 7-day meal plan. Mention specific recipes when relevant. End with a brief grocery insight.';

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are GroceryGenie, a friendly meal planning assistant. Provide Malaysian-inspired suggestions when possible, with short bullet points for each day.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", $parts),
            ],
        ];

        $response = gg_hf_chat(
            $messages,
            'meta-llama/Llama-3.1-8B-Instruct:novita',
            [
                'temperature' => 0.6,
                'max_tokens' => 500,
            ]
        );

        if (isset($response['choices'][0]['message']['content'])) {
            $homeAiOutput = trim((string)$response['choices'][0]['message']['content']);
        } elseif (isset($response['generated_text'])) {
            $homeAiOutput = trim((string)$response['generated_text']);
        }

        if ($homeAiOutput === '') {
            $homeAiError = 'The AI did not return any suggestions. Please try again.';
        }
    } catch (Throwable $e) {
        $homeAiError = $e->getMessage();
    }
}

// Homepage metrics
$totalApproved = 0;
$featuredCount = 0;
$budgetFriendlyCount = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM recipe WHERE status = 'approved'")) {
    $totalApproved = (int)$result->fetch_assoc()['total'];
    $result->free();
}
if ($result = $conn->query("SELECT COUNT(*) AS total FROM recipe WHERE status = 'approved' AND is_featured = 1")) {
    $featuredCount = (int)$result->fetch_assoc()['total'];
    $result->free();
}
if ($result = $conn->query("SELECT COUNT(*) AS total FROM recipe WHERE status = 'approved' AND (price_range LIKE 'Cheap%' OR total_cost <= 20)")) {
    $budgetFriendlyCount = (int)$result->fetch_assoc()['total'];
    $result->free();
}

// Featured recipes curated by admin
$featuredRecipes = fetch_recipe_set(
    $conn,
    'is_featured = 1',
    'updated_at DESC',
    $maxFeatured
);

if (empty($featuredRecipes)) {
    $featuredRecipes = fetch_recipe_set($conn, '', 'updated_at DESC', 6);
}

$featuredIds = array_map(fn($row) => (int)$row['recipe_id'], $featuredRecipes);

// Latest arrivals excluding featured ones
$latestRecipes = fetch_recipe_set(
    $conn,
    '',
    'updated_at DESC',
    6,
    $featuredIds
);

// Budget friendly picks
$budgetRecipes = fetch_recipe_set(
    $conn,
    "price_range LIKE 'Cheap%' OR total_cost <= 20",
    'updated_at DESC',
    3,
    $featuredIds
);

// Breakfast & Brunch highlights
$breakfastRecipes = fetch_recipe_set(
    $conn,
    "recipe_category = 'Breakfast'",
    'updated_at DESC',
    3,
    $featuredIds
);

include 'customer_header.php';
?>

<section class="gg-hero mb-5" data-aos="fade-up" style="background-image: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1800&q=80'); background-size: cover; background-position: center;">
  <div class="container">
    <div class="gg-hero-content">
      <span class="gg-hero-eyebrow"><i class="fas fa-star"></i> Curated for You</span>
      <h1>Crave-worthy meals tailored to your pantry and budget.</h1>
      <p>Explore fresh inspiration every week, build your plan with an AI-powered calendar, and let GroceryGenie keep the shopping list on track.</p>
      <div class="hero-actions d-flex flex-wrap gap-3 mt-4">
        <a href="all_recipes.php" class="gg-btn-primary"><i class="fas fa-book-open"></i> Explore All Recipes</a>
        <a href="saved_recipes.php" class="gg-btn-outline"><i class="fas fa-heart"></i> My Saved Recipes</a>
      </div>
    </div>
  </div>
</section>

<div class="container mb-5">
  <!-- Live Metrics -->
  <section class="gg-section" data-aos="fade-up" data-aos-delay="50">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="gg-metric-card h-100">
          <div class="gg-metric-icon"><i class="fas fa-utensils"></i></div>
          <h3><?php echo number_format($totalApproved); ?></h3>
          <p>Approved recipes contributed by the GroceryGenie community.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="gg-metric-card h-100">
          <div class="gg-metric-icon"><i class="fas fa-star"></i></div>
          <h3><?php echo number_format($featuredCount); ?> featured</h3>
          <p>Hand-picked dishes showcased by our admin team this week.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="gg-metric-card h-100">
          <div class="gg-metric-icon"><i class="fas fa-tags"></i></div>
          <h3><?php echo number_format($budgetFriendlyCount); ?></h3>
          <p>Budget-friendly meals under RM20—perfect for wallet-wise planning.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Featured Recipes -->
  <section class="gg-section" data-aos="fade-up">
    <h3 class="gg-section-title">
      <span><i class="fas fa-fire"></i></span>
      Recommended by Our Admins
    </h3>
    <p class="gg-subtext mb-4">Fresh picks curated just for you. Tap in to start cooking.</p>
    <?php if (empty($featuredRecipes)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No featured recipes yet—check back soon!
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featuredRecipes as $index => $recipe): ?>
                <?php
                    $imageSrc = recipe_image_url($recipe['recipe_image'] ?? null, $placeholderImage);
                    $summary = recipe_summary($recipe['recipe_description'] ?? '');
                    $priceClass = price_chip_class($recipe['price_range'] ?? null);
                    $delay = ($index % 3) * 80 + 80;
                ?>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
                    <div class="gg-card">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                        <div class="gg-card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                                <?php if ($recipe['total_cost'] > 0): ?>
                                    <span class="gg-chip gg-chip--warning"><i class="fas fa-wallet"></i> RM <?php echo number_format($recipe['total_cost'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-3 text-muted"><?php echo htmlspecialchars($summary); ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php if (!empty($recipe['price_range'])): ?>
                                    <span class="<?php echo htmlspecialchars($priceClass); ?>"><i class="fas fa-coins"></i> <?php echo htmlspecialchars($recipe['price_range']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($recipe['dietary_tag'])): ?>
                                    <span class="gg-chip gg-chip--success"><i class="fas fa-leaf"></i> <?php echo htmlspecialchars($recipe['dietary_tag']); ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="recipe_details.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="gg-btn-primary w-100 mt-auto">
                                <i class="fas fa-book"></i> View recipe
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </section>

  <!-- Latest Arrivals -->
  <section class="gg-section" data-aos="fade-up">
    <h3 class="gg-section-title">
      <span><i class="fas fa-clock"></i></span>
      Just Added
    </h3>
    <p class="gg-subtext mb-4">Discover the newest dishes shared by GroceryGenie creators.</p>
    <?php if (empty($latestRecipes)): ?>
        <div class="alert alert-light border text-muted">
            <i class="fas fa-utensil-spoon me-2"></i>No recent recipes to show yet. Be the first to submit a new one!
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($latestRecipes as $index => $recipe): ?>
                <?php
                    $imageSrc = recipe_image_url($recipe['recipe_image'] ?? null, $placeholderImage);
                    $summary = recipe_summary($recipe['recipe_description'] ?? '');
                    $priceClass = price_chip_class($recipe['price_range'] ?? null);
                    $delay = ($index % 3) * 80 + 80;
                ?>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
                    <div class="gg-card">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                        <div class="gg-card-body">
                            <h5 class="mb-2"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($summary); ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php if (!empty($recipe['price_range'])): ?>
                                    <span class="<?php echo htmlspecialchars($priceClass); ?>"><i class="fas fa-coins"></i> <?php echo htmlspecialchars($recipe['price_range']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($recipe['recipe_category'])): ?>
                                    <span class="gg-chip"><i class="fas fa-compass"></i> <?php echo htmlspecialchars($recipe['recipe_category']); ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="recipe_details.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="gg-btn-outline w-100 mt-auto">
                                <i class="fas fa-eye"></i> View recipe
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </section>

  <!-- Budget Friendly -->
  <section class="gg-section" data-aos="fade-up">
    <h3 class="gg-section-title">
      <span><i class="fas fa-piggy-bank"></i></span>
      Budget-Friendly Bites
    </h3>
    <p class="gg-subtext mb-4">Keep flavour high and costs low with these wallet-wise wins.</p>
    <?php if (empty($budgetRecipes)): ?>
        <p class="text-muted">No budget-friendly recipes yet—stay tuned!</p>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($budgetRecipes as $index => $recipe): ?>
                <?php
                    $imageSrc = recipe_image_url($recipe['recipe_image'] ?? null, $placeholderImage);
                    $summary = recipe_summary($recipe['recipe_description'] ?? '');
                    $delay = ($index % 3) * 80 + 80;
                ?>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
                    <div class="gg-card">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                        <div class="gg-card-body">
                            <h5 class="mb-2"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($summary); ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="gg-chip gg-chip--success"><i class="fas fa-coins"></i> Budget Pick</span>
                                <?php if (!empty($recipe['dietary_tag'])): ?>
                                    <span class="gg-chip"><i class="fas fa-seedling"></i> <?php echo htmlspecialchars($recipe['dietary_tag']); ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="recipe_details.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="gg-btn-primary w-100 mt-auto">
                                <i class="fas fa-shopping-basket"></i> View recipe
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </section>

  <!-- Breakfast Spotlight -->
  <section class="gg-section" data-aos="fade-up">
    <h3 class="gg-section-title">
      <span><i class="fas fa-coffee"></i></span>
      Sunrise Starts
    </h3>
    <p class="gg-subtext mb-4">Light bites and hearty favourites to keep mornings bright.</p>
    <?php if (empty($breakfastRecipes)): ?>
        <p class="text-muted">No breakfast recipes yet. Share your morning favourite!</p>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($breakfastRecipes as $index => $recipe): ?>
                <?php
                    $imageSrc = recipe_image_url($recipe['recipe_image'] ?? null, $placeholderImage);
                    $summary = recipe_summary($recipe['recipe_description'] ?? '');
                    $delay = ($index % 3) * 80 + 80;
                ?>
                <div class="col-md-4" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
                    <div class="gg-card">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                        <div class="gg-card-body">
                            <h5><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($summary); ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="gg-chip"><i class="fas fa-sun"></i> Bright mornings</span>
                                <?php if (!empty($recipe['price_range'])): ?>
                                    <span class="<?php echo htmlspecialchars(price_chip_class($recipe['price_range'])); ?>"><i class="fas fa-coins"></i> <?php echo htmlspecialchars($recipe['price_range']); ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="recipe_details.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="gg-btn-outline w-100 mt-auto">
                                <i class="fas fa-book-open"></i> View recipe
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </section>

  <!-- AI Meal Ideas -->
  <section class="gg-section ai-ideas-section" data-aos="fade-up">
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-5">
        <div class="ai-ideas-side h-100 p-4">
          <span class="ai-kicker"><i class="fas fa-bolt me-2"></i>Smart planning</span>
          <h3 class="mt-3 mb-2">AI Meal Ideas</h3>
          <p class="text-muted">
            Share your cravings or ingredients and let GroceryGenie sketch a 7-day plan that matches your pantry and pace.
          </p>
          <ul class="ai-benefits list-unstyled mt-4 mb-0">
            <li><i class="fas fa-check-circle me-2"></i> Suggests dishes listed in GroceryGenie</li>
            <li><i class="fas fa-check-circle me-2"></i> Balances budgets, diets, and weeknight wins</li>
            <li><i class="fas fa-check-circle me-2"></i> Ends with a quick grocery insight</li>
          </ul>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="ai-ideas-form h-100 p-4">
          <form method="POST" class="row g-3">
            <input type="hidden" name="home_ai_action" value="suggest">
            <div class="col-12">
              <label class="form-label ai-label">Preferences &amp; goals</label>
              <textarea name="home_ai_prompt" class="form-control ai-input" rows="3" placeholder="E.g. quick weeknight dinners, high protein, serves 2"><?php echo htmlspecialchars($homeAiPromptInput); ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label ai-label">Key ingredients</label>
              <input type="text" name="home_ai_ingredients" class="form-control ai-input" value="<?php echo htmlspecialchars($homeAiIngredientsInput); ?>" placeholder="Chicken breast, broccoli, rice">
            </div>
            <div class="col-12">
              <button type="submit" class="btn ai-btn w-100"><i class="fas fa-magic me-2"></i>Ask GroceryGenie</button>
            </div>
          </form>
          <?php if ($homeAiError !== ''): ?>
            <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($homeAiError); ?></div>
          <?php elseif ($homeAiOutput !== ''): ?>
            <div class="ai-response-box mt-3">
              <?php echo nl2br(htmlspecialchars($homeAiOutput)); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>

<style>
  .gg-hero {
    position: relative;
    border-radius: 32px;
    overflow: hidden;
    min-height: 380px;
    display: flex;
    align-items: center;
    box-shadow: 0 32px 65px rgba(15, 23, 42, 0.24);
    background-size: cover;
    background-position: center;
    color: #fff;
  }
  .gg-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(17, 24, 39, 0.75), rgba(255, 145, 77, 0.55));
    backdrop-filter: blur(2px);
  }
  .gg-hero-content {
    position: relative;
    z-index: 1;
    max-width: 620px;
  }
  .gg-hero-content h1 {
    font-size: 2.8rem;
    font-weight: 700;
    line-height: 1.15;
    margin-bottom: 1rem;
  }
  .gg-hero-content p {
    font-size: 1.05rem;
    color: rgba(243, 244, 246, 0.85);
    margin-bottom: 1.5rem;
  }
  .gg-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.75rem;
  }
  .hero-actions .gg-btn-outline {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.4);
    color: #fff;
  }
  .hero-actions .gg-btn-outline:hover {
    background: rgba(255, 255, 255, 0.28);
    color: #fff;
  }

  .home-sections {
    position: relative;
    z-index: 1;
    margin-bottom: 4rem;
  }
  .gg-section {
    background: #ffffff;
    border-radius: 28px;
    border: 1px solid rgba(226, 232, 240, 0.6);
    padding: 2.5rem 2rem;
    margin-bottom: 3rem;
    box-shadow: 0 24px 45px rgba(15, 23, 42, 0.12);
  }
  .gg-section-title {
    font-size: 1.55rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #1f2937;
  }
  .gg-section-title span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 16px;
    background: rgba(255, 145, 77, 0.16);
    color: #ff6a00;
    font-size: 1.1rem;
  }
  .gg-subtext {
    color: #6b7280;
    font-size: 0.95rem;
  }

  .gg-metric-card {
    background: linear-gradient(140deg, #ffffff, rgba(255, 145, 77, 0.12));
    border-radius: 24px;
    padding: 1.8rem 1.6rem;
    border: 1px solid rgba(226, 232, 240, 0.7);
    box-shadow: 0 20px 35px rgba(15, 23, 42, 0.1);
  }
  .gg-metric-icon {
    width: 54px;
    height: 54px;
    border-radius: 18px;
    background: rgba(255, 145, 77, 0.16);
    color: #f97316;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 1rem;
  }
  .gg-metric-card h3 {
    font-size: 1.65rem;
    font-weight: 700;
    color: #111827;
  }
.gg-metric-card {
  background: linear-gradient(140deg, #ffffff, rgba(255, 145, 77, 0.12));
  border-radius: 24px;
  padding: 1.8rem 1.6rem;
  border: 1px solid rgba(226, 232, 240, 0.7);
  box-shadow: 0 20px 35px rgba(15, 23, 42, 0.1);
}
.gg-metric-icon {
  width: 54px;
  height: 54px;
  border-radius: 18px;
  background: rgba(255, 145, 77, 0.16);
  color: #f97316;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 1rem;
}
.gg-metric-card h3 {
  font-size: 1.65rem;
  font-weight: 700;
  color: #111827;
}
.gg-metric-card p {
  color: #4b5563;
  margin-bottom: 0;
}

.gg-card {
  border: none;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 30px 45px rgba(15, 23, 42, 0.12);
  background: #ffffff;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  height: 100%;
}
  .gg-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 36px 55px rgba(15, 23, 42, 0.16);
  }
  .gg-card img {
    height: 220px;
    object-fit: cover;
  }
  .gg-card-body {
    padding: 1.7rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex: 1;
  }
  .gg-card-body h5 {
    font-weight: 600;
    color: #1f2937;
  }
  .gg-card-body p {
    color: #6b7280;
    font-size: 0.95rem;
  }

  .gg-card-body .gg-card-body .gg-btn-primary,
  .gg-card-body .gg-btn-outline {
    margin-top: auto;
  }

  .gg-btn-primary {
    background: linear-gradient(135deg, #ff914d, #ff6a00);
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 0.85rem 1.8rem;
    font-weight: 600;
    box-shadow: 0 20px 35px rgba(255, 106, 0, 0.25);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    text-align: center;
  }
  .gg-btn-primary:hover {
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 26px 45px rgba(255, 106, 0, 0.3);
  }

  .hero-actions .gg-btn-outline,
  .gg-section .gg-btn-outline {
    border-radius: 999px;
    padding: 0.85rem 1.6rem;
    font-weight: 600;
    border: 1px solid rgba(148, 163, 184, 0.5);
    color: #1f2937;
    background: rgba(255, 255, 255, 0.75);
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    text-align: center;
  }
  .hero-actions .gg-btn-outline:hover,
  .gg-section .gg-btn-outline:hover {
    color: #111;
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
    box-shadow: 0 20px 30px rgba(148, 163, 184, 0.2);
  }

  .gg-chip {
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.4rem 0.8rem;
    background: rgba(148, 163, 184, 0.18);
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }
  .gg-chip i {
    font-size: 0.75rem;
  }

  @media (max-width: 767.98px) {
    .gg-hero {
      border-radius: 24px;
      min-height: 320px;
    }
    .gg-hero-content h1 {
      font-size: 2.1rem;
    }
    .gg-section {
      padding: 2.1rem 1.6rem;
    }
    .gg-card img {
      height: 200px;
    }
  }

  .ai-ideas-section {
    background: linear-gradient(135deg, rgba(255, 145, 77, 0.08), rgba(67, 97, 238, 0.08));
    border-radius: 28px;
    padding: 2.5rem 2rem;
  }
  .ai-ideas-side {
    background: rgba(255, 255, 255, 0.65);
    border-radius: 22px;
    border: 1px solid rgba(255, 145, 77, 0.22);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1);
  }
  .ai-kicker {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: rgba(255, 145, 77, 0.18);
    color: #c2410c;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.75rem;
  }
  .ai-benefits li {
    margin-bottom: 0.6rem;
    color: #1f2937;
    font-weight: 500;
  }
  .ai-benefits i {
    color: #10b981;
  }
  .ai-ideas-form {
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
  }
  .ai-label {
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    font-weight: 600;
  }
  .ai-input {
    border-radius: 16px;
    padding: 0.9rem 1rem;
    border: 1px solid rgba(148, 163, 184, 0.35);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .ai-input:focus {
    border-color: rgba(255, 145, 77, 0.6);
    box-shadow: 0 0 0 4px rgba(255, 145, 77, 0.18);
  }
  .ai-btn {
    background: linear-gradient(135deg, #ff914d, #ff6a00);
    border: none;
    border-radius: 16px;
    font-weight: 600;
    color: #fff;
    padding: 0.9rem 0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .ai-btn:hover {
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 14px 26px rgba(255, 106, 0, 0.25);
  }
  .ai-response-box {
    background: #f8fafc;
    border-radius: 18px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    padding: 1.1rem 1.25rem;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    font-size: 0.95rem;
    color: #1f2937;
    white-space: pre-wrap;
  }
  @media (max-width: 767.98px) {
    .ai-ideas-section {
      padding: 2rem 1.5rem;
    }
    .ai-ideas-side, .ai-ideas-form {
      border-radius: 18px;
    }
  }
</style>

<!-- Animate On Scroll -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ duration: 1000, once: true });
</script>

<?php
$conn->close();
include 'customer_footer.php';
?>
