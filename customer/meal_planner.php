<?php
// customer/meal_planner.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.php');
    exit();
}
require_once __DIR__ . '/../db_connect.php';

$customer_id = (int)$_SESSION['customer_id'];

// Handle POST: generate consolidated shopping list
$consolidated = [];
$estimate_total = 0.0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_recipe_ids'])) {
    $ids = json_decode($_POST['plan_recipe_ids'], true);
    if (is_array($ids) && count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT ri.item_id, gi.item_name, gi.unit, gi.price_per_unit, SUM(ri.quantity) AS total_qty
                FROM recipe_ingredients ri
                JOIN groceryitem gi ON gi.item_id = ri.item_id
                WHERE ri.recipe_id IN ($placeholders)
                GROUP BY ri.item_id, gi.item_name, gi.unit, gi.price_per_unit";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param($types, ...$ids);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $consolidated[] = $row;
                $estimate_total += ((float)$row['price_per_unit']) * ((int)$row['total_qty']);
            }
            $st->close();
        }
    }
}

$pendingAiRequest = false;
$aiPromptInput = '';
$aiIngredientsInput = '';
$aiOutput = '';
$aiError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_action']) && $_POST['ai_action'] === 'suggest') {
    $pendingAiRequest = true;
    $aiPromptInput = trim($_POST['ai_prompt'] ?? '');
    $aiIngredientsInput = trim($_POST['ai_ingredients'] ?? '');
}

$cartPrefillEncoded = '';
if (!empty($consolidated)) {
    $cartPrefill = [];
    foreach ($consolidated as $row) {
        $cartPrefill[$row['item_name']] = [
            'qty' => (int)$row['total_qty'],
            'price' => (float)$row['price_per_unit'],
        ];
    }
    $cartPrefillEncoded = rawurlencode(json_encode($cartPrefill));
}

// Fetch approved recipes to display in library
$recipes = [];
if ($rs = $conn->query("SELECT recipe_id, recipe_name, recipe_image FROM recipe WHERE status='approved' ORDER BY created_at DESC")) {
    while ($r = $rs->fetch_assoc()) {
        $recipes[] = $r;
    }
    $rs->close();
}

if ($pendingAiRequest) {
    try {
        require_once __DIR__ . '/../includes/huggingface_client.php';

        $recipeNames = array_map(static fn($recipe) => trim($recipe['recipe_name']), $recipes);
        $recipeNames = array_values(array_filter($recipeNames));
        $recipeSummary = '';
        if (!empty($recipeNames)) {
            $slice = array_slice($recipeNames, 0, 25);
            $recipeSummary = 'Approved recipes available: ' . implode(', ', $slice) . (count($recipeNames) > 25 ? ', …' : '');
        }

        $userParts = [];
        if ($aiPromptInput !== '') {
            $userParts[] = 'Meal preferences: ' . $aiPromptInput;
        }
        if ($aiIngredientsInput !== '') {
            $userParts[] = 'Key ingredients on hand: ' . $aiIngredientsInput;
        }
        if ($recipeSummary !== '') {
            $userParts[] = $recipeSummary;
        }
        $userParts[] = 'Plan a balanced 7-day schedule using the recipe names when appropriate. Focus on Malaysian tastes when relevant.';

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are GroceryGenie, a friendly meal planning assistant. Provide concise 7-day plans with short bullet points. Reference recipe names provided by the user when suggesting dishes. Finish with a quick grocery insight if useful.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", $userParts),
            ],
        ];

        $response = gg_hf_chat(
            $messages,
            'meta-llama/Llama-3.1-8B-Instruct:novita',
            [
                'temperature' => 0.6,
                'max_tokens' => 600,
            ]
        );

        if (isset($response['choices'][0]['message']['content'])) {
            $aiOutput = trim((string)$response['choices'][0]['message']['content']);
        } elseif (isset($response['generated_text'])) {
            $aiOutput = trim((string)$response['generated_text']);
        }

        if ($aiOutput === '') {
            $aiError = 'Received an empty response from the AI.';
        }
    } catch (Throwable $e) {
        $aiError = $e->getMessage();
    }
}

include 'customer_header.php';
?>

