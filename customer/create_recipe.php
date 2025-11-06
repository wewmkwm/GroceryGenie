<?php
// customer/create_recipe.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

$conn = new mysqli('localhost', 'root', '', 'grocerygenie');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$storedData = $_SESSION['recipe_data'] ?? [];
$isEditing = isset($_SESSION['recipe_edit']['recipe_id']);
$existingImage = $_SESSION['recipe_edit']['original_image'] ?? ($storedData['recipe_image'] ?? null);
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $formError = "<div class='alert alert-danger shadow-sm mb-3'><i class='fas fa-shield-alt me-2'></i>Security check failed. Please refresh and try again.</div>";
    } else {
        $nextData = [
            'recipe_name' => $_POST['recipe_name'] ?? '',
            'recipe_description' => $_POST['recipe_description'] ?? '',
            'recipe_category' => $_POST['recipe_category'] ?? '',
            'dietary_tag' => $_POST['dietary_tag'] ?? '',
            'steps' => $_POST['steps'] ?? '',
            'recipe_image' => $existingImage
        ];

        $uploadError = null;
        if (!empty($_FILES['recipe_image']['name'])) {
            $uploadResult = gg_secure_upload(
                $_FILES['recipe_image'],
                dirname(__DIR__) . '/uploads/recipes/'
            );
            if ($uploadResult['success']) {
                $nextData['recipe_image'] = $uploadResult['filename'];
            } else {
                $uploadError = $uploadResult['message'];
            }
        }

        if ($uploadError) {
            $formError = "<div class='alert alert-danger shadow-sm mb-3'><i class='fas fa-shield-alt me-2'></i>" . htmlspecialchars($uploadError) . "</div>";
        } else {
            $_SESSION['recipe_data'] = $nextData;
            header('Location: select_recipe_ingredients.php');
            exit();
        }
    }
}

include 'customer_header.php';
?>

