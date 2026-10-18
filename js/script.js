document.addEventListener('DOMContentLoaded', function () {

  // ── Page loader ────────────────────────────────────────────────
  (function() {
    const pageLoader = document.getElementById('page-loader');
    if (!pageLoader) return;

    const minDisplay = 1600;
    const startTime  = Date.now();

    function hideLoader() {
      const elapsed = Date.now() - startTime;
      const delay   = Math.max(0, minDisplay - elapsed);
      setTimeout(function() {
        pageLoader.classList.add('fade-out');
        setTimeout(function() { pageLoader.remove(); }, 600);
      }, delay);
    }

    // Check all three ready states
    if (document.readyState === 'complete') {
      // Already loaded — hide after min display time
      hideLoader();
    } else if (document.readyState === 'interactive') {
      // DOM ready but resources still loading
      window.addEventListener('load', hideLoader, { once: true });
      setTimeout(hideLoader, 3500); // hard fallback
    } else {
      window.addEventListener('load', hideLoader, { once: true });
      setTimeout(hideLoader, 3500); // hard fallback
    }
  })();


  // ---------- Booking form + availability calendar (book.php only) ----------
  const form = document.getElementById('booking-form');
  if (form) {
    let bookedRanges = [];
    let pricePerNight = window.QUATTRO_PRICE_PER_NIGHT || 0;
    let currency = window.QUATTRO_CURRENCY || 'KES';
    let minStay = window.QUATTRO_MIN_STAY || 1;
    let discountPercent = window.QUATTRO_DISCOUNT_PERCENT || 0;
    let discountMinNights = window.QUATTRO_DISCOUNT_MIN_NIGHTS || 0;
    let includedGuests = window.QUATTRO_INCLUDED_GUESTS || 2;
    let extraGuestFee = window.QUATTRO_EXTRA_GUEST_FEE || 0;
    let viewYear, viewMonth;

    const today = new Date();
    viewYear = today.getFullYear();
    viewMonth = today.getMonth();

    const calGrid = document.getElementById('cal-grid');
    const calLoading = document.getElementById('cal-loading');
    const calLabel = document.getElementById('cal-month-label');
    const prevBtn = document.getElementById('cal-prev');
    const nextBtn = document.getElementById('cal-next');

    function dateToStr(d) { return d.toISOString().split('T')[0]; }

    function isBooked(dateStr) {
      return bookedRanges.some(r => dateStr >= r.checkin_date && dateStr < r.checkout_date);
    }

    function renderCalendar() {
      calGrid.innerHTML = '';
      const dows = ['Su','Mo','Tu','We','Th','Fr','Sa'];
      dows.forEach(d => {
        const el = document.createElement('div');
        el.className = 'dow';
        el.textContent = d;
        calGrid.appendChild(el);
      });

      const firstDay = new Date(viewYear, viewMonth, 1);
      const startOffset = firstDay.getDay();
      const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

      for (let i = 0; i < startOffset; i++) {
        const el = document.createElement('div');
        el.className = 'cal-day empty';
        calGrid.appendChild(el);
      }

      const todayStr = dateToStr(new Date());

      for (let day = 1; day <= daysInMonth; day++) {
        const cellDate = new Date(viewYear, viewMonth, day);
        const cellStr = dateToStr(cellDate);
        const el = document.createElement('div');
        el.textContent = day;
        el.className = 'cal-day';

        if (cellStr < todayStr) {
          el.classList.add('past');
        } else if (isBooked(cellStr)) {
          el.classList.add('booked');
        } else {
          el.classList.add('available');
        }
        calGrid.appendChild(el);
      }

      const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      calLabel.textContent = monthNames[viewMonth] + ' ' + viewYear;
    }

    let currentCalFloor = 'floor2';
    const floorSelect = document.getElementById('floor');
    if (floorSelect) currentCalFloor = floorSelect.value || 'floor2';

    function fetchBookedDates(floor) {
      if (calLoading) calLoading.style.display = 'flex';
      fetch('get_booked_dates.php?floor=' + encodeURIComponent(floor))
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            bookedRanges = data.ranges;
            if (data.price_per_night) pricePerNight = data.price_per_night;
            if (data.currency) currency = data.currency;
            if (data.min_stay_nights) minStay = data.min_stay_nights;
            if (data.discount_percent !== undefined) discountPercent = data.discount_percent;
            if (data.discount_min_nights !== undefined) discountMinNights = data.discount_min_nights;
            if (data.included_guests !== undefined) includedGuests = data.included_guests;
            if (data.extra_guest_fee !== undefined) extraGuestFee = data.extra_guest_fee;
          }
          renderCalendar();
          if (typeof updatePriceSummary === 'function') updatePriceSummary();
        })
        .catch(() => renderCalendar())
        .finally(() => { if (calLoading) calLoading.style.display = 'none'; });
    }

    prevBtn.addEventListener('click', () => {
      viewMonth--;
      if (viewMonth < 0) { viewMonth = 11; viewYear--; }
      renderCalendar();
    });
    nextBtn.addEventListener('click', () => {
      viewMonth++;
      if (viewMonth > 11) { viewMonth = 0; viewYear++; }
      renderCalendar();
    });

    fetchBookedDates(currentCalFloor);

    // Floor selection: keep booking form + calendar in sync
    const calFloorTabs = document.querySelectorAll('.cal-floor-tab');
    function setCalFloor(floor) {
      currentCalFloor = floor;
      calFloorTabs.forEach(t => t.classList.toggle('active', t.getAttribute('data-cal-floor') === floor));
      fetchBookedDates(floor);
    }
    calFloorTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const floor = tab.getAttribute('data-cal-floor');
        if (floorSelect) floorSelect.value = floor;
        setCalFloor(floor);
      });
    });
    if (floorSelect) {
      floorSelect.addEventListener('change', () => setCalFloor(floorSelect.value));
    }

    const checkinInput = document.getElementById('checkin_date');
    const checkoutInput = document.getElementById('checkout_date');
    const todayISO = dateToStr(new Date());
    checkinInput.setAttribute('min', todayISO);
    checkoutInput.setAttribute('min', todayISO);

    const priceSummary = document.getElementById('price-summary');
    const priceNights = document.getElementById('price-nights');
    const priceTotal = document.getElementById('price-total');
    const feedback = document.getElementById('form-feedback');
    const submitBtn = document.getElementById('submit-btn');
    const guestsInput = document.getElementById('guests');

    function rangeOverlapsBooked(inVal, outVal) {
      return bookedRanges.some(r => inVal < r.checkout_date && outVal > r.checkin_date);
    }

    function updatePriceSummary() {
      const inVal = checkinInput.value;
      const outVal = checkoutInput.value;
      if (!inVal || !outVal) { priceSummary.style.display = 'none'; return; }

      const inDate = new Date(inVal);
      const outDate = new Date(outVal);
      const nights = Math.round((outDate - inDate) / (1000 * 60 * 60 * 24));

      if (nights <= 0) { priceSummary.style.display = 'none'; return; }

      if (rangeOverlapsBooked(inVal, outVal)) {
        priceSummary.style.display = 'none';
        feedback.textContent = 'One or more of the selected dates is already booked. Please choose different dates.';
        feedback.className = 'error';
        submitBtn.disabled = true;
        return;
      }
      if (submitBtn.disabled && feedback.textContent.indexOf('already booked') !== -1) {
        submitBtn.disabled = false;
      }

      const guests = Math.max(1, parseInt(guestsInput.value, 10) || 1);
      const extraGuests = Math.max(0, guests - includedGuests);
      const floorMultiplier = (floorSelect && floorSelect.value === 'both') ? 2 : 1;
      const subtotal = (pricePerNight * floorMultiplier * nights) + (extraGuestFee * extraGuests * nights);
      const discountApplies = discountPercent > 0 && discountMinNights > 0 && nights >= discountMinNights;
      const total = discountApplies ? subtotal * (1 - discountPercent / 100) : subtotal;

      priceSummary.style.display = 'flex';
      priceNights.textContent = nights + (nights === 1 ? ' night' : ' nights')
        + (floorMultiplier === 2 ? ' · both floors' : '')
        + (extraGuests > 0 && extraGuestFee > 0 ? ' · ' + extraGuests + ' extra guest(s)' : '')
        + (discountApplies ? ' · ' + discountPercent + '% off applied' : '');
      priceTotal.textContent = currency + ' ' + Math.round(total).toLocaleString();

      if (nights < minStay) {
        feedback.textContent = 'Minimum stay is ' + minStay + ' night(s).';
        feedback.className = 'error';
      } else if (feedback.classList.contains('error') && (feedback.textContent.indexOf('Minimum stay') === 0 || feedback.textContent.indexOf('already booked') !== -1)) {
        feedback.style.display = 'none';
        feedback.className = '';
      }
    }

    checkinInput.addEventListener('input', () => {
      checkoutInput.setAttribute('min', checkinInput.value);
      updatePriceSummary();
    });
    checkinInput.addEventListener('change', () => {
      checkoutInput.setAttribute('min', checkinInput.value);
      updatePriceSummary();
    });
    checkoutInput.addEventListener('input', updatePriceSummary);
    checkoutInput.addEventListener('change', updatePriceSummary);
    guestsInput.addEventListener('input', updatePriceSummary);
    guestsInput.addEventListener('change', updatePriceSummary);

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (rangeOverlapsBooked(checkinInput.value, checkoutInput.value)) {
        feedback.textContent = 'One or more of the selected dates is already booked. Please choose different dates.';
        feedback.className = 'error';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span>Sending...';
      feedback.className = '';
      feedback.style.display = 'none';

      const formData = new FormData(form);

      fetch('process_booking.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            // Replace entire form with success card
            const formWrap = form.closest('.card') || form.parentElement;
            formWrap.innerHTML = `
              <div class="booking-success">
                <div class="booking-success-icon"><i class="fa-solid fa-circle-check"></i></div>
                <h3>Request received!</h3>
                <p>${data.message}</p>
                <p class="booking-success-note">We will contact you on WhatsApp or phone to confirm. Check your email for a summary.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top:24px;display:inline-block;">Back to Home</a>
              </div>
            `;
            // Refresh calendar if visible
            if (typeof fetchBookedDates === 'function') fetchBookedDates(typeof currentCalFloor !== 'undefined' ? currentCalFloor : 'floor2');
          } else {
            feedback.textContent = data.message;
            feedback.className = 'error';
            feedback.style.display = 'block';
          }
        })
        .catch(() => {
          feedback.textContent = 'Network error. Please try again or WhatsApp us directly.';
          feedback.className = 'error';
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.textContent = typeof currentLang !== 'undefined' && currentLang === 'sw' ? 'Omba Kuweka Nafasi' : 'Request to Book';
        });
    });

    // Auto-apply referral code from a shared link (?ref=QH1023)
    const referralInput = document.getElementById('referral_code');
    const referralFieldWrap = document.getElementById('referral-field-wrap');
    const referralAppliedNote = document.getElementById('referral-applied-note');
    const urlParams = new URLSearchParams(window.location.search);
    const refFromUrl = urlParams.get('ref');
    if (refFromUrl && referralInput) {
      referralInput.value = refFromUrl.toUpperCase();
      if (referralFieldWrap) referralFieldWrap.style.display = 'none';
      if (referralAppliedNote) referralAppliedNote.style.display = 'flex';
    }
  }

  // ---------- Hero photo carousel (index.php) ----------
  const heroSlidesWrap = document.getElementById('hero-slides');
  const heroDotsWrap = document.getElementById('hero-dots');
  if (heroSlidesWrap) {
    const slides = heroSlidesWrap.querySelectorAll('.hero-slide');
    let heroIndex = 0;

    slides.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.setAttribute('aria-label', 'Go to photo ' + (i + 1));
      if (i === 0) dot.classList.add('active');
      dot.addEventListener('click', () => showHeroSlide(i));
      heroDotsWrap.appendChild(dot);
    });
    const dots = heroDotsWrap.querySelectorAll('button');

    function showHeroSlide(i) {
      slides.forEach(s => s.classList.remove('active'));
      dots.forEach(d => d.classList.remove('active'));
      slides[i].classList.add('active');
      dots[i].classList.add('active');
      heroIndex = i;
    }

    setInterval(() => {
      showHeroSlide((heroIndex + 1) % slides.length);
    }, 5000);
  }


  // ---------- Star rating widget (testimonials.php) ----------
  const starButtons = document.querySelectorAll('#star-rating button');
  const ratingInput = document.getElementById('rating');
  if (starButtons.length && ratingInput) {
    starButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const val = parseInt(btn.getAttribute('data-value'), 10);
        ratingInput.value = val;
        starButtons.forEach(b => {
          b.classList.toggle('active', parseInt(b.getAttribute('data-value'), 10) <= val);
        });
      });
    });
  }

  // ---------- Review form submission (testimonials.php) ----------
  const reviewForm = document.getElementById('review-form');
  if (reviewForm) {
    const reviewFeedback = document.getElementById('review-feedback');
    const reviewSubmitBtn = document.getElementById('review-submit-btn');
    reviewForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (ratingInput.value === '0') {
        reviewFeedback.textContent = 'Please select a star rating.';
        reviewFeedback.className = 'error';
        return;
      }
      reviewSubmitBtn.disabled = true;
      reviewSubmitBtn.innerHTML = '<span class="spinner"></span>Sending...';
      reviewFeedback.className = '';
      reviewFeedback.style.display = 'none';

      fetch('submit_review.php', { method: 'POST', body: new FormData(reviewForm) })
        .then(res => res.json())
        .then(data => {
          reviewFeedback.textContent = data.message;
          reviewFeedback.className = data.success ? 'success' : 'error';
          if (data.success) {
            reviewForm.reset();
            starButtons.forEach(b => b.classList.remove('active'));
            ratingInput.value = '0';
          }
        })
        .catch(() => {
          reviewFeedback.textContent = 'Network error. Please try again.';
          reviewFeedback.className = 'error';
        })
        .finally(() => {
          reviewSubmitBtn.disabled = false;
          reviewSubmitBtn.textContent = 'Submit Review';
        });
    });
  }

  // ---------- Issue report form submission (report-issue.php) ----------
  const issueForm = document.getElementById('issue-form');
  if (issueForm) {
    const issueFeedback = document.getElementById('issue-feedback');
    const issueSubmitBtn = document.getElementById('issue-submit-btn');
    issueForm.addEventListener('submit', function (e) {
      e.preventDefault();
      issueSubmitBtn.disabled = true;
      issueSubmitBtn.innerHTML = '<span class="spinner"></span>Sending...';
      issueFeedback.className = '';
      issueFeedback.style.display = 'none';

      fetch('report_issue.php', { method: 'POST', body: new FormData(issueForm) })
        .then(res => res.json())
        .then(data => {
          issueFeedback.textContent = data.message;
          issueFeedback.className = data.success ? 'success' : 'error';
          if (data.success) issueForm.reset();
        })
        .catch(() => {
          issueFeedback.textContent = 'Network error. Please try again or WhatsApp us directly.';
          issueFeedback.className = 'error';
        })
        .finally(() => {
          issueSubmitBtn.disabled = false;
          issueSubmitBtn.textContent = 'Submit Report';
        });
    });
  }

  // ---------- Dark/light theme toggle (every page) ----------
  // ---------- Mobile nav toggle (every page) ----------
  const navToggle = document.getElementById('nav-toggle');
  const navLinks = document.querySelector('nav.links');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', () => {
      const isOpen = navLinks.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    navLinks.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // ---------- Language toggle (every page) ----------
  let currentLang = 'en';
  const langBtn = document.getElementById('lang-toggle');
  if (langBtn) {
    langBtn.addEventListener('click', () => {
      currentLang = currentLang === 'en' ? 'sw' : 'en';
      document.documentElement.lang = currentLang;
      document.querySelectorAll('[data-en]').forEach(el => {
        const text = currentLang === 'sw' ? el.getAttribute('data-sw') : el.getAttribute('data-en');
        if (text !== null) el.textContent = text;
      });
      document.querySelectorAll('[data-en-html]').forEach(el => {
        const html = currentLang === 'sw' ? el.getAttribute('data-sw-html') : el.getAttribute('data-en-html');
        if (html !== null) el.innerHTML = html;
      });
    });
  }

  // ---------- Quick-pick (index.php combined section) ----------
  const qsBtn = document.getElementById('qs-btn');
  const qsResult = document.getElementById('qs-result');
  const qsCheckin = document.getElementById('qs-checkin');
  const qsCheckout = document.getElementById('qs-checkout');
  const qsFloor = document.getElementById('qs-floor');

  if (qsBtn && qsCheckin && qsCheckout) {
    const todayStr = new Date().toISOString().split('T')[0];
    qsCheckin.min = todayStr;
    qsCheckout.min = todayStr;
    qsCheckin.addEventListener('change', () => { qsCheckout.min = qsCheckin.value; });

    qsBtn.addEventListener('click', function(e) {
      if (!qsCheckin.value || !qsCheckout.value) {
        e.preventDefault();
        if (qsResult) { qsResult.textContent = 'Please select check-in and check-out dates.'; }
        return;
      }
      if (qsCheckout.value <= qsCheckin.value) {
        e.preventDefault();
        if (qsResult) { qsResult.textContent = 'Check-out must be after check-in.'; }
        return;
      }
      const params = new URLSearchParams({
        floor:   qsFloor ? qsFloor.value : 'floor2',
        checkin: qsCheckin.value,
        checkout: qsCheckout.value
      });
      window.location.href = 'book.php?' + params.toString();
    });
  }

  // ---------- Home page second calendar (booking section) ----------
  const homeCalGrid = document.getElementById('cal-grid-home');
  if (homeCalGrid) {
    let homeBookedRanges = [];
    let homeFloor = 'floor2';
    let homeYear = new Date().getFullYear();
    let homeMonth = new Date().getMonth();
    const homeLoading = document.getElementById('cal-loading-home');
    const homeLabel = document.getElementById('cal-month-label-home');
    const homePrev = document.getElementById('cal-prev-home');
    const homeNext = document.getElementById('cal-next-home');

    function homeIsBooked(ds) {
      return homeBookedRanges.some(r => ds >= r.checkin_date && ds < r.checkout_date);
    }
    function homeRenderCal() {
      homeCalGrid.innerHTML = '';
      const dows = ['Su','Mo','Tu','We','Th','Fr','Sa'];
      dows.forEach(d => { const e = document.createElement('div'); e.className = 'dow'; e.textContent = d; homeCalGrid.appendChild(e); });
      const firstDay = new Date(homeYear, homeMonth, 1);
      const startOffset = firstDay.getDay();
      const daysInMonth = new Date(homeYear, homeMonth + 1, 0).getDate();
      for (let i = 0; i < startOffset; i++) { const e = document.createElement('div'); e.className = 'cal-day empty'; homeCalGrid.appendChild(e); }
      const todayStr2 = new Date().toISOString().split('T')[0];
      for (let day = 1; day <= daysInMonth; day++) {
        const ds = new Date(homeYear, homeMonth, day).toISOString().split('T')[0];
        const e = document.createElement('div');
        e.textContent = day; e.className = 'cal-day';
        if (ds < todayStr2) e.classList.add('past');
        else if (homeIsBooked(ds)) e.classList.add('booked');
        else e.classList.add('available');
        homeCalGrid.appendChild(e);
      }
      const mNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      if (homeLabel) homeLabel.textContent = mNames[homeMonth] + ' ' + homeYear;
    }
    function homeFetchDates(floor) {
      if (homeLoading) homeLoading.style.display = 'flex';
      fetch('get_booked_dates.php?floor=' + encodeURIComponent(floor))
        .then(r => r.json()).then(data => { homeBookedRanges = (data && data.ranges) ? data.ranges : (Array.isArray(data) ? data : []); homeRenderCal(); })
        .catch(() => { homeBookedRanges = []; homeRenderCal(); })
        .finally(() => { if (homeLoading) homeLoading.style.display = 'none'; });
    }
    homeFetchDates(homeFloor);
    if (homePrev) homePrev.addEventListener('click', () => { homeMonth--; if (homeMonth < 0) { homeMonth = 11; homeYear--; } homeRenderCal(); });
    if (homeNext) homeNext.addEventListener('click', () => { homeMonth++; if (homeMonth > 11) { homeMonth = 0; homeYear++; } homeRenderCal(); });
    document.querySelectorAll('#cal-floor-tabs-home .cal-floor-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#cal-floor-tabs-home .cal-floor-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        homeFloor = btn.getAttribute('data-cal-floor');
        homeFetchDates(homeFloor);
      });
    });
  }



  // ---------- Cookie consent banner ----------
  const cookieBanner = document.getElementById('cookie-banner');
  const cookieAccept = document.getElementById('cookie-accept');
  const cookieDecline = document.getElementById('cookie-decline');
  if (cookieBanner) {
    const consent = localStorage.getItem('quattro_cookie_consent');
    if (!consent) {
      setTimeout(() => cookieBanner.classList.add('visible'), 800);
    }
    if (cookieAccept) {
      cookieAccept.addEventListener('click', () => {
        localStorage.setItem('quattro_cookie_consent', 'accepted');
        cookieBanner.classList.remove('visible');
      });
    }
    if (cookieDecline) {
      cookieDecline.addEventListener('click', () => {
        localStorage.setItem('quattro_cookie_consent', 'declined');
        cookieBanner.classList.remove('visible');
      });
    }
  }

  // ---------- Terms checkbox validation ----------
  const termsCheck = document.getElementById('terms_agree');
  const bookingForm = document.getElementById('booking-form');
  if (termsCheck && bookingForm) {
    bookingForm.addEventListener('submit', function(e) {
      if (!termsCheck.checked) {
        e.preventDefault();
        const fb = document.getElementById('form-feedback');
        if (fb) {
          fb.textContent = 'Please agree to the Terms & Conditions to proceed.';
          fb.className = 'error';
          fb.style.display = 'block';
          termsCheck.focus();
        }
      }
    }, true); // capture phase so it fires before main submit handler
  }



  // ── GALLERY — masonry tabs + lightbox ───────────────────────────
  const gTabs = document.querySelectorAll('.gallery-tab[data-tab]');
  if (gTabs.length) {
    // Tab switching
    gTabs.forEach(btn => {
      btn.addEventListener('click', () => {
        gTabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.getAttribute('data-tab');
        document.querySelectorAll('.gallery-panel').forEach(p => {
          p.style.display = p.id === 'panel-' + tab ? '' : 'none';
        });
      });
    });

    // Lightbox state
    const lb         = document.getElementById('lightbox');
    const lbImg      = document.getElementById('lb-img');
    const lbCaption  = document.getElementById('lb-caption');
    const lbCounter  = document.getElementById('lb-counter');
    const lbClose    = document.getElementById('lb-close');
    const lbPrev     = document.getElementById('lb-prev');
    const lbNext     = document.getElementById('lb-next');
    let lbImages     = [];   // current tab's image array
    let lbIndex      = 0;

    function lbOpen(tab, index) {
      lbImages = (window.GALLERY_DATA && window.GALLERY_DATA[tab]) || [];
      lbIndex  = index;
      lbShow();
      lb.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function lbShow() {
      const img = lbImages[lbIndex];
      if (!img) return;
      lbImg.src      = img.src;
      lbImg.alt      = img.label;
      lbCaption.textContent = img.label;
      lbCounter.textContent = (lbIndex + 1) + ' / ' + lbImages.length;
    }

    function closeLightbox() {
      lb.classList.remove('open');
      document.body.style.overflow = '';
      lbImg.src = '';
    }

    function lbGo(dir) {
      lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
      lbShow();
    }

    if (lbClose) lbClose.addEventListener('click', closeLightbox);
    if (lb)      lb.addEventListener('click', e => { if (e.target === lb) closeLightbox(); });
    if (lbPrev)  lbPrev.addEventListener('click', () => lbGo(-1));
    if (lbNext)  lbNext.addEventListener('click', () => lbGo(1));

    // Keyboard nav
    document.addEventListener('keydown', e => {
      if (!lb || !lb.classList.contains('open')) return;
      if (e.key === 'ArrowLeft')  lbGo(-1);
      if (e.key === 'ArrowRight') lbGo(1);
      if (e.key === 'Escape')     closeLightbox();
    });

    // Touch/swipe
    let touchStartX = 0;
    if (lb) {
      lb.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
      lb.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - touchStartX;
        if (Math.abs(dx) > 40) lbGo(dx < 0 ? 1 : -1);
      });
    }

    // Attach click to all masonry items
    document.querySelectorAll('.gallery-item').forEach(item => {
      item.addEventListener('click', () => {
        const tab   = item.getAttribute('data-tab');
        const index = parseInt(item.getAttribute('data-index'), 10);
        lbOpen(tab, index);
      });
    });
  }



  // ── Lazy loading ─────────────────────────────────────────────────
  // Native lazy loading is supported in all modern browsers.
  // This polyfill catches any dynamically added images and ensures
  // images without loading="lazy" still get deferred where appropriate.
  (function() {
    // Set loading=lazy on any img missing it (except above-fold markers)
    document.querySelectorAll('img:not([loading])').forEach(function(img) {
      // Skip if it has eager marker or is a lightbox image
      if (img.id === 'lb-img' || img.classList.contains('eager')) return;
      img.setAttribute('loading', 'lazy');
      img.setAttribute('decoding', 'async');
    });

    // IntersectionObserver for browsers that need it (very old Safari)
    if ('loading' in HTMLImageElement.prototype) return; // native support
    if (!('IntersectionObserver' in window)) return;     // too old, skip

    const observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (!entry.isIntersecting) return;
        const img = entry.target;
        if (img.dataset.src) {
          img.src = img.dataset.src;
          delete img.dataset.src;
        }
        img.classList.remove('lazy-pending');
        observer.unobserve(img);
      });
    }, { rootMargin: '200px 0px' });

    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
      observer.observe(img);
    });
  })();


});