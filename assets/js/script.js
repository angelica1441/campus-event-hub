document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-confirm]');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message =
                form.getAttribute('data-confirm') ||
                'Are you sure you want to continue?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-dismiss-alert]').forEach(function (button) {
        button.addEventListener('click', function () {
            const alert = button.closest('.alert');

            if (alert) {
                alert.remove();
            }
        });
    });
});

/* Premium motion enhancements */
document.addEventListener('DOMContentLoaded', function () {
    // Button press animation
    document.querySelectorAll(
        '.btn, .btn-dashboard, .btn-action, .login-submit'
    ).forEach(function (button) {
        button.addEventListener('pointerdown', function () {
            button.classList.add('ceh-pressed');
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(
            function (eventName) {
                button.addEventListener(eventName, function () {
                    button.classList.remove('ceh-pressed');
                });
            }
        );
    });

    // Respect reduced-motion preferences
    if (
        !window.matchMedia ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    // Reveal dashboard cards and event cards when visible
    const revealTargets = document.querySelectorAll(
        '.events-grid .event-card, ' +
        '.registrations-grid .registration-card, ' +
        '.dashboard-stats .stat-card, ' +
        '.dashboard-page .events-panel, ' +
        '.dashboard-page .recent-panel'
    );

    if ('IntersectionObserver' in window && revealTargets.length) {
        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('ceh-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.08 }
        );

        revealTargets.forEach(function (element) {
            element.classList.add('ceh-observe');
            observer.observe(element);
        });
    }
});