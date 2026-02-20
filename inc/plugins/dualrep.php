<?php

if(!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if(!defined('DUALREP_VERSION')) {
    define('DUALREP_VERSION', '1.3.3');
}
if(!defined('DUALREP_CACHE_TTL')) {
    define('DUALREP_CACHE_TTL', 60);
}
if(!defined('DUALREP_CACHE_KEY')) {
    define('DUALREP_CACHE_KEY', 'dualrep_voters');
}
if(!defined('DUALREP_CACHE_MAX_PIDS')) {
    define('DUALREP_CACHE_MAX_PIDS', 300);
}

$plugins->add_hook('global_start', 'dualrep_global_start');
$plugins->add_hook('showthread_start', 'dualrep_showthread_start');
$plugins->add_hook('postbit', 'dualrep_postbit');
$plugins->add_hook('postbit_prev', 'dualrep_postbit');
$plugins->add_hook('xmlhttp', 'dualrep_xmlhttp');

function dualrep_info()
{
    return array(
        'name' => 'Dual Reputation Buttons',
        'description' => 'Moves reputation button under postbit_editreason and splits into quick + modal buttons; adds voters tooltip for quick button; shows post rating on quick button.',
        'website' => '',
        'author' => 'Feathertail',
        'authorsite' => '',
        'version' => DUALREP_VERSION,
        'compatibility' => '18*'
    );
}

function dualrep_is_installed()
{
    global $db;

    $q1 = $db->simple_select('templates', 'tid', "title='dualrep_postbit_buttons'", array('limit' => 1));
    $tid = (int)$db->fetch_field($q1, 'tid');

    $q2 = $db->simple_select('settinggroups', 'gid', "name='dualrep'", array('limit' => 1));
    $gid = (int)$db->fetch_field($q2, 'gid');

    return ($tid > 0 || $gid > 0);
}

function dualrep_write_file($path, $content)
{
    $dir = dirname($path);
    if(!is_dir($dir)) return false;
    if(!is_writable($dir) && !(file_exists($path) && is_writable($path))) return false;

    $ok = @file_put_contents($path, $content);
    return ($ok !== false);
}

function dualrep_assets_css()
{
    return <<<CSS
.rep-actions{display:inline-flex;gap:6px;align-items:center;}
.rep-action.rep-quick.rep-disabled{opacity:var(--dualrep-disabled-opacity,.65);cursor:default;pointer-events:auto;}

.rep-pop{
  position:absolute;
  z-index:99999;
  display:none;
  min-width:240px;
  max-width:360px;
  border:1px solid var(--dualrep-pop-border, rgba(0,0,0,.15));
  border-radius:var(--dualrep-pop-radius, 8px);
  background:var(--dualrep-pop-bg, #fff);
  color:var(--dualrep-pop-fg, inherit);
  box-shadow:var(--dualrep-pop-shadow, 0 10px 30px rgba(0,0,0,.18));
  padding:10px 12px;
}
.rep-pop-title{font-weight:600;margin:0 0 8px;color:var(--dualrep-pop-title, inherit);}
.rep-pop-list{display:grid;gap:8px;}
.rep-pop-item{display:flex;align-items:center;gap:8px;}
.rep-pop-avatar{
  width:var(--dualrep-avatar-size, 28px);
  height:var(--dualrep-avatar-size, 28px);
  border-radius:var(--dualrep-avatar-radius, 6px);
  object-fit:cover;
  display:block;
}
.rep-pop-name{text-decoration:none;color:var(--dualrep-link, inherit);}
.rep-pop-name:hover{color:var(--dualrep-link-hover, var(--dualrep-link, inherit));}
.rep-pop-footer{display:flex;justify-content:space-between;align-items:center;margin-top:10px;}
.rep-pop-count{color:var(--dualrep-muted, rgba(0,0,0,.65));}
.rep-pop-all{text-decoration:none;font-weight:600;color:var(--dualrep-link, inherit);}
.rep-pop-all:hover{color:var(--dualrep-link-hover, var(--dualrep-link, inherit));}
.rep-pop-empty{opacity:.8;color:var(--dualrep-muted, rgba(0,0,0,.65));}
.rep-pop-loading{opacity:.8;color:var(--dualrep-muted, rgba(0,0,0,.65));}

.rep-voters-overlay{
  position:fixed;
  inset:0;
  z-index:100000;
  display:none;
  background:var(--dualrep-overlay-bg, rgba(0,0,0,.45));
}
.rep-voters-dialog{
  width:min(720px,calc(100vw - 24px));
  max-height:min(80vh,720px);
  overflow:auto;
  background:var(--dualrep-modal-bg, #fff);
  color:var(--dualrep-modal-fg, inherit);
  border-radius:var(--dualrep-modal-radius, 10px);
  margin:10vh auto 0;
  box-shadow:var(--dualrep-modal-shadow, 0 18px 50px rgba(0,0,0,.30));
  border:1px solid var(--dualrep-modal-border, rgba(0,0,0,.12));
}
.rep-voters-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding:14px 16px;
  border-bottom:1px solid var(--dualrep-modal-divider, rgba(0,0,0,.12));
}
.rep-voters-title{font-weight:700;color:var(--dualrep-modal-title, inherit);}
.rep-voters-close{
  border:0;
  background:transparent;
  font-size:22px;
  line-height:1;
  cursor:pointer;
  padding:4px 8px;
  color:var(--dualrep-close, inherit);
}
.rep-voters-close:hover{color:var(--dualrep-close-hover, var(--dualrep-close, inherit));}
.rep-voters-body{padding:12px 16px 16px;}
.rep-voters-list{display:grid;gap:10px;}
.rep-voters-empty{opacity:.85;color:var(--dualrep-muted, rgba(0,0,0,.65));}
CSS;
}

function dualrep_assets_js()
{
    return <<<JS
(function(){
  var cfg = window.DualRepCfg || {};
  window.DualRep = window.DualRep || {};
  var DualRep = window.DualRep;
  if(DualRep.__inited) return;
  DualRep.__inited = true;

  DualRep.cfg = cfg;
  DualRep.cache = { preview: Object.create(null), all: Object.create(null) };
  DualRep.inflight = { preview: Object.create(null), all: Object.create(null) };

  function isHoverCapable(){
    try { return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches); }
    catch(e){ return true; }
  }

  function formatSum(n){
    n = parseInt(n || 0, 10);
    if(n > 0 && DualRep.cfg.showPlus) return '+' + n;
    return String(n);
  }

  function setQuickLabel(el, sum){
    try{
      el.setAttribute('data-rep-sum', String(sum));
      var sp = el.querySelector('span');
      if(sp) sp.textContent = formatSum(sum);
    }catch(e){}
  }

  function notify(msg){
    if(!msg) return;
    if(window.jQuery && jQuery.jGrowl){ jQuery.jGrowl(msg); return; }
    try{ console.log(msg); }catch(e){}
  }

  function extractMessage(html, cls){
    var re = new RegExp('class="[^"]*\\b' + cls + '\\b[^"]*"[^>]*>([\\s\\S]*?)<\\/', 'i');
    var m = html && html.match(re);
    if(!m) return '';
    return m[1].replace(/<[^>]*>/g,' ').replace(/\\s+/g,' ').trim();
  }

  function parseError(html){
    return extractMessage(html, 'error') || extractMessage(html, 'error_inline') || '';
  }

  function parseSuccess(html){
    return extractMessage(html, 'success_message') || '';
  }

  function getUser(){
    return DualRep.cfg.user || {uid:0, username:'', avatar:'', profile:''};
  }

  function patchDataAfterVote(data){
    if(!data || !data.ok) return data;
    var u = getUser();
    if(!u || !u.uid) return data;

    data.sum = parseInt(data.sum || 0, 10) + 1;
    data.total = parseInt(data.total || 0, 10) + 1;

    data.voters = Array.isArray(data.voters) ? data.voters.slice() : [];
    var idx = -1;
    for(var i=0;i<data.voters.length;i++){
      if(parseInt(data.voters[i].uid,10) === parseInt(u.uid,10)){ idx = i; break; }
    }
    if(idx !== -1) data.voters.splice(idx,1);

    data.voters.unshift({
      uid: u.uid,
      username: u.username,
      profile: u.profile,
      avatar: u.avatar || '',
      aw: 0,
      ah: 0
    });

    var lim = parseInt(DualRep.cfg.previewLimit || 0, 10);
    if(lim > 0 && data.__limited) data.voters = data.voters.slice(0, lim);

    return data;
  }

  DualRep.quick = function(el, uid, pid){
    uid = parseInt(uid,10);
    pid = parseInt(pid,10);
    if(!uid || !pid) return false;

    if(el && el.getAttribute('data-rep-disabled') === '1') return false;

    try{
      if(el && el.getAttribute('data-rep-busy') === '1') return false;
      if(el) el.setAttribute('data-rep-busy','1');
    }catch(e){}

    var key = (window.my_post_key || '');
    var url = 'reputation.php?action=do_add&uid=' + encodeURIComponent(uid) + '&pid=' + encodeURIComponent(pid);

    function disableQuick(){
      try{
        if(!el) return;
        el.setAttribute('data-rep-disabled','1');
        el.setAttribute('aria-disabled','true');
        el.setAttribute('onclick','return false;');
        if(el.classList){
          el.classList.add('rep-disabled');
          el.classList.add('rep-done');
        }
      }catch(e){}
    }

    function done(ok, msg){
      try{ if(el) el.removeAttribute('data-rep-busy'); }catch(e){}
      if(ok){
        disableQuick();
        var cur = parseInt(el.getAttribute('data-rep-sum') || '0', 10);
        var next = cur + 1;
        setQuickLabel(el, next);

        if(DualRep.cache.preview[pid]) DualRep.cache.preview[pid] = patchDataAfterVote(DualRep.cache.preview[pid]);
        if(DualRep.cache.all[pid]) DualRep.cache.all[pid] = patchDataAfterVote(DualRep.cache.all[pid]);

        if(DualRep.__popPid === pid && DualRep.__pop){
          renderIntoPop(DualRep.__pop, DualRep.cache.preview[pid] || null, true);
        }
      }
      if(msg) notify(msg);
    }

    if(window.jQuery && typeof jQuery.post === 'function'){
      jQuery.post(url, {
        my_post_key: key,
        rid: 0,
        reputation: 1,
        comments: '',
        nomodal: 1
      }, function(data){
        var html = (typeof data === 'string') ? data : '';
        var err = parseError(html);
        if(err) return done(false, err);
        var okMsg = parseSuccess(html);
        return done(true, okMsg || (DualRep.cfg.text && DualRep.cfg.text.voteSuccess) || 'OK');
      }).fail(function(xhr){
        var html = (xhr && xhr.responseText) ? xhr.responseText : '';
        var err = parseError(html);
        return done(false, err || '');
      });
      return false;
    }

    var body = 'my_post_key=' + encodeURIComponent(key)
      + '&rid=0'
      + '&reputation=1'
      + '&comments='
      + '&nomodal=1';

    if(window.fetch){
      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With':'XMLHttpRequest'
        },
        body: body
      }).then(function(r){ return r.text(); })
        .then(function(t){
          var err = parseError(t);
          if(err) return done(false, err);
          var okMsg = parseSuccess(t);
          return done(true, okMsg || (DualRep.cfg.text && DualRep.cfg.text.voteSuccess) || 'OK');
        })
        .catch(function(){ done(false, ''); });
      return false;
    }

    try{
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
      xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
      xhr.onreadystatechange = function(){
        if(xhr.readyState !== 4) return;
        var t = xhr.responseText || '';
        var err = parseError(t);
        if(err) return done(false, err);
        var okMsg = parseSuccess(t);
        return done(true, okMsg || (DualRep.cfg.text && DualRep.cfg.text.voteSuccess) || 'OK');
      };
      xhr.send(body);
    }catch(e){
      done(false, '');
    }

    return false;
  };

  var pop = null;
  var popHideT = 0;
  var hoverT = 0;

  function ensurePop(){
    if(pop) return pop;
    pop = document.createElement('div');
    pop.className = 'rep-pop';
    pop.innerHTML =
      '<div class="rep-pop-title">' + ((DualRep.cfg.text && DualRep.cfg.text.votersTitle) || 'Voters') + '</div>' +
      '<div class="rep-pop-list"></div>' +
      '<div class="rep-pop-footer">' +
        '<span class="rep-pop-count"></span>' +
        '<a href="javascript:void(0)" class="rep-pop-all" style="display:none">' + ((DualRep.cfg.text && DualRep.cfg.text.showAll) || 'All') + '</a>' +
      '</div>';
    document.body.appendChild(pop);

    pop.addEventListener('mouseenter', function(){
      if(popHideT){ clearTimeout(popHideT); popHideT = 0; }
    });

    pop.addEventListener('mouseleave', function(){
      scheduleHide();
    });

    pop.querySelector('.rep-pop-all').addEventListener('click', function(e){
      e.preventDefault();
      if(!DualRep.__popPid) return;
      showAll(DualRep.__popPid);
    });

    return pop;
  }

  function scheduleHide(){
    if(popHideT) clearTimeout(popHideT);
    popHideT = setTimeout(function(){
      if(pop) pop.style.display = 'none';
      DualRep.__popPid = 0;
    }, 220);
  }

  function placePop(btn){
    var r = btn.getBoundingClientRect();
    var x = r.left + window.scrollX;
    var y = r.bottom + window.scrollY + 8;

    pop.style.left = x + 'px';
    pop.style.top = y + 'px';

    var pr = pop.getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var vh = document.documentElement.clientHeight;

    if(pr.right > vw){
      var nx = Math.max(8, vw - pr.width - 8) + window.scrollX;
      pop.style.left = nx + 'px';
    }
    if(pr.bottom > vh){
      var ny = (r.top + window.scrollY) - pr.height - 8;
      pop.style.top = Math.max(8 + window.scrollY, ny) + 'px';
    }
  }

  function renderIntoPop(root, data, limited){
    var mode = DualRep.cfg.displayMode || 'both';
    var list = root.querySelector('.rep-pop-list');
    var count = root.querySelector('.rep-pop-count');
    var allBtn = root.querySelector('.rep-pop-all');

    list.innerHTML = '';
    allBtn.style.display = 'none';
    count.textContent = '';

    if(!data){
      list.innerHTML = '<div class="rep-pop-loading">' + ((DualRep.cfg.text && DualRep.cfg.text.loading) || 'Loading...') + '</div>';
      return;
    }

    var total = parseInt(data.total || 0, 10);
    count.textContent = total ? (((DualRep.cfg.text && DualRep.cfg.text.totalPrefix) || 'Total: ') + total) : '';

    var voters = Array.isArray(data.voters) ? data.voters : [];
    if(!voters.length){
      list.innerHTML = '<div class="rep-pop-empty">' + ((DualRep.cfg.text && DualRep.cfg.text.noVotes) || 'Empty') + '</div>';
      return;
    }

    voters.forEach(function(v){
      var row = document.createElement('div');
      row.className = 'rep-pop-item';

      if(mode === 'avatar' || mode === 'both'){
        if(v.avatar){
          var a1 = document.createElement('a');
          a1.href = v.profile;
          var img = document.createElement('img');
          img.className = 'rep-pop-avatar';
          img.src = v.avatar;
          img.alt = v.username || '';
          a1.appendChild(img);
          row.appendChild(a1);
        } else {
          var stub = document.createElement('span');
          stub.style.width = (DualRep.cfg.avatarSize || 28) + 'px';
          stub.style.height = (DualRep.cfg.avatarSize || 28) + 'px';
          row.appendChild(stub);
        }
      }

      if(mode === 'nick' || mode === 'both'){
        var a2 = document.createElement('a');
        a2.className = 'rep-pop-name';
        a2.href = v.profile;
        a2.textContent = v.username || ('UID ' + v.uid);
        row.appendChild(a2);
      }

      list.appendChild(row);
    });

    if(limited && total > voters.length){
      allBtn.style.display = '';
    }
  }

  function fetchVoters(pid, all){
    pid = parseInt(pid,10);
    var bucket = all ? 'all' : 'preview';

    if(DualRep.cache[bucket][pid]) return Promise.resolve(DualRep.cache[bucket][pid]);
    if(DualRep.inflight[bucket][pid]) return DualRep.inflight[bucket][pid];

    var url = 'xmlhttp.php?action=dualrep_voters&pid=' + encodeURIComponent(pid) + (all ? '&all=1' : '');

    var p = fetch(url, { credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if(!res || !res.ok){
          DualRep.cache[bucket][pid] = { ok:false, total:0, sum:0, voters:[] };
          return DualRep.cache[bucket][pid];
        }
        if(!all){
          res.__limited = true;
          var lim = parseInt(DualRep.cfg.previewLimit || 0, 10);
          if(lim > 0 && Array.isArray(res.voters)) res.voters = res.voters.slice(0, lim);
        } else {
          res.__limited = false;
        }
        DualRep.cache[bucket][pid] = res;
        return res;
      })
      .catch(function(){
        DualRep.cache[bucket][pid] = { ok:false, total:0, sum:0, voters:[] };
        return DualRep.cache[bucket][pid];
      })
      .finally(function(){
        delete DualRep.inflight[bucket][pid];
      });

    DualRep.inflight[bucket][pid] = p;
    return p;
  }

  function showPreview(btn){
    var pid = parseInt(btn.getAttribute('data-rep-pid') || '0', 10);
    if(!pid) return;

    if(popHideT){ clearTimeout(popHideT); popHideT = 0; }

    ensurePop();
    DualRep.__pop = pop;
    DualRep.__popPid = pid;

    placePop(btn);
    pop.style.display = 'block';
    renderIntoPop(pop, null, true);

    var token = (DualRep.__reqToken = (DualRep.__reqToken || 0) + 1);

    fetchVoters(pid, false).then(function(res){
      if(!pop || DualRep.__popPid !== pid) return;
      if(token !== DualRep.__reqToken) return;

      if(!res || !res.ok){
        var list = pop.querySelector('.rep-pop-list');
        list.innerHTML = '<div class="rep-pop-empty">' + ((DualRep.cfg.text && DualRep.cfg.text.loadFail) || 'Failed') + '</div>';
        return;
      }
      renderIntoPop(pop, res, true);
      placePop(btn);
    });
  }

  var overlay = null;
  var dialogList = null;
  var dialogTitle = null;

  function ensureOverlay(){
    if(overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'rep-voters-overlay';
    overlay.innerHTML =
      '<div class="rep-voters-dialog" role="dialog" aria-modal="true">' +
        '<div class="rep-voters-head">' +
          '<div class="rep-voters-title"></div>' +
          '<button type="button" class="rep-voters-close" aria-label="' + ((DualRep.cfg.text && DualRep.cfg.text.close) || 'Close') + '">×</button>' +
        '</div>' +
        '<div class="rep-voters-body">' +
          '<div class="rep-voters-list"></div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);
    dialogTitle = overlay.querySelector('.rep-voters-title');
    dialogList = overlay.querySelector('.rep-voters-list');

    overlay.querySelector('.rep-voters-close').addEventListener('click', function(){
      overlay.style.display = 'none';
    });

    overlay.addEventListener('click', function(e){
      if(e.target === overlay) overlay.style.display = 'none';
    });

    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape' && overlay && overlay.style.display === 'block'){
        overlay.style.display = 'none';
      }
    });

    return overlay;
  }

  function renderAllList(voters){
    dialogList.innerHTML = '';
    if(!voters || !voters.length){
      dialogList.innerHTML = '<div class="rep-voters-empty">' + ((DualRep.cfg.text && DualRep.cfg.text.noVotes) || 'Empty') + '</div>';
      return;
    }

    var mode = DualRep.cfg.displayMode || 'both';

    voters.forEach(function(v){
      var row = document.createElement('div');
      row.className = 'rep-pop-item';

      if(mode === 'avatar' || mode === 'both'){
        if(v.avatar){
          var a1 = document.createElement('a');
          a1.href = v.profile;
          var img = document.createElement('img');
          img.className = 'rep-pop-avatar';
          img.src = v.avatar;
          img.alt = v.username || '';
          a1.appendChild(img);
          row.appendChild(a1);
        } else {
          var stub = document.createElement('span');
          stub.style.width = (DualRep.cfg.avatarSize || 28) + 'px';
          stub.style.height = (DualRep.cfg.avatarSize || 28) + 'px';
          row.appendChild(stub);
        }
      }

      if(mode === 'nick' || mode === 'both'){
        var a2 = document.createElement('a');
        a2.className = 'rep-pop-name';
        a2.href = v.profile;
        a2.textContent = v.username || ('UID ' + v.uid);
        row.appendChild(a2);
      }

      dialogList.appendChild(row);
    });
  }

  function showAll(pid){
    ensureOverlay();
    overlay.style.display = 'block';
    dialogTitle.textContent = (DualRep.cfg.text && DualRep.cfg.text.loading) || 'Loading...';
    dialogList.innerHTML = '';

    fetchVoters(pid, true).then(function(res){
      if(!res || !res.ok){
        dialogTitle.textContent = (DualRep.cfg.text && DualRep.cfg.text.loadFail) || 'Failed';
        dialogList.innerHTML = '<div class="rep-voters-empty">' + ((DualRep.cfg.text && DualRep.cfg.text.loadFail) || 'Failed') + '</div>';
        return;
      }

      var total = parseInt(res.total || 0, 10);
      var prefix = (DualRep.cfg.text && DualRep.cfg.text.votedByPrefix) || 'Оценило: ';
      dialogTitle.textContent = prefix + total;

      renderAllList(res.voters);
    });
  }

  DualRep.openAll = showAll;

  function findQuick(e){
    return e.target && e.target.closest ? e.target.closest('a.rep-action.rep-quick') : null;
  }

  var hoverCapable = isHoverCapable();

  if(hoverCapable){
    document.addEventListener('mouseover', function(e){
      var btn = findQuick(e);
      if(!btn) return;

      if(hoverT) clearTimeout(hoverT);
      hoverT = setTimeout(function(){
        showPreview(btn);
      }, parseInt(DualRep.cfg.hoverDelay || 180, 10));
    }, true);

    document.addEventListener('mouseout', function(e){
      var btn = findQuick(e);
      if(!btn) return;

      if(hoverT){ clearTimeout(hoverT); hoverT = 0; }
      if(e.relatedTarget && btn.contains(e.relatedTarget)) return;
      scheduleHide();
    }, true);
  } else {
    document.addEventListener('click', function(e){
      var btn = findQuick(e);
      if(!btn) return;

      var disabled = (btn.getAttribute('data-rep-disabled') === '1');
      if(disabled){
        e.preventDefault();
        showPreview(btn);
        return;
      }
    }, true);
  }

})();
JS;
}

