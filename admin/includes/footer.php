<?php
/**
 * SB-Tech — footer (Smart-School style). Closes the wrapper opened in
 * head.php, renders the footer, then loads shared JS and closes the document.
 * NOTE: in ?pdf=1|preview=1|print=1|word=1 output the module replaces/flushes
 * this buffer before it is reached — keep this file output-light.
 */
?>
</div>
<!-- ./wrapper -->

<!-- Footer -->
<footer class="main-footer text-center">
    <div class="container">
        <div class="row row-sm">
            <div class="col-md-12">
                <span><strong><?= e(office_display_name()) ?></strong> © <?php echo date('Y'); ?>.
                    All rights reserved.</span>
            </div>
        </div>
    </div>
</footer>

<!-- ═══ Global Omnibar (Ctrl+K / Cmd+K) ═══ -->
<?php $omniCsrf = csrfToken(); ?>
<style>
#cmsOmnibar{position:fixed;inset:0;z-index:1050;display:none}
#cmsOmnibar.open{display:block}
#cmsOmnibarBack{position:absolute;inset:0;background:rgba(0,0,0,.45)}
#cmsOmnibarPanel{position:absolute;top:12vh;left:50%;transform:translateX(-50%);width:96%;max-width:620px;
    background:var(--card-bg,#fff);border-radius:10px;box-shadow:0 18px 60px rgba(0,0,0,.28);
    display:flex;flex-direction:column;max-height:65vh;overflow:hidden}
#cmsOmnibarInput{width:100%;border:none;outline:none;padding:14px 18px;font-size:1.05rem;
    background:transparent;color:var(--text-color,#111);border-bottom:1px solid var(--border-color,#e2e8f0)}
#cmsOmnibarInput::placeholder{color:var(--text-secondary,#9ca3af)}
#cmsOmnibarList{flex:1;overflow-y:auto;padding:6px 0}
.cms-omni-group{padding:4px 18px 2px;font-size:.7rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.06em;color:var(--text-secondary,#6c757d);margin-top:4px}
.cms-omni-item{display:flex;align-items:center;gap:10px;padding:8px 18px;cursor:pointer;
    text-decoration:none;color:inherit;border-left:3px solid transparent;transition:background .12s}
.cms-omni-item:hover,.cms-omni-item.active{background:var(--bg-light,#f3f4f6)}
.cms-omni-item.active{border-left-color:var(--primary-color,#3b82f6)}
.cms-omni-item i{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.cms-omni-title{font-weight:600;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cms-omni-sub{font-size:.78rem;color:var(--text-secondary,#6c757d);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:1;min-width:0}
.cms-omni-empty{padding:18px;text-align:center;color:var(--text-secondary,#6c757d);font-size:.9rem}
#cmsOmnibarFooter{padding:6px 18px;border-top:1px solid var(--border-color,#e2e8f0);
    font-size:.72rem;color:var(--text-secondary,#9ca3af);display:flex;gap:14px}
#cmsOmnibarFooter kbd{background:var(--bg-light,#e5e7eb);padding:1px 5px;border-radius:3px;font-size:.7rem}
</style>
<div id="cmsOmnibar">
    <div id="cmsOmnibarBack"></div>
    <div id="cmsOmnibarPanel" role="dialog" aria-modal="true" aria-label="Quick search">
        <input type="text" id="cmsOmnibarInput" placeholder="Search leads, clients, projects…" autocomplete="off" autofocus>
        <div id="cmsOmnibarList" role="listbox"></div>
        <div id="cmsOmnibarFooter">
            <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
            <span><kbd>Enter</kbd> Open</span>
            <span><kbd>Esc</kbd> Close</span>
        </div>
    </div>
</div>
<script>
(function(){
    var $ob = document.getElementById('cmsOmnibar'),
        $back = document.getElementById('cmsOmnibarBack'),
        $input = document.getElementById('cmsOmnibarInput'),
        $list = document.getElementById('cmsOmnibarList'),
        idx = -1,
        items = [],
        timer = null;

    function esc(s){ return (s=='null'||s==null)?'':String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function open(){ $ob.classList.add('open'); $input.value=''; $list.innerHTML=''; idx=-1; items=[]; setTimeout(function(){ $input.focus(); },20); }
    function close(){ $ob.classList.remove('open'); $input.blur(); }

    $back.addEventListener('click', close);
    document.addEventListener('keydown', function(e){
        if ((e.ctrlKey||e.metaKey) && e.key==='k') { e.preventDefault(); if($ob.classList.contains('open')) close(); else open(); }
        if (!$ob.classList.contains('open')) return;
        if (e.key==='Escape') { close(); return; }
        if (e.key==='ArrowDown') { e.preventDefault(); if(items.length){ idx=Math.min(idx+1,items.length-1); hl(); } }
        if (e.key==='ArrowUp') { e.preventDefault(); if(items.length){ idx=Math.max(idx-1,0); hl(); } }
        if (e.key==='Enter' && idx>=0 && items[idx]) { e.preventDefault(); window.location.href=items[idx].url; }
    });

    function hl(){ items.forEach(function($el,i){ $el.$row.classList.toggle('active', i===idx); }); if(items[idx]&&idx>=0){ items[idx].$row.scrollIntoView({block:'nearest'}); } }

    $input.addEventListener('input', function(){
        clearTimeout(timer);
        var q = $input.value.trim();
        if (q.length < 2) { items=[]; $list.innerHTML=''; return; }
        timer = setTimeout(function(){
            fetch('search.php?q='+encodeURIComponent(q))
                .then(function(r){ return r.json(); })
                .then(function(d){ render(d.results||[]); })
                .catch(function(){ items=[]; $list.innerHTML='<div class="cms-omni-empty">Search failed.</div>'; });
        }, 200);
    });

    function render(results){
        items=[]; $list.innerHTML=''; idx=results.length?0:-1;
        if(!results.length){ $list.innerHTML='<div class="cms-omni-empty">No results</div>'; return; }
        var groups={};
        results.forEach(function(r){
            var g=r.group||'Other';
            if(!groups[g]) groups[g]=[];
            groups[g].push(r);
        });
        Object.keys(groups).forEach(function(g){
            $list.insertAdjacentHTML('beforeend','<div class="cms-omni-group">'+esc(g)+'</div>');
            groups[g].forEach(function(r){
                var row=document.createElement('a');
                row.className='cms-omni-item';
                row.href=r.url||'#';
                row.setAttribute('role','option');
                row.innerHTML='<i class="'+esc(r.icon)+'"></i><span class="cms-omni-title">'+esc(r.title)+'</span><span class="cms-omni-sub">'+esc(r.sub)+'</span>';
                $list.appendChild(row);
                var entry={url:r.url||'#',$row:row};
                items.push(entry);
                row.addEventListener('mouseenter', function(){ idx=items.indexOf(entry); hl(); });
                row.addEventListener('click', function(e){ e.preventDefault(); close(); window.location.href=entry.url; });
            });
        });
        if(items.length) hl();
    }
    document.addEventListener('click', function(e){
        if (!$ob.classList.contains('open')) return;
        var panel = document.getElementById('cmsOmnibarPanel');
        if (!panel.contains(e.target) && !$input.contains(e.target)) close();
    });
})();
</script>

<?php include __DIR__ . '/javascript.php'; ?>
</body>
</html>