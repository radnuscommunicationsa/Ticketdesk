<!DOCTYPE html>
<html>
<head>
    <title>Upload Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .test-container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            position: relative;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }
        .upload-area:hover { border-color: #5552DD; background: rgba(85,82,221,0.05); }
        .upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .upload-icon { font-size: 2.5rem; color: #94a3b8; margin-bottom: 4px; }
        .upload-text { font-size: 1rem; color: #475569; }
        .upload-sub { font-size: 0.85rem; color: #94a3b8; }
        .file-preview { display: none; margin-top: 10px; padding: 10px; background: #f1f5f9; border-radius: 6px; align-items: center; gap: 8px; }
        .file-preview.show { display: flex; }
    </style>
</head>
<body>
    <div class="test-container">
        <h2>Image Upload Test</h2>
        <p>If you can see the upload area below with an icon and text, the fix is working.</p>

        <div class="upload-area" id="uploadArea">
            <input type="file" id="fileInput" accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.zip">
            <div class="upload-icon"><i class="fa-regular fa-paperclip"></i></div>
            <div class="upload-text">Click to upload or drag & drop</div>
            <div class="upload-sub">JPG, PNG, PDF, DOC, XLSX, ZIP — Max 5MB</div>
        </div>

        <div class="file-preview" id="filePreview">
            <span id="fileIcon"><i class="fa-regular fa-file"></i></span>
            <span id="fileName"></span>
            <span id="fileSize"></span>
            <button onclick="clearFile()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="debug" style="margin-top: 20px; padding: 10px; background: #f8fafc; border-radius: 4px; font-size: 0.85rem;"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var fileInput = document.getElementById('fileInput');
        var uploadArea = document.getElementById('uploadArea');
        var preview = document.getElementById('filePreview');
        var debug = document.getElementById('debug');

        if (!uploadArea || !fileInput) {
            debug.innerHTML = '<span style="color: red;">❌ Error: Upload elements not found in DOM</span>';
            return;
        }

        debug.innerHTML = '<span style="color: green;">✅ Upload area loaded successfully</span>';

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                showPreview(this.files[0]);
            }
        });

        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag');
            if (e.dataTransfer && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showPreview(e.dataTransfer.files[0]);
            }
        });

        window.showPreview = function(file) {
            var icons = {
                jpg: '<i class="fa-regular fa-file-image"></i>',
                jpeg: '<i class="fa-regular fa-file-image"></i>',
                png: '<i class="fa-regular fa-file-image"></i>',
                gif: '<i class="fa-regular fa-file-image"></i>',
                pdf: '<i class="fa-regular fa-file-pdf"></i>',
                doc: '<i class="fa-regular fa-file-word"></i>',
                docx: '<i class="fa-regular fa-file-word"></i>',
                xlsx: '<i class="fa-regular fa-file-excel"></i>',
                zip: '<i class="fa-regular fa-file-zipper"></i>',
                txt: '<i class="fa-regular fa-file-lines"></i>'
            };

            var ext = file.name.split('.').pop().toLowerCase();
            document.getElementById('fileIcon').innerHTML = icons[ext] || '<i class="fa-regular fa-file"></i>';
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            preview.classList.add('show');
            debug.innerHTML = '<span style="color: green;">✅ File selected: ' + file.name + '</span>';
        };

        window.clearFile = function() {
            fileInput.value = '';
            preview.classList.remove('show');
            debug.innerHTML = '<span style="color: #888;">File cleared</span>';
        };
    });
    </script>
</body>
</html>
