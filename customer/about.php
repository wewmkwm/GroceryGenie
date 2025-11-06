<?php
// customer/about.php
include 'customer_header.php';
?>

<style>
  .about-hero {
    background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(244,114,182,0.12));
    border-radius: var(--gg-radius-lg);
    padding: 3rem 2.5rem;
    box-shadow: var(--gg-shadow-soft);
  }
  .about-hero h1 {
    font-weight: 700;
    color: var(--gg-secondary);
  }
  .about-mission,
  .about-values {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 2.2rem;
  }
  .about-values .icon-wrap {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    font-size: 1.1rem;
    margin-bottom: 1rem;
  }
  .about-timeline {
    border-left: 2px solid rgba(15,23,42,0.08);
    padding-left: 1.5rem;
  }
  .about-timeline .timeline-item {
    margin-bottom: 1.5rem;
    position: relative;
  }
  .about-timeline .timeline-item::before {
    content: '';
    position: absolute;
    left: -1.6rem;
    top: 0.3rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--gg-primary);
  }
  .about-partners img {
    width: 140px;
    max-width: 100%;
    filter: grayscale(100%);
    opacity: 0.7;
    transition: opacity .2s ease, filter .2s ease;
  }
  .about-partners img:hover {
    filter: none;
    opacity: 1;
  }
</style>

<main class="container my-5">
  <section class="about-hero mb-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="gg-hero-eyebrow"><i class="fas fa-seedling"></i> Our Story</span>
        <h1 class="display-5 mb-3">We help households plan smarter and shop with confidence.</h1>
        <p class="lead text-muted mb-0">
          GroceryGenie was born from the belief that weekly grocery runs can be effortless.
          We bring together store owners, curated recipes, and pantry planning to make sure
          you always know what to buy, how much to spend, and where to save.
        </p>
      </div>
      <div class="col-lg-5 text-lg-end">
        <div class="bg-white rounded-3 shadow-sm p-4">
          <h4 class="fw-semibold mb-3">GroceryGenie in numbers</h4>
          <ul class="list-unstyled mb-0">
            <li class="mb-2"><strong>10,000+</strong> grocery items tracked across neighbourhood stores.</li>
            <li class="mb-2"><strong>2,500+</strong> curated recipes to spark meal inspiration.</li>
            <li class="mb-0"><strong>18 minutes</strong> average time saved per shopping session.</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="row g-4 mb-5">
    <div class="col-lg-6">
      <div class="about-mission h-100">
        <h2 class="fw-semibold mb-3"><i class="fas fa-bullseye me-2 text-warning"></i>Our mission</h2>
        <p class="text-muted mb-3">
          We are on a mission to empower local commerce by giving customers a transparent view of prices
          and availability, while giving store owners the digital tools they need to thrive.
        </p>
        <div class="about-timeline">
          <div class="timeline-item">
            <h6 class="fw-semibold mb-1">2022 · Idea spark</h6>
            <p class="text-muted mb-0">Started as a simple pantry app for friends juggling meal prep.</p>
          </div>
          <div class="timeline-item">
            <h6 class="fw-semibold mb-1">2023 · Pilot launch</h6>
            <p class="text-muted mb-0">Partnered with independent grocers to surface real-time inventory online.</p>
          </div>
          <div class="timeline-item">
            <h6 class="fw-semibold mb-1">2024 · GroceryGenie today</h6>
            <p class="text-muted mb-0">Expanded nationwide with curated recipes, basket estimates, and smart reorders.</p>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="about-values h-100">
        <h2 class="fw-semibold mb-4"><i class="fas fa-heart me-2 text-danger"></i>What guides us</h2>
        <div class="row g-4">
          <div class="col-sm-6">
            <div class="icon-wrap"><i class="fas fa-users"></i></div>
            <h5 class="fw-semibold">Community first</h5>
            <p class="text-muted mb-0">We build bridges between households and local merchants, championing fair prices.</p>
          </div>
          <div class="col-sm-6">
            <div class="icon-wrap"><i class="fas fa-lightbulb"></i></div>
            <h5 class="fw-semibold">Simple innovation</h5>
            <p class="text-muted mb-0">Powerful planning tools wrapped in a friendly interface anyone can pick up.</p>
          </div>
          <div class="col-sm-6">
            <div class="icon-wrap"><i class="fas fa-leaf"></i></div>
            <h5 class="fw-semibold">Less waste</h5>
            <p class="text-muted mb-0">Smart lists minimise duplicate purchases and reduce food waste at home.</p>
          </div>
          <div class="col-sm-6">
            <div class="icon-wrap"><i class="fas fa-lock"></i></div>
            <h5 class="fw-semibold">Trusted data</h5>
            <p class="text-muted mb-0">We respect your privacy and design every flow with transparency in mind.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-5">
    <div class="row g-4 align-items-center">
      <div class="col-md-6">
        <h2 class="fw-semibold mb-3"><i class="fas fa-hands-helping me-2 text-success"></i>Partners &amp; collaborators</h2>
        <p class="text-muted mb-4">
          From family-run grocers to sustainable farms, GroceryGenie partners with suppliers who care about freshness,
          quality, and fair sourcing. We also collaborate with nutritionists and local chefs to build recipe collections
          tailored to Malaysian households.
        </p>
        <a href="contact.php" class="gg-btn-primary"><i class="fas fa-envelope me-1"></i>Partner with us</a>
      </div>
      <div class="col-md-6">
        <div class="about-partners text-center px-3">
          <img src="../assets/img/partner_farm.png" alt="HarvestCo Farms" class="mx-3 my-2">
          <img src="../assets/img/partner_grocer.png" alt="Neighbourhood Fresh" class="mx-3 my-2">
          <img src="../assets/img/partner_kitchen.png" alt="Chef Collective" class="mx-3 my-2">
        </div>
      </div>
    </div>
  </section>

  <section class="mb-5">
    <div class="bg-white rounded-3 shadow-sm p-4 p-lg-5">
      <div class="row g-4 align-items-center">
        <div class="col-lg-7">
          <h2 class="fw-semibold mb-3">Ready to shop smarter?</h2>
          <p class="text-muted mb-0">Create an account, save your favourite recipes, and let GroceryGenie handle the rest.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
          <a href="customer_register.php" class="gg-btn-primary"><i class="fas fa-user-plus me-1"></i>Join GroceryGenie</a>
          <a href="shopping.php" class="gg-btn-outline ms-2"><i class="fas fa-shopping-basket me-1"></i>Explore the market</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include 'customer_footer.php'; ?>
