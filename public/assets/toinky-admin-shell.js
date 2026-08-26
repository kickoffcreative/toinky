document.querySelectorAll('[data-sidebar-section]').forEach(section=>{
 const button=section.querySelector('.sidebar-section-toggle');
 if(!button)return;
 const key='toinky-sidebar-'+section.dataset.sidebarSection;
 const apply=collapsed=>{section.classList.toggle('collapsed',collapsed);button.setAttribute('aria-expanded',collapsed?'false':'true');const marker=button.querySelector('span');if(marker)marker.textContent=collapsed?'+':'−';};
 try{apply(localStorage.getItem(key)==='collapsed');}catch(error){apply(false);}
 button.addEventListener('click',()=>{const collapsed=!section.classList.contains('collapsed');apply(collapsed);try{localStorage.setItem(key,collapsed?'collapsed':'open');}catch(error){}});
});