function dualrep_ensure_assets()
{
    global $db;

    $tpl_html = '<span class="rep-actions">{$quick}{$modal}</span>';

    $q = $db->simple_select('templates', 'tid', "title='dualrep_postbit_buttons'", array('limit' => 1));
    $tid = (int)$db->fetch_field($q, 'tid');

    if(!$tid) {
        $db->insert_query('templates', array(
            'title' => 'dualrep_postbit_buttons',
            'template' => $tpl_html,
            'sid' => -1,
            'version' => '1800',
            'dateline' => TIME_NOW
        ));
    } else {
        $db->update_query('templates', array(
            'template' => $tpl_html,
            'dateline' => TIME_NOW
        ), "tid='{$tid}'");
    }

    $qg = $db->simple_select('settinggroups', 'gid', "name='dualrep'", array('limit' => 1));
    $gid = (int)$db->fetch_field($qg, 'gid');

    if(!$gid) {
        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'dualrep',
            'title' => 'Dual Reputation Buttons',
            'description' => 'Настройки двойной кнопки репутации (быстро + модалка) и списка оценивших.',
            'disporder' => 1,
            'isdefault' => 0
        ));
    }

    $settings = array(
        array(
            'name' => 'dualrep_preview_limit',
            'title' => 'Количество пользователей в подсказке',
            'description' => 'Сколько оценивших показывать в мини-окошке при наведении на быструю кнопку.',
            'optionscode' => 'numeric',
            'value' => '5',
            'disporder' => 1
        ),
        array(
            'name' => 'dualrep_display_mode',
            'title' => 'Что показывать в списке оценивших',
            'description' => 'nick = только ник; avatar = только аватар; both = ник и аватар.',
            'optionscode' => "select\nnick=Ник\navatar=Аватар\nboth=Ник и аватар",
            'value' => 'both',
            'disporder' => 2
        ),
        array(
            'name' => 'dualrep_show_plus',
            'title' => 'Показывать плюс у положительных значений',
            'description' => 'Если включено, положительные значения будут отображаться как +6. Если выключено — 6.',
            'optionscode' => 'yesno',
            'value' => '1',
            'disporder' => 3
        ),
        array(
            'name' => 'dualrep_hover_delay',
            'title' => 'Задержка подсказки (мс)',
            'description' => 'Задержка перед показом мини-окошка при наведении мышью.',
            'optionscode' => 'numeric',
            'value' => '180',
            'disporder' => 4
        )
    );

    foreach($settings as $s) {
        $qs = $db->simple_select('settings', 'sid', "name='".$db->escape_string($s['name'])."'", array('limit' => 1));
        $sid = (int)$db->fetch_field($qs, 'sid');
        if(!$sid) {
            $db->insert_query('settings', array(
                'name' => $s['name'],
                'title' => $s['title'],
                'description' => $s['description'],
                'optionscode' => $s['optionscode'],
                'value' => $s['value'],
                'disporder' => (int)$s['disporder'],
                'gid' => (int)$gid
            ));
        }
    }

    if(function_exists('rebuild_settings')) {
        rebuild_settings();
    } else {
        require_once MYBB_ROOT.'inc/functions.php';
        rebuild_settings();
    }

    $css_ok = dualrep_write_file(MYBB_ROOT.'jscripts/dualrep.css', dualrep_assets_css());
    $js_ok = dualrep_write_file(MYBB_ROOT.'jscripts/dualrep.js', dualrep_assets_js());

    global $plugins;
    if(isset($plugins) && is_object($plugins)) {
        $data = array('css_ok' => $css_ok, 'js_ok' => $js_ok);
        $plugins->run_hooks('dualrep_assets_written', $data);
    }
}

