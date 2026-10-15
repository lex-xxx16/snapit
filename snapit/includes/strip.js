window.SnapStrip={html:function(photos,c){
 var w=520,ar='',m=/^(\d+)x(\d+)$/.exec(c.print||'');
 if(m){ar='aspect-ratio:'+m[1]+'/'+m[2]+';';w=(+m[1]<=2)?270:400;}
 var bgi=c.pattern==='dots'?'background-image:radial-gradient(rgba(128,128,128,.25) 1px,transparent 1.6px);background-size:14px 14px;':'';
 var h='<div class="snap-strip" style="max-width:'+w+'px;'+ar+bgi+'--bg:'+c.bg+';--bc:'+c.bc+';--bw:'+c.bw+'px;--gap:'+c.gap+'px;--rad:'+c.rad+'px;--fg:'+c.fg+';">'
  +'<div class="snap-grid" style="grid-template-columns:repeat('+c.cols+',1fr);grid-template-rows:repeat('+c.rows+',1fr);">';
 for(var i=0;i<c.count;i++){h+=photos[i]?'<img src="'+photos[i]+'" alt="Photo '+(i+1)+'" style="filter:'+(c.filter||'none')+'">':'<div class="snap-slot"><i class="fa-regular fa-image fa-2x"></i></div>';}
 return h+'</div><div class="snap-foot">'+(c.caption||'')+'</div></div>';}};
