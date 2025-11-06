<?php
// customer/contact.php
include 'customer_header.php';
?>

<style>
  .contact-hero {
    background: linear-gradient(135deg, rgba(16,185,129,0.12), rgba(59,130,246,0.12));
    border-radius: var(--gg-radius-lg);
    padding: 3rem 2.5rem;
    box-shadow: var(--gg-shadow-soft);
  }
  .contact-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 2rem;
    height: 100%;
  }
  .contact-card .icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    font-size: 1.1rem;
  }
  .contact-form .form-control,
  .contact-form .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .contact-form .form-control:focus,
  .contact-form .form-select:focus {
    box-shadow: 0 0 0 3px rgba(59,130,246,0.25);
  }
  .contact-info-list li {
    margin-bottom: 1.2rem;
  }
  #mapPreview {
    width: 100%;
    height: 260px;
    border-radius: var(--gg-radius-md);
    border: none;
    box-shadow: var(--gg-shadow-soft);
  }
</style>

<main class="container my-5">
  <section class="contact-hero mb-5">
    <div class="row g-4 align-items-center">
      <div class="col-lg-7">
        <span class="gg-hero-eyebrow"><i class="fas fa-comments"></i> Contact us</span>
        <h1 class="display-6 fw-semibold mb-3">We love hearing from our shoppers and store partners.</h1>
        <p class="lead text-muted mb-4">
          Whether you’re looking for support, want to collaborate, or simply have feedback about your GroceryGenie experience,
          our team is just a message away. Fill out the form or reach out through the channels below.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="mailto:hello@grocerygenie.my" class="gg-btn-primary"><i class="fas fa-envelope me-2"></i>Email support</a>
          <a href="tel:+601155002200" class="gg-btn-outline"><i class="fas fa-phone me-2"></i>+60 11-5500 2200</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="bg-white rounded-3 shadow-sm p-4">
          <h4 class="fw-semibold mb-3">Operating hours</h4>
          <ul class="list-unstyled mb-0 text-muted">
            <li class="mb-2"><strong>Weekdays:</strong> 9.00am – 8.00pm</li>
            <li class="mb-2"><strong>Saturday:</strong> 10.00am – 6.00pm</li>
            <li class="mb-0"><strong>Sunday &amp; public holidays:</strong> Closed ❤️</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="row g-4 mb-5">
    <div class="col-lg-7">
      <div class="contact-card contact-form">
        <h2 class="fw-semibold mb-3"><i class="fas fa-paper-plane me-2 text-primary"></i>Send us a note</h2>
        <p class="text-muted mb-4">Drop in your questions or feedback and we’ll get back to you within one business day.</p>
        <form method="POST" action="#" onsubmit="alert('Thanks for reaching out! Our support team will contact you soon.'); return false;">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input type="text" class="form-control" placeholder="Your name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" placeholder="name@email.com" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Reason</label>
              <select class="form-select" required>
                <option value="" selected disabled>Select a topic</option>
                <option value="orders">Order &amp; delivery</option>
                <option value="account">Account support</option>
                <option value="partnership">Partnership enquiry</option>
                <option value="feedback">Product feedback</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone (optional)</label>
              <input type="tel" class="form-control" placeholder="+60 xx-xxxxxxx">
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea class="form-control" rows="5" placeholder="Tell us how we can help..." required></textarea>
            </div>
            <div class="col-12 text-end">
              <button type="submit" class="gg-btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit message</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="contact-card">
        <h2 class="fw-semibold mb-3"><i class="fas fa-map-marker-alt me-2 text-danger"></i>Visit our hub</h2>
        <p class="text-muted">
          GroceryGenie HQ<br>
          27 Jalan Tun Dr Awang,<br>
          Bayan Lepas, 11900 Penang<br>
          Malaysia
        </p>
        <iframe
          id="mapPreview"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3976.817471715487!2d100.276672!3d5.285153!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x304ac0a0d9c9d7ed%3A0x7b51b6fcd508c45d!2sBayan%20Lepas!5e0!3m2!1sen!2smy!4v1700000000000"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"></iframe>
        <hr class="my-4">
        <h5 class="fw-semibold mb-3">Quick contacts</h5>
        <ul class="list-unstyled contact-info-list text-muted mb-0">
          <li><i class="fas fa-life-ring me-2 text-primary"></i><strong>Customer care:</strong> support@grocerygenie.my</li>
          <li><i class="fas fa-store me-2 text-success"></i><strong>Store owners:</strong> partners@grocerygenie.my</li>
          <li><i class="fas fa-briefcase me-2 text-warning"></i><strong>Careers:</strong> talents@grocerygenie.my</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="bg-white rounded-3 shadow-sm p-4 p-lg-5 mb-5">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h2 class="fw-semibold mb-2">Prefer chatting with us?</h2>
        <p class="text-muted mb-0">Send us a DM on Instagram at <a href="https://instagram.com/grocerygenie" target="_blank" rel="noopener">@grocerygenie</a> or message us on WhatsApp at <strong>+60 11-5500 2200</strong>.</p>
      </div>
      <div class="col-lg-5 text-lg-end">
        <a href="https://wa.me/601155002200" target="_blank" rel="noopener" class="gg-btn-primary"><i class="fab fa-whatsapp me-2"></i>Chat on WhatsApp</a>
      </div>
    </div>
  </section>
</main>

<?php include 'customer_footer.php'; ?>