<style>
  .mp-wrapper {
    margin-top: 2.5rem;
    margin-bottom: 4rem;
  }
  .mp-hero {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.95), rgba(255, 145, 77, 0.9));
    border-radius: 28px;
    padding: 2.75rem 2.25rem;
    color: #ffffff;
    box-shadow: 0 30px 55px rgba(15, 23, 42, 0.22);
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: center;
    justify-content: space-between;
  }
  .mp-hero-content {
    max-width: 640px;
  }
  .mp-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
    margin-bottom: 1.1rem;
  }
  .mp-hero-title {
    font-weight: 700;
    font-size: clamp(2rem, 3vw, 2.6rem);
    margin-bottom: 1.1rem;
  }
  .mp-hero-lead {
    font-size: 1.05rem;
    line-height: 1.75;
    color: rgba(255, 255, 255, 0.88);
    margin-bottom: 0;
  }
  .mp-hero-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1rem;
    min-width: 220px;
  }
  .mp-hero-actions .gg-btn-outline {
    border-radius: 999px;
    padding: 0.7rem 1.8rem;
    font-weight: 600;
    border-color: rgba(255, 255, 255, 0.75);
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 20px 32px rgba(15, 23, 42, 0.28);
  }
  .mp-hero-actions .gg-btn-outline:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.9);
  }
  .mp-hero-tip {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.78);
    text-align: right;
    max-width: 240px;
  }
  .mp-card {
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 30px 55px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(15, 23, 42, 0.03);
  }
  .mp-card + .mp-card {
    margin-top: 1.75rem;
  }
  .mp-card-header {
    padding: 1.6rem 1.85rem 1.2rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    flex-wrap: wrap;
  }
  .mp-card-heading {
    flex: 1 1 240px;
  }
  .mp-card-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    font-weight: 700;
    color: #94a3b8;
    margin-bottom: 0.6rem;
  }
  .mp-card-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.45rem;
  }
  .mp-card-subtitle {
    font-size: 0.94rem;
    color: #64748b;
    margin-bottom: 0;
    line-height: 1.6;
  }
  .mp-card-body {
    padding: 1.9rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }
  .mp-body-note {
    font-size: 0.92rem;
    color: #475569;
    margin-bottom: 0;
    line-height: 1.6;
  }
  .mp-search {
    position: relative;
  }
  .mp-search i {
    position: absolute;
    top: 50%;
    left: 1rem;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.9rem;
  }
  .mp-search input {
    padding-left: 2.75rem;
    border-radius: 999px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: none;
    height: 44px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .mp-search input:focus {
    border-color: rgba(67, 97, 238, 0.55);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.18);
    outline: none;
  }
  .mp-library-list {
    max-height: 60vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding-right: 0.3rem;
  }
  .mp-library-list::-webkit-scrollbar {
    width: 8px;
  }
  .mp-library-list::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 999px;
  }
  .recipe-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem;
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #ffffff;
    cursor: grab;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
  }
  .recipe-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.14);
    border-color: rgba(67, 97, 238, 0.45);
  }
  .recipe-card.dragging {
    opacity: 0.7;
  }
  .recipe-card img {
    width: 68px;
    height: 68px;
    object-fit: cover;
    border-radius: 16px;
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.16);
  }
  .recipe-card .drag-badge {
    position: absolute;
    right: 1rem;
    top: 0.9rem;
    padding: 0.3rem 0.75rem;
    font-size: 0.7rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    border-radius: 999px;
    background: rgba(67, 97, 238, 0.12);
    color: #1d4ed8;
    font-weight: 700;
  }
  .mp-empty {
    text-align: center;
    padding: 1.5rem;
    border-radius: 20px;
    background: rgba(15, 23, 42, 0.03);
    color: #64748b;
    font-weight: 500;
  }
  .mp-calendar {
    padding: 0 1.85rem 1.85rem;
  }
  .mp-days {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1.2rem;
    margin-top: 1.8rem;
  }
  .mp-day {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 20px;
    padding: 1.1rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    min-height: 220px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
  }
  .mp-day:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.12);
  }
  .mp-day-title {
    font-size: 0.82rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #94a3b8;
    font-weight: 700;
  }
  .slot {
    flex: 1;
    border: 2px dashed rgba(148, 163, 184, 0.4);
    border-radius: 18px;
    background: rgba(248, 250, 252, 0.9);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: stretch;
    transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    overflow: hidden;
  }
  .slot.drag-target {
    border-color: rgba(67, 97, 238, 0.7);
    background: rgba(219, 234, 254, 0.5);
    box-shadow: inset 0 0 0 1px rgba(67, 97, 238, 0.25);
  }
  .pill {
    position: relative;
    width: 100%;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 20px 38px rgba(15, 23, 42, 0.16);
  }
  .pill img {
    width: 100%;
    height: 140px;
    object-fit: cover;
  }
  .pill-body {
    padding: 0.85rem 1rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f2937;
    text-align: center;
    line-height: 1.5;
  }
  .pill-label {
    pointer-events: none;
    display: block;
  }
  .pill-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    border: none;
    background: rgba(15, 23, 42, 0.65);
    color: #ffffff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: background 0.18s ease, transform 0.18s ease;
  }
  .pill-remove:hover {
    background: rgba(239, 68, 68, 0.9);
    transform: scale(1.05);
  }
  .mp-ai .form-label {
    font-size: 0.78rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    font-weight: 700;
    color: #94a3b8;
    margin-bottom: 0.45rem;
  }
  .mp-ai .mp-ai-note {
    font-size: 0.92rem;
    color: #475569;
    line-height: 1.6;
  }
  .mp-ai textarea,
  .mp-ai input {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: none;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
  }
  .mp-ai textarea:focus,
  .mp-ai input:focus {
    border-color: rgba(67, 97, 238, 0.55);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.18);
  }
  .mp-ai textarea {
    min-height: 120px;
    resize: vertical;
  }
  .mp-ai .gg-btn-primary {
    border-radius: 999px;
    padding: 0.65rem 1.4rem;
    font-weight: 600;
  }
  .mp-ai .ai-response {
    background: rgba(67, 97, 238, 0.1);
    border-radius: 18px;
    border: 1px solid rgba(67, 97, 238, 0.2);
    padding: 1.1rem 1.25rem;
    font-size: 0.95rem;
    line-height: 1.65;
    color: #1f2937;
  }
  .mp-summary table {
    border-radius: 18px;
    overflow: hidden;
  }
  .mp-summary table thead {
    background: rgba(15, 23, 42, 0.05);
  }
  .mp-summary table th {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #64748b;
    border-bottom: none;
  }
  .mp-summary table td {
    font-size: 0.96rem;
    color: #1f2937;
    border-color: rgba(15, 23, 42, 0.05);
  }
  .mp-summary .est-total {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.05rem;
  }
  .mp-summary .gg-btn-outline {
    border-radius: 999px;
    padding: 0.65rem 1.6rem;
    font-weight: 600;
  }
  @media (max-width: 991.98px) {
    .mp-hero {
      flex-direction: column;
      align-items: flex-start;
      text-align: left;
    }
    .mp-hero-actions {
      align-items: flex-start;
    }
    .mp-hero-tip {
      text-align: left;
    }
    .slot {
      min-height: 140px;
    }
  }
  @media (max-width: 575.98px) {
    .mp-card-header {
      padding: 1.3rem 1.5rem 1rem;
    }
    .mp-card-body {
      padding: 1.5rem;
    }
    .mp-days {
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
  }
</style>

<div class="container mp-wrapper">
  <section class="mp-hero">
    <div class="mp-hero-content">
      <span class="mp-hero-eyebrow"><i class="fas fa-calendar-alt"></i> Weekly Planner</span>
      <h1 class="mp-hero-title">Design your perfect week of meals</h1>
      <p class="mp-hero-lead">Drag recipes into each day, generate a smart shopping list, and let GroceryGenie keep your pantry stocked without the guesswork.</p>
    </div>
    <div class="mp-hero-actions">
      <?php if (!empty($consolidated) && !empty($cartPrefillEncoded)) : ?>
        <a href="shopping.php?cart=<?php echo $cartPrefillEncoded; ?>" class="gg-btn-outline"><i class="fas fa-shopping-cart me-2"></i>Prefill cart</a>
        <span class="mp-hero-tip">Everything you add to the planner shows up in a ready-made cart.</span>
      <?php else : ?>
        <span class="mp-hero-tip">Tip: Add recipes to this week, then build your cart from the consolidated list in one click.</span>
      <?php endif; ?>
    </div>
  </section>

  <div class="mp-card mp-ai mb-4">
    <div class="mp-card-header">
      <div class="mp-card-heading">
        <span class="mp-card-eyebrow">Assistant</span>
        <h3 class="mp-card-title"><i class="fas fa-robot text-warning"></i>AI meal ideas</h3>
        <p class="mp-card-subtitle">Describe your cravings or ingredients and let GroceryGenie draft a weekly plan.</p>
      </div>
    </div>
    <div class="mp-card-body">
      <div class="mp-ai-note">Blend dietary goals, cuisines, or time limits—the assistant keeps suggestions concise.</div>
      <form method="POST" class="mt-3">
        <input type="hidden" name="ai_action" value="suggest">
        <div class="mb-3">
          <label class="form-label">Preferences &amp; goals</label>
          <textarea rows="4" name="ai_prompt" class="form-control" placeholder="E.g. quick dinners, budget-friendly, 4 servings"><?php echo htmlspecialchars($aiPromptInput); ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Key ingredients</label>
          <input type="text" name="ai_ingredients" class="form-control" value="<?php echo htmlspecialchars($aiIngredientsInput); ?>" placeholder="Chicken breast, broccoli, rice">
        </div>
        <button type="submit" class="gg-btn-primary w-100"><i class="fas fa-magic me-1"></i>Ask AI for a plan</button>
      </form>
      <?php if ($aiError): ?>
        <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($aiError); ?></div>
      <?php elseif ($aiOutput !== ''): ?>
        <div class="ai-response mt-3"><?php echo nl2br(htmlspecialchars($aiOutput)); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4 mt-4">
    <div class="col-xl-4">
      <div class="mp-card mp-library">
        <div class="mp-card-header">
          <div class="mp-card-heading">
            <span class="mp-card-eyebrow">Browse</span>
            <h3 class="mp-card-title"><i class="fas fa-book-open text-warning"></i>Recipe library</h3>
            <p class="mp-card-subtitle">Search approved recipes and drag them into the weekly planner.</p>
          </div>
        </div>
        <div class="mp-card-body">
          <p class="mp-body-note">Tip: drag the same recipe to multiple days if you love leftovers.</p>
          <div class="mp-search">
            <i class="fas fa-search"></i>
            <input type="text" id="librarySearch" class="form-control" placeholder="Search recipes...">
          </div>
          <div class="mp-library-list lib-list">
            <?php if ($recipes) : ?>
              <?php foreach ($recipes as $r) :
                $recipeName = htmlspecialchars($r['recipe_name']);
                $imagePathRaw = !empty($r['recipe_image'])
                  ? '../uploads/recipes/' . $r['recipe_image']
                  : 'https://via.placeholder.com/80?text=R';
                $imagePath = htmlspecialchars($imagePathRaw, ENT_QUOTES, 'UTF-8');
              ?>
                <div class="recipe-card" draggable="true" data-recipe-id="<?php echo (int)$r['recipe_id']; ?>" data-recipe-name="<?php echo $recipeName; ?>" data-recipe-image="<?php echo $imagePath; ?>">
                  <span class="drag-badge"><i class="fas fa-grip-lines"></i></span>
                  <img src="<?php echo $imagePath; ?>" alt="<?php echo $recipeName; ?>">
                  <div>
                    <div class="fw-semibold"><?php echo $recipeName; ?></div>
                    <small class="text-muted">Drag into the calendar</small>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else : ?>
              <div class="mp-empty">No approved recipes yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-8">
      <div class="mp-card mp-planner">
        <form method="POST" id="plannerForm">
          <input type="hidden" name="plan_recipe_ids" id="plan_recipe_ids">
          <div class="mp-card-header">
            <div class="mp-card-heading">
              <span class="mp-card-eyebrow">Weekly layout</span>
              <h3 class="mp-card-title"><i class="fas fa-calendar-week text-warning"></i>This week</h3>
              <p class="mp-card-subtitle">Drag a recipe into each day. Duplicate dishes for leftovers or quick repeats.</p>
            </div>
            <button type="submit" class="gg-btn-primary"><i class="fas fa-list-check me-2"></i>Generate shopping list</button>
          </div>
          <div class="mp-calendar">
            <div class="mp-days">
              <?php $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; foreach ($days as $d) : ?>
                <div class="mp-day" data-day="<?php echo $d; ?>">
                  <div class="mp-day-title"><?php echo $d; ?></div>
                  <div class="slot" data-slot-for="<?php echo $d; ?>"></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </form>
      </div>

      <?php if (!empty($consolidated)) : ?>
        <div class="mp-card mp-summary mt-4">
          <div class="mp-card-header">
            <div class="mp-card-heading">
              <span class="mp-card-eyebrow">Shopping support</span>
              <h3 class="mp-card-title"><i class="fas fa-basket-shopping text-warning"></i>Consolidated shopping list</h3>
              <p class="mp-card-subtitle">Summed across everything you scheduled this week.</p>
            </div>
          </div>
          <div class="mp-card-body">
            <p class="mp-body-note">Quantities combine duplicate ingredients automatically, so you only buy what you need.</p>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Unit</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Est. Price (RM)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($consolidated as $row) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                      <td><?php echo htmlspecialchars($row['unit']); ?></td>
                      <td class="text-end"><?php echo (int)$row['total_qty']; ?></td>
                      <td class="text-end"><?php echo number_format((float)$row['price_per_unit'] * (int)$row['total_qty'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-4">
              <div>
                <div class="text-uppercase small text-muted fw-semibold">Estimated total</div>
                <div class="est-total">RM <?php echo number_format($estimate_total, 2); ?></div>
              </div>
              <?php if (!empty($cartPrefillEncoded)) : ?>
                <a href="shopping.php?cart=<?php echo $cartPrefillEncoded; ?>" class="gg-btn-outline"><i class="fas fa-shopping-cart me-2"></i>Buy these items</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const draggables = document.querySelectorAll('.recipe-card[draggable="true"]');
  const slots = document.querySelectorAll('.slot');
  const idsField = document.getElementById('plan_recipe_ids');
  const librarySearch = document.getElementById('librarySearch');

  draggables.forEach(el => {
    el.addEventListener('dragstart', e => {
      e.dataTransfer.setData('text/plain', JSON.stringify({
        id: el.dataset.recipeId,
        name: el.dataset.recipeName,
        image: el.dataset.recipeImage || ''
      }));
      el.classList.add('dragging');
    });
    el.addEventListener('dragend', () => el.classList.remove('dragging'));
  });

  slots.forEach(slot => {
    slot.addEventListener('dragover', e => { e.preventDefault(); slot.classList.add('drag-target'); });
    slot.addEventListener('dragleave', () => slot.classList.remove('drag-target'));
    slot.addEventListener('drop', e => {
      e.preventDefault();
      slot.classList.remove('drag-target');
      try {
        const data = JSON.parse(e.dataTransfer.getData('text/plain'));
        const pill = document.createElement('span');
        pill.className = 'pill';
        pill.dataset.recipeId = data.id;

        const img = document.createElement('img');
        img.src = (data.image && data.image.length) ? data.image : 'https://via.placeholder.com/300?text=Recipe';
        img.alt = data.name;

        const body = document.createElement('div');
        body.className = 'pill-body';

        const label = document.createElement('span');
        label.className = 'pill-label';
        label.textContent = data.name;
        body.appendChild(label);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'pill-remove';
        removeBtn.setAttribute('aria-label', 'Remove recipe');
        removeBtn.innerHTML = '&times;';

        pill.appendChild(img);
        pill.appendChild(body);
        pill.appendChild(removeBtn);

        slot.innerHTML = '';
        slot.appendChild(pill);
        updateIds();
        removeBtn.addEventListener('click', (btnEvent) => {
          btnEvent.preventDefault();
          pill.remove();
          updateIds();
        });
      } catch (_) {}
    });
  });

  function updateIds() {
    const ids = [];
    document.querySelectorAll('.slot .pill').forEach(p => ids.push(parseInt(p.dataset.recipeId, 10)));
    idsField.value = JSON.stringify(ids);
  }

  if (librarySearch) {
    librarySearch.addEventListener('input', () => {
      const term = librarySearch.value.trim().toLowerCase();
      document.querySelectorAll('.lib-list .recipe-card').forEach(card => {
        const name = (card.dataset.recipeName || '').toLowerCase();
        const match = !term || name.includes(term);
        card.style.display = match ? 'flex' : 'none';
      });
    });
  }
</script>

<?php include 'customer_footer.php'; ?>







