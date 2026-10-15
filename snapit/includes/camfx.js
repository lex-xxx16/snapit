window.CamFX={
 _n:null,
 noise:function(){if(this._n)return this._n;var c=document.createElement('canvas');c.width=c.height=200;var x=c.getContext('2d'),d=x.createImageData(200,200);
  for(var i=0;i<d.data.length;i+=4){var v=Math.random()*255;d.data[i]=d.data[i+1]=d.data[i+2]=v;d.data[i+3]=255;}
  x.putImageData(d,0,0);return(this._n=c);},
 // HTML overlay for the live camera / preview (fx: {grain,vignette,fade} 0-100)
 overlay:function(fx){var h='',a='position:absolute;inset:0;pointer-events:none;border-radius:inherit;';
  if(fx.fade)h+='<div style="'+a+'background:rgba(40,36,32,'+(fx.fade/100)+');mix-blend-mode:screen"></div>';
  if(fx.grain)h+='<div style="'+a+'background:url('+this.noise().toDataURL()+');opacity:'+(fx.grain/100*0.6)+';mix-blend-mode:overlay"></div>';
  if(fx.vignette)h+='<div style="'+a+'background:radial-gradient(ellipse at center,transparent 55%,rgba(0,0,0,'+(fx.vignette/100*0.9)+') 100%)"></div>';
  return h;},
 // bake the same effects into a captured canvas
 apply:function(ctx,w,h,fx){if(!fx)return;ctx.save();ctx.filter='none';
  if(fx.fade){ctx.globalCompositeOperation='screen';ctx.fillStyle='rgba(40,36,32,'+(fx.fade/100)+')';ctx.fillRect(0,0,w,h);}
  if(fx.grain){ctx.globalCompositeOperation='overlay';ctx.globalAlpha=fx.grain/100*0.6;ctx.fillStyle=ctx.createPattern(this.noise(),'repeat');ctx.fillRect(0,0,w,h);ctx.globalAlpha=1;}
  if(fx.vignette){ctx.globalCompositeOperation='source-over';var g=ctx.createRadialGradient(w/2,h/2,Math.min(w,h)*0.3,w/2,h/2,Math.hypot(w,h)/2);
   g.addColorStop(0.55,'rgba(0,0,0,0)');g.addColorStop(1,'rgba(0,0,0,'+(fx.vignette/100*0.9)+')');ctx.fillStyle=g;ctx.fillRect(0,0,w,h);}
  ctx.restore();}
};
