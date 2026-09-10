(()=> {
    'use strict';

    const app=window.toinkyPageBuilder;
    if(!app)return;

    const q=selector=>document.querySelector(selector);
    const toggle=q('#sidebar-toggle');
    const shell=document.querySelector('.page-builder-shell');
    const collapsedKey='toinky-page-builder-sidebar-collapsed';

    const setSidebar=collapsed=>{
        if(!shell||!toggle)return;
        shell.classList.toggle('sidebar-collapsed',collapsed);
        toggle.setAttribute('aria-expanded',collapsed?'false':'true');
        const icon=toggle.querySelector('[aria-hidden="true"]');
        if(icon)icon.textContent=collapsed?'>':'<';
        const label=collapsed?'Show properties sidebar':'Hide properties sidebar';
        toggle.setAttribute('aria-label',label);
        toggle.title=label;
        try{localStorage.setItem(collapsedKey,collapsed?'1':'0')}catch(error){}
    };

    if(toggle&&shell){
        let collapsed=false;
        try{collapsed=localStorage.getItem(collapsedKey)==='1'}catch(error){}
        setSidebar(collapsed);
        toggle.addEventListener('click',()=>setSidebar(!shell.classList.contains('sidebar-collapsed')));
    }

    const sectionNav=q('#page-builder-sections');
    const orderInput=q('#section-order');
    const orderSave=q('#section-order-save');
    let draggedSection=null;

    const syncSectionOrder=()=>{
        if(!sectionNav||!orderInput)return;
        const ids=[...sectionNav.querySelectorAll('[data-section-id]')].map(item=>item.dataset.sectionId);
        orderInput.value=JSON.stringify(ids);
        if(orderSave)orderSave.disabled=false;
    };

    if(sectionNav){
        sectionNav.querySelectorAll('[draggable="true"]').forEach(item=>{
            item.addEventListener('dragstart',()=>{
                draggedSection=item;
                item.classList.add('is-dragging');
            });
            item.addEventListener('dragend',()=>{
                item.classList.remove('is-dragging');
                sectionNav.querySelectorAll('.drop-target').forEach(x=>x.classList.remove('drop-target'));
                draggedSection=null;
            });
            item.addEventListener('dragover',event=>{
                if(!draggedSection||item===draggedSection||item.dataset.fixed==='1')return;
                event.preventDefault();
                item.classList.add('drop-target');
            });
            item.addEventListener('dragleave',()=>item.classList.remove('drop-target'));
            item.addEventListener('drop',event=>{
                if(!draggedSection||item===draggedSection||item.dataset.fixed==='1')return;
                event.preventDefault();
                item.classList.remove('drop-target');
                const rect=item.getBoundingClientRect();
                if(event.clientY<rect.top+rect.height/2)sectionNav.insertBefore(draggedSection,item);
                else sectionNav.insertBefore(draggedSection,item.nextSibling);
                syncSectionOrder();
            });
        });
        syncSectionOrder();
    }

    let previewTimer=null;
    let renderDraftPreview=()=>q('#page-preview').contentWindow.location.reload();

    q('#page-select').addEventListener('change',event=>{
        location.href='page-builder.php?page='+encodeURIComponent(event.target.value);
    });
    q('#preview-refresh').addEventListener('click',()=>renderDraftPreview(true));
    q('#open-preview').addEventListener('click',()=>window.open(q('#page-preview').src,'_blank','noopener'));

    if(app.hero)return;

    let rows=Array.isArray(app.rows)?app.rows:[];
    const media=Array.isArray(app.media)?app.media:[];
    if(!rows.length||rows[0].layout_type!=='CONTAINER'){
        rows=[{layout_type:'CONTAINER',active:true,children:rows}];
    }

    let selected=[0];
    let colorState=null;

    const layouts=[
        ['CONTAINER','Container'],
        ['TEXT_TOP','Top Text Only'],
        ['TEXT_CENTER','Text Center'],
        ['CARDS_ONLY','Cards Only'],
        ['STORY_CARDS','Story Cards'],
        ['TEXT_BULLETS','Text With Bulleted List'],
        ['IMAGE_RIGHT','Image On Right'],
        ['IMAGE_LEFT','Image On Left'],
        ['TWO_COLUMN','Two Columns'],
        ['THREE_COLUMN','Three Columns'],
        ['FOUR_COLUMN','Four Columns'],
        ['SOCIALS','Social Buttons']
    ];
    const backgroundFitOptions=[['FILL','Fill'],['FIT','Fit'],['TILE','Tile']];
    const contentWidthOptions=[['FULL','Full Screen Width'],['MAX','Max Width']];
    const spacingModeOptions=[['ALL','Same all sides'],['SIDES','Different each side']];
    const directionOptions=[['COLUMN','Top to Bottom'],['ROW','Left to Right']];
    const alignItemsOptions=[['stretch','Justify'],['flex-start','Align Left'],['center','Align Center'],['flex-end','Align Right']];
    const wrapOptions=[['1','Wrap on'],['0','Wrap off']];
    const structureKey='toinky-page-builder-structure-popup';
    const iconOptions=[
        ['','No icon'],['✦','Spark'],['◆','Diamond'],['◇','Open Diamond'],
        ['◐','Half Moon'],['○','Circle'],['●','Filled Circle'],['△','Triangle'],
        ['▽','Down Triangle'],['☉','Sun'],['☾','Moon'],['♙','Pawn'],
        ['♜','Tower'],['⚑','Flag'],['⚙','Gear'],['⚖','Balance'],
        ['✉','Message'],['⌁','Wave'],['∞','Infinity'],['?','Question'],
        ['!','Alert']
    ];

    const nodeAt=path=>{
        let node=rows[path[0]];
        for(let i=1;i<path.length;i++)node=(node.children||[])[path[i]];
        return node;
    };
    const ensureChildren=node=>{
        node.children=Array.isArray(node.children)?node.children:[];
        return node;
    };
    const escapeHtml=value=>String(value??'').replace(/[&<>"]/g,character=>({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'
    }[character]));
    const pathsEqual=(first,second)=>first.join('.')===second.join('.');
    const layoutTitle=code=>(layouts.find(item=>item[0]===code)||['','Text row'])[1];
    const nodeTitle=node=>node.layout_type==='CONTAINER'?'Container':
        (node.title_text||node.label_text||node.eyebrow_text||layoutTitle(node.layout_type));
    const mediaOptions=[['','No image'],...media.map(item=>[
        String(item.media_id),item.title_text||('Media '+item.media_id)
    ])];

    const clamp=(value,min,max)=>Math.min(max,Math.max(min,value));
    const parseColor=value=>{
        const raw=String(value??'').trim();
        let match=raw.match(/^#([0-9a-f]{3,8})$/i);
        if(match){
            let hex=match[1];
            if(hex.length===3)hex=hex.split('').map(part=>part+part).join('');
            if(hex.length===4)hex=hex.split('').map(part=>part+part).join('');
            if(hex.length!==6&&hex.length!==8)return null;
            return {
                r:parseInt(hex.slice(0,2),16),
                g:parseInt(hex.slice(2,4),16),
                b:parseInt(hex.slice(4,6),16),
                a:hex.length===8?parseInt(hex.slice(6,8),16)/255:1
            };
        }
        match=raw.match(/^rgba?\(\s*([^)]*)\)$/i);
        if(match){
            const parts=match[1].split(',').map(part=>part.trim());
            if(parts.length<3)return null;
            const channels=parts.slice(0,3).map(part=>part.endsWith('%')?
                clamp(parseFloat(part)*2.55,0,255):clamp(parseFloat(part),0,255));
            const alpha=parts[3]===undefined?1:clamp(parseFloat(parts[3]),0,1);
            if(channels.some(Number.isNaN)||Number.isNaN(alpha))return null;
            return {r:channels[0],g:channels[1],b:channels[2],a:alpha};
        }
        match=raw.match(/^hsla?\(\s*([^)]*)\)$/i);
        if(match){
            const parts=match[1].split(',').map(part=>part.trim());
            if(parts.length<3)return null;
            const h=((parseFloat(parts[0])%360)+360)%360;
            const s=clamp(parseFloat(parts[1]),0,100)/100;
            const l=clamp(parseFloat(parts[2]),0,100)/100;
            const a=parts[3]===undefined?1:clamp(parseFloat(parts[3]),0,1);
            if([h,s,l,a].some(Number.isNaN))return null;
            const chroma=(1-Math.abs(2*l-1))*s;
            const x=chroma*(1-Math.abs((h/60)%2-1));
            const m=l-chroma/2;
            let r=0,g=0,b=0;
            if(h<60){r=chroma;g=x}else if(h<120){r=x;g=chroma}
            else if(h<180){g=chroma;b=x}else if(h<240){g=x;b=chroma}
            else if(h<300){r=x;b=chroma}else{r=chroma;b=x}
            return {r:(r+m)*255,g:(g+m)*255,b:(b+m)*255,a};
        }
        return null;
    };
    const toHex=color=>{
        const channel=value=>Math.round(clamp(value,0,255)).toString(16).padStart(2,'0');
        return '#'+channel(color.r)+channel(color.g)+channel(color.b);
    };
    const rgbToHsl=color=>{
        const r=color.r/255,g=color.g/255,b=color.b/255;
        const max=Math.max(r,g,b),min=Math.min(r,g,b),delta=max-min;
        let h=0;
        const l=(max+min)/2;
        const s=delta===0?0:delta/(1-Math.abs(2*l-1));
        if(delta){
            if(max===r)h=60*(((g-b)/delta)%6);
            else if(max===g)h=60*((b-r)/delta+2);
            else h=60*((r-g)/delta+4);
        }
        return {h:(h+360)%360,s:s*100,l:l*100,a:color.a};
    };
    const number=value=>String(Math.round(value*100)/100);

    let previewScroll={x:0,y:0};
    const readPreviewScroll=()=>{
        const frame=q('#page-preview');
        try{
            if(frame&&frame.contentWindow)previewScroll={
                x:frame.contentWindow.scrollX||0,
                y:frame.contentWindow.scrollY||0
            };
        }catch(error){}
    };
    q('#page-preview')?.addEventListener('load',()=>{
        const frame=q('#page-preview');
        try{
            if(frame&&frame.contentWindow)frame.contentWindow.scrollTo(previewScroll.x,previewScroll.y);
        }catch(error){}
    });

    renderDraftPreview=(immediate=false)=>{
        const form=q('#preview-form');
        const payload=q('#preview-rows-payload');
        if(!form||!payload)return;
        const submit=()=>{
            readPreviewScroll();
            payload.value=JSON.stringify(rows);
            if(form.requestSubmit)form.requestSubmit();
            else form.submit();
        };
        if(previewTimer)clearTimeout(previewTimer);
        if(immediate)submit();
        else previewTimer=setTimeout(submit,350);
    };
    const sync=()=>{q('#rows-payload').value=JSON.stringify(rows);renderDraftPreview()};

    const field=(name,title,value,type='text')=>{
        if(type==='color'){
            const color=parseColor(value)||{r:244,g:238,b:226,a:1};
            return '<label class="builder-field builder-color-field">'+
                '<span>'+escapeHtml(title)+'</span>'+
                '<div class="builder-color-control">'+
                '<button type="button" class="builder-color-swatch" data-color-open="'+
                escapeHtml(name)+'" style="background-color:'+toHex(color)+'" aria-label="Open color picker"></button>'+
                '<span class="builder-color-value" data-color-value="'+escapeHtml(name)+'">'+
                escapeHtml(toHex(color))+'</span></div></label>';
        }
        return '<label class="builder-field">'+escapeHtml(title)+
            '<input type="'+type+'" data-field="'+escapeHtml(name)+'" value="'+
            escapeHtml(value??'')+'"></label>';
    };
    const colorPart=(name,title,value)=>{
        return '<label class="builder-color-edit">'+escapeHtml(title)+
            '<input type="number" data-color-part="'+escapeHtml(name)+'" value="'+
            escapeHtml(value)+'"></label>';
    };
    const select=(name,title,value,options)=>{
        return '<label class="builder-field">'+escapeHtml(title)+
            '<select data-field="'+escapeHtml(name)+'">'+options.map(option=>
            '<option value="'+escapeHtml(option[0])+'" '+
            (String(option[0])===String(value)?'selected':'')+'>'+
            escapeHtml(option[1])+'</option>').join('')+'</select></label>';
    };
    const area=(name,title,value)=>{
        return '<label class="builder-field">'+escapeHtml(title)+
            '<textarea data-field="'+escapeHtml(name)+'">'+
            escapeHtml(value??'')+'</textarea></label>';
    };
    const subheading=title=>'<div class="builder-subheading">'+escapeHtml(title)+'</div>';
    const backgroundControls=(title,prefix,node)=>{
        const key=name=>prefix?prefix+'_'+name:name;
        return subheading(title)+
            field(key('background_color'),'Background color',node[key('background_color')]||'','color')+
            select(key('background_media_id'),'Background image',node[key('background_media_id')]||'',mediaOptions)+
            select(key('background_fit'),'Image fill',node[key('background_fit')]||'FILL',backgroundFitOptions);
    };
    const paddingControls=(title,prefix,node)=>{
        const base=prefix?prefix+'_padding':'padding';
        const modeKey=base+'_mode';
        const allKey=base+'_all_px';
        const key=edge=>base+'_'+edge+'_px';
        const mode=node[modeKey]||'ALL';
        return subheading(title)+
            select(modeKey,'Mode',mode,spacingModeOptions)+
            (mode==='ALL'?field(allKey,'All sides',node[allKey]||0,'number'):
                field(key('top'),'Top',node[key('top')]||0,'number')+
                field(key('right'),'Right',node[key('right')]||0,'number')+
                field(key('bottom'),'Bottom',node[key('bottom')]||0,'number')+
                field(key('left'),'Left',node[key('left')]||0,'number'));
    };
    const marginControls=(title,node)=>{
        const mode=node.margin_mode||'ALL';
        return subheading(title)+
            select('margin_mode','Mode',mode,spacingModeOptions)+
            (mode==='ALL'?field('margin_all_px','All sides',node.margin_all_px||0,'number'):
                field('margin_top_px','Top',node.margin_top_px||0,'number')+
                field('margin_right_px','Right',node.margin_right_px||0,'number')+
                field('margin_bottom_px','Bottom',node.margin_bottom_px||0,'number')+
                field('margin_left_px','Left',node.margin_left_px||0,'number'));
    };

    const layoutControl=node=>{
        return '<div class="builder-layout-control"><span>Row layout</span>'+
            '<button type="button" data-layout-open>'+
            '<span>Change: '+escapeHtml(layoutTitle(node.layout_type||'TEXT_TOP'))+
            '</span><span aria-hidden="true">+</span></button></div>';
    };

    const renderStructureNode=(node,path,depth=0)=>{
        const deletable=path.length>1;
        const deleteButton=deletable?
            '<button type="button" class="builder-node-delete" data-delete="'+
            path.join('.')+'" aria-label="Delete '+escapeHtml(layoutTitle(node.layout_type))+
            '" title="Delete">x</button>':'';
        const container=node.layout_type==='CONTAINER';
        const children=container?(node.children||[]):[];
        const layoutButton=!container&&pathsEqual(path,selected)?
            '<button type="button" class="builder-structure-layout-button" data-layout-open aria-label="Change layout" title="Change layout">#</button>':'';
        return '<div class="builder-structure-row '+(pathsEqual(path,selected)?'selected':'')+
            '" style="--depth:'+depth+'"><button type="button" data-select="'+
            path.join('.')+'"><span class="builder-structure-caret" aria-hidden="true">'+
            (container&&children.length?'>':'')+'</span><span class="builder-structure-icon" aria-hidden="true">'+
            (container?'[]':'T')+'</span><span class="builder-structure-name">'+
            escapeHtml(nodeTitle(node))+'</span><small>'+escapeHtml(layoutTitle(node.layout_type))+
            '</small></button>'+layoutButton+deleteButton+'</div>'+
            (container?children.map((child,index)=>
            renderStructureNode(child,path.concat(index),depth+1)).join('')+
            '<div class="builder-structure-actions" style="--depth:'+(depth+1)+'">'+
            '<button type="button" data-add="'+path.join('.')+'" aria-label="Add content row" title="Add content row">+T</button>'+
            '<button type="button" class="alt" data-add-container="'+path.join('.')+
            '" aria-label="Add Container" title="Add Container">+[]</button></div>':'');
    };

    const renderColumns=node=>{
        if(!['CARDS_ONLY','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN'].includes(node.layout_type))return '';
        const count=node.layout_type==='TWO_COLUMN'?2:(node.layout_type==='FOUR_COLUMN'?4:3);
        node.columns=Array.isArray(node.columns)?node.columns:[];
        return '<div class="builder-subheading">Columns</div>'+
            Array.from({length:count},(_,index)=>{
                const column=node.columns[index]||{};
                return '<div class="builder-column">'+
                    select('columns.'+index+'.icon_text','Icon',column.icon_text||'',iconOptions)+
                    field('columns.'+index+'.title','Column title',column.title||'')+
                    area('columns.'+index+'.content','Column content',column.content||'')+
                    '</div>';
            }).join('');
    };

    const selectedSummary=node=>{
        return '<div class="builder-selected-summary"><span>Selected</span><strong>'+
            escapeHtml(nodeTitle(node))+'</strong><small>'+
            escapeHtml(layoutTitle(node.layout_type))+
            (node.active===false?' · Hidden':'')+'</small></div>';
    };

    const props=()=>{
        const node=nodeAt(selected);
        if(!node)return;
        const container=node.layout_type==='CONTAINER';
        q('#content-properties').innerHTML=
            selectedSummary(node)+
            (container?'<div class="builder-actions"><button type="button" data-add="'+selected.join('.')+
            '" aria-label="Add content row" title="Add content row">+T</button><button type="button" class="alt" data-add-container="'+
            selected.join('.')+'" aria-label="Add Container" title="Add Container">+[]</button></div>'+
            '<p class="builder-muted">Container is a shell. Add content rows or nested Containers.</p>'+
            '<p class="builder-muted">Use Structure to select a child row and edit its content.</p>'+
            subheading('Container')+
            select('content_width','Content width',node.content_width||'FULL',contentWidthOptions)+
            ((node.content_width||'FULL')==='MAX'?field('max_width_px','Width',node.max_width_px||1980,'number'):'')+
            field('min_height_px','Min-height',node.min_height_px||0,'number')+
            subheading('Items')+
            select('items_direction','Direction',node.items_direction||'COLUMN',directionOptions)+
            select('align_items','Align items',node.align_items||'stretch',alignItemsOptions)+
            select('wrap_items','Wrap',(node.wrap_items===false||node.wrap_items==='0')?'0':'1',wrapOptions):
            layoutControl(node)+
            field('label_text','Label',node.label_text||node.eyebrow_text||'')+
            field('title_text','Title',node.title_text||'')+
            area('content_html','Content',node.content_html||'')+
            (node.layout_type==='TEXT_BULLETS'?
                area('bullets_text','Bullets, one per line',(node.bullets||[]).join('\n')):'')+
            renderColumns(node));
        q('#style-properties').innerHTML=container?
            backgroundControls('Container background','',node)+
            marginControls('Container margin',node)+
            paddingControls('Container padding','',node):
            (['IMAGE_LEFT','IMAGE_RIGHT'].includes(node.layout_type)?
                select('image_media_id','Content image',node.image_media_id||'',mediaOptions):'')+
            backgroundControls('Main box background','',node)+
            marginControls('Main box margin',node)+
            paddingControls('Main box padding','',node)+
            (['CARDS_ONLY','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN'].includes(node.layout_type)?
                backgroundControls('Column background','column',node)+
                paddingControls('Column padding','column',node):'');
        q('#advanced-properties').innerHTML=container?
            select('active','Visibility',node.active===false?'0':'1',[
                ['1','Visible'],['0','Hidden']
            ])+
            select('blend_mode','Blend mode',node.blend_mode||'normal',[
                ['normal','Normal'],['multiply','Multiply'],['screen','Screen'],
                ['overlay','Overlay'],['darken','Darken'],['lighten','Lighten'],
                ['difference','Difference']
            ]):
            select('active','Visibility',node.active===false?'0':'1',[
                ['1','Visible'],['0','Hidden']
            ])+
            field('text_color','Text color',node.text_color||'#1C242B','color')+
            select('text_align','Alignment',node.text_align||'left',[
                ['left','Left'],['center','Center'],['right','Right']
            ])+
            select('blend_mode','Blend mode',node.blend_mode||'normal',[
                ['normal','Normal'],['multiply','Multiply'],['screen','Screen'],
                ['overlay','Overlay'],['darken','Darken'],['lighten','Lighten'],
                ['difference','Difference']
            ]);
        sync();
        refreshStructurePopup();
    };

    const saveStructurePopupState=open=>{
        const popup=q('#builder-structure-popup');
        try{
            const rect=popup?popup.getBoundingClientRect():{left:130,top:70};
            localStorage.setItem(structureKey,JSON.stringify({open,left:rect.left,top:rect.top}));
        }catch(error){}
    };

    const readStructurePopupState=()=>{
        try{
            const saved=JSON.parse(localStorage.getItem(structureKey)||'{}');
            return saved&&typeof saved==='object'?saved:{};
        }catch(error){return{};}
    };

    const closeFloating=(includeStructure=false)=>{
        document.querySelectorAll('.builder-floating-popup').forEach(popup=>{
            if(!includeStructure&&popup.id==='builder-structure-popup')return;
            if(popup.id==='builder-structure-popup')saveStructurePopupState(false);
            popup.remove();
        });
        colorState=null;
    };

    const makeDraggable=(popup,handle)=>{
        if(!popup||!handle)return;
        let startX=0,startY=0,startLeft=0,startTop=0,dragging=false;
        const move=event=>{
            if(!dragging)return;
            const left=clamp(startLeft+event.clientX-startX,8,window.innerWidth-popup.offsetWidth-8);
            const top=clamp(startTop+event.clientY-startY,8,window.innerHeight-popup.offsetHeight-8);
            popup.style.left=left+'px';
            popup.style.top=top+'px';
        };
        const stop=()=>{
            dragging=false;
            if(popup.id==='builder-structure-popup')saveStructurePopupState(true);
            document.removeEventListener('pointermove',move);
            document.removeEventListener('pointerup',stop);
        };
        handle.addEventListener('pointerdown',event=>{
            if(event.target.closest('button'))return;
            const rect=popup.getBoundingClientRect();
            startX=event.clientX;
            startY=event.clientY;
            startLeft=rect.left;
            startTop=rect.top;
            dragging=true;
            document.addEventListener('pointermove',move);
            document.addEventListener('pointerup',stop);
        });
    };

    const positionPopup=(popup,anchor)=>{
        const rect=anchor.getBoundingClientRect();
        const width=popup.offsetWidth;
        const height=popup.offsetHeight;
        popup.style.left=clamp(rect.left,8,window.innerWidth-width-8)+'px';
        popup.style.top=clamp(rect.bottom+8,8,window.innerHeight-height-8)+'px';
    };

    const refreshStructurePopup=()=>{
        const popup=q('#builder-structure-popup');
        if(!popup)return;
        const body=popup.querySelector('[data-structure-body]');
        if(!body)return;
        body.innerHTML='<div class="builder-structure-list">'+
            renderStructureNode(rows[0],[0])+'</div>';
    };

    const openStructurePopup=(anchor,position=null)=>{
        const existing=q('#builder-structure-popup');
        if(existing){
            refreshStructurePopup();
            return;
        }
        closeFloating(false);
        const popup=document.createElement('div');
        popup.id='builder-structure-popup';
        popup.className='builder-floating-popup builder-structure-popup';
        popup.setAttribute('role','dialog');
        popup.setAttribute('aria-label','Page structure');
        popup.innerHTML='<div class="builder-popup-head" data-popup-drag>'+
            '<strong>Structure</strong><button type="button" data-popup-close aria-label="Close">x</button></div>'+
            '<div data-structure-body></div>';
        document.body.appendChild(popup);
        refreshStructurePopup();
        if(position){
            popup.style.left=clamp(Number(position.left)||130,8,window.innerWidth-popup.offsetWidth-8)+'px';
            popup.style.top=clamp(Number(position.top)||70,8,window.innerHeight-popup.offsetHeight-8)+'px';
        }else positionPopup(popup,anchor);
        makeDraggable(popup,popup.querySelector('[data-popup-drag]'));
        saveStructurePopupState(true);
    };

    const openLayoutPicker=anchor=>{
        closeFloating(false);
        const popup=document.createElement('div');
        popup.id='builder-layout-popup';
        popup.className='builder-floating-popup builder-layout-popup';
        popup.setAttribute('role','dialog');
        popup.setAttribute('aria-label','Choose row layout');
        popup.innerHTML='<div class="builder-popup-head" data-popup-drag>'+
            '<strong>Choose row layout</strong><button type="button" data-popup-close aria-label="Close">x</button></div>'+
            '<div class="builder-layout-options">'+layouts.map(item=>
            '<button type="button" data-layout-choice="'+item[0]+'">'+
            '<span class="builder-layout-icon" aria-hidden="true">'+(item[0]==='CONTAINER'?'[]':'#')+
            '</span><span>'+escapeHtml(item[1])+'</span></button>').join('')+'</div>';
        document.body.appendChild(popup);
        positionPopup(popup,anchor);
        makeDraggable(popup,popup.querySelector('[data-popup-drag]'));
    };

    const updateColorSummary=()=>{
        if(!colorState)return;
        const hex=toHex(colorState.color);
        document.querySelectorAll('[data-color-value]').forEach(item=>{
            if(item.dataset.colorValue===colorState.field)item.textContent=hex;
        });
        document.querySelectorAll('[data-color-open]').forEach(item=>{
            if(item.dataset.colorOpen===colorState.field)item.style.backgroundColor=hex;
        });
    };

    const colorPopupFields=()=>{
        if(!colorState)return '';
        const color=colorState.color;
        if(colorState.format==='rgb'){
            return '<div class="builder-color-fields">'+
                colorPart('r','R',Math.round(color.r),'number')+
                colorPart('g','G',Math.round(color.g),'number')+
                colorPart('b','B',Math.round(color.b),'number')+
                colorPart('a','A',number(color.a),'number')+'</div>';
        }
        if(colorState.format==='hsla'){
            const hsl=rgbToHsl(color);
            return '<div class="builder-color-fields">'+
                colorPart('h','H',number(hsl.h))+
                colorPart('s','S %',number(hsl.s))+
                colorPart('l','L %',number(hsl.l))+
                colorPart('a','A',number(hsl.a))+'</div>';
        }
        return '<label class="builder-color-edit">HEX<input type="text" data-color-part="hex" value="'+
            escapeHtml(toHex(color))+'"></label>';
    };

    const renderColorPopup=()=>{
        const popup=q('#builder-color-popup');
        if(!popup||!colorState)return;
        const formats=[['hex','HEXA'],['rgb','RGB'],['hsla','HSLA']];
        popup.querySelector('[data-color-body]').innerHTML=
            '<div class="builder-color-format-tabs">'+formats.map(item=>
            '<button type="button" data-color-format="'+item[0]+'" class="'+
            (colorState.format===item[0]?'active':'')+'">'+item[1]+'</button>').join('')+
            '</div><div class="builder-color-native-row">'+
            '<input type="color" data-color-native value="'+toHex(colorState.color)+'">'+
            '<label>Opacity<input type="range" min="0" max="1" step="0.01" data-color-alpha value="'+
            colorState.color.a+'"></label></div>'+colorPopupFields()+
            '<p class="builder-color-status" data-color-status>Changes apply immediately.</p>';
        popup.querySelectorAll('[data-color-part]').forEach(input=>{
            input.classList.add('builder-color-part');
        });
    };

    const openColorPicker=(fieldName,anchor)=>{
        closeFloating(false);
        const parsed=parseColor(nodeAt(selected)[fieldName])||{r:244,g:238,b:226,a:1};
        colorState={field:fieldName,color:parsed,format:'hex'};
        const popup=document.createElement('div');
        popup.id='builder-color-popup';
        popup.className='builder-floating-popup builder-color-popup';
        popup.setAttribute('role','dialog');
        popup.setAttribute('aria-label','Color picker');
        popup.innerHTML='<div class="builder-popup-head" data-popup-drag>'+
            '<strong>Color Picker</strong><button type="button" data-popup-close aria-label="Close">x</button></div>'+
            '<div data-color-body></div><div class="builder-color-tools">'+
            '<button type="button" data-color-eyedropper>Eye dropper</button></div>';
        document.body.appendChild(popup);
        positionPopup(popup,anchor);
        makeDraggable(popup,popup.querySelector('[data-popup-drag]'));
        renderColorPopup();
    };

    const setValue=(node,path,value)=>{
        const bits=path.split('.');
        if(bits.length===1)node[bits[0]]=value;
        else{
            node[bits[0]]=Array.isArray(node[bits[0]])?node[bits[0]]:[];
            node[bits[0]][Number(bits[1])]=node[bits[0]][Number(bits[1])]||{};
            node[bits[0]][Number(bits[1])][bits[2]]=value;
        }
    };

    const readPopupColor=()=>{
        if(!colorState)return null;
        const popup=q('#builder-color-popup');
        if(!popup)return null;
        if(colorState.format==='hex'){
            const hex=popup.querySelector('[data-color-part="hex"]');
            return parseColor(hex?hex.value:'');
        }
        if(colorState.format==='rgb'){
            const parts=['r','g','b'].map(key=>popup.querySelector('[data-color-part="'+key+'"]'));
            const alpha=popup.querySelector('[data-color-part="a"]');
            if(parts.some(input=>!input))return null;
            return {
                r:clamp(parseFloat(parts[0].value),0,255),
                g:clamp(parseFloat(parts[1].value),0,255),
                b:clamp(parseFloat(parts[2].value),0,255),
                a:clamp(parseFloat(alpha?alpha.value:1),0,1)
            };
        }
        const h=popup.querySelector('[data-color-part="h"]');
        const s=popup.querySelector('[data-color-part="s"]');
        const l=popup.querySelector('[data-color-part="l"]');
        const a=popup.querySelector('[data-color-part="a"]');
        if(!h||!s||!l)return null;
        return parseColor('hsla('+h.value+','+s.value+'%,'+l.value+'%,'+(a?a.value:1)+')');
    };

    const applyColor=color=>{
        if(!color||!colorState)return;
        colorState.color=color;
        setValue(nodeAt(selected),colorState.field,toHex(color));
        updateColorSummary();
        sync();
    };

    document.addEventListener('click',event=>{
        const structureOpen=event.target.closest('[data-structure-open]');
        if(structureOpen){
            openStructurePopup(structureOpen);
            return;
        }
        const colorOpen=event.target.closest('[data-color-open]');
        if(colorOpen){
            openColorPicker(colorOpen.dataset.colorOpen,colorOpen);
            return;
        }
        const layoutOpen=event.target.closest('[data-layout-open]');
        if(layoutOpen){
            openLayoutPicker(layoutOpen);
            return;
        }
        const close=event.target.closest('[data-popup-close]');
        if(close){
            const popup=close.closest('.builder-floating-popup');
            if(popup&&popup.id==='builder-structure-popup'){
                saveStructurePopupState(false);
                popup.remove();
            }else if(popup){
                popup.remove();
                colorState=null;
            }else closeFloating(false);
            return;
        }
        const format=event.target.closest('[data-color-format]');
        if(format&&colorState){
            colorState.format=format.dataset.colorFormat;
            renderColorPopup();
            return;
        }
        const eyedropper=event.target.closest('[data-color-eyedropper]');
        if(eyedropper&&colorState){
            if(window.EyeDropper){
                new window.EyeDropper().open().then(result=>{
                    const color=parseColor(result.sRGBHex);
                    if(color)applyColor(color);
                }).catch(()=>{});
            }else{
                const status=q('[data-color-status]');
                if(status)status.textContent='Eye dropper is not available in this browser.';
            }
            return;
        }
        const choice=event.target.closest('[data-layout-choice]');
        if(choice){
            const node=nodeAt(selected);
            const layout=choice.dataset.layoutChoice;
            node.layout_type=layout;
            if(layout==='CONTAINER')ensureChildren(node);
            else if(node.children)delete node.children;
            closeFloating();
            props();
            return;
        }
        const selectButton=event.target.closest('[data-select]');
        if(selectButton){
            selected=selectButton.dataset.select.split('.').map(Number);
            props();
            return;
        }
        const remove=event.target.closest('[data-delete]');
        if(remove){
            const path=remove.dataset.delete.split('.').map(Number);
            if(path.length<2)return;
            const parent=nodeAt(path.slice(0,-1));
            if(!parent||!Array.isArray(parent.children))return;
            parent.children.splice(path[path.length-1],1);
            selected=path.slice(0,-1);
            props();
            return;
        }
        const rootRow=event.target.closest('[data-add-root-row]');
        if(rootRow){
            const root=ensureChildren(rows[0]);
            root.children.push({layout_type:'TEXT_TOP',active:false,label_text:'',title_text:'',content_html:'',bullets:[]});
            selected=[0,root.children.length-1];
            props();
            return;
        }
        const rootContainer=event.target.closest('[data-add-root-container]');
        if(rootContainer){
            const root=ensureChildren(rows[0]);
            root.children.push({layout_type:'CONTAINER',active:true,children:[]});
            selected=[0,root.children.length-1];
            props();
            return;
        }
        const add=event.target.closest('[data-add]');
        if(add){
            const parent=nodeAt(add.dataset.add.split('.').map(Number));
            ensureChildren(parent).children.push({layout_type:'TEXT_TOP',active:false,label_text:'',title_text:'',content_html:'',bullets:[]});
            selected=add.dataset.add.split('.').map(Number).concat(parent.children.length-1);
            props();
            return;
        }
        const addContainer=event.target.closest('[data-add-container]');
        if(addContainer){
            const parent=nodeAt(addContainer.dataset.addContainer.split('.').map(Number));
            ensureChildren(parent).children.push({layout_type:'CONTAINER',active:true,children:[]});
            selected=addContainer.dataset.addContainer.split('.').map(Number).concat(parent.children.length-1);
            props();
            return;
        }
        const tab=event.target.closest('[data-inspector-tab]');
        if(tab){
            document.querySelectorAll('[data-inspector-tab]').forEach(item=>
                item.classList.toggle('active',item===tab));
            document.querySelectorAll('[data-inspector-panel]').forEach(item=>
                item.classList.toggle('active',item.dataset.inspectorPanel===tab.dataset.inspectorTab));
            return;
        }
        const popup=event.target.closest('.builder-floating-popup');
        if(!popup)closeFloating();
    });

    document.addEventListener('input',event=>{
        const native=event.target.closest('[data-color-native]');
        if(native&&colorState){
            const color=parseColor(native.value);
            if(color)applyColor({...color,a:parseFloat(q('[data-color-alpha]')?.value??color.a)});
            return;
        }
        const alpha=event.target.closest('[data-color-alpha]');
        if(alpha&&colorState){
            colorState.color.a=clamp(parseFloat(alpha.value),0,1);
            applyColor(colorState.color);
            return;
        }
        const part=event.target.closest('[data-color-part]');
        if(part&&colorState){
            const color=readPopupColor();
            if(color)applyColor(color);
            return;
        }
        const input=event.target.closest('[data-field]');
        if(!input)return;
        const node=nodeAt(selected);
        const name=input.dataset.field;
        setValue(node,name,name==='active'?input.value==='1':
            (input.type==='number'?Number(input.value):input.value));
        if(name==='bullets_text'){
            node.bullets=String(input.value).split(/\r?\n/).filter(Boolean);
        }
        if(name==='layout_type'&&node.layout_type==='CONTAINER')ensureChildren(node);
        if(name!=='active'&&node.layout_type!=='CONTAINER')node.active=true;
        if(['content_width','padding_mode','margin_mode','column_padding_mode','wrap_items'].includes(name))props();
        sync();
    });

    document.addEventListener('keydown',event=>{
        if(event.key==='Escape')closeFloating(false);
    });

    props();
    const savedStructure=readStructurePopupState();
    if(savedStructure.open)openStructurePopup(q('#structure-open'),savedStructure);
})();