function dualrep_cleanup_legacy_cache()
{
    global $db;
    if(!isset($db) || !is_object($db)) return;

    $db->delete_query('datacache', "title LIKE 'dualrep_voters_%'");
}

function dualrep_install()
{
    dualrep_ensure_assets();
    dualrep_cleanup_legacy_cache();
}

function dualrep_uninstall()
{
    dualrep_deactivate();

    global $db;

    $db->delete_query('templates', "title='dualrep_postbit_buttons'");
    $db->delete_query('settings', "name IN('dualrep_preview_limit','dualrep_display_mode','dualrep_show_plus','dualrep_hover_delay')");
    $db->delete_query('settinggroups', "name='dualrep'");

    $db->delete_query('datacache', "title='".$db->escape_string(DUALREP_CACHE_KEY)."' OR title LIKE 'dualrep_voters_%'");

    if(function_exists('rebuild_settings')) {
        rebuild_settings();
    } else {
        require_once MYBB_ROOT.'inc/functions.php';
        rebuild_settings();
    }

    @unlink(MYBB_ROOT.'jscripts/dualrep.css');
    @unlink(MYBB_ROOT.'jscripts/dualrep.js');
}

function dualrep_activate()
{
    dualrep_ensure_assets();
    dualrep_cleanup_legacy_cache();

    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $pat_postbit = '#(\{\$post\[(?:\'|")input_editreason(?:\'|")\]\})(?!\s*\{\$post\[(?:\'|")dualrep_buttons(?:\'|")\]\})#';
    $rep_postbit = '$1{$post[\'dualrep_buttons\']}';
    find_replace_templatesets('postbit', $pat_postbit, $rep_postbit);
    find_replace_templatesets('postbit_classic', $pat_postbit, $rep_postbit);

    $pat_head = '#(\{\$stylesheets\})(?!\s*\{\$dualrep_assets\})#';
    $rep_head = '$1{$dualrep_assets}';
    find_replace_templatesets('headerinclude', $pat_head, $rep_head);
}

