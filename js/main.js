(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner();
    
    
    // Initiate the wowjs
    new WOW().init();


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.sticky-top').css('top', '0px');
        } else {
            $('.sticky-top').css('top', '-100px');
        }
    });
    
    
    // Dropdown on mouse hover
    const $dropdown = $(".dropdown");
    const $dropdownToggle = $(".dropdown-toggle");
    const $dropdownMenu = $(".dropdown-menu");
    const showClass = "show";
    
    $(window).on("load resize", function() {
        if (this.matchMedia("(min-width: 992px)").matches) {
            $dropdown.hover(
            function() {
                const $this = $(this);
                $this.addClass(showClass);
                $this.find($dropdownToggle).attr("aria-expanded", "true");
                $this.find($dropdownMenu).addClass(showClass);
            },
            function() {
                const $this = $(this);
                $this.removeClass(showClass);
                $this.find($dropdownToggle).attr("aria-expanded", "false");
                $this.find($dropdownMenu).removeClass(showClass);
            }
            );
        } else {
            $dropdown.off("mouseenter mouseleave");
            $dropdown.removeClass(showClass);
            $dropdown.find($dropdownToggle).attr("aria-expanded", "false");
            $dropdown.find($dropdownMenu).removeClass(showClass);
        }
    });
    
    
    // Back to top button
    $(window).scroll(function () {
        if ($(this).scrollTop() > 300) {
            $('.back-to-top').fadeIn('slow');
        } else {
            $('.back-to-top').fadeOut('slow');
        }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });


    // Facts counter
    $('[data-toggle="counter-up"]').counterUp({
        delay: 10,
        time: 2000
    });


    // Date and time picker
    $('.date').datetimepicker({
        format: 'L'
    });
    $('.time').datetimepicker({
        format: 'LT'
    });


    // Testimonials carousel
    $(".testimonial-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        center: true,
        margin: 25,
        dots: true,
        loop: true,
        nav : false,
        responsive: {
            0:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:3
            }
        }
    });

    // Simple form wiring for contact & booking
    function wireForm(formId, statusId, endpoint) {
        const form = document.getElementById(formId);
        const status = document.getElementById(statusId);
        if (!form || !status) return;

        const showStatus = (ok, message) => {
            status.classList.remove('d-none', 'alert-success', 'alert-danger');
            status.classList.add(ok ? 'alert-success' : 'alert-danger');
            // Enforce clear color feedback (green on success, red on error)
            status.style.backgroundColor = ok ? '#d1e7dd' : '#f8d7da';
            status.style.color = ok ? '#0f5132' : '#842029';
            status.textContent = message;
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            // Anti double-submit token for booking
            if (formId === 'bookingForm') {
                let token = form.querySelector('input[name=\"submission_id\"]');
                if (!token) {
                    token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = 'submission_id';
                    form.appendChild(token);
                }
                token.value = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
            }

            const data = new FormData(form); // keep as form-data for PHP
            showStatus(true, 'Envoi en cours...');
            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    body: data,
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok || !body.ok) {
                    throw new Error(body.error || 'Erreur serveur');
                }
                showStatus(true, 'Message envoyé avec succès.');
                form.reset();
            } catch (err) {
                showStatus(false, err.message || 'Impossible d\'envoyer le message.');
            }
        });
    }

    wireForm('contactForm', 'contactStatus', '/api/send.php');
    wireForm('bookingForm', 'bookingStatus', '/api/send-booking.php');

})(jQuery);


