<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Mobile Sidebar Test</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="assets/css/style.css"/>
<style>
.test-panel {
    position: fixed; top: 80px; right: 20px;
    background: white; border: 2px solid #333;
    padding: 15px; z-index: 9999;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    font-family: monospace; font-size: 12px;
    max-width: 300px;
}
.test-status { margin: 5px 0; padding: 5px; border-radius: 4px; }
.test-pass { background: #d4edda; color: #155724; }
.test-fail { background: #f8d7da; color: #721c24; }
.test-info { background: #d1ecf1; color: #0c5460; }
</style>
</head>
<body>
<div class="test-panel">
    <h4 style="margin-top:0">🔧 Mobile Sidebar Test</h4>
    <div id="test-results"></div>
    <button onclick="runTests()" style="margin-top:10px;padding:8px 16px;cursor:pointer">Run Tests</button>
</div>

<script src="assets/js/theme.js"></script>
<script>
function runTests() {
    const results = document.getElementById('test-results');
    results.innerHTML = '';

    const tests = [
        {
            name: 'Hamburger exists',
            test: () => document.querySelector('.hamburger') !== null
        },
        {
            name: 'Mobile sidebar element exists',
            test: () => document.querySelector('.mobile-sidebar') !== null
        },
        {
            name: 'Mobile sidebar is hidden initially',
            test: () => {
                const ms = document.querySelector('.mobile-sidebar');
                if (!ms) return false;
                const style = window.getComputedStyle(ms);
                return style.display === 'none' || !ms.classList.contains('open');
            }
        },
        {
            name: 'Mobile header exists in sidebar',
            test: () => document.querySelector('.mobile-header') !== null
        },
        {
            name: 'Logo-large in mobile header',
            test: () => document.querySelector('.mobile-header .logo-large') !== null
        },
        {
            name: 'Close button exists',
            test: () => document.querySelector('.mobile-sidebar-close') !== null
        },
        {
            name: 'Overlay exists',
            test: () => document.querySelector('.mobile-nav-overlay') !== null
        },
        {
            name: '.logo-compact in topbar (admin)',
            test: () => document.querySelector('.logo-compact') !== null
        },
        {
            name: '.sidebar-header in desktop sidebar',
            test: () => document.querySelector('.sidebar .sidebar-header') !== null
        },
        {
            name: 'Hamburger click toggles menu',
            test: () => {
                const hamburger = document.querySelector('.hamburger');
                const mobileSidebar = document.querySelector('.mobile-sidebar');
                if (!hamburger || !mobileSidebar) return false;

                const wasOpen = mobileSidebar.classList.contains('open');
                hamburger.click();
                const isOpenNow = mobileSidebar.classList.contains('open');

                // Reset state
                if (mobileSidebar.classList.contains('open')) {
                    hamburger.click();
                }

                return wasOpen !== isOpenNow;
            }
        }
    ];

    let passCount = 0;

    tests.forEach(t => {
        const passed = t.test();
        if (passed) passCount++;
        const div = document.createElement('div');
        div.className = 'test-status ' + (passed ? 'test-pass' : 'test-fail');
        div.textContent = (passed ? '✅' : '❌') + ' ' + t.name;
        results.appendChild(div);
    });

    const summary = document.createElement('div');
    summary.className = 'test-status test-info';
    summary.textContent = `Results: ${passCount}/${tests.length} tests passed`;
    results.appendChild(summary);
}

// Auto-run tests on load
window.addEventListener('load', () => {
    setTimeout(runTests, 500); // Wait for JS to initialize
});
</script>
</body>
</html>