function dualrep_deactivate()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

    $pat_remove_buttons = '#\s*\{\$post\[(?:\'|")dualrep_buttons(?:\'|")\]\}#';
    find_replace_templatesets('postbit', $pat_remove_buttons, '');
    find_replace_templatesets('postbit_classic', $pat_remove_buttons, '');

    $pat_remove_assets = '#\s*\{\$dualrep_assets\}#';
    find_replace_templatesets('headerinclude', $pat_remove_assets, '');
}

function dualrep_t($key, $fallback)
{
    global $lang;
    if(isset($lang) && isset($lang->$key) && $lang->$key !== '') {
        return $lang->$key;
    }
    return $fallback;
}

function dualrep_load_lang()
{
    global $lang;
    if(!isset($lang) || !is_object($lang)) return;

    if(isset($lang->dualrep_quick_title)) return;

    if(method_exists($lang, 'load')) {
        $lang->load('dualrep');
    }
}

function dualrep_global_start()
{
    global $mybb, $dualrep_assets, $plugins;

    if(defined('THIS_SCRIPT') && THIS_SCRIPT !== 'showthread.php') {
        $dualrep_assets = '';
        return;
    }

    dualrep_load_lang();

    $preview_limit = isset($mybb->settings['dualrep_preview_limit']) ? (int)$mybb->settings['dualrep_preview_limit'] : 5;
    if($preview_limit <= 0) $preview_limit = 5;

    $display_mode = isset($mybb->settings['dualrep_display_mode']) ? (string)$mybb->settings['dualrep_display_mode'] : 'both';
    if(!in_array($display_mode, array('nick','avatar','both'), true)) $display_mode = 'both';

    $show_plus = isset($mybb->settings['dualrep_show_plus']) ? (int)$mybb->settings['dualrep_show_plus'] : 1;
    $hover_delay = isset($mybb->settings['dualrep_hover_delay']) ? (int)$mybb->settings['dualrep_hover_delay'] : 180;

    $uid = isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0;
    $username = isset($mybb->user['username']) ? (string)$mybb->user['username'] : '';
    $avatar = isset($mybb->user['avatar']) ? (string)$mybb->user['avatar'] : '';
    $profile = $uid ? ('member.php?action=profile&uid='.$uid) : '';

    $cfg = array(
        'previewLimit' => $preview_limit,
        'displayMode' => $display_mode,
        'showPlus' => $show_plus ? 1 : 0,
        'hoverDelay' => $hover_delay,
        'user' => array(
            'uid' => $uid,
            'username' => $username,
            'avatar' => $avatar,
            'profile' => $profile
        ),
        'text' => array(
            'voteSuccess' => dualrep_t('dualrep_vote_success', 'Репутация +1'),
            'votersTitle' => dualrep_t('dualrep_voters_title', 'Оценили'),
            'showAll' => dualrep_t('dualrep_show_all', 'Показать всех'),
            'totalPrefix' => dualrep_t('dualrep_total_prefix', 'Всего: '),
            'loading' => dualrep_t('dualrep_loading', 'Загрузка...'),
            'noVotes' => dualrep_t('dualrep_no_votes', 'Пока нет оценок'),
            'loadFail' => dualrep_t('dualrep_load_fail', 'Не удалось загрузить список'),
            'ratingPrefix' => dualrep_t('dualrep_rating_prefix', 'рейтинг '),
            'close' => dualrep_t('dualrep_close', 'Закрыть'),
            'votedByPrefix' => dualrep_t('dualrep_voted_by_prefix', 'Оценило: ')
        )
    );

    if(isset($plugins) && is_object($plugins)) {
        $plugins->run_hooks('dualrep_assets_cfg', $cfg);
    }

    $cfg_json = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    if($cfg_json === false) $cfg_json = '{}';

    $bburl = isset($mybb->settings['bburl']) ? rtrim($mybb->settings['bburl'], '/') : '';
    $css_url = $bburl.'/jscripts/dualrep.css?v='.rawurlencode(DUALREP_VERSION);
    $js_url = $bburl.'/jscripts/dualrep.js?v='.rawurlencode(DUALREP_VERSION);

    $css_exists = file_exists(MYBB_ROOT.'jscripts/dualrep.css');
    $js_exists = file_exists(MYBB_ROOT.'jscripts/dualrep.js');

    $assets = '';
    if($css_exists) {
        $assets .= '<link rel="stylesheet" type="text/css" href="'.$css_url.'" />'."\n";
    } else {
        $assets .= '<style>'.dualrep_assets_css().'</style>'."\n";
    }

    $assets .= '<script>window.DualRepCfg='.$cfg_json.';</script>'."\n";

    if($js_exists) {
        $assets .= '<script src="'.$js_url.'"></script>'."\n";
    } else {
        $assets .= '<script>'.dualrep_assets_js().'</script>'."\n";
    }

    if(isset($plugins) && is_object($plugins)) {
        $wrap = array('html' => $assets);
        $plugins->run_hooks('dualrep_assets_html', $wrap);
        if(isset($wrap['html'])) $assets = $wrap['html'];
    }

    $dualrep_assets = $assets;
}

