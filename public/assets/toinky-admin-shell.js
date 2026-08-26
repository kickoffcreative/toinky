const workspaceMobile=window.matchMedia('(max-width: 800px)');
document.querySelectorAll('[data-sidebar-section]').forEach(section=>{
 const button=section.querySelector('.sidebar-section-toggle');
 if(!button)return;
 const key='toinky-sidebar-'+section.dataset.sidebarSection;
 const apply=collapsed=>{section.classList.toggle('collapsed',collapsed);button.setAttribute('aria-expanded',collapsed?'false':'true');const marker=button.querySelector('span');if(marker)marker.textContent=collapsed?'+':'−';};
 if(workspaceMobile.matches)apply(true);else try{apply(localStorage.getItem(key)==='collapsed');}catch(error){apply(false);}
 button.addEventListener('click',()=>{const collapsed=!section.classList.contains('collapsed');apply(collapsed);try{localStorage.setItem(key,collapsed?'collapsed':'open');}catch(error){}});
});
const workspaceSidebar=document.getElementById('workspace-sidebar'),workspaceToggle=document.querySelector('.workspace-menu-toggle'),workspaceEdge=document.querySelector('.workspace-menu-edge'),workspaceClose=document.querySelector('.workspace-menu-close'),workspaceBackdrop=document.querySelector('.workspace-sidebar-backdrop');
if(workspaceSidebar&&workspaceToggle){
 const setDrawer=(open,persist=true)=>{document.body.classList.toggle('workspace-menu-open',open);workspaceToggle.setAttribute('aria-expanded',open?'true':'false');if(persist&&!workspaceMobile.matches)try{localStorage.setItem('toinky-workspace-drawer',open?'open':'closed');}catch(error){}};
 const restore=()=>{if(workspaceMobile.matches)setDrawer(false,false);else{let open=true;try{open=localStorage.getItem('toinky-workspace-drawer')!=='closed';}catch(error){}setDrawer(open,false);}};
 workspaceToggle.addEventListener('click',()=>setDrawer(!document.body.classList.contains('workspace-menu-open')));
 workspaceEdge?.addEventListener('click',()=>setDrawer(true));workspaceClose?.addEventListener('click',()=>setDrawer(false));workspaceBackdrop?.addEventListener('click',()=>setDrawer(false));
 workspaceSidebar.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{if(workspaceMobile.matches)setDrawer(false,false);}));
 document.addEventListener('keydown',event=>{if(event.key==='Escape')setDrawer(false)});workspaceMobile.addEventListener('change',restore);restore();
}
