<?php
// customer/privacypolicy.php
include 'customer_header.php';
?>

<style>
  .privacy-hero {
    background: linear-gradient(135deg, rgba(59,130,246,0.12), rgba(79,70,229,0.12));
    border-radius: var(--gg-radius-lg);
    padding: 3rem 2.5rem;
    box-shadow: var(--gg-shadow-soft);
  }
  .privacy-section {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 2.2rem;
  }
  .privacy-section h3 {
    font-weight: 600;
  }
  .privacy-section ul {
    padding-left: 1.1rem;
  }
  .privacy-section ul li {
    margin-bottom: 0.75rem;
  }
  .privacy-updated {
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.12);
    color: var(--gg-primary-dark);
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
  }
  .privacy-faq details {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: var(--gg-radius-sm);
    padding: 1.1rem 1.4rem;
    background: #fff;
  }
  .privacy-faq summary {
    cursor: pointer;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .privacy-faq summary::marker,
  .privacy-faq summary::-webkit-details-marker {
    display: none;
  }
  .privacy-faq summary i {
    transition: transform .2s ease;
  }
  .privacy-faq details[open] summary i {
    transform: rotate(180deg);
  }
</style>

<main class="container my-5">
  <section class="privacy-hero mb-5">
    <span class="gg-hero-eyebrow"><i class="fas fa-shield-alt"></i> Privacy Policy</span>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <h1 class="display-6 fw-semibold">Your trust is at the heart of GroceryGenie.</h1>
        <p class="lead text-muted mb-0">
          This policy explains what data we collect, why we collect it, and how we keep it safe.
          We’ve designed every feature to give you control over your shopping journey without compromising privacy.
        </p>
      </div>
      <span class="privacy-updated"><i class="fas fa-clock"></i>Last updated: 1 October 2025</span>
    </div>
  </section>

  <section class="privacy-section mb-4">
    <h3 class="mb-3"><i class="fas fa-database me-2 text-primary"></i>Information we collect</h3>
    <p class="text-muted">We collect the minimum information needed to deliver a seamless shopping experience:</p>
    <ul class="text-muted">
      <li><strong>Account details:</strong> name, email, password (encrypted), and optional phone number.</li>
      <li><strong>Shopping activity:</strong> saved recipes, pantry lists, and order history to help you reorder faster.</li>
      <li><strong>Location preferences:</strong> store locations you choose to follow or set as favourites.</li>
      <li><strong>Device and usage info:</strong> anonymised logs to improve performance, prevent fraud, and monitor uptime.</li>
    </ul>
  </section>

  <section class="privacy-section mb-4">
    <h3 class="mb-3"><i class="fas fa-key me-2 text-success"></i>How we use your data</h3>
    <ul class="text-muted mb-0">
      <li>Provide core features like ingredient search, cart management, and order processing.</li>
      <li>Recommend relevant recipes, deals, or nearby stores based on your interactions.</li>
      <li>Send essential notifications about orders, deliveries, or account security.</li>
      <li>Analyse usage trends (in aggregate) to build better tools for shoppers and merchants.</li>
    </ul>
  </section>

  <section class="privacy-section mb-4">
    <h3 class="mb-3"><i class="fas fa-user-shield me-2 text-warning"></i>Your choices &amp; controls</h3>
    <p class="text-muted">You decide what happens with your information:</p>
    <ul class="text-muted">
      <li><strong>Profile settings:</strong> update personal details or opt-in/out of marketing emails anytime.</li>
      <li><strong>Data export:</strong> request a copy of your grocery lists and order history via support@grocerygenie.my.</li>
      <li><strong>Data deletion:</strong> close your account and we’ll purge associated personal data within 30 days.</li>
      <li><strong>Cookie preferences:</strong> manage analytics cookies from your browser or device settings.</li>
    </ul>
  </section>

  <section class="privacy-section mb-4">
    <h3 class="mb-3"><i class="fas fa-lock me-2 text-danger"></i>Keeping your data secure</h3>
    <ul class="text-muted">
      <li>We encrypt all sensitive information in transit and at rest.</li>
      <li>Access to personal data is restricted to trained staff who need it to provide support.</li>
      <li>We use reputable payment partners and never store raw card details on our servers.</li>
      <li>Regular security audits ensure GroceryGenie meets or exceeds industry safeguards.</li>
    </ul>
  </section>

  <section class="privacy-section mb-5 privacy-faq">
    <h3 class="mb-3"><i class="fas fa-question-circle me-2 text-info"></i>Frequently asked questions</h3>
    <div class="mb-3">
      <details>
        <summary>Do you share my information with third parties? <i class="fas fa-angle-down"></i></summary>
        <p class="text-muted mt-3 mb-0">
          We never sell personal data. We only share information with delivery partners or payment processors when it’s necessary
          to complete your order, and always under strict confidentiality agreements.
        </p>
      </details>
    </div>
    <div class="mb-3">
      <details>
        <summary>What about data stored in my shopping cart? <i class="fas fa-angle-down"></i></summary>
        <p class="text-muted mt-3 mb-0">
          Cart contents are saved to your account to let you resume shopping seamlessly across devices. You can clear them anytime
          from your cart page.
        </p>
      </details>
    </div>
    <div>
      <details>
        <summary>Who can I contact about privacy matters? <i class="fas fa-angle-down"></i></summary>
        <p class="text-muted mt-3 mb-0">
          Email our Data Protection Officer at <a href="mailto:privacy@grocerygenie.my">privacy@grocerygenie.my</a>.
          We’re happy to clarify policies or handle special requests.
        </p>
      </details>
    </div>
  </section>

  <section class="bg-white rounded-3 shadow-sm p-4 p-lg-5 mb-5">
    <div class="row g-4 align-items-center">
      <div class="col-lg-8">
        <h2 class="fw-semibold mb-2">Still have questions?</h2>
        <p class="text-muted mb-0">Our support team would love to help. Drop us a note and we’ll respond within one business day.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a href="contact.php" class="gg-btn-primary"><i class="fas fa-envelope me-1"></i>Contact support</a>
      </div>
    </div>
  </section>
</main>

<?php include 'customer_footer.php'; ?>
