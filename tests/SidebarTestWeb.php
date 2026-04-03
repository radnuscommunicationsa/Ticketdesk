<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sidebar Backend Tests - Web Runner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .test-results {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            background: #1e293b;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            max-height: 600px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .test-pass { color: #4ade80; }
        .test-fail { color: #f87171; }
        .test-info { color: #60a5fa; font-weight: bold; }
        .test-summary {
            margin-top: 20px;
            padding: 15px;
            background: #0f172a;
            border-radius: 8px;
            color: #f1f5f9;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: #3b82f6;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 10px 5px;
        }
        .btn:hover { background: #2563eb; transform: translateY(-2px); }
        .btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        .note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-server"></i> Sidebar Backend Tests</h1>
            <p>PHP Unit Tests for Database Queries and Security</p>
        </div>
        <div class="content">
            <div class="note">
                <i class="fa-solid fa-lightbulb"></i> <strong>Note:</strong> This test runs server-side PHP tests to validate database connectivity, queries, security checks, and helper functions.
            </div>

            <button id="runBtn" class="btn" onclick="runTests()">
                <i class="fa-solid fa-play"></i> Run Tests
            </button>
            <a href="index.html" class="btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Test Suite
            </a>

            <div class="test-results" id="output" style="display:none;">
                <!-- Test results will appear here -->
            </div>
        </div>
    </div>

    <script>
        async function runTests() {
            const btn = document.getElementById('runBtn');
            const output = document.getElementById('output');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Running...';
            output.style.display = 'block';
            output.innerHTML = '<div class="test-info">🚀 Starting tests...</div>';

            try {
                const response = await fetch('SidebarTest.php');
                const text = await response.text();

                // Parse output and format it
                let formatted = '';
                const lines = text.split('\n');

                lines.forEach(line => {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('✅')) {
                        formatted += `<div class="test-pass">${line}</div>`;
                    } else if (trimmed.startsWith('❌')) {
                        formatted += `<div class="test-fail">${line}</div>`;
                    } else if (trimmed.includes('SUMMARY') || trimmed.includes('=') || trimmed.includes('Total') || trimmed.includes('Passed') || trimmed.includes('Failed') || trimmed.includes('Success Rate')) {
                        formatted += `<div class="test-summary">${line}</div>`;
                    } else if (trimmed.includes('Running')) {
                        formatted += `<div class="test-info">${line}</div>`;
                    } else {
                        formatted += `<div>${line}</div>`;
                    }
                });

                output.innerHTML = formatted;
            } catch (error) {
                output.innerHTML = `<div class="test-fail">❌ Error: ${error.message}</div>`;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-play"></i> Run Tests';
            }
        }
    </script>
</body>
</html>
