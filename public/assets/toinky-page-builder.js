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
    let draggedStructurePath=null;

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

    // Scroll preview iframe to the selected section (no highlight for now)
    const scrollPreviewToSection=(path,attempt=0)=>{
        const iframe=q('#page-preview');
        if(!iframe){if(attempt<3)setTimeout(()=>scrollPreviewToSection(path,attempt+1),500);return;}
        let doc=null;
        try{doc=iframe.contentDocument;}catch(e){}
        if(!doc){if(attempt<3)setTimeout(()=>scrollPreviewToSection(path,attempt+1),500);return;}
        const secMatch=window.location.search.match(/[?&]section=(\d+)/);
        const sectionId=secMatch?secMatch[1]:null;
        if(!sectionId)return;
        let secEl=null;
        try{secEl=doc.querySelector('[data-builder-section="'+sectionId+'"]');}catch(e){}
        if(secEl){
            try{secEl.scrollIntoView({block:'start',behavior:'smooth'});}catch(e){}
        }else if(attempt<3){
            setTimeout(()=>scrollPreviewToSection(path,attempt+1),500);
        }
    };

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
    const directionOptions=[['COLUMN','Top to Bottom'],['ROW','Left to Right']];
    const alignItemsOptions=[['stretch','Justify'],['flex-start','Align Left'],['center','Align Center'],['flex-end','Align Right']];
    const alignSelfOptions=[['stretch','Justify'],['flex-start','Left'],['center','Center'],['flex-end','Right']];
    const wrapOptions=[['1','Wrap on'],['0','Wrap off']];
    const structureKey='toinky-page-builder-structure-popup';
    const spacingPresets=[['','Custom'],['TIGHT','Tight'],['NORMAL','Normal'],['SPACIOUS','Spacious']];
    const spacingPresetValues={
        TIGHT:{padding_mode:'ALL',padding_all_px:24,margin_mode:'ALL',margin_all_px:0},
        NORMAL:{padding_mode:'ALL',padding_all_px:56,margin_mode:'ALL',margin_all_px:0},
        SPACIOUS:{padding_mode:'ALL',padding_all_px:96,margin_mode:'ALL',margin_all_px:0}
    };
    const responsiveColumnOptions=[['','Auto'],['1','1'],['2','2'],['3','3'],['4','4']];
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
    const styleFields=[
        'background_color','background_media_id','background_fit','text_color','text_align',
        'blend_mode','align_self','font_size','font_weight','text_transform',
        'margin_mode','margin_all_px','margin_top_px','margin_right_px','margin_bottom_px','margin_left_px',
        'padding_mode','padding_all_px','padding_top_px','padding_right_px','padding_bottom_px','padding_left_px',
        'column_background_color','column_background_media_id','column_background_fit',
        'column_margin_mode','column_margin_all_px','column_margin_top_px','column_margin_right_px','column_margin_bottom_px','column_margin_left_px',
        'column_padding_mode','column_padding_all_px','column_padding_top_px','column_padding_right_px','column_padding_bottom_px','column_padding_left_px',
        'content_width','max_width_px','min_height_px','items_direction','align_items','wrap_items',
        'image_width_px','image_height_px','tablet_padding_all_px','mobile_padding_all_px',
        'tablet_margin_all_px','mobile_margin_all_px','tablet_font_size','mobile_font_size',
        'tablet_columns','mobile_columns','tablet_image_width_px','mobile_image_width_px',
        'tablet_image_height_px','mobile_image_height_px'
    ];
    const allowedImportLayouts=new Set(layouts.map(item=>item[0]));
    const allowedImportFields=new Set([
        'layout_type','cards_per_row','active','style_name','spacing_preset','label_text','eyebrow_text',
        'title_text','content_html','bullets','image_media_id','columns','children',
        ...styleFields
    ]);
    const allowedImportColumnFields=new Set([
        'icon_text','title','content','background_color','background_media_id','background_fit',
        'margin_mode','margin_all_px','margin_top_px','margin_right_px','margin_bottom_px','margin_left_px',
        'padding_mode','padding_all_px','padding_top_px','padding_right_px','padding_bottom_px','padding_left_px'
    ]);
    const dangerousImportPattern=/(<\s*\/?\s*(script|iframe|object|embed|style|link|meta)\b|javascript\s*:|\bon[a-z]+\s*=|srcdoc\s*=|data\s*:\s*text\/html)/i;

    const clamp=(value,min,max)=>Math.min(max,Math.max(min,value));
    const parseColor=value=>{
        const raw=String(value??'').trim();
        let match=raw.match(/^#?([0-9a-f]{3,8})$/i);
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
    const builderAiRules=()=>[
        'TOINKY PAGE BUILDER AI RULES',
        '',
        'Return JSON only. Do not wrap it in Markdown fences. Do not include comments.',
        'Root format: {"rows":[...]} where rows is an array.',
        'Every non-hero section must start with one top-level CONTAINER row.',
        'A CONTAINER is only a shell. It can have backgrounds, spacing, width, flex settings, and children. It must not contain title/content fields.',
        'Containers may contain nested CONTAINER rows and content rows.',
        'Allowed layout_type values: '+layouts.map(item=>item[0]).join(', ')+'.',
        'Recommended root container example:',
        '{"rows":[{"layout_type":"CONTAINER","active":true,"content_width":"FULL","padding_mode":"ALL","padding_all_px":0,"children":[]}]}',
        '',
        'Nested container example:',
        '{"rows":[{"layout_type":"CONTAINER","active":true,"content_width":"FULL","padding_mode":"ALL","padding_all_px":0,"children":[{"layout_type":"TEXT_CENTER","active":true,"label_text":"Example Label","title_text":"Example Title","content_html":"<p>Example content.</p>"},{"layout_type":"CONTAINER","active":true,"content_width":"MAX","max_width_px":1200,"padding_mode":"ALL","padding_all_px":24,"children":[{"layout_type":"FOUR_COLUMN","active":true,"label_text":"Feature Grid","title_text":"Four reasons to play","content_html":"<p>Short intro for the grid.</p>","columns":[{"icon_text":"✦","title":"Column One","content":"<p>Column copy.</p>"},{"icon_text":"◆","title":"Column Two","content":"<p>Column copy.</p>"},{"icon_text":"☉","title":"Column Three","content":"<p>Column copy.</p>"},{"icon_text":"⚑","title":"Column Four","content":"<p>Column copy.</p>"}]}]}]}]}',
        '',
        'Content row fields: label_text, title_text, content_html, bullets, image_media_id, columns, active.',
        'Allowed inline HTML in content_html/content: p, br, strong, em, u, h2, h3, ul, ol, li, a.',
        'Safe links only: https://, mailto:, /relative, ?query, or #anchor.',
        'Do not include script, iframe, object, embed, style tags, event attributes like onclick/onload, javascript: URLs, or data:text/html URLs.',
        'Media fields must use existing numeric media IDs only. If no media ID is provided, use null or an empty string.',
        '',
        'Common style fields: background_color, background_media_id, background_fit (FILL/FIT/TILE), text_color, text_align (left/center/right), blend_mode (normal/multiply/screen/overlay/darken/lighten/difference), align_self, margin_mode, margin_all_px, padding_mode, padding_all_px.',
        'Container fields: content_width (FULL/MAX), max_width_px, min_height_px, items_direction (COLUMN/ROW), align_items (stretch/flex-start/center/flex-end), wrap_items.',
        'Columned layouts use columns: [{"icon_text":"","title":"","content":""}].',
        'Use concise, production-ready copy. Keep empty values as empty strings, not placeholder text.'
    ].join('\n');
    const builderOutlinePrompt=()=>[
        'Create a Toinky page content outline first, then after approval generate Page Builder JSON using the copied Toinky Page Builder AI Rules.',
        '',
        'Ask me for: target page, audience, goal, tone, primary CTA, required sections, available media IDs, preferred colors, and any content that must stay unchanged.',
        '',
        'When generating JSON, use a root CONTAINER, nested containers when grouping rows under one background, and content rows for text/image/columns/cards/socials. Keep it safe: no scripts, no event attributes, no unsafe URLs.'
    ].join('\n');
    const writeClipboard=text=>{
        if(navigator.clipboard?.writeText)return navigator.clipboard.writeText(text);
        const textarea=document.createElement('textarea');
        textarea.value=text;
        textarea.setAttribute('readonly','');
        textarea.style.position='fixed';
        textarea.style.left='-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
        return Promise.resolve();
    };
    const aiStatus=(message,error=false)=>{
        const status=q('#builder-ai-status');
        if(!status)return;
        status.textContent=message;
        status.classList.toggle('error',error);
        if(message)setTimeout(()=>{
            if(status.textContent===message)status.textContent='';
        },2600);
    };
    const primitiveImportValue=value=>{
        if(Array.isArray(value))return value.map(item=>primitiveImportValue(item)).filter(item=>
            item===null||['string','number','boolean'].includes(typeof item));
        if(value===null||['string','number','boolean'].includes(typeof value))return value;
        return '';
    };
    const sanitizeImportColumn=column=>{
        const out={};
        if(!column||typeof column!=='object'||Array.isArray(column))return out;
        Object.keys(column).forEach(key=>{
            if(allowedImportColumnFields.has(key))out[key]=primitiveImportValue(column[key]);
        });
        return out;
    };
    const sanitizeImportNode=(node,depth=0)=>{
        if(!node||typeof node!=='object'||Array.isArray(node)||depth>10)return null;
        const layout=String(node.layout_type||'TEXT_TOP').toUpperCase();
        const out={layout_type:allowedImportLayouts.has(layout)&&layout!=='HERO'?layout:'TEXT_TOP'};
        Object.keys(node).forEach(key=>{
            if(!allowedImportFields.has(key)||key==='layout_type'||key==='children'||key==='columns')return;
            out[key]=primitiveImportValue(node[key]);
        });
        if(Array.isArray(node.columns))out.columns=node.columns.slice(0,6).map(sanitizeImportColumn);
        if(out.layout_type==='CONTAINER')out.children=Array.isArray(node.children)?
            node.children.map(child=>sanitizeImportNode(child,depth+1)).filter(Boolean):[];
        return out;
    };
    const parseAiRows=jsonText=>{
        const raw=String(jsonText||'').trim();
        if(raw.length<2)throw new Error('Paste Page Builder JSON first.');
        if(raw.length>600000)throw new Error('The JSON is too large. Keep imports under 600 KB.');
        if(dangerousImportPattern.test(raw)){
            throw new Error('Import blocked: remove scripts, event attributes, unsafe URLs, or embedded HTML documents.');
        }
        const parsed=JSON.parse(raw);
        let imported=Array.isArray(parsed)?parsed:parsed?.rows;
        if(!Array.isArray(imported)&&parsed?.layout_type)imported=[parsed];
        if(!Array.isArray(imported))throw new Error('JSON must be an array, a row object, or an object with a rows array.');
        imported=imported.map(item=>sanitizeImportNode(item)).filter(Boolean);
        if(!imported.length)throw new Error('No valid rows were found.');
        if(imported[0].layout_type!=='CONTAINER'){
            imported=[{layout_type:'CONTAINER',active:true,content_width:'FULL',padding_mode:'ALL',padding_all_px:0,children:imported}];
        }
        return imported;
    };
    const openAiImport=()=>{
        const modal=q('#builder-ai-modal');
        if(!modal)return;
        const error=q('#builder-ai-error');
        if(error)error.textContent='';
        modal.hidden=false;
        q('#builder-ai-json')?.focus();
    };
    const closeAiImport=()=>{
        const modal=q('#builder-ai-modal');
        if(modal)modal.hidden=true;
    };

    let previewScroll={x:0,y:0,docX:0,docY:0,bodyX:0,bodyY:0};
    const readPreviewScroll=()=>{
        const frame=q('#page-preview');
        try{
            const win=frame?.contentWindow;
            const doc=win?.document;
            if(frame&&frame.contentWindow)previewScroll={
                x:win.scrollX||0,
                y:win.scrollY||0,
                docX:doc?.documentElement?.scrollLeft||0,
                docY:doc?.documentElement?.scrollTop||0,
                bodyX:doc?.body?.scrollLeft||0,
                bodyY:doc?.body?.scrollTop||0
            };
        }catch(error){}
    };
    q('#page-preview')?.addEventListener('load',()=>{
        const frame=q('#page-preview');
        try{
            const win=frame?.contentWindow;
            const doc=win?.document;
            if(win){
                const x=Math.max(previewScroll.x,previewScroll.docX,previewScroll.bodyX);
                const y=Math.max(previewScroll.y,previewScroll.docY,previewScroll.bodyY);
                const restore=()=>{
                    win.scrollTo(x,y);
                    if(doc?.documentElement)doc.documentElement.scrollTo(x,y);
                    if(doc?.body)doc.body.scrollTo(x,y);
                };
                restore();
                requestAnimationFrame(restore);
                setTimeout(restore,80);
                setTimeout(restore,220);
            }
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
    window.toinkyPBSelectSync=function(sel){
        try{
            const fname=sel.dataset.field||'';
            const parts=fname.split('.');
            if(parts.length!==3||parts[0]!=='columns')return;
            sel.dispatchEvent(new CustomEvent('pb-select-change',{bubbles:true,detail:{field:fname,value:sel.value}}));
        }catch(e){}
    };
    const select=(name,title,value,options)=>{
        return '<label class="builder-field">'+escapeHtml(title)+
            '<select data-field="'+escapeHtml(name)+'" onchange="window.toinkyPBSelectSync(this)">'+options.map(option=>
            '<option value="'+escapeHtml(option[0])+'" '+
            (String(option[0])===String(value)?'selected':'')+'>'+
            escapeHtml(option[1])+'</option>').join('')+'</select></label>';
    };
    const area=(name,title,value)=>{
        return '<label class="builder-field">'+escapeHtml(title)+
            '<textarea data-field="'+escapeHtml(name)+'">'+
            escapeHtml(value??'')+'</textarea></label>';
    };
    const buttonsEditor=node=>{
        const buttons=Array.isArray(node.buttons)?node.buttons:[];
        let html=subheading('Buttons');
        for(let i=0;i<4;i++){
            const btn=buttons[i]||{};
            html+='<div class="builder-subheading" style="font-size:12px;margin-top:8px;">Button '+(i+1)+'</div>'+
                field('buttons.'+i+'.label','Label',btn.label||'')+
                field('buttons.'+i+'.url','URL',btn.url||'');
        }
        return html;
    };
    const subheading=title=>'<div class="builder-subheading">'+escapeHtml(title)+'</div>';
    const backgroundControls=(title,prefix,node)=>{
        const key=name=>prefix?prefix+'_'+name:name;
        let html=subheading(title)+
            field(key('background_color'),'Background color',node[key('background_color')]||'','color')+
            select(key('background_media_id'),'Background image',node[key('background_media_id')]||'',mediaOptions)+
            select(key('background_fit'),'Image fill',node[key('background_fit')]||'FILL',backgroundFitOptions);
        if(!prefix){
            html+=field('overlay_color','Overlay color',node.overlay_color||'','color')+
                field('overlay_opacity','Overlay opacity %',node.overlay_opacity??0,'number');
        }
        return html;
    };
    const columnBackgroundControls=(index,title,column)=>{
        const base='columns.'+index+'.';
        return subheading(title)+
            field(base+'background_color','Background color',column.background_color||'','color')+
            select(base+'background_media_id','Background image',column.background_media_id||'',mediaOptions)+
            select(base+'background_fit','Image fill',column.background_fit||'FILL',backgroundFitOptions);
    };
    const paddingControls=(title,prefix,node)=>{
        const base=prefix?prefix+'_padding':'padding';
        const modeKey=base+'_mode';
        const allKey=base+'_all_px';
        const key=edge=>base+'_'+edge+'_px';
        const mode=node[modeKey]||'ALL';
        const value=edge=>mode==='ALL'?(node[allKey]??0):(node[key(edge)]??0);
        return spacingControls(title,modeKey,mode,allKey,key,value);
    };
    const marginControls=(title,node)=>{
        const mode=node.margin_mode||'ALL';
        const key=edge=>'margin_'+edge+'_px';
        const value=edge=>mode==='ALL'?(node.margin_all_px??0):(node[key(edge)]??0);
        return spacingControls(title,'margin_mode',mode,'margin_all_px',key,value);
    };
    const nestedSpacingControls=(title,fieldBase,node,keyBase,defaultAll=0)=>{
        const mode=node[keyBase+'mode']||'ALL';
        const allKey=fieldBase+'all_px';
        const key=edge=>fieldBase+edge+'_px';
        const value=edge=>mode==='ALL'?(node[keyBase+'all_px']??defaultAll):(node[keyBase+edge+'_px']??defaultAll);
        return spacingControls(title,fieldBase+'mode',mode,allKey,key,value);
    };
    const renderColumnStyleControls=node=>{
        if(!['CARDS_ONLY','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN'].includes(node.layout_type))return '';
        const count=node.layout_type==='TWO_COLUMN'?2:(node.layout_type==='FOUR_COLUMN'?4:3);
        node.columns=Array.isArray(node.columns)?node.columns:[];
        return Array.from({length:count},(_,index)=>{
            const column=node.columns[index]||{};
            const base='columns.'+index+'.';
            const title=(node.layout_type==='CARDS_ONLY'?'Card ':'Column ')+(index+1);
            return '<div class="builder-column-style">'+
                columnBackgroundControls(index,title,column)+
                nestedSpacingControls('Margin',base+'margin_',column,'margin_',10)+
                nestedSpacingControls('Padding',base+'padding_',column,'padding_',0)+
                '</div>';
        }).join('');
    };
    const spacingControls=(title,modeKey,mode,allKey,key,value)=>{
        const linked=mode==='ALL';
        const input=edge=>'<label><input type="number" data-field="'+
            escapeHtml(linked?allKey:key(edge))+'" value="'+escapeHtml(value(edge))+
            '"><span>'+edge.charAt(0).toUpperCase()+edge.slice(1)+'</span></label>';
        return '<div class="builder-spacing-control"><div class="builder-spacing-head">'+
            '<span>'+escapeHtml(title)+'</span><small>px</small></div><div class="builder-spacing-grid">'+
            ['top','right','bottom','left'].map(input).join('')+
            '<button type="button" class="'+(linked?'active':'')+'" data-choice-field="'+
            escapeHtml(modeKey)+'" data-choice-value="'+(linked?'SIDES':'ALL')+
            '" aria-label="'+(linked?'Unlink sides':'Link sides')+'" title="'+
            (linked?'Unlink sides':'Link sides')+'">⌁</button></div></div>';
    };
    const styleLibrary=()=>{
        const root=rows[0]||{};
        root.style_library=Array.isArray(root.style_library)?root.style_library:[];
        return root.style_library;
    };
    const savedStyleOptions=()=>[['','Choose saved style'],...styleLibrary().map((style,index)=>[
        String(index),style.name||('Style '+(index+1))
    ])];
    const copyStyle=node=>{
        const snapshot={};
        styleFields.forEach(key=>{
            if(Object.prototype.hasOwnProperty.call(node,key))snapshot[key]=node[key];
        });
        if(Array.isArray(node.columns)){
            snapshot.columns=node.columns.map(column=>{
                const copy={};
                ['background_color','background_media_id','background_fit','margin_mode','margin_all_px',
                    'margin_top_px','margin_right_px','margin_bottom_px','margin_left_px','padding_mode',
                    'padding_all_px','padding_top_px','padding_right_px','padding_bottom_px','padding_left_px'
                ].forEach(key=>{
                    if(Object.prototype.hasOwnProperty.call(column,key))copy[key]=column[key];
                });
                return copy;
            });
        }
        return snapshot;
    };
    const saveCurrentStyle=()=>{
        const node=nodeAt(selected);
        if(!node)return;
        const name=String(node.style_name||nodeTitle(node)||'Saved style').trim().slice(0,80);
        const library=styleLibrary();
        const next={name,values:copyStyle(node)};
        const index=library.findIndex(style=>style.name===name);
        if(index>=0)library[index]=next;
        else library.push(next);
        rows[0].style_library=library.slice(-30);
        sync();
        props();
    };
    const applySavedStyle=index=>{
        const style=styleLibrary()[Number(index)];
        const node=nodeAt(selected);
        if(!style||!node||!style.values)return;
        Object.entries(style.values).forEach(([key,value])=>{
            if(key==='columns'&&Array.isArray(value)){
                node.columns=Array.isArray(node.columns)?node.columns:[];
                value.forEach((column,columnIndex)=>{
                    node.columns[columnIndex]={...(node.columns[columnIndex]||{}),...column};
                });
            }else node[key]=value;
        });
        node.style_name=style.name||node.style_name||'';
        sync();
        props();
    };
    const styleReuseControls=node=>{
        return subheading('Reusable style')+
            field('style_name','Name',node.style_name||'')+
            '<div class="builder-style-reuse">'+
            select('style_pick','Apply saved style','',savedStyleOptions())+
            '<button type="button" data-style-apply title="Apply saved style" aria-label="Apply saved style">↙</button>'+
            '<button type="button" data-style-save title="Save selected styling" aria-label="Save selected styling">+</button>'+
            '</div>';
    };
    const responsiveControls=node=>{
        const columned=['CARDS_ONLY','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN'].includes(node.layout_type);
        const imaged=['IMAGE_LEFT','IMAGE_RIGHT'].includes(node.layout_type);
        return subheading('Responsive overrides')+
            field('tablet_padding_all_px','Tablet padding',node.tablet_padding_all_px||'','number')+
            field('mobile_padding_all_px','Mobile padding',node.mobile_padding_all_px||'','number')+
            field('tablet_margin_all_px','Tablet margin',node.tablet_margin_all_px||'','number')+
            field('mobile_margin_all_px','Mobile margin',node.mobile_margin_all_px||'','number')+
            field('tablet_font_size','Tablet font size',node.tablet_font_size||'')+
            field('mobile_font_size','Mobile font size',node.mobile_font_size||'')+
            (columned?
                select('tablet_columns','Tablet columns',node.tablet_columns||'',responsiveColumnOptions)+
                select('mobile_columns','Mobile columns',node.mobile_columns||'',responsiveColumnOptions):'')+
            (imaged?
                field('tablet_image_width_px','Tablet image width',node.tablet_image_width_px||'','number')+
                field('tablet_image_height_px','Tablet image height',node.tablet_image_height_px||'','number')+
                field('mobile_image_width_px','Mobile image width',node.mobile_image_width_px||'','number')+
                field('mobile_image_height_px','Mobile image height',node.mobile_image_height_px||'','number'):'');
    };
    const choiceButtons=(name,title,value,options)=>{
        return '<div class="builder-choice-control"><div class="builder-spacing-head"><span>'+
            escapeHtml(title)+'</span></div><div class="builder-choice-buttons">'+options.map(option=>
            '<button type="button" class="'+(String(option[0])===String(value)?'active':'')+
            '" data-choice-field="'+escapeHtml(name)+'" data-choice-value="'+
            escapeHtml(option[0])+'" title="'+escapeHtml(option[1])+'" aria-label="'+
            escapeHtml(option[1])+'">'+escapeHtml(option[1].charAt(0))+'</button>').join('')+
            '</div></div>';
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
        const duplicateButton=deletable?
            '<button type="button" class="builder-node-duplicate" data-duplicate="'+
            path.join('.')+'" aria-label="Duplicate '+escapeHtml(layoutTitle(node.layout_type))+
            '" title="Duplicate">⧉</button>':'';
        return '<div class="builder-structure-row '+(pathsEqual(path,selected)?'selected':'')+
            '" style="--depth:'+depth+'" data-structure-path="'+path.join('.')+
            '" draggable="'+(path.length>1?'true':'false')+'"><button type="button" data-select="'+
            path.join('.')+'"><span class="builder-structure-caret" aria-hidden="true">'+
            (container&&children.length?'>':'')+'</span><span class="builder-structure-icon" aria-hidden="true">'+
            (container?'[]':'T')+'</span><span class="builder-structure-name">'+
            escapeHtml(nodeTitle(node))+'</span><small>'+escapeHtml(layoutTitle(node.layout_type))+
            '</small></button>'+layoutButton+duplicateButton+deleteButton+'</div>'+
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
                    subheading((node.layout_type==='CARDS_ONLY'?'Card ':'Column ')+(index+1))+
                    select('columns.'+index+'.icon_text','Icon',column.icon_text||'',iconOptions)+
                    field('columns.'+index+'.icon_size_px','Icon size (px)',column.icon_size_px||32,'number')+
                    field('columns.'+index+'.icon_color','Icon color',column.icon_color||'#B58E46','color')+
                    select('columns.'+index+'.icon_align','Icon position',column.icon_align||'left',[['left','Left'],['center','Center'],['right','Right']])+
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
        const propContainer=q('#content-properties');
        const savedWinY=window.scrollY;
        if(propContainer)propContainer.dataset.selectedPath=selected.join('.');
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
            (node.layout_type==='STORY_CARDS'?select('cards_per_row','Cards per row',String(node.cards_per_row||3),[['2','2 per row'],['3','3 per row'],['4','4 per row']]):'')+
            subheading('Main')+
            field('label_text','Label',node.label_text||node.eyebrow_text||'')+
            field('title_text','Title',node.title_text||'')+
            area('content_html','Content',node.content_html||'')+
            buttonsEditor(node)+
            (node.layout_type==='TEXT_BULLETS'?
                area('bullets_text','Bullets, one per line',(node.bullets||[]).join('\n')):'')+
            renderColumns(node));
        q('#style-properties').innerHTML=container?
            styleReuseControls(node)+
            select('spacing_preset','Spacing preset',node.spacing_preset||'',spacingPresets)+
            backgroundControls('Container background','',node)+
            marginControls('Container margin',node)+
            paddingControls('Container padding','',node)+
            choiceButtons('align_self','Align self',node.align_self||'stretch',alignSelfOptions)+
            responsiveControls(node):
            styleReuseControls(node)+
            select('spacing_preset','Spacing preset',node.spacing_preset||'',spacingPresets)+
            (['IMAGE_LEFT','IMAGE_RIGHT'].includes(node.layout_type)?
                select('image_media_id','Content image',node.image_media_id||'',mediaOptions)+
                field('image_width_px','Image width (px, blank auto)',node.image_width_px||'','number')+
                field('image_height_px','Image height (px, blank auto)',node.image_height_px||'','number'):'')+
            subheading('Main')+
            backgroundControls('Main box background','',node)+
            marginControls('Main box margin',node)+
            paddingControls('Main box padding','',node)+
            choiceButtons('align_self','Align self',node.align_self||'stretch',alignSelfOptions)+
            (['CARDS_ONLY','TWO_COLUMN','THREE_COLUMN','FOUR_COLUMN'].includes(node.layout_type)?
                subheading('Columns')+
                backgroundControls('Column background','column',node)+
                marginControls('Column margin',{...node,margin_mode:node.column_margin_mode||'ALL',margin_all_px:node.column_margin_all_px??10,margin_top_px:node.column_margin_top_px??10,margin_right_px:node.column_margin_right_px??10,margin_bottom_px:node.column_margin_bottom_px??10,margin_left_px:node.column_margin_left_px??10}).replaceAll('margin_','column_margin_')+
                paddingControls('Column padding','column',node)+
                renderColumnStyleControls(node):'')+
            responsiveControls(node);
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
            field('title_color','Title color',node.title_color||'','color')+
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
        if(propContainer)propContainer.scrollTop=0;
        if(window.scrollY!==savedWinY)window.scrollTo(window.scrollX,savedWinY);
    
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

    const clearStructureDropState=()=>{
        document.querySelectorAll('.builder-structure-row').forEach(row=>{
            row.classList.remove('is-dragging','drop-before','drop-after');
        });
    };

    const sameParentPath=(left,right)=>{
        if(!left||!right||left.length!==right.length||left.length<2)return false;
        return left.slice(0,-1).every((item,index)=>item===right[index]);
    };

    const moveStructureNode=(fromPath,toPath,after)=>{
        if(!sameParentPath(fromPath,toPath))return false;
        const parent=nodeAt(fromPath.slice(0,-1));
        if(!parent||!Array.isArray(parent.children))return false;
        const fromIndex=fromPath[fromPath.length-1];
        let toIndex=toPath[toPath.length-1]+(after?1:0);
        if(fromIndex===toIndex||fromIndex+1===toIndex)return false;
        const moved=parent.children.splice(fromIndex,1)[0];
        if(fromIndex<toIndex)toIndex-=1;
        parent.children.splice(toIndex,0,moved);
        selected=fromPath.slice(0,-1).concat(toIndex);
        return true;
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

    const renderBrandColors=()=>{
        const brandColors=(window.toinkyPageBuilder&&window.toinkyPageBuilder.brandColors)||[];
        if(!brandColors.length)return '';
        let html='<div class="builder-brand-colors" style="margin:10px 0;padding:10px 12px;border-top:1px solid #eee;"><div style="font-size:11px;font-weight:bold;margin-bottom:8px;color:#666;">BRAND COLORS</div><div style="display:flex;flex-wrap:wrap;gap:8px;">';
        brandColors.forEach(c=>{
            const hex=(c.hex||'').toUpperCase();
            if(!hex)return;
            html+='<button type="button" data-brand-color="'+hex+'" title="'+escapeHtml(c.name||hex)+'" style="width:32px;height:32px;border-radius:4px;border:2px solid #ddd;background:'+hex+';cursor:pointer;"></button>';
        });
        html+='</div></div>';
        return html;
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
            colorState.color.a+'"></label>'+ 
            '<button type="button" data-color-transparent style="margin-left:8px;padding:4px 12px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;">Transparent</button></div>'+
            renderBrandColors()+colorPopupFields()+
            '<p class="builder-color-status" data-color-status>Changes apply immediately.</p>';
        popup.querySelectorAll('[data-color-part]').forEach(input=>{
            input.classList.add('builder-color-part');
        });
        const transBtn=popup.querySelector('[data-color-transparent]');
        if(transBtn&&!transBtn.dataset.bound){
            transBtn.dataset.bound='1';
            transBtn.addEventListener('click',()=>{
                if(!colorState)return;
                setValue(nodeAt(selected),colorState.field,'');
                updateColorSummary();
                sync();
                const popup2=q('#builder-color-popup');
                if(popup2)popup2.style.display='none';
                colorState=null;
            });
        }
        popup.querySelectorAll('[data-brand-color]').forEach(btn=>{
            if(btn.dataset.bound)return;
            btn.dataset.bound='1';
            btn.addEventListener('click',()=>{
                if(!colorState)return;
                const hex=btn.getAttribute('data-brand-color');
                setValue(nodeAt(selected),colorState.field,hex);
                updateColorSummary();
                sync();
                const popup2=q('#builder-color-popup');
                if(popup2)popup2.style.display='none';
                colorState=null;
            });
        });
    };

    const getValue=(node,path)=>{
        const bits=path.split('.');
        if(bits.length===1)return node?node[bits[0]]:undefined;
        if(bits.length===3&&bits[0]==='columns'){
            const index=Number(bits[1]);
            return node&&Array.isArray(node.columns)&&node.columns[index]?node.columns[index][bits[2]]:undefined;
        }
        return undefined;
    };

    const openColorPicker=(fieldName,anchor)=>{
        closeFloating(false);
        const parsed=parseColor(getValue(nodeAt(selected),fieldName))||{r:244,g:238,b:226,a:1};
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
            const newSel=selectButton.dataset.select.split('.').map(Number);
            const changed=JSON.stringify(newSel)!==JSON.stringify(selected);
            selected=newSel;
            if(changed)props();
            // Scroll the selected tree item into view
            const selEl=document.querySelector('.builder-structure-row.selected');
            if(selEl)selEl.scrollIntoView({block:'nearest',behavior:'smooth'});
            // Highlight in preview iframe
            scrollPreviewToSection(newSel);
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
        const duplicate=event.target.closest('[data-duplicate]');
        if(duplicate){
            const path=duplicate.dataset.duplicate.split('.').map(Number);
            if(path.length<2)return;
            const parent=nodeAt(path.slice(0,-1));
            const index=path[path.length-1];
            if(!parent||!Array.isArray(parent.children)||!parent.children[index])return;
            const copy=JSON.parse(JSON.stringify(parent.children[index]));
            if(copy.title_text)copy.title_text=copy.title_text+' Copy';
            parent.children.splice(index+1,0,copy);
            selected=path.slice(0,-1).concat(index+1);
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
        const choiceButton=event.target.closest('[data-choice-field]');
        if(choiceButton){
            const node=nodeAt(selected);
            setValue(node,choiceButton.dataset.choiceField,choiceButton.dataset.choiceValue);
            props();
            return;
        }
        const styleSave=event.target.closest('[data-style-save]');
        if(styleSave){
            saveCurrentStyle();
            return;
        }
        const styleApply=event.target.closest('[data-style-apply]');
        if(styleApply){
            const selectNode=q('[data-field="style_pick"]');
            if(selectNode&&selectNode.value!=='')applySavedStyle(selectNode.value);
            return;
        }
        const popup=event.target.closest('.builder-floating-popup');
        if(!popup)closeFloating();
    });

    document.addEventListener('dragstart',event=>{
        const row=event.target.closest('.builder-structure-row[draggable="true"]');
        if(!row||event.target.closest('[data-delete],[data-duplicate],[data-layout-open]'))return;
        draggedStructurePath=row.dataset.structurePath.split('.').map(Number);
        row.classList.add('is-dragging');
        if(event.dataTransfer){
            event.dataTransfer.effectAllowed='move';
            event.dataTransfer.setData('text/plain',row.dataset.structurePath);
        }
    });

    document.addEventListener('dragover',event=>{
        const row=event.target.closest('.builder-structure-row[draggable="true"]');
        if(!row||!draggedStructurePath)return;
        const targetPath=row.dataset.structurePath.split('.').map(Number);
        if(!sameParentPath(draggedStructurePath,targetPath)||pathsEqual(draggedStructurePath,targetPath))return;
        event.preventDefault();
        const rect=row.getBoundingClientRect();
        const after=event.clientY>rect.top+rect.height/2;
        clearStructureDropState();
        row.classList.add(after?'drop-after':'drop-before');
    });

    document.addEventListener('drop',event=>{
        const row=event.target.closest('.builder-structure-row[draggable="true"]');
        if(!row||!draggedStructurePath)return;
        const targetPath=row.dataset.structurePath.split('.').map(Number);
        const rect=row.getBoundingClientRect();
        const after=event.clientY>rect.top+rect.height/2;
        event.preventDefault();
        if(moveStructureNode(draggedStructurePath,targetPath,after))props();
        draggedStructurePath=null;
        clearStructureDropState();
    });

    document.addEventListener('dragend',()=>{
        draggedStructurePath=null;
        clearStructureDropState();
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
        const name=input.dataset.field;
        // Resolve the full nested path (e.g., [0,2] for child row) - not just top-level
        const node=nodeAt(selected);
        if(!node)return;
        setValue(node,name,name==='active'?input.value==='1':
            (input.type==='number'?Number(input.value):input.value));
        if(name==='spacing_preset'&&spacingPresetValues[input.value]){
            Object.assign(node,spacingPresetValues[input.value]);
        }
        if(name==='bullets_text'){
            node.bullets=String(input.value).split(/\r?\n/).filter(Boolean);
        }
        if(name==='layout_type'&&node.layout_type==='CONTAINER')ensureChildren(node);
        if(name!=='active'&&node.layout_type!=='CONTAINER')node.active=true;
        if(['content_width','padding_mode','margin_mode','column_padding_mode','column_margin_mode','wrap_items','spacing_preset'].includes(name)||name.endsWith('.margin_mode')||name.endsWith('.padding_mode'))props();
        sync();
    });

    q('#ai-rules-copy')?.addEventListener('click',()=>{
        writeClipboard(builderAiRules()).then(()=>aiStatus('AI rules copied.')).catch(()=>
            aiStatus('Unable to copy. Select and copy manually.',true));
    });

    q('#ai-outline-copy')?.addEventListener('click',()=>{
        writeClipboard(builderOutlinePrompt()).then(()=>aiStatus('Outline prompt copied.')).catch(()=>
            aiStatus('Unable to copy. Select and copy manually.',true));
    });

    q('#ai-json-open')?.addEventListener('click',openAiImport);
    q('#builder-ai-close')?.addEventListener('click',closeAiImport);
    q('#builder-ai-clear')?.addEventListener('click',()=>{
        const textarea=q('#builder-ai-json');
        const error=q('#builder-ai-error');
        if(textarea)textarea.value='';
        if(error)error.textContent='';
    });
    q('#builder-ai-modal')?.addEventListener('click',event=>{
        if(event.target===event.currentTarget)closeAiImport();
    });
    q('#builder-ai-import')?.addEventListener('click',()=>{
        const textarea=q('#builder-ai-json');
        const error=q('#builder-ai-error');
        try{
            rows=parseAiRows(textarea?.value||'');
            selected=[0];
            if(error)error.textContent='';
            closeAiImport();
            props();
            aiStatus('AI JSON imported as a draft. Review, then save changes.');
        }catch(importError){
            if(error)error.textContent=importError.message||'Import failed.';
        }
    });

        document.addEventListener('pb-select-change',event=>{
        const fname=event.detail&&event.detail.field;
        const val=event.detail&&event.detail.value;
        if(!fname)return;
        const parts=fname.split('.');
        if(parts.length!==3||parts[0]!=='columns')return;
        const node=nodeAt(selected);
        if(!node)return;
        setValue(node,fname,val);
    });
    document.addEventListener('change',event=>{
        const input=event.target.closest('[data-field]');
        if(!input)return;
        if(input.tagName!=='SELECT')return;
        const node=nodeAt(selected);
        if(!node)return;
        setValue(node,input.dataset.field,input.value);
    });
    
document.addEventListener('keydown',event=>{
        if(event.key==='Escape'){
            closeAiImport();
            closeFloating(false);
        }
    });

    props();
    const savedStructure=readStructurePopupState();
    if(savedStructure.open)openStructurePopup(q('#structure-open'),savedStructure);

    // AJAX save via index.php (no page refresh) - avoids 404 on page-builder.php POST
    const builderForm=q('#builder-form');
    if(builderForm){
        builderForm.addEventListener('submit',async event=>{
            event.preventDefault();
            const saveBtn=builderForm.querySelector('.builder-save');
            const origText=saveBtn?saveBtn.textContent:'';
            if(saveBtn){saveBtn.disabled=true;saveBtn.textContent='Saving...';}
            try{
                // Sync all data-field inputs directly to rows (bulletproof)
                // Use nodeAt(selected) to resolve nested paths, not just top-level rows
                try{
                    const row=nodeAt(selected);
                    if(row){
                        if(!Array.isArray(row.columns))row.columns=[];
                        document.querySelectorAll('[data-field]').forEach(input=>{
                            const fname=input.dataset.field||'';
                            const parts=fname.split('.');
                            if(parts.length!==3||parts[0]!=='columns')return;
                            const colIdx=parseInt(parts[1],10);
                            if(isNaN(colIdx))return;
                            const fieldName=parts[2];
                            if(!row.columns[colIdx])row.columns[colIdx]={};
                            let val=input.value;
                            if(input.type==='number'){val=Number(val);if(isNaN(val))val=0;}
                            row.columns[colIdx][fieldName]=val;
                        });
                    }
                }catch(syncErr){console.error('[PB Save] Sync failed:',syncErr);}
                // Sync the rows payload
                const payloadInput=q('#rows-payload');
                if(payloadInput)payloadInput.value=JSON.stringify(rows);
                const formData=new FormData(builderForm);
                // Add page code for redirect fallback
                const pageMatch=window.location.search.match(/[?&]page=([^&]+)/);
                if(pageMatch)formData.append('page',decodeURIComponent(pageMatch[1]));
                const resp=await fetch('index.php',{
                    method:'POST',
                    headers:{'X-Requested-With':'XMLHttpRequest'},
                    body:formData,
                    credentials:'same-origin'
                });
                const data=await resp.json();
                if(!resp.ok||!data.ok)throw new Error((data&&data.error)||('Save failed ('+resp.status+')'));
                // Success - show message without refresh
                let msg=q('#builder-save-msg');
                if(!msg){
                    msg=document.createElement('div');
                    msg.id='builder-save-msg';
                    msg.className='builder-save-success';
                    msg.style.cssText='position:fixed;top:20px;right:20px;background:#1C242B;color:#F4EEE2;padding:12px 20px;border-radius:8px;z-index:9999;';
                    document.body.appendChild(msg);
                }
                msg.textContent='Changes saved.';
                msg.style.display='block';
                setTimeout(()=>{msg.style.display='none';},3000);
            }catch(err){
                // Fallback to normal form submit on error
                console.error('AJAX save failed, falling back:',err);
                builderForm.submit();
            }finally{
                if(saveBtn){saveBtn.disabled=false;saveBtn.textContent=origText;}
            }
        });
    }
})();