function dualrep_add_class($html, $classes_to_add)
{
    if(preg_match('/\bclass=(["\'])(.*?)\1/i', $html)) {
        return preg_replace_callback(
            '/\bclass=(["\'])(.*?)\1/i',
            function($m) use ($classes_to_add) {
                $q = $m[1];
                $cls = trim($m[2].' '.$classes_to_add);
                return 'class='.$q.$cls.$q;
            },
            $html,
            1
        );
    }
    return preg_replace('/<a\b/i', '<a class="'.htmlspecialchars_uni($classes_to_add).'"', $html, 1);
}

function dualrep_set_attr($html, $attr, $value)
{
    $attr_re = preg_quote($attr, '/');
    $value = htmlspecialchars_uni($value);

    if(preg_match('/\b'.$attr_re.'=(["\']).*?\1/i', $html)) {
        return preg_replace_callback(
            '/\b'.$attr_re.'=(["\']).*?\1/i',
            function() use ($attr, $value) {
                return $attr.'="'.$value.'"';
            },
            $html,
            1
        );
    }

    $insert = '<a '.$attr.'="'.$value.'"';
    return preg_replace('/<a\b/i', $insert, $html, 1);
}

function dualrep_set_span_text($html, $text)
{
    $text = htmlspecialchars_uni($text);

    if(preg_match('/<span\b[^>]*>[\s\S]*?<\/span>/i', $html)) {
        return preg_replace('/<span\b[^>]*>[\s\S]*?<\/span>/i', '<span>'.$text.'</span>', $html, 1);
    }

    return $html;
}

