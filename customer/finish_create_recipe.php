<!-- customer/finish_create_recipe.php -->

<?php include 'customer_header.php'; ?>

<style>
  .finish-section {
    background: linear-gradient(135deg, rgba(255, 145, 77, 0.12), rgba(70, 186, 104, 0.12));
    min-height: calc(100vh - 180px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
  }
  .finish-card {
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 35px 65px rgba(15, 23, 42, 0.12);
    max-width: 720px;
    width: 100%;
    padding: 48px 56px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .finish-card::before,
  .finish-card::after {
    content: "";
    position: absolute;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: rgba(255, 145, 77, 0.25);
    filter: blur(6px);
    z-index: 0;
  }
  .finish-card::before {
    top: -40px;
    right: -55px;
  }
  .finish-card::after {
    bottom: -55px;
    left: -50px;
    background: rgba(70, 186, 104, 0.25);
  }
  .finish-icon {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #46ba68 0%, #2aa053 100%);
    box-shadow: 0 18px 30px rgba(42, 160, 83, 0.28);
    color: #fff;
    font-size: 2.8rem;
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
  }
  .finish-title {
    font-weight: 700;
    font-size: 2rem;
    color: #1f2937;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
  }
  .finish-subtitle {
    color: #4b5563;
    font-size: 1.05rem;
    margin-bottom: 28px;
    position: relative;
    z-index: 1;
  }
  .finish-highlight {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 999px;
    background: rgba(255, 145, 77, 0.16);
    color: #ff6500;
    font-weight: 600;
    margin-bottom: 24px;
    position: relative;
    z-index: 1;
  }
  .finish-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
    position: relative;
    z-index: 1;
  }
  .finish-actions a {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 26px;
    border-radius: 999px;
    font-weight: 600;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .finish-primary {
    background: linear-gradient(135deg, #ff914d 0%, #ff6a00 100%);
    color: #fff;
    box-shadow: 0 16px 24px rgba(255, 106, 0, 0.25);
  }
  .finish-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 30px rgba(255, 106, 0, 0.32);
  }
  .finish-secondary {
    background: #f3f4f6;
    color: #1f2937;
  }
  .finish-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 20px rgba(15, 23, 42, 0.14);
  }
  @media (max-width: 767.98px) {
    .finish-card {
      padding: 40px 28px;
      border-radius: 20px;
    }
    .finish-title {
      font-size: 1.75rem;
    }
    .finish-icon {
      width: 82px;
      height: 82px;
      font-size: 2.4rem;
    }
    .finish-actions a {
      width: 100%;
      justify-content: center;
    }
  }
</style>

<section class="finish-section">
  <div class="finish-card">
    <div class="finish-icon">
      <i class="fas fa-check"></i>
    </div>
    <div class="finish-highlight">
      Recipe submitted for review
    </div>
    <h2 class="finish-title">Great job, chef!</h2>
    <p class="finish-subtitle">
      Your recipe is on its way to our moderation team. Once approved, it will appear in GroceryGenie so the community can cook along with you.
    </p>
    <div class="finish-actions">
      <a href="customer_profile.php" class="finish-primary">
        <i class="fas fa-user-circle"></i> View my recipes
      </a>
      <a href="create_recipe.php" class="finish-secondary">
        <i class="fas fa-plus-circle"></i> Craft another recipe
      </a>
      <a href="customer_home.php" class="finish-secondary">
        <i class="fas fa-home"></i> Return home
      </a>
    </div>
  </div>
</section>

<?php include 'customer_footer.php'; ?>
