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
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar || !topbarRight) return;

    // Create hamburger button
    var hamburger = document.createElement('button');
    hamburger.className = 'hamburger';
    hamburger.setAttribute('aria-label', 'Toggle menu');
    hamburger.innerHTML = '<span></span><span></span><span></span>';

    // Create mobile sidebar clone
    var mobileSidebar = document.createElement('div');
    mobileSidebar.className = 'mobile-sidebar';
    mobileSidebar.innerHTML = sidebar.innerHTML;

    // Create overlay
    var overlay = document.createElement('div');
    overlay.className = 'mobile-nav-overlay';

    document.body.appendChild(mobileSidebar);
    document.body.appendChild(overlay);

    // Insert hamburger into topbar (after logo)
    var logo = document.querySelector('.logo');
    if (logo && logo.parentNode) {
        logo.parentNode.insertBefore(hamburger, logo.nextSibling);
    }

    // Toggle menu
    function openMenu() {
        hamburger.classList.add('open');
        mobileSidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        hamburger.classList.remove('open');
        mobileSidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', function () {
        if (mobileSidebar.classList.contains('open')) closeMenu();
        else openMenu();
    });

    overlay.addEventListener('click', closeMenu);

    // Close menu on sidebar link click
    mobileSidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });

    // Close on resize back to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) closeMenu();
    });
});