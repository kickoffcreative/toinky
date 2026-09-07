(()=>{
    const root=document.querySelector('[data-inky][data-context="story-writer"]');
    if(!root)return;
    let dependencies=window.toinkyDraftDependencies&&typeof window.toinkyDraftDependencies==='object'?window.toinkyDraftDependencies:{};
    const normalize=value=>String(value||'').replace(/\s+/g,' ').trim().toLowerCase();
    const setDependencies=next=>{dependencies=next&&typeof next==='object'?next:{};root.dataset.inkyDraftDependenciesVersion=String(Date.now());decorate();};
    const refresh=async plotId=>{
        if(!plotId)return;
        try{
            const body=new URLSearchParams({csrf_token:root.dataset.csrf,plot_id:String(plotId)});
            const response=await fetch('inky-draft-dependencies.php',{method:'POST',body,headers:{Accept:'application/json'},credentials:'same-origin'});
            const data=await response.json();
            if(response.ok&&data.ok)setDependencies(data.draft_dependencies);
        }catch(error){}
    };
    const decorate=()=>{
        const items=[...root.querySelectorAll('[data-inky-accepted] .inky-guide-accepted-item')];
        items.forEach(item=>{
            const edit=item.querySelector('.inky-guide-history-edit'),label=item.querySelector(':scope > span');
            if(!edit||!label)return;
            const current=item.dataset.inkyDraftKey||normalize(label.textContent);
            const key=item.dataset.inkyDraftKey||Object.keys(dependencies).find(candidate=>normalize(dependencies[candidate]?.match)===current);
            const entry=key?dependencies[key]:null;
            if(!entry)return;
            const version=(root.dataset.inkyDraftDependenciesVersion||'')+key+JSON.stringify(entry);
            if(item.dataset.inkyDraftRender===version)return;
            item.dataset.inkyDraftKey=key;
            item.dataset.inkyDraftRender=version;
            label.textContent=entry.display||entry.match||label.textContent;
            let note=item.querySelector('.inky-guide-draft-dependency');
            if(!note){note=document.createElement('small');note.className='inky-guide-draft-dependency';item.insertBefore(note,edit);}
            const links=Array.isArray(entry.dependencies)?entry.dependencies:[];
            note.textContent=links.length?'Linked to '+links.join(' · '):'';
            note.hidden=!links.length;
            edit.disabled=Boolean(entry.blocked);
            edit.title=entry.blocked?'Deactivate or remove the linked records before editing this accepted entry.':'';
            item.classList.toggle('is-inky-draft-locked',Boolean(entry.blocked));
        });
    };
    const observer=new MutationObserver(decorate);
    observer.observe(root,{childList:true,subtree:true});
    decorate();
    const request=window.toinkyRequest;
    if(request)window.toinkyRequest=async(url,options={})=>{
        const response=await request(url,options);
        const body=options.body;
        if(String(url).includes('inky-starter.php')&&body?.get&&['save_proposal','save_progress'].includes(body.get('mode'))){
            const plotId=body.get('plot_id');
            window.setTimeout(()=>refresh(plotId),180);
        }
        return response;
    };
})();
