/**
 * SB-Tech — image upload field with an in-browser editor (Filament-style).
 *
 * Markup comes from image_field_html() (functions/images.php):
 *   <div class="imgf" data-image-field data-aspect="1" data-max="1024"> … <input type="file" class="imgf__input"> </div>
 *
 * Pick / drop an image → editor modal (Cropper.js): crop with aspect presets,
 * rotate, flip, zoom, reset. "Apply" renders the result to a canvas and puts
 * it back into the same <input type="file"> via DataTransfer, so the form
 * submits normally and the server (store_image_upload) re-validates and
 * re-encodes it. "Edit" on an already-saved image loads it (same origin)
 * and the edited copy replaces it on submit.
 *
 * Public API (window.ImageField):
 *   init(root?)          enhance fields inside root (default: document)
 *   reset(input, url)    clear any pending file and show url as the saved image
 *                        (used by drawers that refill the form per row)
 */
(function () {
    'use strict';

    var MAX_BYTES = 10 * 1024 * 1024;
    var PRESETS = [
        { label: 'Free', value: NaN },
        { label: '1:1', value: 1 },
        { label: '4:3', value: 4 / 3 },
        { label: '3:2', value: 3 / 2 },
        { label: '16:9', value: 16 / 9 },
        { label: '3:4', value: 3 / 4 }
    ];

    var modal = null;       // shared editor modal (built on first use)
    var cropper = null;
    var session = null;     // { field, file, objectUrl, aspect }

    function canTransferFiles() {
        try { return typeof DataTransfer === 'function' && !!new DataTransfer().items; } catch (e) { return false; }
    }

    function setInputFile(input, file) {
        var dt = new DataTransfer();
        if (file) { dt.items.add(file); }
        input.files = dt.files;
    }

    function outputType(file) {
        var t = (file && file.type) || '';
        if (t === 'image/png' || t === 'image/gif') { return 'image/png'; }
        if (t === 'image/webp') { return 'image/webp'; }
        return 'image/jpeg';
    }

    function renameFor(name, type) {
        var ext = { 'image/png': 'png', 'image/webp': 'webp', 'image/jpeg': 'jpg' }[type];
        var base = (name || 'image').replace(/\.[^.]+$/, '') || 'image';
        return base + '.' + ext;
    }

    function ratioLabel(r) {
        for (var i = 0; i < PRESETS.length; i++) {
            if (Math.abs(PRESETS[i].value - r) < 0.01) { return PRESETS[i].label; }
        }
        return null;
    }

    /* ── Field ─────────────────────────────────────────────────────── */

    function Field(root) {
        this.root = root;
        this.input = root.querySelector('.imgf__input');
        this.drop = root.querySelector('.imgf__drop');
        this.preview = root.querySelector('.imgf__preview');
        this.badge = root.querySelector('.imgf__badge');
        this.btnEdit = root.querySelector('.imgf__btn-edit');
        this.btnPick = root.querySelector('.imgf__btn-pick');
        this.btnUndo = root.querySelector('.imgf__btn-undo');
        this.aspect = parseFloat(root.getAttribute('data-aspect'));
        this.max = parseInt(root.getAttribute('data-max'), 10) || 1600;
        this.savedUrl = this.preview.getAttribute('src') || '';
        this.original = null;   // raw file the user picked (re-edit starts here)
        this.previewUrl = null;
        this.bind();
    }

    Field.prototype.bind = function () {
        var self = this;
        this.drop.addEventListener('click', function () { self.input.click(); });
        this.drop.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.input.click(); }
        });
        this.btnPick.addEventListener('click', function () { self.input.click(); });
        this.btnEdit.addEventListener('click', function () { self.edit(); });
        this.btnUndo.addEventListener('click', function () { self.reset(self.savedUrl); });

        this.input.addEventListener('change', function () {
            if (self.internalChange) { return; }
            var f = self.input.files && self.input.files[0];
            if (f) { self.accept(f); }
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            self.drop.addEventListener(ev, function (e) { e.preventDefault(); self.root.classList.add('is-dragover'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            self.drop.addEventListener(ev, function (e) { e.preventDefault(); self.root.classList.remove('is-dragover'); });
        });
        this.drop.addEventListener('drop', function (e) {
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (f) { self.accept(f); }
        });
    };

    /** A raw file was picked or dropped: validate, remember it, open editor. */
    Field.prototype.accept = function (file) {
        var accepted = (this.input.getAttribute('accept') || 'image/jpeg,image/png,image/webp,image/gif').split(',');
        if (accepted.indexOf(file.type) === -1) {
            var names = accepted.map(function (t) { return t.replace('image/', '').replace('jpeg', 'JPG').toUpperCase(); });
            this.fail('Please choose a ' + names.join(', ') + ' image.');
            return;
        }
        if (file.size > MAX_BYTES) {
            this.fail('That image is larger than 10 MB.');
            return;
        }
        this.original = file;
        this.commit(file, false);
        if (canTransferFiles() && window.Cropper) {
            openEditor(this, file);
        }
    };

    Field.prototype.fail = function (msg) {
        this.internalChange = true;
        if (canTransferFiles()) { setInputFile(this.input, null); } else { this.input.value = ''; }
        this.internalChange = false;
        if (window.Swal) { window.Swal.fire({ icon: 'warning', title: 'Image not accepted', text: msg }); } else { alert(msg); }
    };

    /** Put file into the input + preview. edited=true shows the "Edited" badge. */
    Field.prototype.commit = function (file, edited) {
        this.internalChange = true;
        if (canTransferFiles()) { setInputFile(this.input, file); }
        this.internalChange = false;
        if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); }
        this.previewUrl = URL.createObjectURL(file);
        this.showPreview(this.previewUrl);
        this.badge.hidden = !edited;
        this.btnUndo.hidden = false;
        this.btnPick.querySelector('span').textContent = 'Replace';
    };

    Field.prototype.showPreview = function (url) {
        this.preview.src = url || '';
        this.preview.hidden = !url;
        this.btnEdit.hidden = !url;
        this.root.classList.toggle('has-image', !!url);
    };

    /** Open the editor on the raw pick, or on the saved image. */
    Field.prototype.edit = function () {
        var self = this;
        if (this.original) { openEditor(this, this.original); return; }
        if (!this.savedUrl) { return; }
        this.root.classList.add('is-loading');
        fetch(this.savedUrl, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.blob(); })
            .then(function (blob) {
                var name = decodeURIComponent(self.savedUrl.split('?')[0].split('/').pop() || 'image');
                var file = new File([blob], name, { type: blob.type || 'image/jpeg' });
                self.original = file;
                openEditor(self, file);
            })
            .catch(function () { self.fail('The saved image could not be loaded for editing.'); })
            .then(function () { self.root.classList.remove('is-loading'); });
    };

    /** Drop any pending file and show url as the saved image. */
    Field.prototype.reset = function (url) {
        this.savedUrl = url || '';
        this.original = null;
        this.internalChange = true;
        if (canTransferFiles()) { setInputFile(this.input, null); } else { this.input.value = ''; }
        this.internalChange = false;
        if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; }
        this.showPreview(this.savedUrl);
        this.badge.hidden = true;
        this.btnUndo.hidden = true;
        this.btnPick.querySelector('span').textContent = this.savedUrl ? 'Replace' : 'Upload';
    };

    /* ── Editor modal ──────────────────────────────────────────────── */

    function buildModal() {
        var el = document.createElement('div');
        el.className = 'imgf-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Edit image');
        el.hidden = true;
        el.innerHTML =
            '<div class="imgf-modal__panel">' +
              '<div class="imgf-modal__head">' +
                '<h3><i class="fas fa-crop-alt" aria-hidden="true"></i>Edit image</h3>' +
                '<button type="button" class="imgf-modal__x" data-act="cancel" aria-label="Close"><i class="fas fa-times"></i></button>' +
              '</div>' +
              '<div class="imgf-modal__stage"><img alt=""></div>' +
              '<div class="imgf-modal__tools">' +
                '<div class="imgf-seg" data-role="ratios" role="group" aria-label="Aspect ratio"></div>' +
                '<div class="imgf-seg" role="group" aria-label="Transform">' +
                  '<button type="button" data-act="rotL" title="Rotate left"><i class="fas fa-undo"></i></button>' +
                  '<button type="button" data-act="rotR" title="Rotate right"><i class="fas fa-redo"></i></button>' +
                  '<button type="button" data-act="flipH" title="Flip horizontal"><i class="fas fa-arrows-alt-h"></i></button>' +
                  '<button type="button" data-act="flipV" title="Flip vertical"><i class="fas fa-arrows-alt-v"></i></button>' +
                  '<button type="button" data-act="zoomOut" title="Zoom out"><i class="fas fa-search-minus"></i></button>' +
                  '<button type="button" data-act="zoomIn" title="Zoom in"><i class="fas fa-search-plus"></i></button>' +
                  '<button type="button" data-act="reset" title="Reset"><i class="fas fa-sync-alt"></i></button>' +
                '</div>' +
              '</div>' +
              '<div class="imgf-modal__foot">' +
                '<span class="imgf-modal__info" aria-live="polite"></span>' +
                '<button type="button" class="btn btn-light" data-act="cancel">Cancel</button>' +
                '<button type="button" class="btn btn-primary" data-act="apply"><i class="fas fa-check mr-1"></i>Apply</button>' +
              '</div>' +
            '</div>';
        document.body.appendChild(el);

        el.addEventListener('click', function (e) {
            if (e.target === el) { closeEditor(); return; }
            var btn = e.target.closest('[data-act]');
            if (!btn || !cropper) { return; }
            var act = btn.getAttribute('data-act');
            var d = cropper.getData();
            switch (act) {
                case 'cancel': closeEditor(); break;
                case 'apply': applyEdit(); break;
                case 'rotL': cropper.rotate(-90); break;
                case 'rotR': cropper.rotate(90); break;
                case 'flipH': cropper.scaleX(-(d.scaleX || 1)); break;
                case 'flipV': cropper.scaleY(-(d.scaleY || 1)); break;
                case 'zoomIn': cropper.zoom(0.1); break;
                case 'zoomOut': cropper.zoom(-0.1); break;
                case 'reset': cropper.reset(); setRatio(session.aspect); break;
                case 'ratio': setRatio(parseFloat(btn.getAttribute('data-ratio'))); break;
            }
        });
        document.addEventListener('keydown', function (e) {
            if (!el.hidden && e.key === 'Escape') { e.stopPropagation(); closeEditor(); }
        }, true);
        return el;
    }

    function renderRatios(defaultAspect) {
        var wrap = modal.querySelector('[data-role="ratios"]');
        var list = PRESETS.slice();
        if (!isNaN(defaultAspect) && !ratioLabel(defaultAspect)) {
            list.splice(1, 0, { label: 'Default', value: defaultAspect });
        }
        wrap.innerHTML = list.map(function (p) {
            return '<button type="button" data-act="ratio" data-ratio="' + p.value + '">' + p.label + '</button>';
        }).join('');
    }

    function setRatio(r) {
        cropper.setAspectRatio(isNaN(r) ? NaN : r);
        modal.querySelectorAll('[data-role="ratios"] button').forEach(function (b) {
            var v = parseFloat(b.getAttribute('data-ratio'));
            b.classList.toggle('is-active', (isNaN(v) && isNaN(r)) || Math.abs(v - r) < 0.0001);
        });
    }

    function updateInfo() {
        if (!cropper || !session) { return; }
        var d = cropper.getData(true);
        var scale = Math.min(1, session.field.max / Math.max(d.width, d.height));
        modal.querySelector('.imgf-modal__info').textContent =
            Math.round(d.width * scale) + ' × ' + Math.round(d.height * scale) + ' px';
    }

    function openEditor(field, file) {
        if (!window.Cropper) { return; }
        modal = modal || buildModal();
        session = { field: field, file: file, objectUrl: URL.createObjectURL(file), aspect: field.aspect };
        renderRatios(field.aspect);
        var img = modal.querySelector('.imgf-modal__stage img');
        if (cropper) { cropper.destroy(); cropper = null; }
        img.src = session.objectUrl;
        modal.hidden = false;
        document.body.classList.add('imgf-open');
        cropper = new window.Cropper(img, {
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            responsive: true,
            background: false,
            toggleDragModeOnDblclick: false,
            ready: function () { setRatio(session.aspect); updateInfo(); },
            crop: updateInfo
        });
        modal.querySelector('[data-act="apply"]').focus();
    }

    function closeEditor() {
        if (cropper) { cropper.destroy(); cropper = null; }
        if (session) { URL.revokeObjectURL(session.objectUrl); }
        session = null;
        if (modal) { modal.hidden = true; }
        document.body.classList.remove('imgf-open');
    }

    function applyEdit() {
        var s = session;
        var type = outputType(s.file);
        var canvas = cropper.getCroppedCanvas({
            maxWidth: s.field.max,
            maxHeight: s.field.max,
            fillColor: type === 'image/jpeg' ? '#fff' : 'transparent',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        if (!canvas) { closeEditor(); return; }
        var btn = modal.querySelector('[data-act="apply"]');
        btn.disabled = true;
        canvas.toBlob(function (blob) {
            btn.disabled = false;
            if (blob) {
                s.field.commit(new File([blob], renameFor(s.file.name, type), { type: type }), true);
            }
            closeEditor();
        }, type, 0.9);
    }

    /* ── Public API ───────────────────────────────────────────────── */

    function init(scope) {
        (scope || document).querySelectorAll('[data-image-field]').forEach(function (el) {
            if (!el.imgField) { el.imgField = new Field(el); }
        });
    }

    window.ImageField = {
        init: init,
        reset: function (input, url) {
            var root = input && input.closest('[data-image-field]');
            if (root) { init(root.parentNode); root.imgField.reset(url); }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }
})();
