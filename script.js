// ===== GLOBAL STATE =====
let calDate = new Date();
let bookedDates = []; // Will store dates fetched from the server

// ===== NAVBAR & MENU =====
let lastScrollY = window.scrollY;
const navbar = document.querySelector(".navbar");

window.addEventListener("scroll", () => {
  const y = window.scrollY;
  if (y > lastScrollY && y > 80) navbar.classList.add("hide");
  else navbar.classList.remove("hide");
  lastScrollY = y;
});

const hamburger = document.getElementById("hamburger");
const mobileNav = document.getElementById("mobileNav");

if (hamburger && mobileNav) {
  hamburger.addEventListener("click", () => {
    const open = hamburger.classList.toggle("open");
    mobileNav.classList.toggle("open", open);
    document.body.style.overflow = open ? "hidden" : "";
  });

  document.addEventListener("click", (e) => {
    if (!navbar.contains(e.target) && !mobileNav.contains(e.target)) {
      closeMobileNav();
    }
  });
}

function closeMobileNav() {
  if (hamburger) hamburger.classList.remove("open");
  if (mobileNav) mobileNav.classList.remove("open");
  document.body.style.overflow = "";
}

// ===== HERO SLIDESHOW =====
const galleryImages = [
  "assets/gallery1.jpg", "assets/gallery2.jpg", "assets/gallery3.jpg",
  "assets/gallery4.jpg", "assets/gallery5.jpg", "assets/gallery6.jpg",
  "assets/gallery7.jpg", "assets/gallery8.jpg", "assets/gallery9.jpg", "assets/gallery10.jpg"
];

const slideWrapper = document.querySelector(".hero-gallery .slide");
if (slideWrapper) {
  let current = 0;
  let isTransitioning = false;
  const DURATION = 650;
  const EASING = "cubic-bezier(0.4, 0, 0.2, 1)";

  function nextSlide() {
    if (isTransitioning) return;
    isTransitioning = true;
    const nextIndex = (current + 1) % galleryImages.length;
    const nextImg = new Image();
    nextImg.style.transform = "translateX(100%)";
    nextImg.src = galleryImages[nextIndex];
    slideWrapper.appendChild(nextImg);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const currentImg = slideWrapper.children[0];
        currentImg.style.transition = `transform ${DURATION}ms ${EASING}`;
        currentImg.style.transform = "translateX(-100%)";
        nextImg.style.transition = `transform ${DURATION}ms ${EASING}`;
        nextImg.style.transform = "translateX(0%)";
        setTimeout(() => {
          currentImg.remove();
          current = nextIndex;
          isTransitioning = false;
        }, DURATION);
      });
    });
  }
  let timer = setInterval(nextSlide, 3000);
  slideWrapper.addEventListener("mouseenter", () => clearInterval(timer));
  slideWrapper.addEventListener("mouseleave", () => { timer = setInterval(nextSlide, 3000); });
}

// ===== CALENDAR FUNCTIONALITY =====
async function renderCalendar() {
  const year = calDate.getFullYear();
  const month = calDate.getMonth();

  // Update label
  const label = document.getElementById("calMonthLabel");
  if (label) label.textContent = new Date(year, month).toLocaleString("default", { month: "long", year: "numeric" });

  // Fetch live availability from API
  try {
    const res = await fetch('api/api.php?action=get_calendar&user_type=client');
    const data = await res.json();
    bookedDates = data.map(b => b.start);
  } catch (err) {
    console.warn("Could not fetch calendar dates", err);
  }

  let html = "";
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let i = 1; i <= daysInMonth; i++) {
    const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
    const isBooked = bookedDates.includes(dateString);

    html += `
      <div class="cal-cell ${isBooked ? 'booked' : ''}" data-date="${dateString}">
        <div class="cal-date">${i}</div>
      </div>`;
  }

  const grid = document.getElementById("calGrid");
  if (grid) {
    grid.innerHTML = html;

    // Allow clicking available dates to fill the form
    grid.querySelectorAll(".cal-cell:not(.booked)").forEach(cell => {
      cell.onclick = () => {
        const dateInput = document.getElementById('b_date');
        if (dateInput) {
          dateInput.value = cell.dataset.date;
          dateInput.classList.add("has-value");
          document.querySelector(".form-container").scrollIntoView({ behavior: "smooth" });
        }
      };
    });
  }
}

// ===== INITIALIZATION & FORM HANDLING =====
document.addEventListener('DOMContentLoaded', () => {
  renderCalendar();

  // Package Selection Buttons (Service Section)
  const pkgButtons = document.querySelectorAll(".book-btn");
  const pkgSelect = document.getElementById("b_package");

  pkgButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const pkg = btn.dataset.package;
      if (pkgSelect) {
        pkgSelect.value = pkg;
        pkgSelect.classList.add("selected");
        document.querySelector(".form-container").scrollIntoView({ behavior: "smooth" });
      }
    });
  });

  // Handle Style for manual select changes
  if (pkgSelect) {
    pkgSelect.addEventListener("change", () => {
      pkgSelect.value === "" ? pkgSelect.classList.remove("selected") : pkgSelect.classList.add("selected");
    });
  }

  // Booking Form Submission
  const bookingForm = document.getElementById('bookingForm');
  if (bookingForm) {
    bookingForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerText;
  
    btn.innerText = "Sending...";
    btn.disabled = true;
  
    const payload = {
      name: document.getElementById('b_name').value,
      email: document.getElementById('b_email').value,
      phone: document.getElementById('b_phone').value,
      date: document.getElementById('b_date').value,
      package: pkgSelect ? pkgSelect.value : "",
      message: document.getElementById('b_message').value
    };
  
    try {
      const res = await fetch('api/api.php?action=book', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
  
      if (data.success) {
        const trackingUrl = window.location.origin + '/track.php?id=' + data.token;
        
        document.getElementById('bookingForm').style.display = 'none';
        
        const successDiv = document.getElementById('successMessage');
        successDiv.style.display = 'block';
        
        const linkEl = document.getElementById('trackLink');
        linkEl.href = trackingUrl;
        linkEl.innerText = trackingUrl;

        bookingForm.reset();
        renderCalendar();
      } else {
        alert("Error: " + (data.message || "Please try again."));
      }
    } catch (err) {
      alert("Network error. Please check your connection.");
    } finally {
      btn.innerText = originalText;
      btn.disabled = false;
    }
});

dateInput = document.getElementById('b_date');

dateInput.addEventListener('input', function() {
    if (this.value) {
        this.style.color = "white";
    } else {
        this.style.color = "rgba(255, 255, 255, 0.5)";
    }
});

// For the select dropdown color change
const packageSelect = document.getElementById('b_package');
packageSelect.addEventListener('change', function() {
    this.style.color = "white";
});

dateInput = document.getElementById('b_date');

dateInput.addEventListener('change', function() {
    if (this.value) {
        this.classList.add('has-value');
    } else {
        this.classList.remove('has-value');
    }
});

// Calendar Navigation
const prevBtn = document.getElementById("calPrev");
const nextBtn = document.getElementById("calNext");

if (prevBtn) prevBtn.onclick = () => { calDate.setMonth(calDate.getMonth() - 1); renderCalendar(); };
if (nextBtn) nextBtn.onclick = () => { calDate.setMonth(calDate.getMonth() + 1); renderCalendar(); };