<style>
  .cr-wrapper {
    margin-top: 2rem;
    margin-bottom: 4rem;
  }
  .cr-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-lg);
    color: #fff;
    padding: 2.8rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    display: flex;
    flex-wrap: wrap;
    gap: 1.8rem;
    justify-content: space-between;
  }
  .cr-hero h1 {
    font-weight: 700;
    margin-bottom: 0.9rem;
  }
  .cr-hero p {
    color: rgba(255, 255, 255, 0.85);
    max-width: 520px;
  }
  .cr-layout {
    margin-top: 2.5rem;
  }
  .cr-side-card,
  .cr-form-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
    height: 100%;
  }
  .cr-side-card .card-body {
    padding: 1.8rem;
  }
  .cr-side-step {
    border-left: 4px solid rgba(255, 138, 76, 0.55);
    padding-left: 1rem;
    margin-bottom: 1.8rem;
  }
  .cr-side-step:last-child {
    margin-bottom: 0;
  }
  .cr-side-step h5 {
    font-weight: 700;
    margin-bottom: 0.8rem;
  }
  .cr-form-card .card-body {
    padding: 2rem;
  }
  .cr-form-card label {
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .cr-form-card .form-control,
  .cr-form-card .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .cr-form-card .form-control:focus,
  .cr-form-card .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .cr-upload {
    border: 2px dashed rgba(255, 138, 76, 0.6);
    border-radius: var(--gg-radius-md);
    height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    color: var(--gg-muted);
    cursor: pointer;
    background: rgba(255, 138, 76, 0.08);
    transition: all 0.25s ease;
    text-align: center;
  }
  .cr-upload:hover {
    border-color: rgba(255, 138, 76, 0.9);
    color: rgba(255, 138, 76, 0.9);
    background: rgba(255, 138, 76, 0.12);
  }
  .cr-upload-preview img {
    max-width: 100%;
    max-height: 220px;
    border-radius: var(--gg-radius-sm);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
  }
</style>

<div class="container cr-wrapper">
  <section class="cr-hero">
    <div>
      <span class="gg-hero-eyebrow"><i class="fas fa-utensils"></i> Share your creation</span>
      <h1>Turn your favourite dish into a GroceryGenie recipe.</h1>
      <p>Describe the flavours, add a mouth-watering photo, and we’ll help map the ingredients to the nearest store. Your friends will thank you.</p>
    </div>
    <div class="text-nowrap">
      <a href="saved_recipes.php" class="gg-btn-outline text-white"><i class="fas fa-bookmark"></i> View saved recipes</a>
    </div>
  </section>
  <?php if ($isEditing): ?>
    <div class="alert alert-warning shadow-sm mt-3">
      <i class="fas fa-history me-2"></i>You are updating an existing recipe. Submit the revised details so our team can review it again.
    </div>
  <?php endif; ?>

  <div class="row cr-layout g-4">
    <div class="col-lg-4">
      <div class="card cr-side-card">
        <div class="card-body">
          <div class="cr-side-step">
            <h5>Step 1 · Category</h5>
            <p class="text-muted small mb-3">What kind of meal is this? We’ll tailor suggestions so others discover it at the right time.</p>
            <?php $categoryValue = $storedData['recipe_category'] ?? ''; ?>
            <select class="form-select" name="recipe_category" form="createRecipeForm" required>
              <option value="" <?php echo $categoryValue === '' ? 'selected' : ''; ?>>-- Choose Category --</option>
              <option value="Breakfast" <?php echo $categoryValue === 'Breakfast' ? 'selected' : ''; ?>>Breakfast &amp; Brunch</option>
              <option value="Lunch" <?php echo $categoryValue === 'Lunch' ? 'selected' : ''; ?>>Quick Lunch</option>
              <option value="Dinner" <?php echo $categoryValue === 'Dinner' ? 'selected' : ''; ?>>Dinner</option>
              <option value="Snacks" <?php echo $categoryValue === 'Snacks' ? 'selected' : ''; ?>>Snacks &amp; Bites</option>
              <option value="Dessert" <?php echo $categoryValue === 'Dessert' ? 'selected' : ''; ?>>Dessert</option>
              <option value="Drinks" <?php echo $categoryValue === 'Drinks' ? 'selected' : ''; ?>>Beverages</option>
            </select>
          </div>
          <div class="cr-side-step">
            <h5>Step 2 · Dietary focus</h5>
            <p class="text-muted small mb-3">Highlight dietary tags so GroceryGenie can personalise meal plans.</p>
            <?php $dietaryValue = $storedData['dietary_tag'] ?? ''; ?>
            <select class="form-select" name="dietary_tag" form="createRecipeForm" required>
              <option value="" <?php echo $dietaryValue === '' ? 'selected' : ''; ?>>-- Choose Preference --</option>
              <option value="None" <?php echo $dietaryValue === 'None' ? 'selected' : ''; ?>>None</option>
              <option value="Vegetarian" <?php echo $dietaryValue === 'Vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
              <option value="Vegan" <?php echo $dietaryValue === 'Vegan' ? 'selected' : ''; ?>>Vegan</option>
              <option value="Halal" <?php echo $dietaryValue === 'Halal' ? 'selected' : ''; ?>>Halal</option>
              <option value="Kosher" <?php echo $dietaryValue === 'Kosher' ? 'selected' : ''; ?>>Kosher</option>
              <option value="Gluten-Free" <?php echo $dietaryValue === 'Gluten-Free' ? 'selected' : ''; ?>>Gluten-Free</option>
              <option value="Low-Carb" <?php echo $dietaryValue === 'Low-Carb' ? 'selected' : ''; ?>>Low-Carb</option>
            </select>
          </div>
          <div class="cr-side-step mb-0">
            <h5>Step 3 · Craft the story</h5>
            <p class="text-muted small mb-0">Describe the dish, share your secrets, and we’ll gather ingredients next.</p>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card cr-form-card">
        <div class="card-body">
          <?php if ($formError) echo $formError; ?>
          <form id="createRecipeForm" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="mb-4">
              <label class="form-label">Recipe cover photo *</label>
              <div class="cr-upload" onclick="document.getElementById('recipeImage').click();">
                <i class="fas fa-cloud-upload-alt fa-2x"></i>
                <div>Tap or drag to upload</div>
                <small class="text-muted">High-res JPG, PNG, or JPEG</small>
              </div>
              <input type="file" id="recipeImage" name="recipe_image" accept="image/*" class="d-none" <?php echo empty($storedData['recipe_image']) && empty($existingImage) ? 'required' : ''; ?>>
              <div id="preview" class="cr-upload-preview mt-3">
                <?php
                  $imageToShow = $storedData['recipe_image'] ?? $existingImage ?? null;
                  if (!empty($imageToShow)) {
                      $path = strpos($imageToShow, '/') !== false ? $imageToShow : '../uploads/recipes/' . $imageToShow;
                      echo '<img src="'.htmlspecialchars($path).'" alt="Recipe preview">';
                  }
                ?>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Recipe name *</label>
              <input type="text" name="recipe_name" class="form-control" placeholder="e.g. Sambal Prawn Stir-Fry" value="<?php echo htmlspecialchars($storedData['recipe_name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Short description *</label>
              <textarea name="recipe_description" class="form-control" rows="3" placeholder="What makes this special? Share flavour notes and best moments to serve." required><?php echo htmlspecialchars($storedData['recipe_description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-4">
              <label class="form-label">Preparation steps *</label>
              <textarea name="steps" class="form-control" rows="6" placeholder="1. First step...
2. Second step...
3. Third step..." required><?php echo htmlspecialchars($storedData['steps'] ?? ''); ?></textarea>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <button type="submit" class="gg-btn-primary">
                Next: Choose ingredients <i class="fas fa-arrow-right"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    var input = document.getElementById('recipeImage');
    var preview = document.getElementById('preview');
    if (!input || !preview) return;

    input.addEventListener('change', function(event) {
      preview.innerHTML = '';
      var file = event.target.files && event.target.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function(e) {
        var img = document.createElement('img');
        img.src = e.target.result;
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  })();
</script>

<?php include 'customer_footer.php'; ?>
