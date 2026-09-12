/* =========================================================
   HM CAFÉ — INNOVATIVE JAVASCRIPT ENGINE
   Sticky Header, Particles FX, Counter Animation,
   Smooth Scroll & Micro-interactions
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =========================
       MOBILE MENU
    ========================== */
    const hamburger = document.getElementById("hamburger");
    const mainNav = document.getElementById("mainNav");

    if (hamburger && mainNav) {
        hamburger.addEventListener("click", function () {
            const isOpen = mainNav.classList.toggle("show");
            hamburger.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });

        mainNav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                mainNav.classList.remove("show");
                hamburger.setAttribute("aria-expanded", "false");
            });
        });
    }

    /* =========================
       ACTIVE NAV & SCROLL SPY
    ========================== */
    const sections = document.querySelectorAll("section[id]");
    const links = document.querySelectorAll(".main-nav a");
    let isNavClicking = false;

    function updateActiveNav() {
        if (isNavClicking) return;

        const currentPath = window.location.pathname.replace(/^\/+|\/+$/g, "");
        const currentHash = window.location.hash;

        // If on a dedicated subpage (e.g. login, shop) with no section hash
        if (currentPath !== "" && currentPath !== "index.php" && !currentHash) {
            links.forEach(function (link) {
                const href = link.getAttribute("href") || "";
                if (href.includes(currentPath) && !href.includes("#")) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
            return;
        }

        if (sections.length === 0 || links.length === 0) return;

        let current = "";
        const scrollPosition = window.scrollY + 140;

        sections.forEach(function (section) {
            const rect = section.getBoundingClientRect();
            const sectionTop = rect.top + window.pageYOffset;
            const sectionHeight = section.offsetHeight;
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                current = section.getAttribute("id");
            }
        });

        // If near top of page, activate home
        if (window.scrollY < 120 && document.getElementById("home")) {
            current = "home";
        }

        // If at the very bottom of page, activate last section
        if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 50)) {
            const lastSection = sections[sections.length - 1];
            if (lastSection) {
                current = lastSection.getAttribute("id");
            }
        }

        if (current !== "") {
            links.forEach(function (link) {
                const href = link.getAttribute("href") || "";
                if (href.endsWith("#" + current) || href === "#" + current) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        }
    }

    // Set initial active state and listen to scroll
    updateActiveNav();
    window.addEventListener("scroll", updateActiveNav, { passive: true });
    window.addEventListener("hashchange", updateActiveNav);

    // Initial check if opened with hash (e.g. /#menu, /#events)
    if (window.location.hash) {
        setTimeout(function () {
            const hashId = window.location.hash.substring(1);
            const target = document.getElementById(hashId);
            if (target) {
                const offset = 90;
                const targetTop = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: targetTop > 0 ? targetTop : 0, behavior: "smooth" });
            }
        }, 200);
    }

    // Click handler for navigation items
    links.forEach(function (link) {
        link.addEventListener("click", function (e) {
            const href = link.getAttribute("href") || "";
            const hashIndex = href.indexOf("#");

            if (hashIndex !== -1) {
                const targetId = href.substring(hashIndex + 1);
                const targetSection = document.getElementById(targetId);

                if (targetSection) {
                    e.preventDefault();
                    isNavClicking = true;
                    links.forEach(function (l) { l.classList.remove("active"); });
                    link.classList.add("active");

                    if (mainNav) {
                        mainNav.classList.remove("show");
                        if (hamburger) hamburger.setAttribute("aria-expanded", "false");
                    }

                    const offset = 90;
                    const targetTop = targetSection.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({
                        top: targetTop > 0 ? targetTop : 0,
                        behavior: "smooth"
                    });

                    if (history.pushState) {
                        history.pushState(null, null, "#" + targetId);
                    }

                    setTimeout(function () {
                        isNavClicking = false;
                        updateActiveNav();
                    }, 850);
                }
            } else {
                links.forEach(function (l) { l.classList.remove("active"); });
                link.classList.add("active");
            }
        });
    });

    /* =========================
       STICKY HEADER (GLASSMORPHISM)
    ========================== */
    const header = document.querySelector(".header");
    if (header) {
        window.addEventListener("scroll", function () {
            if (window.scrollY > 50) {
                header.classList.add("scrolled");
            } else {
                header.classList.remove("scrolled");
            }
        }, { passive: true });
    }

    /* =========================
       ANIMATED NUMBER COUNTERS
    ========================== */
    function animateCounters() {
        document.querySelectorAll("[data-count]").forEach(function (el) {
            if (el.dataset.counted) return;

            const target = parseInt(el.dataset.count, 10);
            const suffix = el.dataset.suffix || "";
            const duration = 1600;
            const start = performance.now();

            function step(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                // Ease out cubic
                const ease = 1 - Math.pow(1 - progress, 3);
                const current = Math.round(target * ease);
                el.textContent = current.toLocaleString() + suffix;
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            }

            el.dataset.counted = "true";
            requestAnimationFrame(step);
        });
    }

    // Observe counter elements
    if ("IntersectionObserver" in window) {
        const counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounters();
                }
            });
        }, { threshold: 0.2 });

        document.querySelectorAll("[data-count]").forEach(function (el) {
            counterObserver.observe(el);
        });
    } else {
        animateCounters();
    }

    /* =========================
       PARTICLE BACKGROUND (SUBTLE)
    ========================== */
    function initParticles() {
        const canvas = document.createElement("canvas");
        canvas.id = "particleCanvas";
        canvas.style.position = "fixed";
        canvas.style.top = "0";
        canvas.style.left = "0";
        canvas.style.width = "100vw";
        canvas.style.height = "100vh";
        canvas.style.pointerEvents = "none";
        canvas.style.zIndex = "0";
        canvas.style.opacity = "0.3";
        document.body.prepend(canvas);
        const ctx = canvas.getContext("2d");

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener("resize", resize);

        const particles = [];
        const count = Math.min(30, Math.floor(window.innerWidth / 45));

        for (let i = 0; i < count; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                size: Math.random() * 2 + 0.5,
                alpha: Math.random() * 0.3 + 0.1,
            });
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            particles.forEach(function (p) {
                p.x += p.vx;
                p.y += p.vy;

                if (p.x < 0) p.x = canvas.width;
                if (p.x > canvas.width) p.x = 0;
                if (p.y < 0) p.y = canvas.height;
                if (p.y > canvas.height) p.y = 0;

                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fillStyle = "rgba(0, 212, 20, " + p.alpha + ")";
                ctx.fill();
            });

            // Draw subtle connections
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    if (dist < 120) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = "rgba(0, 212, 20, " + (0.05 * (1 - dist / 120)) + ")";
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }

            requestAnimationFrame(draw);
        }

        draw();
    }

    if (window.innerWidth >= 768) {
        initParticles();
    }

    /* =========================
       CURSOR GLOW EFFECT
    ========================== */
    if (window.innerWidth >= 1024) {
        const glow = document.createElement("div");
        glow.className = "cursor-glow";
        glow.style.position = "fixed";
        glow.style.pointerEvents = "none";
        glow.style.zIndex = "1";
        document.body.appendChild(glow);

        let mouseX = 0, mouseY = 0;
        let glowX = 0, glowY = 0;

        document.addEventListener("mousemove", function (e) {
            mouseX = e.clientX;
            mouseY = e.clientY;
            glow.classList.add("active");
        });

        document.addEventListener("mouseleave", function () {
            glow.classList.remove("active");
        });

        function updateGlow() {
            glowX += (mouseX - glowX) * 0.08;
            glowY += (mouseY - glowY) * 0.08;
            glow.style.left = glowX + "px";
            glow.style.top = glowY + "px";
            requestAnimationFrame(updateGlow);
        }
        updateGlow();
    }

    /* =========================
       SMOOTH SCROLL
    ========================== */
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener("click", function (e) {
            const id = this.getAttribute("href");
            if (id === "#") return;
            const target = document.querySelector(id);
            if (target) {
                e.preventDefault();
                const offset = 80;
                const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: top, behavior: "smooth" });
            }
        });
    });

    /* =========================
       ACCORDION GALLERY INTERACTION
    ========================== */
    const accordionPanels = document.querySelectorAll(".accordion-panel");
    if (accordionPanels.length > 0) {
        accordionPanels.forEach(function (panel) {
            panel.addEventListener("mouseenter", function () {
                accordionPanels.forEach(function (p) { p.classList.remove("active"); });
                panel.classList.add("active");
            });
            panel.addEventListener("click", function () {
                accordionPanels.forEach(function (p) { p.classList.remove("active"); });
                panel.classList.add("active");
            });
        });
    }

});