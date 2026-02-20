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
    var re = new RegExp('class="[^"]*\b' + cls + '\b[^"]*"[^>]*>([\s\S]*?)<\/', 'i');
    var m = html && html.match(re);
    if(!m) return '';
    return m[1].replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
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