  <?php
  // customer/explore_recipes.php
  if (session_status() === PHP_SESSION_NONE) session_start();
  require_once __DIR__ . '/../db_connect.php'; // not used heavily, but consistent
  include 'customer_header.php';
  ?>

  <style>
    .explore-page {
      background: linear-gradient(130deg, #f5f5ff 0%, #fff8f0 45%, #ffffff 100%);
      min-height: 100vh;
    }
    .explore-hero {
      position: relative;
      padding: 70px 0 40px;
    }
    .explore-hero::after {
      content: "";
      position: absolute;
      inset: 0;
      background: url('../assets/img/pattern-dots.png') repeat;
      opacity: 0.08;
      pointer-events: none;
    }
    .explore-hero-card {
      position: relative;
      z-index: 1;
      background: rgba(255, 255, 255, 0.88);
      border-radius: 28px;
      padding: 2.8rem 2.5rem;
      box-shadow: 0 25px 60px rgba(79, 70, 229, 0.12);
      backdrop-filter: blur(6px);
      overflow: hidden;
    }
    .explore-hero-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.45rem 1rem;
      border-radius: 999px;
      background: rgba(99, 102, 241, 0.12);
      color: #4338ca;
      font-weight: 600;
      font-size: 0.85rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .explore-hero-title {
      font-size: 2.4rem;
      font-weight: 700;
      color: #1f2937;
      margin: 1rem 0 0.75rem;
    }
    .explore-hero-subtitle {
      color: #475569;
      max-width: 540px;
      font-size: 1rem;
    }
    .explore-filters {
      margin-top: 2rem;
      position: relative;
    }
    .explore-search-group {
      display: flex;
      gap: 0.85rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .search-wrapper {
      position: relative;
      flex: 1 1 280px;
    }
    .search-wrapper .search-icon {
      position: absolute;
      top: 50%;
      left: 1.05rem;
      transform: translateY(-50%);
      color: #9ca3af;
      pointer-events: none;
    }
    .explore-search-input {
      border-radius: 18px;
      border: 1px solid rgba(148, 163, 184, 0.5);
      padding: 0.85rem 1.1rem 0.85rem 2.5rem;
      font-size: 1rem;
      box-shadow: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .explore-search-input:focus {
      border-color: rgba(99, 102, 241, 0.75);
      box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
    }
    .btn-search {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      border: none;
      color: #fff;
      padding: 0.85rem 1.8rem;
      border-radius: 16px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      box-shadow: 0 18px 32px rgba(99, 102, 241, 0.28);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-search:hover,
    .btn-search:focus {
      color: #fff;
      transform: translateY(-1px);
      box-shadow: 0 22px 36px rgba(99, 102, 241, 0.32);
    }
    .powered-by {
      font-size: 0.85rem;
      color: #64748b;
      margin-top: 0.35rem;
    }
    .chip-row {
      display: flex;
      gap: 0.6rem;
      flex-wrap: wrap;
      margin-top: 1.4rem;
    }
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.45rem 1rem;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.4);
      background: #ffffff;
      color: #475569;
      font-weight: 500;
      font-size: 0.9rem;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
      transition: all 0.2s ease;
    }
    .chip:hover {
      border-color: rgba(99, 102, 241, 0.45);
      color: #4338ca;
      transform: translateY(-1px);
    }
    .chip.active {
      background: linear-gradient(135deg, #fde68a 0%, #fca5a5 100%);
      color: #7a341f;
      border-color: transparent;
      box-shadow: 0 10px 24px rgba(234, 179, 8, 0.25);
    }
    .explore-content {
      margin-top: 20px;
      padding-bottom: 70px;
    }
    .status-message {
      text-align: center;
      color: #64748b;
      font-size: 0.95rem;
      margin-top: 2rem;
    }
    .status-message strong {
      color: #1f2937;
    }
    .explore-grid {
      display: grid;
      gap: 24px;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      margin-top: 2.5rem;
    }
    .recipe-card {
      background: #ffffff;
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(226, 232, 240, 0.8);
      display: flex;
      flex-direction: column;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      cursor: pointer;
    }
    .recipe-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 26px 55px rgba(79, 70, 229, 0.18);
    }
    .recipe-thumb {
      width: 100%;
      height: 180px;
      object-fit: cover;
    }
    .recipe-body {
      padding: 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
    }
    .recipe-title {
      font-weight: 600;
      color: #1f2937;
      line-height: 1.35;
      min-height: 3.2rem;
    }
    .recipe-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: #64748b;
    }
    .recipe-meta span {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.8rem;
      border-radius: 999px;
      background: rgba(226, 232, 240, 0.6);
    }
    .recipe-cta {
      margin-top: auto;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      color: #6366f1;
      font-weight: 600;
      font-size: 0.9rem;
    }
    .recipe-cta i {
      transition: transform 0.2s ease;
    }
    .recipe-card:hover .recipe-cta i {
      transform: translateX(4px);
    }
    .note-tip {
      font-size: 0.92rem;
      color: #475569;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.65rem 1rem;
      border-radius: 14px;
      background: rgba(226, 232, 240, 0.45);
    }
    .modal-header {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      color: #fff;
      border-bottom: none;
    }
    .modal-header .btn-close {
      filter: invert(1);
    }
    .modal-body {
      padding: 1.8rem;
    }
    .modal-img {
      width: 100%;
      height: 260px;
      object-fit: cover;
      border-radius: 18px;
      box-shadow: 0 18px 35px rgba(15, 23, 42, 0.18);
    }
    .badge-cat {
      background: rgba(253, 224, 71, 0.2);
      color: #92400e;
      font-weight: 600;
      padding: 0.4rem 0.9rem;
      border-radius: 999px;
    }
    .badge-area {
      background: rgba(96, 165, 250, 0.2);
      color: #1d4ed8;
      padding: 0.4rem 0.9rem;
      border-radius: 999px;
    }
    #mealIngredients {
      columns: 2;
      column-gap: 1.5rem;
      padding-left: 1.2rem;
      color: #475569;
    }
    #mealInstructions {
      color: #1f2937;
      line-height: 1.6;
      background: rgba(248, 250, 252, 0.75);
      border-radius: 16px;
      padding: 1.1rem 1.2rem;
    }
    .muted {
      color: #64748b;
      font-size: 0.92rem;
    }
    .skeleton-card {
      position: relative;
      overflow: hidden;
      border-radius: 22px;
      background: rgba(226, 232, 240, 0.6);
      height: 280px;
    }
    .skeleton-shimmer {
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(226, 232, 240, 0.35) 0%, rgba(255, 255, 255, 0.75) 50%, rgba(226, 232, 240, 0.35) 100%);
      animation: shimmer 1.5s infinite;
    }
    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }
    @media (max-width: 991.98px) {
      .explore-hero {
        padding: 60px 0 30px;
      }
      .explore-hero-card {
        padding: 2.3rem 2rem;
      }
      #mealIngredients {
        columns: 1;
      }
    }
    @media (max-width: 767.98px) {
      .explore-search-group {
        flex-direction: column;
        align-items: stretch;
      }
      .btn-search {
        width: 100%;
        justify-content: center;
      }
      .chip-row {
        overflow-x: auto;
        padding-bottom: 0.35rem;
      }
      .chip {
        white-space: nowrap;
      }
      .explore-grid {
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      }
    }
  </style>

  <div class="explore-page">
    <div class="explore-hero">
      <div class="container">
        <div class="explore-hero-card">
          <div>
            <span class="explore-hero-chip"><i class="fas fa-globe-americas"></i> Global Pantry</span>
            <h1 class="explore-hero-title">Explore curated recipes for fresh inspiration</h1>
            <p class="explore-hero-subtitle">Search by ingredient, cuisine, or let the featured categories guide you. Tap any card to reveal ingredients and full cooking instructions pulled live from TheMealDB.</p>
          </div>
          <div class="explore-filters">
            <div class="explore-search-group">
              <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input id="q" type="text" class="form-control explore-search-input" placeholder="Try \"thai curry\", \"salmon\", or \"dessert\"">
              </div>
              <button id="btnSearch" class="btn btn-search"><i class="fas fa-compass"></i> Search Recipes</button>
            </div>
            <div class="powered-by"><i class="fas fa-bolt me-1 text-warning"></i> Powered by TheMealDB</div>
            <div id="chips" class="chip-row"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="explore-content container">
      <div id="statusMessage" class="status-message d-none"></div>
      <div id="resultGrid" class="explore-grid"></div>
      <div class="note-tip mt-4"><i class="fas fa-lightbulb text-warning"></i> Tip: Save your favourites as custom recipes in GroceryGenie for quick reorders.</div>
    </div>
  </div>

  <!-- Details Modal -->
  <div class="modal fade" id="mealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="mealTitle">Recipe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <img id="mealThumb" class="modal-img mb-3" alt="Meal image">
          <div class="mb-3">
            <span id="mealCategory" class="badge badge-cat"></span>
            <span id="mealArea" class="badge badge-area ms-1"></span>
          </div>
          <h6>Ingredients</h6>
          <ul id="mealIngredients"></ul>
          <h6 class="mt-3">Instructions</h6>
          <div id="mealInstructions" class="muted" style="white-space:pre-wrap;"></div>
        </div>
      </div>
    </div>
    
  </div>

  <script>
    const grid = document.getElementById('resultGrid');
    const chips = document.getElementById('chips');
    const q = document.getElementById('q');
    const btnSearch = document.getElementById('btnSearch');
    const statusMessage = document.getElementById('statusMessage');
    const DEFAULT_QUERY = 'chicken';
    const FALLBACK_THUMB = 'https://via.placeholder.com/400x300?text=Recipe';

    function setStatus(message = '', type = 'info') {
      if (!statusMessage) return;
      const icons = {
        loading: '<i class="fas fa-spinner fa-spin me-2 text-primary"></i>',
        info: '<i class="fas fa-info-circle me-2 text-primary"></i>',
        empty: '<i class="fas fa-utensils-slash me-2 text-warning"></i>',
        error: '<i class="fas fa-exclamation-triangle me-2 text-danger"></i>'
      };
      if (!message) {
        statusMessage.classList.add('d-none');
        statusMessage.innerHTML = '';
        return;
      }
      statusMessage.classList.remove('d-none');
      statusMessage.innerHTML = `${icons[type] || ''}${message}`;
    }

    function showLoadingCards(count = 6) {
      if (!grid) return;
      grid.innerHTML = '';
      setStatus('Searching for delicious ideas...', 'loading');
      for (let i = 0; i < count; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton-card';
        skeleton.innerHTML = '<div class="skeleton-shimmer"></div>';
        grid.appendChild(skeleton);
      }
    }

    async function getJSON(url) {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    }

    function clearActiveChips() {
      if (!chips) return;
      chips.querySelectorAll('.chip.active').forEach(x => x.classList.remove('active'));
    }

    function renderMeals(meals) {
      if (!grid) return;
      grid.innerHTML = '';
      if (!Array.isArray(meals) || meals.length === 0) {
        setStatus('No recipes matched your filters. Try another keyword or pick a different category.', 'empty');
        return;
      }
      setStatus('');
      meals.forEach(m => {
        const card = document.createElement('div');
        card.className = 'recipe-card';
        const thumb = m.strMealThumb || FALLBACK_THUMB;
        const title = m.strMeal || 'Recipe';
        const category = m.strCategory || '';
        const area = m.strArea || '';
        card.innerHTML = `
          <img src="${thumb}" alt="${title}" class="recipe-thumb" loading="lazy">
          <div class="recipe-body">
            <div class="recipe-title">${title}</div>
            <div class="recipe-meta">
              ${category ? `<span><i class="fas fa-tag"></i> ${category}</span>` : ''}
              ${area ? `<span><i class="fas fa-map-marker-alt"></i> ${area}</span>` : ''}
            </div>
            <span class="recipe-cta">View details <i class="fas fa-arrow-right"></i></span>
          </div>
        `;
        card.addEventListener('click', () => openDetails(m.idMeal));
        grid.appendChild(card);
      });
    }

    async function loadCategories() {
      if (!chips) return;
      chips.innerHTML = '<span class="muted">Loading popular categories...</span>';
      try {
        const j = await getJSON('../api/recipes_external.php?action=categories');
        const list = (j && j.meals) ? j.meals.slice(0, 20) : [];
        if (!list.length) {
          chips.innerHTML = '<span class="muted">Categories unavailable right now.</span>';
          return;
        }
        chips.innerHTML = '';
        list.forEach(c => {
          const el = document.createElement('span');
          el.className = 'chip';
          el.innerHTML = `<i class="fas fa-tag"></i> ${c.strCategory}`;
          el.addEventListener('click', async () => {
            const wasActive = el.classList.contains('active');
            clearActiveChips();
            if (wasActive) {
              if (q) q.value = '';
              await loadDefaultMeals();
              return;
            }
            if (q) q.value = '';
            el.classList.add('active');
            showLoadingCards();
            try {
              const res = await getJSON('../api/recipes_external.php?action=filter&c=' + encodeURIComponent(c.strCategory));
              renderMeals(res && res.meals ? res.meals : []);
            } catch (error) {
              if (grid) grid.innerHTML = '';
              setStatus('Unable to load that category right now. Please try again shortly.', 'error');
            }
          });
          chips.appendChild(el);
        });
      } catch (error) {
        chips.innerHTML = '<span class="muted">Failed to load categories.</span>';
      }
    }

    async function doSearch() {
      const term = (q && q.value ? q.value : '').trim();
      clearActiveChips();
      if (!term) {
        await loadDefaultMeals();
        return;
      }
      showLoadingCards();
      try {
        const res = await getJSON('../api/recipes_external.php?action=search&q=' + encodeURIComponent(term));
        renderMeals(res && res.meals ? res.meals : []);
      } catch (error) {
        if (grid) grid.innerHTML = '';
        setStatus('We could not reach the recipe service. Please try again in a moment.', 'error');
      }
    }

    async function loadDefaultMeals() {
      showLoadingCards();
      try {
        const res = await getJSON('../api/recipes_external.php?action=search&q=' + encodeURIComponent(DEFAULT_QUERY));
        renderMeals(res && res.meals ? res.meals : []);
      } catch (error) {
        if (grid) grid.innerHTML = '';
        setStatus('Unable to load recipes right now. Please refresh the page later.', 'error');
      }
    }

    async function openDetails(id) {
      try {
        const res = await getJSON('../api/recipes_external.php?action=details&id=' + encodeURIComponent(id));
        const m = res && res.meals && res.meals[0] ? res.meals[0] : null;
        if (!m) return;
        document.getElementById('mealTitle').textContent = m.strMeal || 'Recipe';
        document.getElementById('mealThumb').src = m.strMealThumb || FALLBACK_THUMB;
        document.getElementById('mealThumb').alt = m.strMeal || 'Meal';
        document.getElementById('mealCategory').textContent = m.strCategory || 'Unknown category';
        document.getElementById('mealArea').textContent = m.strArea || 'Unknown origin';
        const ul = document.getElementById('mealIngredients');
        if (ul) {
          ul.innerHTML = '';
          for (let i = 1; i <= 20; i++) {
            const ing = m['strIngredient' + i];
            const mea = m['strMeasure' + i];
            if (ing && ing.trim() !== '') {
              const li = document.createElement('li');
              li.textContent = ing + (mea ? ' - ' + mea : '');
              ul.appendChild(li);
            }
          }
        }
        document.getElementById('mealInstructions').textContent = m.strInstructions || 'No instructions available.';
        const modal = new bootstrap.Modal(document.getElementById('mealModal'));
        modal.show();
      } catch (error) {
        setStatus('Unable to load recipe details at the moment.', 'error');
      }
    }

    if (btnSearch) {
      btnSearch.addEventListener('click', doSearch);
    }
    if (q) {
      q.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          doSearch();
        }
      });
      q.addEventListener('input', () => {
        if (!(q.value || '').trim()) {
          clearActiveChips();
          loadDefaultMeals();
        }
      });
    }

    (async function init() {
      await loadCategories();
      await loadDefaultMeals();
    })();
  </script>

  <?php include 'customer_footer.php'; ?>
