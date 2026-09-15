/**
 * SB-Tech — Reusable File Preview Lightbox.
 *
 * Preview images, PDFs, text files, and more in a beautiful modal.
 * Usage:
 *   <button class="tms-file-preview"
 *           data-src="https://example.com/file.pdf"
 *           data-name="contract.pdf"
 *           data-type="application/pdf">Preview</button>
 *
 * Or bulk-initialize via data-attr on existing links:
 *   <a href="file.pdf" class="tms-file-preview-trigger" target="_blank">view</a>
 */

(function () {
    'use strict';

    var MODAL_ID = 'tmsFilePreviewModal';

    function getModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) return existing;

        var modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'tms-file-preview-modal';
        modal.innerHTML =
            '<div class="tms-fp-backdrop"></div>' +
            '<div class="tms-fp-container">' +
                '<div class="tms-fp-header">' +
                    '<span class="tms-fp-name"></span>' +
                    '<div class="tms-fp-actions">' +
                        '<a class="tms-fp-btn tms-fp-download" href="#" download title="Download"><i class="fas fa-download"></i></a>' +
                        '<a class="tms-fp-btn tms-fp-external" href="#" target="_blank" rel="noopener" title="Open in new tab"><i class="fas fa-external-link-alt"></i></a>' +
                        '<button class="tms-fp-btn tms-fp-close" title="Close" type="button"><i class="fas fa-times"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="tms-fp-body"></div>' +
            '</div>';
        document.body.appendChild(modal);

        modal.querySelector('.tms-fp-close').addEventListener('click', closePreview);
        modal.querySelector('.tms-fp-backdrop').addEventListener('click', closePreview);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById(MODAL_ID).classList.contains('open')) {
                closePreview();
            }
        });

        return modal;
    }

    function closePreview() {
        var modal = document.getElementById(MODAL_ID);
        if (modal) {
            modal.classList.remove('open');
            modal.querySelector('.tms-fp-body').innerHTML = '';
            document.body.style.overflow = '';
        }
    }

    function getMimeType(url, declaredType) {
        if (declaredType && declaredType !== '') return declaredType.toLowerCase();
        var ext = url.split('?')[0].split('.').pop().toLowerCase();
        var map = {
            pdf: 'application/pdf',
            jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml',
            txt: 'text/plain', csv: 'text/csv', json: 'application/json', xml: 'text/xml',
            mp4: 'video/mp4', webm: 'video/webm', ogg: 'video/ogg',
            mp3: 'audio/mpeg', wav: 'audio/wav',
            doc: 'application/msword', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            xls: 'application/vnd.ms-excel', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ppt: 'application/vnd.ms-powerpoint', pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            zip: 'application/zip'
        };
        return map[ext] || 'application/octet-stream';
    }

    function isImageType(mime) {
        return mime.indexOf('image/') === 0;
    }

    function isPdfType(mime) {
        return mime === 'application/pdf';
    }

    function isVideoType(mime) {
        return mime.indexOf('video/') === 0;
    }

    function isAudioType(mime) {
        return mime.indexOf('audio/') === 0;
    }

    function isTextType(mime) {
        return mime.indexOf('text/') === 0 ||
               mime === 'application/json' ||
               mime === 'application/xml';
    }

    function isOfficeType(mime) {
        return mime.indexOf('application/vnd.') === 0 && !isPdfType(mime);
    }

    function fileIcon(mime) {
        if (mime.indexOf('image/') === 0) return 'fas fa-file-image';
        if (isPdfType(mime)) return 'fas fa-file-pdf';
        if (mime.indexOf('word') !== -1) return 'fas fa-file-word';
        if (mime.indexOf('sheet') !== -1 || mime.indexOf('excel') !== -1) return 'fas fa-file-excel';
        if (mime.indexOf('presentation') !== -1 || mime.indexOf('powerpoint') !== -1) return 'fas fa-file-powerpoint';
        if (mime.indexOf('video/') === 0) return 'fas fa-file-video';
        if (mime.indexOf('audio/') === 0) return 'fas fa-file-audio';
        if (mime.indexOf('zip') !== -1) return 'fas fa-file-archive';
        if (isTextType(mime)) return 'fas fa-file-alt';
        return 'fas fa-file';
    }

    function openPreview(url, name, declaredType) {
        var modal = getModal();
        var mime = getMimeType(url, declaredType);
        var body = modal.querySelector('.tms-fp-body');
        var fileName = name || url.split('/').pop().split('?')[0];

        modal.querySelector('.tms-fp-name').textContent = fileName;
        modal.querySelector('.tms-fp-download').href = url;
        modal.querySelector('.tms-fp-download').setAttribute('download', fileName);
        modal.querySelector('.tms-fp-external').href = url;

        body.innerHTML = '';

        if (isImageType(mime)) {
            var img = document.createElement('img');
            img.className = 'tms-fp-content tms-fp-image';
            img.src = url;
            img.alt = fileName;
            img.onload = function () {
                if (img.naturalWidth > 1100) img.style.maxWidth = '1100px';
            };
            body.appendChild(img);
        } else if (isPdfType(mime)) {
            var frame = document.createElement('iframe');
            frame.className = 'tms-fp-content tms-fp-iframe';
            frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'embedded=1';
            body.appendChild(frame);
        } else if (isVideoType(mime)) {
            var vid = document.createElement('video');
            vid.className = 'tms-fp-content tms-fp-video';
            vid.controls = true;
            vid.autoplay = true;
            vid.src = url;
            body.appendChild(vid);
        } else if (isAudioType(mime)) {
            var aud = document.createElement('audio');
            aud.className = 'tms-fp-content tms-fp-audio';
            aud.controls = true;
            aud.autoplay = true;
            aud.src = url;
            body.appendChild(aud);
        } else if (isTextType(mime)) {
            var pre = document.createElement('pre');
            pre.className = 'tms-fp-content tms-fp-text';
            pre.textContent = 'Loading…';
            body.appendChild(pre);
            fetch(url).then(function (r) { return r.text(); }).then(function (txt) {
                pre.textContent = txt;
            }).catch(function () {
                pre.textContent = 'Unable to load file content.';
            });
        } else {
            // Unsupported — show file icon + download prompt
            var wrapper = document.createElement('div');
            wrapper.className = 'tms-fp-content tms-fp-unsupported';
            var iconClass = fileIcon(mime);
            var sizeStr = mime.split('/').pop().toUpperCase();
            wrapper.innerHTML =
                '<div class="tms-fp-unsupported-icon"><i class="' + iconClass + '"></i></div>' +
                '<div class="tms-fp-unsupported-name">' + fileName.replace(/</g, '&lt;') + '</div>' +
                '<div class="tms-fp-unsupported-type">' + sizeStr + '</div>' +
                '<a href="' + url + '" download="' + fileName.replace(/"/g, '&quot;') + '" class="btn btn-primary mt-3">' +
                    '<i class="fas fa-download mr-1"></i>Download file</a>';
            body.appendChild(wrapper);
        }

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    // Auto-init on click
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tms-file-preview');
        if (btn) {
            e.preventDefault();
            openPreview(btn.dataset.src, btn.dataset.name, btn.dataset.type);
            return;
        }
    });

    // Expose globally
    window.openFilePreview = openPreview;
    window.closeFilePreview = closePreview;
})();
