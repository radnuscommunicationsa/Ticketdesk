// TicketDesk — Dark/Light Mode + Mobile Menu
(function () {
    if (localStorage.getItem('td_theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    var dark = document.body.classList.contains('dark-mode');

    // ── Dark Mode Toggle ──
    function makeToggle(extraClass) {
        var btn = document.createElement('button');
        btn.className = 'td-toggle-btn' + (extraClass ? ' ' + extraClass : '');
        btn.title = 'Toggle Light / Dark Mode';
        btn.innerHTML =
            '<span class="td-toggle-track"></span>' +
            '<span class="td-toggle-label">' + (dark ? '🌙 Dark' : '☀️ Light') + '</span>';
        btn.addEventListener('click', function () {
            dark = !dark;
            document.body.classList.toggle('dark-mode', dark);
            localStorage.setItem('td_theme', dark ? 'dark' : 'light');
            document.querySelectorAll('.td-toggle-label').forEach(function (el) {
                el.textContent = dark ? '🌙 Dark' : '☀️ Light';
            });
        });
        return btn;
    }

    // Inject toggle into topbar
    var topbarRight = document.querySelector('.topbar-right');
    if (topbarRight) {
        topbarRight.insertBefore(makeToggle(), topbarRight.firstChild);
    }

    // Inject toggle on login page
    if (document.querySelector('.login-wrap') && !topbarRight) {
        var wrap = document.createElement('div');
        wrap.className = 'td-login-toggle';
        wrap.appendChild(makeToggle());
        document.body.appendChild(wrap);
    }

    // ── Hamburger Mobile Menu ──
    function initMobileMenu() {
        // Try admin sidebar (included via PHP) first, then inline sidebar inside .shell
        var sidebar = document.querySelector('.sidebar') || document.querySelector('.shell > .sidebar');
        if (!sidebar || !topbarRight) return;

        // Create hamburger button
        var hamburger = document.createElement('button');
        hamburger.className = 'hamburger';
        hamburger.setAttribute('aria-label', 'Toggle navigation menu');
        hamburger.setAttribute('aria-controls', 'mobile-sidebar');
        hamburger.setAttribute('aria-expanded', 'false');
        hamburger.innerHTML = '<span></span><span></span><span></span>';

        // Create mobile sidebar clone
        var mobileSidebar = document.createElement('div');
        mobileSidebar.className = 'mobile-sidebar';
        mobileSidebar.setAttribute('aria-hidden', 'true');
        mobileSidebar.setAttribute('role', 'dialog');
        mobileSidebar.setAttribute('aria-label', 'Navigation menu');
        mobileSidebar.id = 'mobile-sidebar';

        // Create sidebar header with close button for mobile
        var mobileHeader = document.createElement('div');
        mobileHeader.className = 'mobile-header';
        mobileHeader.innerHTML = `
            <div class="logo-large">
                <div class="logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="logo-text">
                    <div class="logo-title">TicketDesk</div>
                    <div class="logo-badge">ADMIN</div>
                </div>
            </div>
            <button class="mobile-sidebar-close" aria-label="Close menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        // Clone sidebar content
        var sidebarContent = document.createElement('div');
        sidebarContent.innerHTML = sidebar.innerHTML;

        mobileSidebar.appendChild(mobileHeader);
        mobileSidebar.appendChild(sidebarContent);

        // Create overlay
        var overlay = document.createElement('div');
        overlay.className = 'mobile-nav-overlay';

        document.body.appendChild(mobileSidebar);
        document.body.appendChild(overlay);

        // Insert hamburger into topbar (after compact logo)
        var logoCompact = document.querySelector('.logo-compact');
        if (logoCompact && logoCompact.parentNode) {
            logoCompact.parentNode.insertBefore(hamburger, logoCompact.nextSibling);
        } else {
            // Fallback: insert at start of topbar-right
            if (topbarRight.parentNode) {
                topbarRight.parentNode.insertBefore(hamburger, topbarRight);
            }
        }

        // Focus management
        var lastFocusedElement; // Store the element that had focus before menu opened
        var focusableSelectors = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
        var focusableElements = [];

        function getFocusableElements() {
            return Array.from(mobileSidebar.querySelectorAll(focusableSelectors))
                .filter(el => el.offsetParent !== null && !el.hasAttribute('disabled') && el.tabIndex !== -1);
        }

        function openMenu() {
            lastFocusedElement = document.activeElement;
            hamburger.classList.add('open');
            mobileSidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            hamburger.setAttribute('aria-expanded', 'true');
            mobileSidebar.setAttribute('aria-hidden', 'false');

            // Focus first focusable element in the menu after a short delay
            setTimeout(function() {
                focusableElements = getFocusableElements();
                if (focusableElements.length > 0) {
                    focusableElements[0].focus();
                } else {
                    closeBtn.focus();
                }
            }, 50);
        }

        function closeMenu() {
            hamburger.classList.remove('open');
            mobileSidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
            hamburger.setAttribute('aria-expanded', 'false');
            mobileSidebar.setAttribute('aria-hidden', 'true');

            // Return focus to the hamburger (or the element that opened the menu)
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }

        hamburger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (mobileSidebar.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // Close button in mobile header
        var closeBtn = mobileSidebar.querySelector('.mobile-sidebar-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMenu);
        }

        overlay.addEventListener('click', closeMenu);

        // Close menu on sidebar link click
        mobileSidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        // Escape key closes menu
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mobileSidebar.classList.contains('open')) {
                closeMenu();
            }
        });

        // Focus trap: Tab and Shift+Tab within the mobile menu
        mobileSidebar.addEventListener('keydown', function(e) {
            if (!mobileSidebar.classList.contains('open')) return;

            if (e.key === 'Tab') {
                focusableElements = getFocusableElements();
                if (focusableElements.length === 0) {
                    e.preventDefault();
                    return;
                }

                var firstElement = focusableElements[0];
                var lastElement = focusableElements[focusableElements.length - 1];

                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        });

        // Close on resize back to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) closeMenu();
        });
    }

    // Initialize after a short delay to ensure PHP includes are loaded
    setTimeout(initMobileMenu, 50);
});