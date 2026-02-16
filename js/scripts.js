document.addEventListener('DOMContentLoaded', () => {
    // Mobile Menu Toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    const navItems = document.querySelectorAll('.nav-links a');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');

            // Animate hamburger icon
            const spans = menuToggle.querySelectorAll('span');
            if (navLinks.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });
    }

    // Close mobile menu when a link is clicked
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            if (navLinks.classList.contains('active')) {
                navLinks.classList.remove('active');
                // Reset hamburger icon
                const spans = menuToggle.querySelectorAll('span');
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });
    });

    // Smooth scrolling for anchor links (fallback for browsers that don't support scroll-behavior: smooth)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                const headerOffset = 80; // Height of fixed header
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        });
    });

    // Simple scroll animation for fade-in elements
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Apply animation classes to sections
    document.querySelectorAll('.section, .hero-content').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(el);
    });
    // SVG Animated Contact Form Handling
    const contactForm = document.getElementById('contactForm');
    const formStatus = document.getElementById('form-status');
    const sendBtnContainer = document.querySelector('.send-btn-container');
    const nativeSubmitBtn = document.getElementById('nativeSubmit');

    // GSAP Timeline
    let tl;

    if (contactForm && sendBtnContainer) {

        // Setup initial state
        gsap.set("#paperPlaneRoute", { drawSVG: "0% 0%", opacity: 0 });
        // Note: drawSVG is paid, so we will use stroke-dasharray CSS trick if drawSVG fails or just fade in
        // Since we didn't include DrawSVG, we'll animate opacity/stroke-dashoffset manually if needed

        // Logic for the button click
        sendBtnContainer.addEventListener('click', function () {
            // Trigger the native submit to run HTML5 validation
            if (contactForm.checkValidity()) {
                // If valid, prevent default submission and run animation + ajax
                startAnimationAndSend();
            } else {
                // If invalid, click the hidden submit button to trigger browser validation UI
                nativeSubmitBtn.click();
            }
        });

        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            // This listener is here just in case the native button is triggered directly,
            // but our main logic is in the container click. 
            // If triggered by nativeSubmit.click() and valid, it comes here.
            // But we handle the animation/send call separately to coordinate.
        });
    }

    function startAnimationAndSend() {
        if (tl && tl.isActive()) return;

        tl = gsap.timeline();

        // 1. Button Squish & Morph (Approximated with Scale & Border Radius)
        tl.to("#btnBase", { duration: 0.2, scale: 0.95, transformOrigin: "50% 50%" });
        tl.to("#btnBase", { duration: 0.2, scale: 1, transformOrigin: "50% 50%" });

        // 2. Rect to Circle (Simulated by width change and border radius)
        // Since MorphSVG is paid, we animate the width/rx of the rect
        tl.to("#btnBase", {
            duration: 0.5,
            attr: { width: 158, rx: 79, x: 621 }, // Shrink width to height (158) and center it
            ease: "power2.inOut"
        }, "morph");

        // Fade out text
        tl.to("#txtSend", { duration: 0.3, opacity: 0 }, "morph");

        // 3. Plane takes off
        tl.to("#paperPlane", {
            duration: 1.5,
            motionPath: {
                path: "#paperPlaneRoute",
                align: "#paperPlaneRoute",
                alignOrigin: [0.5, 0.5],
                autoRotate: 90
            },
            ease: "power2.in"
        }, "flight");

        // Fade in opacity of route if we want to show it, or just keep invisible

        // 4. AJAX Request
        const formData = new FormData(contactForm);

        // We simulate the timing so the request happens during flight
        fetch(contactForm.action, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (response.ok) {
                    return response.text();
                } else {
                    throw new Error('Form submission failed');
                }
            })
            .then(text => {
                // 5. Success State animation
                showSuccessState(text);
            })
            .catch(error => {
                showErrorState(error.message);
            });
    }

    function showSuccessState(msg) {
        // Expand button back
        tl.to("#btnBase", {
            duration: 0.5,
            attr: { width: 480, rx: 23, x: 460 }, // Back to rect
            fill: "white",
            ease: "power2.out"
        }, "expand");

        // Show Sent Text
        tl.to("#rectSent", { opacity: 1, duration: 0.3 }, "expand");
        tl.to("#txtSent", { opacity: 1, duration: 0.3 }, "expand");

        // Change text color
        tl.to("#txtSent", { fill: "var(--primary-color)" }, "expand");

        formStatus.innerHTML = '<span style="color: green;">' + msg + '</span>';
        contactForm.reset();

        // Reset after a delay
        setTimeout(() => {
            resetButton();
        }, 5000);
    }

    function showErrorState(msg) {
        formStatus.innerHTML = '<span style="color: red;">' + msg + '</span>';
        resetButton();
    }

    function resetButton() {
        gsap.to("#btnBase", {
            duration: 0.5,
            attr: { width: 563.765, rx: 27, x: 418.117 },
            fill: "var(--primary-color)"
        });
        gsap.to("#txtSend", { opacity: 1, duration: 0.3 });
        gsap.to("#rectSent", { opacity: 0, duration: 0.3 });
        gsap.set("#paperPlane", { x: 0, y: 0, rotation: 0 }); // Reset plane position
        formStatus.innerHTML = '';
    }

});
