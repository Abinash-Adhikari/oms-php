/**
 * SB-Tech — Reusable File List + Preview Gallery Modal.
 *
 * Listens on the file icon / preview icons of documents and expense-claim
 * receipts. Opens one modal that shows a clickable file list plus an inline
 * preview of the active file (iframe/embed/image/video/audio/text per type,
 * via file-preview.js), with Open-in-new-tab, Download and Preview fullscreen
 * actions in the footer next to Close.
 *
 * Usage:
 *   openFileGallery('Receipt files (2)', [
 *     { url: '...', name: 'a.pdf', ext: 'pdf', icon: 'fas fa-file-pdf text-danger' },
 *     { url: '...', name: 'b.png', ext: 'png', icon: 'fas fa-file-image text-info' }
 *   ]);
 */

(function () {
    'use strict';

    var MODAL_ID = 'tmsFileGalleryModal';
    var activeIndex = 0;
    var activeFiles = [];

    function getModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) return existing;

        var modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h5 class="modal-title" id="tmsFgTitle"></h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                    '</div>' +
                    '<div class="modal-body p-0">' +
                        '<div class="tms-fg-layout">' +
                            '<div class="tms-fg-list" id="tmsFgList"></div>' +
                            '<div class="tms-fg-pane" id="tmsFgPane"></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss="modal">Close</button>' +
                        '<div class="tms-fg-actions">' +
                            '<a class="tms-fg-btn" id="tmsFgOpen" href="#" target="_blank" rel="noopener" title="Open in new tab"><i class="fas fa-external-link-alt"></i></a>' +
                            '<a class="tms-fg-btn" id="tmsFgDownload" href="#" download title="Download"><i class="fas fa-download"></i></a>' +
                            '<button type="button" class="tms-fg-btn" id="tmsFgPreview" title="Preview fullscreen"><i class="fas fa-eye"></i></button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str == null ? '' : String(str)));
        return d.innerHTML;
    }

    function selectFile(index) {
        activeIndex = index;
        var f = activeFiles[index];
        if (!f) return;

        var items = document.querySelectorAll('#tmsFgList .tms-fg-item');
        items.forEach(function (el, i) { el.classList.toggle('active', i === index); });

        document.getElementById('tmsFgOpen').href = f.url;
        document.getElementById('tmsFgDownload').href = f.url;
        document.getElementById('tmsFgDownload').setAttribute('download', f.name);
        document.getElementById('tmsFgPreview').onclick = function () {
            if (window.openFilePreview) window.openFilePreview(f.url, f.name);
        };

        var pane = document.getElementById('tmsFgPane');
        pane.innerHTML = '';
        if (window.renderFilePreviewInto) {
            window.renderFilePreviewInto(pane, f.url, f.name);
        } else {
            pane.innerHTML = '<div class="text-muted p-4">Preview unavailable.</div>';
        }
    }

    function open(title, files) {
        activeFiles = files || [];
        activeIndex = 0;

        var modal = getModal();
        document.getElementById('tmsFgTitle').innerHTML = '<i class="fas fa-paperclip mr-1"></i>' + escapeHtml(title || 'Files');
        document.getElementById('tmsFgList').innerHTML = '';
        document.getElementById('tmsFgPane').innerHTML = '';

        if (!activeFiles.length) {
            document.getElementById('tmsFgList').innerHTML = '<div class="tms-fg-empty">No files attached.</div>';
            jQuery(modal).modal('show');
            return;
        }

        activeFiles.forEach(function (f, i) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'tms-fg-item' + (i === 0 ? ' active' : '');
            row.innerHTML = '<span class="tms-fg-item-icon"><i class="' + (f.icon || 'fas fa-file text-muted') + '"></i></span>' +
                            '<span class="tms-fg-item-name" title="' + escapeHtml(f.name) + '">' + escapeHtml(f.name) + '</span>';
            row.addEventListener('click', function () { selectFile(i); });
            document.getElementById('tmsFgList').appendChild(row);
        });

        selectFile(0);
        jQuery(modal).modal('show');
    }

    window.openFileGallery = open;
})();