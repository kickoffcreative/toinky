(()=>{
    const root=document.querySelector('[data-inky][data-context="story-writer"]');
    if(!root)return;
    let dependencies=window.toinkyDraftDependencies&&typeof window.toinkyDraftDependencies==='object'?window.toinkyDraftDependencies:{};
    let draftEntries=Array.isArray(window.toinkyDraftEntries)?window.toinkyDraftEntries:[];
    const normalize=value=>String(value||'').replace(/\s+/g,' ').trim().toLowerCase();
    const setDraftData=(nextDependencies,nextEntries)=>{
        dependencies=nextDependencies&&typeof nextDependencies==='object'?nextDependencies:{};
        draftEntries=Array.isArray(nextEntries)?nextEntries:[];
        root.dataset.inkyDraftDependenciesVersion=String(Date.now());
        decorate();
    };
    const writerLink=(entry)=>{
        if(!entry?.writer_url)return null;
        const link=document.createElement('a');
        link.className='inky-guide-draft-writer-link';
        link.dataset.inkyWriterLink='';
        link.href=entry.writer_url;
        link.textContent=entry.entity==='decision'?'Edit Options and Effects':'Open in Story Writer';
        return link;
    };
    const renderChildren=(item,children)=>{
        item.querySelector('[data-inky-draft-children]')?.remove();
        if(!Array.isArray(children)||!children.length)return;
        const box=document.createElement('div');
        box.className='inky-guide-draft-children';
        box.dataset.inkyDraftChildren='';
        children.forEach((child,index)=>{
            const row=document.createElement('div');
            row.className='inky-guide-draft-child';
            const text=document.createElement('span');
            text.textContent=child.display||child.label||`Option ${index+1}`;
            row.append(text);
            (Array.isArray(child.effects)?child.effects:[]).forEach(effect=>{
                const effectNote=document.createElement('small');
                effectNote.textContent=effect;
                row.append(effectNote);
            });
            box.append(row);
        });
        item.append(box);
    };
    const renderWriterEntries=()=>{
        const list=root.querySelector('[data-inky-accepted]');
        if(!list)return;
        const manual=draftEntries.filter(entry=>entry&&entry.source==='writer');
        if(!manual.length){delete root.dataset.inkyWriterScanVersion;return;}
        const version=JSON.stringify(manual);
        if(root.dataset.inkyWriterScanVersion===version&&list.querySelector('[data-inky-writer-entry]'))return;
        list.querySelectorAll('[data-inky-writer-entry]').forEach(item=>item.remove());
        root.dataset.inkyWriterScanVersion=version;
        const groups=new Map([...list.querySelectorAll('.inky-guide-accepted-group')].map(group=>{
            const summary=group.querySelector(':scope > summary');
            const stage=(summary?.textContent||'').replace(/\s+·\s+\d+\s*$/,'').trim();
            return [stage,group.querySelector(':scope > .inky-guide-accepted-group-items')];
        }).filter(([,body])=>body));
        const manualByStage=new Map();
        manual.forEach(entry=>{
            const stage=entry.stage||'Story Writer';
            if(!manualByStage.has(stage))manualByStage.set(stage,[]);
            manualByStage.get(stage).push(entry);
        });
        manualByStage.forEach((entries,stage)=>{
            let body=groups.get(stage);
            if(!body){
                const disclosure=document.createElement('details');
                disclosure.className='inky-guide-accepted-group';
                const summary=document.createElement('summary');
                summary.textContent=stage+' · '+entries.length;
                body=document.createElement('div');
                body.className='inky-guide-accepted-group-items';
                disclosure.append(summary,body);
                list.append(disclosure);
                groups.set(stage,body);
            }
            entries.forEach(entry=>{
                const row=document.createElement('div');
                row.className='inky-guide-accepted-item inky-guide-writer-entry';
                row.dataset.inkyWriterEntry='';
                const label=document.createElement('span');
                label.textContent=entry.display||entry.match||'Story Writer entry';
                row.append(label);
                const source=document.createElement('small');
                source.className='inky-guide-writer-entry-source';
                source.textContent='Direct Entry';
                row.append(source);
                const link=writerLink(entry);
                if(link)row.append(link);
                renderChildren(row,entry.children);
                body.append(row);
            });
            const summary=body.parentElement?.querySelector(':scope > summary');
            if(summary){
                const count=body.querySelectorAll(':scope > .inky-guide-accepted-item').length;
                summary.textContent=stage+' · '+count;
            }
        });
    };
    const refresh=async plotId=>{
        if(!plotId)return;
        try{
            const body=new URLSearchParams({csrf_token:root.dataset.csrf,plot_id:String(plotId)});
            const response=await fetch('inky-draft-dependencies.php',{method:'POST',body,headers:{Accept:'application/json'},credentials:'same-origin'});
            const data=await response.json();
            if(response.ok&&data.ok)setDraftData(data.draft_dependencies,data.draft_entries);
        }catch(error){}
    };
    const decorate=()=>{
        const items=[...root.querySelectorAll('[data-inky-accepted] .inky-guide-accepted-item:not(.inky-guide-writer-entry)')];
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
            item.querySelector('[data-inky-writer-link]')?.remove();
            const link=writerLink(entry);
            if(link)item.append(link);
            renderChildren(item,entry.children);
        });
        renderWriterEntries();
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
