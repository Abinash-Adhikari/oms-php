/**
 * Question-paper equation helper for Summernote.
 * Preview + insert via MathJax SVG → PNG (Dompdf/Word safe).
 */
(function (window, $) {
  'use strict';

  var activeContext = null;
  var previewTimer = null;

  function mj() {
    if (!window.MathJax) {
      return Promise.reject(new Error('MathJax failed to load.'));
    }
    // MathJax finishes boot asynchronously even when the <script> is sync.
    var ready =
      window.MathJax.startup && window.MathJax.startup.promise
        ? window.MathJax.startup.promise
        : Promise.resolve();
    return ready.then(function () {
      if (!window.MathJax.tex2svgPromise) {
        throw new Error('MathJax failed to load.');
      }
      return window.MathJax;
    });
  }

  function updatePreview() {
    var latex = ($('#qpEqLatex').val() || '').trim();
    var display = $('#qpEqDisplay').is(':checked');
    var $preview = $('#qpEqPreview');
    var $err = $('#qpEqError');
    $err.hide().text('');

    if (!latex) {
      $preview.html('<span class="text-muted">Preview appears here</span>');
      return;
    }

    $preview.html('<span class="text-muted">Rendering…</span>');
    mj()
      .then(function (MJ) {
        var wrapped = display ? '\\displaystyle{' + latex + '}' : latex;
        return MJ.tex2svgPromise(wrapped, { display: !!display });
      })
      .then(function (node) {
        var svg = node.querySelector('svg');
        if (!svg) throw new Error('No SVG produced.');
        $preview.empty().append(svg.cloneNode(true));
      })
      .catch(function (e) {
        $err.text(e.message || 'Invalid LaTeX').show();
        $preview.html('<span class="text-muted">Fix LaTeX to preview</span>');
      });
  }

  function svgToPngDataUrl(svgEl, scale) {
    scale = scale || 2;
    return new Promise(function (resolve, reject) {
      try {
        var clone = svgEl.cloneNode(true);
        if (!clone.getAttribute('xmlns')) {
          clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }
        var bbox = svgEl.getBoundingClientRect();
        var w = Math.max(1, Math.ceil(bbox.width || parseFloat(svgEl.getAttribute('width')) || 120));
        var h = Math.max(1, Math.ceil(bbox.height || parseFloat(svgEl.getAttribute('height')) || 40));
        clone.setAttribute('width', String(w));
        clone.setAttribute('height', String(h));

        var xml = new XMLSerializer().serializeToString(clone);
        var url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
        var img = new Image();
        img.onload = function () {
          var canvas = document.createElement('canvas');
          canvas.width = Math.ceil(w * scale);
          canvas.height = Math.ceil(h * scale);
          var ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          resolve(canvas.toDataURL('image/png'));
        };
        img.onerror = function () {
          reject(new Error('Could not rasterize equation SVG.'));
        };
        img.src = url;
      } catch (e) {
        reject(e);
      }
    });
  }

  function latexToPng(latex, displayMode) {
    return mj().then(function (MJ) {
      var wrapped = displayMode ? '\\displaystyle{' + latex + '}' : latex;
      return MJ.tex2svgPromise(wrapped, { display: !!displayMode }).then(function (node) {
        var svg = node.querySelector('svg');
        if (!svg) throw new Error('No SVG produced for equation.');
        var holder = document.createElement('div');
        holder.style.cssText = 'position:absolute;left:-99999px;top:0;visibility:hidden;';
        holder.appendChild(svg);
        document.body.appendChild(holder);
        return svgToPngDataUrl(svg, 2).then(
          function (dataUrl) {
            document.body.removeChild(holder);
            return dataUrl;
          },
          function (err) {
            document.body.removeChild(holder);
            throw err;
          }
        );
      });
    });
  }

  function open(context) {
    activeContext = context || null;
    $('#qpEqLatex').val('');
    $('#qpEqError').hide().text('');
    $('#qpEqPreview').html('<span class="text-muted">Preview appears here</span>');
    $('#qpEquationModal').modal('show');
    setTimeout(function () {
      $('#qpEqLatex').trigger('focus');
    }, 300);
  }

  function insert() {
    var latex = ($('#qpEqLatex').val() || '').trim();
    var display = $('#qpEqDisplay').is(':checked');
    if (!latex) {
      $('#qpEqError').text('Enter an equation first.').show();
      return;
    }
    var $btn = $('#qpEqInsertBtn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Rendering…');

    latexToPng(latex, display)
      .then(function (dataUrl) {
        if (activeContext && activeContext.invoke) {
          var esc = $('<div>').text(latex).html();
          activeContext.invoke(
            'editor.pasteHTML',
            '<img src="' + dataUrl + '" class="qp-equation" alt="' + esc.replace(/"/g, '&quot;') + '" data-latex="' + esc + '" />'
          );
        }
        $('#qpEquationModal').modal('hide');
      })
      .catch(function (err) {
        $('#qpEqError').text(err.message || String(err)).show();
      })
      .finally(function () {
        $btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Insert into question');
      });
  }

  function registerSummernotePlugin() {
    if (!$.summernote || !$.summernote.plugins || $.summernote.plugins.qpEquation) return;
    $.extend($.summernote.plugins, {
      qpEquation: function (context) {
        var ui = $.summernote.ui;
        context.memo('button.qpEquation', function () {
          return ui
            .button({
              contents: '<i class="fa fa-square-root-alt"></i> Eq',
              tooltip: 'Insert equation (LaTeX)',
              click: function () {
                open(context);
              }
            })
            .render();
        });
      }
    });
  }

  /** Shared Summernote init for question bank / paper forms. */
  function initEditor($el, opts) {
    opts = opts || {};
    if (!$el || !$el.length || typeof $.fn.summernote === 'undefined') return;
    if ($el.next('.note-editor').length) return;
    registerSummernotePlugin();
    $el.summernote({
      height: opts.height || 160,
      placeholder: opts.placeholder || 'Type… Use Eq for maths formulas.',
      toolbar: opts.toolbar || [
        ['font', ['bold', 'italic', 'underline', 'superscript', 'subscript', 'clear']],
        ['fontsize', ['fontsize']],
        ['para', ['ul', 'ol']],
        ['table', ['table']],
        ['insert', ['picture', 'qpEquation', 'hr']],
        ['view', ['codeview']]
      ],
      callbacks: {
        onImageUpload: function (files) {
          var editor = $(this);
          Array.prototype.forEach.call(files, function (file) {
            var reader = new FileReader();
            reader.onload = function (e) {
              editor.summernote('insertImage', e.target.result, file.name || 'diagram');
            };
            reader.readAsDataURL(file);
          });
        }
      }
    });
  }

  function destroyEditor($el) {
    if ($el && $el.length && $el.next('.note-editor').length) {
      $el.summernote('destroy');
    }
  }

  $(function () {
    registerSummernotePlugin();

    $('#qpEqLatex, #qpEqDisplay').on('input change keyup', function () {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(updatePreview, 180);
    });

    $('#qpEqTemplates').on('click', '.qp-eq-chip', function () {
      var latex = $(this).data('latex') || '';
      var cur = $('#qpEqLatex').val() || '';
      $('#qpEqLatex').val(cur ? cur + ' ' + latex : latex);
      updatePreview();
    });

    $('#qpEqInsertBtn').on('click', insert);
  });

  window.QpEquation = {
    open: open,
    initEditor: initEditor,
    destroyEditor: destroyEditor
  };
})(window, jQuery);
