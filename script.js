// ===== GLOBAL STATE =====
let calDate = new Date();
let bookedDates = []; 

// ===== NAVBAR & MENU =====
let lastScrollY = window.scrollY;
const navbar = document.querySelector(".navbar");

if (navbar) {
    window.addEventListener("scroll", () => {
        const y = window.scrollY;
        if (y > lastScrollY && y > 80) navbar.classList.add("hide");
        else navbar.classList.remove("hide");
        lastScrollY = y;
    });
}

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

// ===== CALENDAR RENDERER =====
async function renderCalendar() {
    const year = calDate.getFullYear();
    const month = calDate.getMonth();
    const label = document.getElementById("calMonthLabel");
    if (label) label.textContent = new Date(year, month).toLocaleString("default", { month: "long", year: "numeric" });

    try {
        const res = await fetch('api/api.php?action=get_calendar&user_type=client');
        const data = await res.json();
        bookedDates = data.map(b => b.start);
    } catch (err) {
        console.warn("Could not fetch calendar dates", err);
    }

    let html = "";
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Get today's local date formatted exactly as "YYYY-MM-DD"
    const now = new Date();
    const todayString = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    for (let i = 1; i <= daysInMonth; i++) {
        const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const isBooked = bookedDates.includes(dateString);
        
        // Pure string comparison: If the cell date is less than today's date string, it's in the past
        const isPast = dateString < todayString;

        // Add 'disabled' class if it's a past date
        html += `<div class="cal-cell ${isBooked ? 'booked' : ''} ${isPast ? 'disabled' : ''}" data-date="${dateString}">
                    <div class="cal-date">${i}</div>
                 </div>`;
    }

    const grid = document.getElementById("calGrid");
    if (grid) {
        grid.innerHTML = html;
        
        // Lock it down: ONLY look for cells that explicitly don't have .booked or .disabled classes
        grid.querySelectorAll(".cal-cell").forEach(cell => {
            if (cell.classList.contains('booked') || cell.classList.contains('disabled')) {
                cell.onclick = null;
                cell.style.pointerEvents = "none";
            } else {
                cell.onclick = () => {
                    const dateInput = document.getElementById('b_date');
                    if (dateInput) {
                        dateInput.value = cell.dataset.date;
                        dateInput.dispatchEvent(new Event('change')); 
                        document.querySelector(".form-container").scrollIntoView({ behavior: "smooth" });
                    }
                };
            }
        });
    }
}

// ===== INITIALIZATION & FORM HANDLING =====
document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();

    // 1. Setup Elements
    const bookingForm = document.getElementById('bookingForm');
    const pkgSelect = document.getElementById("b_package");
    const dateInput = document.getElementById('b_date');
    const phoneInput = document.getElementById('b_phone'); // Targeted Phone Field
    const pkgButtons = document.querySelectorAll(".book-btn");
    const prevBtn = document.getElementById("calPrev");
    const nextBtn = document.getElementById("calNext");

    // Max length constraint helper for +63 numbers
    if (phoneInput) {
        phoneInput.setAttribute('maxlength', '13');
    }

    // 2. Package Selection (Service Buttons)
    pkgButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            if (pkgSelect) {
                pkgSelect.value = btn.dataset.package;
                pkgSelect.style.color = "white"; // Mobile fix
                pkgSelect.classList.add("selected");
                document.querySelector(".form-container").scrollIntoView({ behavior: "smooth" });
            }
        });
    });

    // 3. Date Input Styling Fixes (Consolidated)
    if (dateInput) {
        // Set HTML min constraint to today
        const now = new Date();
        const todayString = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        dateInput.setAttribute('min', todayString);

        const updateDateStyle = () => {
            if (dateInput.value) {
                dateInput.style.color = "white";
                dateInput.classList.add("has-value");
            } else {
                dateInput.style.color = "rgba(255, 255, 255, 0.5)";
                dateInput.classList.remove("has-value");
            }
        };
        dateInput.addEventListener('input', updateDateStyle);
        dateInput.addEventListener('change', updateDateStyle);
    }

    // NEW: Real-time Phone Filter (Enforces numbers and a single leading plus sign)
    if (phoneInput) {
        phoneInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/[^0-9+]/g, '');
            
            // Enforce that '+' can only occupy index 0
            if (value.indexOf('+') > 0) {
                value = value.substring(0, value.indexOf('+')) + value.substring(value.indexOf('+') + 1);
            }
            e.target.value = value;
        });
    }

    // 4. Select Dropdown Color Change
    if (pkgSelect) {
        pkgSelect.addEventListener("change", () => {
            pkgSelect.style.color = pkgSelect.value === "" ? "rgba(255, 255, 255, 0.5)" : "white";
        });
    }
    
    if (setSelect) {
        setSelect.addEventListener("change", () => {
            setSelect.style.color = setSelect.value === "" ? "rgba(255, 255, 255, 0.5)" : "white";
        });
    }

    // 5. Booking Form Submission
    if (bookingForm) {
        bookingForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // NEW: Philippine Contact Number Checker Logic
            const rawPhone = phoneInput ? phoneInput.value.trim() : "";
            if (rawPhone !== "") {
                const phPhoneRegex = /^(?:\+639|639|09)\d{9}$/;
                
                if (!phPhoneRegex.test(rawPhone)) {
                    alert("Please enter a valid Philippine mobile number.\n\nFormats accepted:\n- 09XXXXXXXXX (e.g. 09123456789)\n- +639XXXXXXXXX (e.g. +639123456789)");
                    if (phoneInput) phoneInput.focus();
                    return; // Terminates submission process immediately
                }
            }

            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerText;

            btn.innerText = "Sending...";
            btn.disabled = true;

            const payload = {
                name: document.getElementById('b_name').value,
                email: document.getElementById('b_email').value,
                phone: document.getElementById('b_phone').value,
                date: document.getElementById('b_date').value,
                package: document.getElementById('b_package').value,
                set: document.getElementById('b_set').value,
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
                    bookingForm.style.display = 'none';
                    const successDiv = document.getElementById('successMessage');
                    if (successDiv) successDiv.style.display = 'block';
                    const linkEl = document.getElementById('trackLink');
                    if (linkEl) {
                        linkEl.href = trackingUrl;
                        linkEl.innerText = trackingUrl;
                    }
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
    }

    // 6. Calendar Navigation
    if (prevBtn) prevBtn.onclick = () => { calDate.setMonth(calDate.getMonth() - 1); renderCalendar(); };
    if (nextBtn) nextBtn.onclick = () => { calDate.setMonth(calDate.getMonth() + 1); renderCalendar(); };

});
