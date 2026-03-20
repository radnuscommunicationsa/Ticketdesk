// TicketDesk — Dark/Light Mode Toggle
(function () {
    if (localStorage.getItem('td_theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
})();

document.addEventListener('DOMContentLoaded', function () {
    var dark = document.body.classList.contains('dark-mode');

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

    // Inject into topbar
    var topbarRight = document.querySelector('.topbar-right');
    if (topbarRight) {
        topbarRight.insertBefore(makeToggle(), topbarRight.firstChild);
    }

    // Inject into login page
    if (document.querySelector('.login-wrap') && !topbarRight) {
        var wrap = document.createElement('div');
        wrap.className = 'td-login-toggle';
        wrap.appendChild(makeToggle());
        document.body.appendChild(wrap);
    }
});