function dualrep_format_sum($sum)
{
    global $mybb;

    $sum = (int)$sum;
    $show_plus = isset($mybb->settings['dualrep_show_plus']) ? (int)$mybb->settings['dualrep_show_plus'] : 1;

    if($sum > 0 && $show_plus) return '+'.$sum;
    return (string)$sum;
}

function dualrep_prefetch_thread_page($tid)
{
    global $db, $mybb, $plugins;

    $tid = (int)$tid;
    if($tid <= 0) return;

    if(isset($GLOBALS['dualrep_prefetch_done']) && $GLOBALS['dualrep_prefetch_done'] === $tid) return;

    $perpage = isset($mybb->settings['postsperpage']) ? (int)$mybb->settings['postsperpage'] : 0;
    if($perpage <= 0) $perpage = 20;

    $page = 1;
    if(method_exists($mybb, 'get_input') && defined('MyBB::INPUT_INT')) {
        $page = (int)$mybb->get_input('page', MyBB::INPUT_INT);
    } else {
        $page = isset($mybb->input['page']) ? (int)$mybb->input['page'] : 1;
    }
    if($page <= 0) $page = 1;

    $offset = ($page - 1) * $perpage;

    $pids = array();
    $q = $db->simple_select('posts', 'pid', "tid='{$tid}' AND visible='1'", array(
        'order_by' => 'dateline',
        'order_dir' => 'ASC',
        'limit_start' => $offset,
        'limit' => $perpage
    ));
    while($row = $db->fetch_array($q)) {
        $pids[] = (int)$row['pid'];
    }

    $GLOBALS['dualrep_page_pids'] = $pids;
    $GLOBALS['dualrep_sum_by_pid'] = array();
    $GLOBALS['dualrep_voted_by_pid'] = array();

    if(!$pids) {
        $GLOBALS['dualrep_prefetch_done'] = $tid;
        return;
    }

    $in = implode(',', array_map('intval', $pids));

    $qs = $db->write_query("
        SELECT pid, SUM(reputation) AS s
        FROM {$db->table_prefix}reputation
        WHERE pid IN ({$in}) AND adduid<>0
        GROUP BY pid
    ");
    while($r = $db->fetch_array($qs)) {
        $GLOBALS['dualrep_sum_by_pid'][(int)$r['pid']] = ($r['s'] === null) ? 0 : (int)$r['s'];
    }

    $viewer_uid = isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0;
    if($viewer_uid > 0) {
        $qv = $db->write_query("
            SELECT pid, 1 AS voted
            FROM {$db->table_prefix}reputation
            WHERE pid IN ({$in}) AND adduid='{$viewer_uid}'
            GROUP BY pid
        ");
        while($r = $db->fetch_array($qv)) {
            $GLOBALS['dualrep_voted_by_pid'][(int)$r['pid']] = true;
        }
    }

    if(isset($plugins) && is_object($plugins)) {
        $payload = array(
            'tid' => $tid,
            'pids' => $pids,
            'sum_by_pid' => &$GLOBALS['dualrep_sum_by_pid'],
            'voted_by_pid' => &$GLOBALS['dualrep_voted_by_pid']
        );
        $plugins->run_hooks('dualrep_prefetch_done', $payload);
    }

    $GLOBALS['dualrep_prefetch_done'] = $tid;
}

function dualrep_showthread_start()
{
    global $mybb, $thread;

    if(empty($mybb->settings['enablereputation'])) return;
    if(empty($thread['tid'])) return;

    dualrep_prefetch_thread_page((int)$thread['tid']);
}

function dualrep_get_post_sum_fallback($pid)
{
    global $db;

    $pid = (int)$pid;
    if($pid <= 0) return 0;

    $q = $db->simple_select('reputation', 'SUM(reputation) AS s', "pid='{$pid}' AND adduid<>0");
    $s = $db->fetch_field($q, 's');
    return ($s === null) ? 0 : (int)$s;
}

function dualrep_get_has_voted_fallback($pid, $viewer_uid)
{
    global $db;

    $pid = (int)$pid;
    $viewer_uid = (int)$viewer_uid;
    if($pid <= 0 || $viewer_uid <= 0) return false;

    $q = $db->simple_select('reputation', 'rid', "pid='{$pid}' AND adduid='{$viewer_uid}'", array('limit' => 1));
    return (bool)$db->fetch_field($q, 'rid');
}

function dualrep_postbit($post)
{
    global $templates, $mybb, $plugins;

    $post['dualrep_buttons'] = '';

    if(empty($mybb->settings['enablereputation'])) {
        return $post;
    }

    $viewer_uid = isset($mybb->user['uid']) ? (int)$mybb->user['uid'] : 0;

    $uid = (int)$post['uid'];
    $pid = (int)$post['pid'];

    $is_owner = ($viewer_uid > 0 && $viewer_uid === $uid);
    $has_rep_button = !empty($post['button_rep']);

    if(!$has_rep_button && !$is_owner) {
        return $post;
    }

    if(isset($GLOBALS['dualrep_sum_by_pid']) && is_array($GLOBALS['dualrep_sum_by_pid']) && array_key_exists($pid, $GLOBALS['dualrep_sum_by_pid'])) {
        $sum = (int)$GLOBALS['dualrep_sum_by_pid'][$pid];
    } else {
        $sum = dualrep_get_post_sum_fallback($pid);
    }

    $sum_label = dualrep_format_sum($sum);

    if($viewer_uid > 0 && isset($GLOBALS['dualrep_voted_by_pid']) && is_array($GLOBALS['dualrep_voted_by_pid'])) {
        $has_voted = !empty($GLOBALS['dualrep_voted_by_pid'][$pid]);
    } else {
        $has_voted = dualrep_get_has_voted_fallback($pid, $viewer_uid);
    }

    $quick_title = $is_owner
        ? dualrep_t('dualrep_quick_title_owner', 'Рейтинг этого сообщения')
        : dualrep_t('dualrep_quick_title', 'Быстро: +1 к рейтингу сообщения');

    if($has_rep_button) {
        $quick = dualrep_add_class($post['button_rep'], 'rep-action rep-quick');
        $quick = dualrep_set_attr($quick, 'onclick', 'return DualRep.quick(this, '.$uid.', '.$pid.');');
        $quick = dualrep_set_attr($quick, 'title', $quick_title);
        $quick = dualrep_set_attr($quick, 'data-rep-uid', (string)$uid);
        $quick = dualrep_set_attr($quick, 'data-rep-pid', (string)$pid);
        $quick = dualrep_set_attr($quick, 'data-rep-sum', (string)$sum);
        $quick = dualrep_set_span_text($quick, $sum_label);

        if($has_voted) {
            $quick = dualrep_add_class($quick, 'rep-disabled rep-done');
            $quick = dualrep_set_attr($quick, 'data-rep-disabled', '1');
            $quick = dualrep_set_attr($quick, 'aria-disabled', 'true');
            $quick = dualrep_set_attr($quick, 'onclick', 'return false;');
        } else {
            $quick = dualrep_set_attr($quick, 'data-rep-disabled', '0');
        }

        $modal = dualrep_add_class($post['button_rep'], 'rep-action rep-modal');
        $modal = dualrep_set_attr($modal, 'onclick', 'MyBB.reputation('.$uid.', '.$pid.'); return false;');

        if($is_owner) {
            $quick = dualrep_add_class($quick, 'rep-owner rep-disabled');
            $quick = dualrep_set_attr($quick, 'data-rep-disabled', '1');
            $quick = dualrep_set_attr($quick, 'aria-disabled', 'true');
            $quick = dualrep_set_attr($quick, 'onclick', 'return false;');
        }
    } else {
        $quick = '<a href="javascript:void(0)" onclick="return false;" title="'.htmlspecialchars_uni($quick_title).'" class="postbit_reputation_add rep-action rep-quick rep-owner rep-disabled" aria-disabled="true" data-rep-disabled="1" data-rep-uid="'.$uid.'" data-rep-pid="'.$pid.'" data-rep-sum="'.$sum.'"><span>'.htmlspecialchars_uni($sum_label).'</span></a>';
        $modal = '';
    }

    if($modal !== '') {
        $modal = '<span class="rep-modal-wrap">'.$modal.'</span>';
    }

    if(isset($plugins) && is_object($plugins)) {
        $ctx = array(
            'post' => &$post,
            'quick' => &$quick,
            'modal' => &$modal,
            'pid' => $pid,
            'uid' => $uid,
            'viewer_uid' => $viewer_uid
        );
        $plugins->run_hooks('dualrep_postbit_buttons', $ctx);
    }

    eval("\$post['dualrep_buttons'] = \"".$templates->get('dualrep_postbit_buttons')."\";");

    $post['button_rep'] = '';

    return $post;
}

function dualrep_can_view_post($pid)
{
    global $db, $plugins;

    $pid = (int)$pid;
    if($pid <= 0) return array(false, 0, 0);

    $q = $db->write_query("
        SELECT p.pid, p.tid, p.visible AS pvisible, t.fid, t.visible AS tvisible
        FROM {$db->table_prefix}posts p
        LEFT JOIN {$db->table_prefix}threads t ON (t.tid=p.tid)
        WHERE p.pid='{$pid}'
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    if(!$row) return array(false, 0, 0);

    $fid = (int)$row['fid'];
    $tid = (int)$row['tid'];

    $can = true;

    if(function_exists('forum_permissions')) {
        $fp = forum_permissions($fid);
        if(empty($fp['canview']) || empty($fp['canviewthreads'])) {
            $can = false;
        }
    }

    if($can) {
        $tvisible = (int)$row['tvisible'];
        $pvisible = (int)$row['pvisible'];

        if(($tvisible != 1 || $pvisible != 1) && function_exists('is_moderator')) {
            if(!is_moderator($fid)) {
                $can = false;
            }
        } elseif($tvisible != 1 || $pvisible != 1) {
            $can = false;
        }
    }

    if(isset($plugins) && is_object($plugins)) {
        $ctx = array('can_view' => $can, 'pid' => $pid, 'fid' => $fid, 'tid' => $tid);
        $plugins->run_hooks('dualrep_voters_can_view', $ctx);
        $can = !empty($ctx['can_view']);
    }

    return array($can, $fid, $tid);
}

function dualrep_cache_prune(&$blob)
{
    $now = TIME_NOW;
    $ttl = (int)DUALREP_CACHE_TTL;
    $max = (int)DUALREP_CACHE_MAX_PIDS;

    if(!is_array($blob)) $blob = array();
    if(!isset($blob['items']) || !is_array($blob['items'])) $blob['items'] = array();

    $items = &$blob['items'];

    foreach($items as $pid => $entry) {
        if(!is_array($entry)) { unset($items[$pid]); continue; }

        foreach(array('p','a') as $k) {
            if(!isset($entry[$k]) || !is_array($entry[$k])) continue;
            $ts = isset($entry[$k]['ts']) ? (int)$entry[$k]['ts'] : 0;
            if($ts <= 0 || ($now - $ts) >= $ttl) {
                unset($entry[$k]);
            }
        }

        if(empty($entry['p']) && empty($entry['a'])) {
            unset($items[$pid]);
        } else {
            $items[$pid] = $entry;
        }
    }

    $cnt = count($items);
    if($max > 0 && $cnt > $max) {
        $scores = array();
        foreach($items as $pid => $entry) {
            $tp = (!empty($entry['p']['ts'])) ? (int)$entry['p']['ts'] : 0;
            $ta = (!empty($entry['a']['ts'])) ? (int)$entry['a']['ts'] : 0;
            $scores[(string)$pid] = max($tp, $ta);
        }
        arsort($scores);
        $keep = array_slice(array_keys($scores), 0, $max);
        $keep = array_flip($keep);
        foreach(array_keys($items) as $pid) {
            if(!isset($keep[(string)$pid])) unset($items[$pid]);
        }
    }
}

function dualrep_xmlhttp()
{
    global $mybb, $db, $cache, $plugins;

    $action = isset($mybb->input['action']) ? $mybb->input['action'] : '';
    if($action !== 'dualrep_voters') {
        return;
    }

    if(empty($mybb->settings['enablereputation'])) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok' => false, 'error' => 'reputation_disabled', 'total' => 0, 'sum' => 0, 'voters' => array()));
        exit;
    }

    if(method_exists($mybb, 'get_input') && defined('MyBB::INPUT_INT')) {
        $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
        $all = (int)$mybb->get_input('all', MyBB::INPUT_INT);
    } else {
        $pid = isset($mybb->input['pid']) ? (int)$mybb->input['pid'] : 0;
        $all = isset($mybb->input['all']) ? (int)$mybb->input['all'] : 0;
    }

    if($pid <= 0) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok' => false, 'error' => 'bad_pid', 'total' => 0, 'sum' => 0, 'voters' => array()));
        exit;
    }

    list($can_view, $fid, $tid) = dualrep_can_view_post($pid);
    if(!$can_view) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok' => false, 'error' => 'no_permission', 'total' => 0, 'sum' => 0, 'voters' => array()));
        exit;
    }

    $preview_limit = isset($mybb->settings['dualrep_preview_limit']) ? (int)$mybb->settings['dualrep_preview_limit'] : 5;
    if($preview_limit <= 0) $preview_limit = 5;

    $preview_cache_cap = max($preview_limit, 50);
    if($preview_cache_cap > 200) $preview_cache_cap = 200;

    if(isset($plugins) && is_object($plugins)) {
        $ctx = array(
            'pid' => $pid,
            'all' => $all,
            'preview_limit' => $preview_limit,
            'preview_cache_cap' => $preview_cache_cap
        );
        $plugins->run_hooks('dualrep_voters_limits', $ctx);
        $preview_limit = (int)$ctx['preview_limit'];
        $preview_cache_cap = (int)$ctx['preview_cache_cap'];
        if($preview_limit <= 0) $preview_limit = 5;
        if($preview_cache_cap < $preview_limit) $preview_cache_cap = $preview_limit;
        if($preview_cache_cap > 500) $preview_cache_cap = 500;
    }

    $now = TIME_NOW;

    if(isset($cache) && is_object($cache) && method_exists($cache, 'read') && method_exists($cache, 'update')) {
        $blob = $cache->read(DUALREP_CACHE_KEY);
        if(!is_array($blob)) $blob = array();
        if(!isset($blob['items']) || !is_array($blob['items'])) $blob['items'] = array();

        dualrep_cache_prune($blob);

        $items = &$blob['items'];
        $entry = isset($items[$pid]) && is_array($items[$pid]) ? $items[$pid] : array();

        if($all) {
            if(isset($entry['a']) && is_array($entry['a'])) {
                $ts = isset($entry['a']['ts']) ? (int)$entry['a']['ts'] : 0;
                if($ts > 0 && ($now - $ts) < DUALREP_CACHE_TTL && isset($entry['a']['payload']) && is_array($entry['a']['payload'])) {
                    $payload = $entry['a']['payload'];
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode($payload);
                    exit;
                }
            }
        } else {
            $hit = false;
            if(isset($entry['p']) && is_array($entry['p'])) {
                $ts = isset($entry['p']['ts']) ? (int)$entry['p']['ts'] : 0;
                $cap = isset($entry['p']['cap']) ? (int)$entry['p']['cap'] : 0;
                if($ts > 0 && ($now - $ts) < DUALREP_CACHE_TTL && isset($entry['p']['payload']) && is_array($entry['p']['payload'])) {
                    $payload = $entry['p']['payload'];
                    $voters = isset($payload['voters']) && is_array($payload['voters']) ? $payload['voters'] : array();
                    $total = isset($payload['total']) ? (int)$payload['total'] : 0;
                    if($cap >= $preview_limit || $total <= count($voters)) {
                        $hit = true;
                    }
                }
            }
            if(!$hit && isset($entry['a']) && is_array($entry['a'])) {
                $ts = isset($entry['a']['ts']) ? (int)$entry['a']['ts'] : 0;
                if($ts > 0 && ($now - $ts) < DUALREP_CACHE_TTL && isset($entry['a']['payload']) && is_array($entry['a']['payload'])) {
                    $payload = $entry['a']['payload'];
                    $hit = true;
                }
            }

            if($hit && isset($payload) && is_array($payload)) {
                if(isset($payload['voters']) && is_array($payload['voters'])) {
                    $payload['voters'] = array_slice($payload['voters'], 0, $preview_limit);
                }
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode($payload);
                exit;
            }
        }
    }

    $qt = $db->simple_select('reputation', 'COUNT(DISTINCT adduid) AS c, SUM(reputation) AS s', "pid='{$pid}' AND adduid<>0");
    $total = (int)$db->fetch_field($qt, 'c');
    $sum = $db->fetch_field($qt, 's');
    $sum = ($sum === null) ? 0 : (int)$sum;

    $limit_sql = '';
    $mode = $all ? 'a' : 'p';

    if($all) {
        $limit_sql = ' LIMIT 500';
    } else {
        $limit_sql = ' LIMIT '.(int)$preview_cache_cap;
    }

    $query = $db->write_query("
        SELECT
            r.adduid AS uid,
            MAX(r.dateline) AS lastvote,
            u.username,
            u.avatar,
            u.avatardimensions
        FROM {$db->table_prefix}reputation r
        LEFT JOIN {$db->table_prefix}users u ON (u.uid=r.adduid)
        WHERE r.pid='{$pid}' AND r.adduid<>0
        GROUP BY r.adduid
        ORDER BY lastvote DESC
        {$limit_sql}
    ");

    $voters = array();
    $default_avatar = isset($mybb->settings['default_avatar']) ? (string)$mybb->settings['default_avatar'] : '';

    while($row = $db->fetch_array($query)) {
        $vuid = (int)$row['uid'];
        if($vuid <= 0) continue;

        $username = isset($row['username']) ? (string)$row['username'] : '';
        $avatar = isset($row['avatar']) ? (string)$row['avatar'] : '';
        $dims = isset($row['avatardimensions']) ? (string)$row['avatardimensions'] : '';

        if($avatar === '' && $default_avatar !== '') {
            $avatar = $default_avatar;
            $dims = '';
        }

        $w = 0;
        $h = 0;
        if($dims && strpos($dims, '|') !== false) {
            list($dw, $dh) = explode('|', $dims, 2);
            $w = (int)$dw;
            $h = (int)$dh;
        }

        $voters[] = array(
            'uid' => $vuid,
            'username' => $username,
            'profile' => 'member.php?action=profile&uid='.$vuid,
            'avatar' => $avatar,
            'aw' => $w,
            'ah' => $h
        );
    }

    $payload = array(
        'ok' => true,
        'pid' => $pid,
        'total' => $total,
        'sum' => $sum,
        'voters' => $voters
    );

    if(isset($plugins) && is_object($plugins)) {
        $ctx = array('payload' => &$payload, 'pid' => $pid, 'fid' => $fid, 'tid' => $tid, 'all' => $all);
        $plugins->run_hooks('dualrep_voters_payload', $ctx);
    }

    if(!$all) {
        if(isset($payload['voters']) && is_array($payload['voters'])) {
            $payload['voters'] = array_slice($payload['voters'], 0, $preview_limit);
        }
    }

    if(isset($cache) && is_object($cache) && method_exists($cache, 'read') && method_exists($cache, 'update')) {
        $blob = $cache->read(DUALREP_CACHE_KEY);
        if(!is_array($blob)) $blob = array();
        if(!isset($blob['items']) || !is_array($blob['items'])) $blob['items'] = array();

        dualrep_cache_prune($blob);

        if(!isset($blob['items'][$pid]) || !is_array($blob['items'][$pid])) {
            $blob['items'][$pid] = array();
        }

        $store_payload = $payload;
        if(!$all && isset($store_payload['voters']) && is_array($store_payload['voters'])) {
            $store_payload['voters'] = $voters;
        }

        $blob['items'][$pid][$mode] = array(
            'ts' => $now,
            'payload' => $store_payload
        );
        if(!$all) {
            $blob['items'][$pid][$mode]['cap'] = (int)$preview_cache_cap;
        }

        dualrep_cache_prune($blob);

        $cache->update(DUALREP_CACHE_KEY, $blob);
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}
