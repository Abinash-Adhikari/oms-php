/**
 * SB-Tech — Reusable File Upload Preview Component.
 *
 * Adds image/video preview on file select, title input, and delete confirmation
 * to any file upload form. Usage:
 *
 *   <div class="file-upload-widget" data-preview="true" data-title="true">
 *       <input type="file" name="my_file" class="file-upload-input">
 *       <div class="file-upload-preview"></div>
 *       <div class="file-upload-info"></div>
 *   </div>
 *
 * Or initialize manually:
 *   initFileUploadPreview('.my-container');
 */

(function () {
    'use strict';

    /** Initialize file upload preview on a container element. */
    function initFileUploadPreview(container) {
        var input = container.querySelector('.file-upload-input');
        if (!input || input._previewInit) return;
        input._previewInit = true;

        var showPreview = container.dataset.preview !== 'false';
        var showTitle = container.dataset.title !== 'false';
        var multiple = input.hasAttribute('multiple');
        var previewDiv = container.querySelector('.file-upload-preview');
        var infoDiv = container.querySelector('.file-upload-info');

        if (!previewDiv) {
            previewDiv = document.createElement('div');
            previewDiv.className = 'file-upload-preview';
            container.appendChild(previewDiv);
        }
        if (!infoDiv && showTitle) {
            infoDiv = document.createElement('div');
            infoDiv.className = 'file-upload-info';
            container.appendChild(infoDiv);
        }

        // Create title input if needed
        var titleInput = null;
        if (showTitle && !container.querySelector('.file-upload-title')) {
            titleInput = document.createElement('div');
            titleInput.className = 'file-upload-title mt-2';
            titleInput.style.display = 'none';
            titleInput.innerHTML =
                '<label style="font-size:.8rem;color:#6b7280;margin-bottom:2px;display:block">File Title / Description</label>' +
                '<input type="text" name="file_title" class="form-control form-control-sm" placeholder="Optional title for this file" maxlength="255">';
            container.appendChild(titleInput);
        } else if (showTitle) {
            titleInput = container.querySelector('.file-upload-title');
        }

        input.addEventListener('change', function () {
            previewDiv.innerHTML = '';
            var files = input.files;
            if (!files || files.length === 0) {
                if (titleInput) titleInput.style.display = 'none';
                return;
            }

            // Show title input
            if (titleInput) titleInput.style.display = 'block';

            Array.from(files).forEach(function (file) {
                var wrapper = document.createElement('div');
                wrapper.className = 'file-upload-preview-item';

                if (file.type.startsWith('image/')) {
                    // Image preview
                    var img = document.createElement('img');
                    img.className = 'file-upload-thumb';
                    img.style.cssText = 'max-width:120px;max-height:90px;border-radius:6px;border:1px solid #e5e7eb;object-fit:cover;margin:4px';
                    var reader = new FileReader();
                    reader.onload = function (e) { img.src = e.target.result; };
                    reader.readAsDataURL(file);
                    wrapper.appendChild(img);
                } else if (file.type === 'application/pdf') {
                    // PDF icon
                    var icon = document.createElement('div');
                    icon.className = 'file-upload-icon';
                    icon.style.cssText = 'width:80px;height:90px;border-radius:6px;border:1px solid #e5e7eb;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:4px';
                    icon.innerHTML = '<i class="fas fa-file-pdf" style="font-size:2rem;color:#dc2626"></i>';
                    wrapper.appendChild(icon);
                } else if (file.type.startsWith('video/')) {
                    // Video thumbnail
                    var vid = document.createElement('video');
                    vid.style.cssText = 'max-width:120px;max-height:90px;border-radius:6px;border:1px solid #e5e7eb;object-fit:cover;margin:4px';
                    vid.muted = true;
                    vid.preload = 'metadata';
                    vid.onloadeddata = function () { vid.currentTime = 1; };
                    vid.src = URL.createObjectURL(file);
                    wrapper.appendChild(vid);
                } else {
                    // Generic file icon
                    var ext = file.name.split('.').pop().toLowerCase();
                    var iconMap = { doc: '#2563eb', docx: '#2563eb', xls: '#16a34a', xlsx: '#16a34a', txt: '#6b7280', zip: '#d97706', rar: '#d97706', pptx: '#ea580c' };
                    var color = iconMap[ext] || '#6b7280';
                    var icon2 = document.createElement('div');
                    icon2.style.cssText = 'width:80px;height:90px;border-radius:6px;border:1px solid #e5e7eb;background:#f9fafb;display:flex;flex-direction:column;align-items:center;justify-content:center;margin:4px';
                    icon2.innerHTML = '<i class="fas fa-file" style="font-size:1.8rem;color:' + color + '"></i><span style="font-size:.65rem;color:#9ca3af;margin-top:2px;text-transform:uppercase">' + ext + '</span>';
                    wrapper.appendChild(icon2);
                }

                // File info
                var info = document.createElement('div');
                info.style.cssText = 'font-size:.78rem;color:#374151;margin:0 4px';
                var size = file.size;
                var sizeStr = size >= 1048576 ? (size / 1048576).toFixed(1) + ' MB' : size >= 1024 ? (size / 1024).toFixed(1) + ' KB' : size + ' B';
                info.innerHTML = '<strong>' + escapeHtml(file.name) + '</strong><br><span style="color:#9ca3af">' + sizeStr + '</span>';
                wrapper.appendChild(info);

                previewDiv.appendChild(wrapper);
            });
        });
    }

    /** Escape HTML for safe insertion. */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /** Initialize all widgets on the page. */
    function initAll() {
        document.querySelectorAll('.file-upload-widget').forEach(function (el) {
            initFileUploadPreview(el);
        });
    }

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose globally for dynamic content
    window.initFileUploadPreview = initFileUploadPreview;
    window.initAllFileUploadPreviews = initAll;
})();

/**
 * Delete confirmation for file attachments.
 * Call from a form's onsubmit:
 *   onsubmit="return confirmDeleteFile(this)"
 */
function confirmDeleteFile(form) {
    return confirm('Are you sure you want to delete this file permanently?');
}
