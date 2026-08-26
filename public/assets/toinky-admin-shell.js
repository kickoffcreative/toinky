const workspaceMobile=window.matchMedia('(max-width: 800px)');
document.querySelectorAll('[data-sidebar-section]').forEach(section=>{
 const button=section.querySelector('.sidebar-section-toggle');
 if(!button)return;
 const key='toinky-sidebar-'+section.dataset.sidebarSection;
 const apply=collapsed=>{section.classList.toggle('collapsed',collapsed);button.setAttribute('aria-expanded',collapsed?'false':'true');const marker=button.querySelector('span');if(marker)marker.textContent=collapsed?'+':'−';};
 if(workspaceMobile.matches)apply(true);else try{apply(localStorage.getItem(key)==='collapsed');}catch(error){apply(false);}
 button.addEventListener('click',()=>{const collapsed=!section.classList.contains('collapsed');apply(collapsed);try{localStorage.setItem(key,collapsed?'collapsed':'open');}catch(error){}});
});
const workspaceSidebar=document.getElementById('workspace-sidebar'),workspaceToggle=document.querySelector('.workspace-menu-toggle'),workspaceClose=document.querySelector('.workspace-menu-close'),workspaceBackdrop=document.querySelector('.workspace-sidebar-backdrop');
if(workspaceSidebar&&workspaceToggle){const setDrawer=open=>{document.body.classList.toggle('workspace-menu-open',open);workspaceToggle.setAttribute('aria-expanded',open?'true':'false')};workspaceToggle.addEventListener('click',()=>setDrawer(!document.body.classList.contains('workspace-menu-open')));workspaceClose?.addEventListener('click',()=>setDrawer(false));workspaceBackdrop?.addEventListener('click',()=>setDrawer(false));workspaceSidebar.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>setDrawer(false)));document.addEventListener('keydown',event=>{if(event.key==='Escape')setDrawer(false)});workspaceMobile.addEventListener('change',event=>{if(!event.matches)setDrawer(false)})}
