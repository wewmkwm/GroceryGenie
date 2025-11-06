<?php
// customer/settings.php — client-side preferences (no DB)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'customer_header.php';
?>

<div class="container customer-settings my-4">
  <div class="row g-4 align-items-center mb-4">
    <div class="col-md-auto">
      <div class="settings-avatar">
        <i class="fas fa-sliders-h"></i>
      </div>
    </div>
    <div class="col">
      <h2 class="mb-1 fw-bold">Display &amp; accessibility</h2>
      <p class="text-muted mb-0">Fine-tune how GroceryGenie looks and feels on this browser. These preferences stay on this device only.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold border-0 pb-0">Appearance</div>
        <div class="card-body">
          <div class="settings-preview mb-4" data-preview>
            <div class="settings-preview__meta mb-2">
              <span class="badge rounded-pill bg-light text-dark" data-preview-mode>Theme: Auto · Light</span>
              <span class="badge rounded-pill bg-light text-dark" data-preview-contrast>Contrast: Standard</span>
            </div>
            <div class="settings-preview__card">
              <div class="settings-preview__toolbar">
                <span class="settings-preview__dot settings-preview__dot--red"></span>
                <span class="settings-preview__dot settings-preview__dot--yellow"></span>
                <span class="settings-preview__dot settings-preview__dot--green"></span>
              </div>
              <h6 class="fw-semibold mb-2">Sample heading</h6>
              <p class="mb-3">Preview updates in real-time so you can see how pages will look with the current settings.</p>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm px-3">Primary action</button>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3">Secondary</button>
              </div>
            </div>
            <div class="settings-preview__meta mt-3">
              <span class="badge rounded-pill bg-light text-dark" data-preview-font>Text size: Default</span>
              <span class="badge rounded-pill bg-light text-dark" data-preview-motion>Motion: Standard</span>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="themeMode">Color theme</label>
            <select id="themeMode" class="form-select">
              <option value="auto">Auto (match system)</option>
              <option value="light">Light</option>
              <option value="dark">Dark</option>
            </select>
            <div class="form-text">Choose how pages should look. Auto keeps up with your operating system.</div>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="hcToggle">
            <label class="form-check-label fw-semibold" for="hcToggle">High contrast</label>
            <div class="form-text">Boosts color contrast and outlines to improve readability.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="fsSelect">Text size</label>
            <select id="fsSelect" class="form-select">
              <option value="">Default</option>
              <option value="lg">Large</option>
            </select>
            <div class="form-text">Increase base font size across GroceryGenie for easier reading.</div>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="rmToggle">
            <label class="form-check-label fw-semibold" for="rmToggle">Reduce motion</label>
            <div class="form-text">Limits non-essential animations and transitions.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold border-0 pb-0">Guide</div>
        <div class="card-body">
          <div class="settings-guide">
            <h6 class="fw-semibold d-flex align-items-center gap-2"><i class="fas fa-moon text-warning"></i>Dark theme</h6>
            <p class="text-muted mb-3">Darken backgrounds and soften contrast to reduce eye strain in low-light conditions. Works best when paired with system-wide dark mode.</p>

            <h6 class="fw-semibold d-flex align-items-center gap-2"><i class="fas fa-universal-access text-primary"></i>High contrast</h6>
            <p class="text-muted mb-3">Adds stronger borders and ramps up foreground contrast to assist visibility. Handy on bright screens or for low-vision accessibility.</p>

            <h6 class="fw-semibold d-flex align-items-center gap-2"><i class="fas fa-text-height text-success"></i>Text size</h6>
            <p class="text-muted mb-3">Switch to large text to scale up buttons, labels, and paragraphs at once. Ideal for distance viewing.</p>

            <h6 class="fw-semibold d-flex align-items-center gap-2"><i class="fas fa-wind text-info"></i>Reduce motion</h6>
            <p class="text-muted mb-0">Turns off parallax effects and lengthy transitions. Recommended if animations cause distraction or discomfort.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-4 d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
    <div class="settings-feedback alert alert-info py-2 px-3 d-none" role="status" data-settings-status></div>
    <div class="ms-sm-auto d-flex gap-2">
      <button id="saveBtn" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button>
      <button id="resetBtn" class="btn btn-outline-secondary">Reset</button>
    </div>
  </div>
</div>

<style>
  .customer-settings .card {
    border-radius: var(--gg-radius-md, 18px);
  }
  .settings-avatar {
    width: 72px;
    height: 72px;
    border-radius: 24px;
    background: linear-gradient(135deg, rgba(255, 165, 76, 0.2), rgba(255, 211, 130, 0.35));
    display: grid;
    place-items: center;
    font-size: 1.8rem;
    color: var(--gg-primary, #ff8a4c);
    box-shadow: inset 0 0 0 1px rgba(255, 138, 76, 0.3);
  }
  .settings-preview {
    border-radius: var(--gg-radius-md, 16px);
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: linear-gradient(135deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.9));
    padding: 1.5rem;
    color: var(--gg-heading, #1f2937);
    transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
  }
  .settings-preview .badge {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
  .settings-preview__card {
    background: rgba(255, 255, 255, 0.85);
    border-radius: var(--gg-radius-md, 16px);
    padding: 1.1rem 1.25rem;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
    backdrop-filter: blur(6px);
    position: relative;
    overflow: hidden;
    animation: settingsPreviewFloat 8s ease-in-out infinite;
  }
  .settings-preview__toolbar {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.9rem;
  }
  .settings-preview__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    opacity: 0.9;
  }
  .settings-preview__dot--red { background: #ff5f56; }
  .settings-preview__dot--yellow { background: #ffbd2e; }
  .settings-preview__dot--green { background: #27c93f; }
  .settings-preview.theme-dark {
    background: linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(30, 41, 59, 0.92));
    color: rgba(229, 231, 235, 0.96);
    border-color: rgba(148, 163, 184, 0.22);
  }
  .settings-preview.theme-dark .settings-preview__card {
    background: rgba(30, 41, 59, 0.85);
    color: rgba(226, 232, 240, 0.95);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.5);
  }
  .settings-preview.theme-dark .badge {
    background: rgba(15, 23, 42, 0.35) !important;
    color: rgba(226, 232, 240, 0.95) !important;
  }
  .settings-preview.hc {
    border-width: 2px;
    border-color: #ffd166;
    box-shadow: 0 0 0 3px rgba(255, 209, 102, 0.25);
  }
  .settings-preview.hc .settings-preview__card {
    outline: 2px solid #ffd166;
  }
  .settings-preview.fs-lg {
    font-size: 1.05rem;
  }
  .settings-preview.rm .settings-preview__card {
    animation: none;
  }
  .settings-preview.rm .settings-preview__card::after {
    content: 'Motion reduced';
    position: absolute;
    bottom: 0.75rem;
    right: 1rem;
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 600;
    color: currentColor;
    opacity: 0.6;
  }
  @keyframes settingsPreviewFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
  }
  .settings-guide p {
    font-size: 0.95rem;
    line-height: 1.55;
  }
  .settings-feedback {
    min-width: 260px;
  }
  @media (max-width: 575px) {
    .settings-avatar {
      width: 60px;
      height: 60px;
      font-size: 1.4rem;
    }
    .settings-preview {
      padding: 1.2rem;
    }
  }
</style>

<script>
  (function () {
    const docEl = document.documentElement;
    const themeMode = document.getElementById('themeMode');
    const hcToggle = document.getElementById('hcToggle');
    const fsSelect = document.getElementById('fsSelect');
    const rmToggle = document.getElementById('rmToggle');
    const preview = document.querySelector('[data-preview]');
    const previewMode = document.querySelector('[data-preview-mode]');
    const previewContrast = document.querySelector('[data-preview-contrast]');
    const previewFont = document.querySelector('[data-preview-font]');
    const previewMotion = document.querySelector('[data-preview-motion]');
    const statusEl = document.querySelector('[data-settings-status]');
    const saveBtn = document.getElementById('saveBtn');
    const resetBtn = document.getElementById('resetBtn');
    const darkQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    const defaults = Object.freeze({
      themeMode: 'auto',
      hc: false,
      fs: '',
      rm: false
    });

    let prefs = loadStoredPreferences();
    let savedPrefs = { ...prefs };
    applyState(prefs, { syncControls: true, broadcast: false });
    refreshButtons();

    function loadStoredPreferences() {
      return {
        themeMode: localStorage.getItem('themeMode') || defaults.themeMode,
        hc: localStorage.getItem('hc') === '1',
        fs: localStorage.getItem('fs') || defaults.fs,
        rm: localStorage.getItem('rm') === '1'
      };
    }

    function applyToDocument(state) {
      const isDark = computeIsDark(state);
      docEl.classList.toggle('theme-dark', isDark);
      docEl.classList.toggle('hc', state.hc);
      docEl.classList.toggle('fs-lg', state.fs === 'lg');
      docEl.classList.toggle('rm', state.rm);
      docEl.setAttribute('data-theme-mode', state.themeMode);
    }

    function applyToPreview(state) {
      if (!preview) return;
      const isDark = computeIsDark(state);
      preview.classList.toggle('theme-dark', isDark);
      preview.classList.toggle('hc', state.hc);
      preview.classList.toggle('fs-lg', state.fs === 'lg');
      preview.classList.toggle('rm', state.rm);

      if (previewMode) {
        let label;
        if (state.themeMode === 'auto') {
          label = isDark ? 'Theme: Auto · Dark' : 'Theme: Auto · Light';
        } else {
          label = state.themeMode === 'dark' ? 'Theme: Dark' : 'Theme: Light';
        }
        previewMode.textContent = label;
      }
      if (previewContrast) {
        previewContrast.textContent = state.hc ? 'Contrast: High' : 'Contrast: Standard';
      }
      if (previewFont) {
        previewFont.textContent = state.fs === 'lg' ? 'Text size: Large' : 'Text size: Default';
      }
      if (previewMotion) {
        previewMotion.textContent = state.rm ? 'Motion: Reduced' : 'Motion: Standard';
      }
    }

    function applyState(state, options = {}) {
      const { syncControls = false, broadcast = true } = options;
      if (syncControls) {
        themeMode.value = state.themeMode;
        hcToggle.checked = !!state.hc;
        fsSelect.value = state.fs;
        rmToggle.checked = !!state.rm;
      }
      applyToDocument(state);
      applyToPreview(state);
      if (broadcast) {
        announce('Preview updated. Remember to save to keep these changes.', 'info');
      }
    }

    function computeIsDark(state) {
      const prefersDark = darkQuery ? darkQuery.matches : false;
      return state.themeMode === 'dark' || (state.themeMode === 'auto' && prefersDark);
    }

    function persistState(state) {
      localStorage.setItem('themeMode', state.themeMode);
      if (state.hc) {
        localStorage.setItem('hc', '1');
      } else {
        localStorage.removeItem('hc');
      }
      if (state.fs) {
        localStorage.setItem('fs', state.fs);
      } else {
        localStorage.removeItem('fs');
      }
      if (state.rm) {
        localStorage.setItem('rm', '1');
      } else {
        localStorage.removeItem('rm');
      }
    }

    function announce(message, variant = 'info', autoHide = true) {
      if (!statusEl) return;
      statusEl.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-warning');
      statusEl.classList.add(`alert-${variant}`);
      statusEl.textContent = message;
      if (autoHide) {
        clearTimeout(announce._timer);
        announce._timer = setTimeout(() => {
          statusEl.classList.add('d-none');
        }, 3500);
      }
    }

    function refreshButtons() {
      const changed = hasChanged();
      saveBtn.disabled = !changed;
      resetBtn.disabled = !changed && !hasNonDefault(savedPrefs);
      saveBtn.classList.toggle('btn-secondary', !changed);
      saveBtn.classList.toggle('btn-primary', changed);
    }

    function hasChanged() {
      return ['themeMode', 'hc', 'fs', 'rm'].some(key => prefs[key] !== savedPrefs[key]);
    }

    function hasNonDefault(state) {
      return state.themeMode !== defaults.themeMode ||
             state.hc !== defaults.hc ||
             state.fs !== defaults.fs ||
             state.rm !== defaults.rm;
    }

    function setPreference(partial) {
      prefs = { ...prefs, ...partial };
      applyState(prefs);
      refreshButtons();
    }

    themeMode.addEventListener('change', () => setPreference({ themeMode: themeMode.value }));
    hcToggle.addEventListener('change', () => setPreference({ hc: hcToggle.checked }));
    fsSelect.addEventListener('change', () => setPreference({ fs: fsSelect.value }));
    rmToggle.addEventListener('change', () => setPreference({ rm: rmToggle.checked }));

    saveBtn.addEventListener('click', () => {
      persistState(prefs);
      savedPrefs = { ...prefs };
      announce('Preferences saved for this browser.', 'success', true);
      refreshButtons();
    });

    resetBtn.addEventListener('click', () => {
      prefs = { ...defaults };
      applyState(prefs, { syncControls: true });
      refreshButtons();
    });

    if (darkQuery && darkQuery.addEventListener) {
      darkQuery.addEventListener('change', () => {
        applyState(prefs, { broadcast: prefs.themeMode === 'auto' });
        if (prefs.themeMode === 'auto') {
          announce('System theme changed. Auto mode updated to match.', 'info');
        }
      });
    }
  })();
</script>

<?php include 'customer_footer.php'; ?>
