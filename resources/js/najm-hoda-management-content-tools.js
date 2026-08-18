const PANEL_ID = 'najm-hoda-management-panel-v2';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const esc = value => String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
const canManage = () => Boolean(window.groupId && window.GroupChatConfig?.canManageSession && [2,3].includes(Number(window.GroupChatConfig?.yourRole)));
const pageContext = widget => typeof widget?.getPageContext === 'function' ? widget.getPageContext() : { module:'groups', resource_type:'group', resource_id:window.groupId };

function addStyles(){
    if(document.getElementById('nh-management-content-tools-styles')) return;
    const style=document.createElement('style');
    style.id='nh-management-content-tools-styles';
    style.textContent=`
      .nh-content-sheet{display:none;background:#fff;border:1px solid #dce7e5;border-radius:13px;margin:0 12px 12px;padding:12px;box-shadow:0 8px 24px rgba(29,79,74,.08)}
      .nh-content-sheet.open{display:block}.nh-content-sheet h4{font-size:12px;margin:0 0 10px;color:#285b56}
      .nh-content-field{margin-bottom:8px}.nh-content-field label{display:block;font-size:9.5px;font-weight:700;color:#66777b;margin-bottom:4px}
      .nh-content-field input,.nh-content-field textarea,.nh-content-field select{width:100%;border:1px solid #dce6e4;border-radius:9px;padding:8px;font-size:10.5px;background:#fbfdfd}
      .nh-content-actions{display:flex;gap:7px;margin-top:9px}.nh-content-primary,.nh-content-secondary{border:0;border-radius:9px;padding:8px 11px;font-size:10px;font-weight:800;cursor:pointer}
      .nh-content-primary{background:#247f76;color:#fff;flex:1}.nh-content-secondary{background:#edf2f1;color:#59696d}
      @media(max-width:420px){.nh-content-sheet{margin:0 9px 9px;padding:10px}.nh-content-field input,.nh-content-field textarea,.nh-content-field select{font-size:11px;padding:9px}}
    `;
    document.head.appendChild(style);
}

function append(panel, role, text, confirmation=false, widget=null){
    const stream=panel.querySelector('[data-nh-flow]');
    if(!stream) return;
    stream.querySelector('.nh-flow-empty')?.remove();
    const row=document.createElement('div');
    row.className=`nh-msg ${role==='user'?'user':'bot'}`;
    row.innerHTML=esc(text).replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/\n/g,'<br>');
    if(confirmation && widget){
        const controls=document.createElement('div');
        controls.className='nh-confirm';
        controls.innerHTML='<button class="yes">✓ تأیید</button><button class="no">✕ لغو</button>';
        controls.querySelector('.yes').onclick=()=>{controls.remove();send(panel,widget,'تأیید','تأیید عملیات')};
        controls.querySelector('.no').onclick=()=>{controls.remove();send(panel,widget,'لغو','لغو عملیات')};
        row.appendChild(controls);
    }
    stream.appendChild(row);
    stream.scrollTop=stream.scrollHeight;
}

function awaitingConfirmation(text){
    const value=String(text||'');
    return (value.includes('تأیید')||value.includes('تایید')) && (value.includes('لغو')||value.includes('انصراف'));
}

async function send(panel, widget, message, label){
    if(panel.dataset.contentBusy==='1') return;
    panel.dataset.contentBusy='1';
    append(panel,'user',label||message);
    try{
        const token=localStorage.getItem('api_token')||'';
        const response=await fetch('/api/najm-hoda/chat',{
            method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf(),...(token?{Authorization:`Bearer ${token}`}:{})},
            body:JSON.stringify({message,agent:document.getElementById('najm-hoda-agent')?.value||'steward',conversation_id:widget.conversationId,context:{page:pageContext(widget)}})
        });
        const data=await response.json().catch(()=>({}));
        if(!response.ok||!data.success){append(panel,'bot',data.message||'اجرای این خدمت با خطا مواجه شد.');return;}
        widget.conversationId=Number(data.conversation_id)||widget.conversationId;
        if(widget.conversationId)localStorage.setItem('najm-hoda-active-conversation-id',String(widget.conversationId));
        append(panel,'bot',data.message||'انجام شد.',awaitingConfirmation(data.message),widget);
    }catch(error){append(panel,'bot','ارتباط با نجم هدا برقرار نشد. دوباره تلاش کنید.');console.error(error)}finally{panel.dataset.contentBusy='0'}
}

function card(key, icon, title, desc){
    const button=document.createElement('button');
    button.type='button'; button.className='nh-mgmt-card'; button.dataset.key=key;
    button.innerHTML=`<span class="nh-mgmt-icon"><i class="fas ${icon}"></i></span><div class="nh-mgmt-card-title">${esc(title)}</div><div class="nh-mgmt-card-desc">${esc(desc)}</div>`;
    return button;
}

function openSheet(panel,name){
    panel.querySelectorAll('.nh-content-sheet,.nh-sheet').forEach(sheet=>sheet.classList.remove('open'));
    panel.querySelector(`[data-content-sheet="${name}"]`)?.classList.add('open');
}

function addSheets(panel,widget){
    if(panel.querySelector('[data-content-sheet="post"]')) return;
    const anchor=panel.querySelector('.nh-flow');
    const wrap=document.createElement('div');
    wrap.innerHTML=`
      <section class="nh-content-sheet" data-content-sheet="post"><h4><i class="far fa-edit"></i> ساخت پست</h4><div class="nh-content-field"><label>عنوان</label><input name="nh_post_title" maxlength="160"></div><div class="nh-content-field"><label>متن پست</label><textarea name="nh_post_text" rows="4" maxlength="5000"></textarea></div><div class="nh-content-actions"><button class="nh-content-primary" data-submit-post>آماده‌سازی و بررسی</button><button class="nh-content-secondary" data-close-content>بستن</button></div></section>
      <section class="nh-content-sheet" data-content-sheet="poll"><h4><i class="fas fa-chart-simple"></i> ساخت نظرسنجی</h4><div class="nh-content-field"><label>سؤال</label><input name="nh_poll_question" maxlength="300"></div><div class="nh-content-field"><label>گزینه‌ها</label><textarea name="nh_poll_options" rows="3" placeholder="هر گزینه در یک خط یا با ویرگول جدا شود"></textarea></div><div class="nh-content-field"><label>مهلت (روز)</label><input name="nh_poll_days" type="number" min="1" max="90" value="3"></div><div class="nh-content-actions"><button class="nh-content-primary" data-submit-poll>آماده‌سازی و بررسی</button><button class="nh-content-secondary" data-close-content>بستن</button></div></section>
      <section class="nh-content-sheet" data-content-sheet="comment"><h4><i class="far fa-comment-dots"></i> ثبت نظر روی پست</h4><div class="nh-content-field"><label>هدف</label><input name="nh_comment_target" placeholder="مثلاً آخرین پست، پست من یا پست #123"></div><div class="nh-content-field"><label>متن نظر</label><textarea name="nh_comment_text" rows="3"></textarea></div><div class="nh-content-actions"><button class="nh-content-primary" data-submit-comment>آماده‌سازی و بررسی</button><button class="nh-content-secondary" data-close-content>بستن</button></div></section>
      <section class="nh-content-sheet" data-content-sheet="reaction"><h4><i class="far fa-thumbs-up"></i> ثبت واکنش</h4><div class="nh-content-field"><label>هدف</label><input name="nh_reaction_target" placeholder="مثلاً آخرین پیام، پیام #45، پست #12 یا نظر #7"></div><div class="nh-content-field"><label>واکنش</label><select name="nh_reaction_type"><option value="پسند">پسند</option><option value="نپسند">نپسند</option><option value="قلب">قلب</option><option value="حمایت">حمایت</option></select></div><div class="nh-content-actions"><button class="nh-content-primary" data-submit-reaction>آماده‌سازی و بررسی</button><button class="nh-content-secondary" data-close-content>بستن</button></div></section>`;
    [...wrap.children].forEach(node=>anchor?.parentNode?.insertBefore(node,anchor));
    panel.querySelectorAll('[data-close-content]').forEach(btn=>btn.onclick=()=>btn.closest('.nh-content-sheet')?.classList.remove('open'));
    const value=name=>panel.querySelector(`[name="${name}"]`)?.value?.trim()||'';
    panel.querySelector('[data-submit-post]').onclick=()=>{const text=value('nh_post_text');if(!text)return append(panel,'bot','متن پست را وارد کنید.');const title=value('nh_post_title');openSheet(panel,'');send(panel,widget,`یک پست بساز | عنوان: ${title||text.slice(0,70)} | متن: ${text}`,'ساخت پست')};
    panel.querySelector('[data-submit-poll]').onclick=()=>{const q=value('nh_poll_question'),opts=value('nh_poll_options'),days=value('nh_poll_days')||'3';if(!q||!opts)return append(panel,'bot','سؤال و حداقل دو گزینه را وارد کنید.');openSheet(panel,'');send(panel,widget,`یک نظرسنجی بساز | سوال: ${q} | گزینه‌ها: ${opts} | مهلت: ${days}`,'ساخت نظرسنجی')};
    panel.querySelector('[data-submit-comment]').onclick=()=>{const target=value('nh_comment_target'),text=value('nh_comment_text');if(!target||!text)return append(panel,'bot','هدف و متن نظر را وارد کنید.');openSheet(panel,'');send(panel,widget,`روی ${target} نظر ثبت کن | متن: ${text}`,'ثبت نظر')};
    panel.querySelector('[data-submit-reaction]').onclick=()=>{const target=value('nh_reaction_target'),type=value('nh_reaction_type');if(!target)return append(panel,'bot','هدف واکنش را مشخص کنید.');openSheet(panel,'');send(panel,widget,`به ${target} واکنش ${type} ثبت کن`,'ثبت واکنش')};
}

function openMessageComposer(){
    document.getElementById('nh-management-header-button')?.click();
    const composer=document.getElementById('message_editor');
    composer?.scrollIntoView({behavior:'smooth',block:'center'});
    window.setTimeout(()=>composer?.focus(),250);
}

function openElection(){
    const overlay=document.getElementById('electionVotingOverlay');
    if(overlay){overlay.style.display='flex';overlay.setAttribute('aria-hidden','false');return;}
    window.GroupChatFeedback?.toast?.('در حال حاضر انتخابات فعالی برای این گروه در دسترس نیست.',{type:'info'});
}

function install(){
    if(!canManage()) return false;
    const panel=document.getElementById(PANEL_ID), widget=window.NajmHoda;
    const host=panel?.querySelector('[data-nh-sections]');
    if(!panel||!host||!widget||panel.dataset.contentToolsInstalled==='1') return false;
    panel.dataset.contentToolsInstalled='1'; addStyles(); addSheets(panel,widget);
    const section=document.createElement('section');section.className='nh-mgmt-section';section.innerHTML='<div class="nh-mgmt-section-head">محتوا، تعامل و حکمرانی</div><div class="nh-mgmt-grid" data-content-tools-grid></div>';
    const grid=section.querySelector('[data-content-tools-grid]');
    const items=[
      ['post','fa-pen-to-square','ساخت پست','پست را با فرم هدایت‌شده آماده و پس از preview منتشر کنید.'],
      ['poll','fa-chart-simple','ساخت نظرسنجی','سؤال، گزینه‌ها و مهلت را مشخص کنید.'],
      ['comment','fa-comment-dots','ثبت نظر','روی پست مشخص یا آخرین پست نظر ثبت کنید.'],
      ['reaction','fa-thumbs-up','ثبت واکنش','روی پیام، پست یا نظر واکنش ثبت کنید.'],
      ['message','fa-paper-plane','ارسال پیام گروه','به composer اصلی گروه بروید و پیام عادی ارسال کنید.'],
      ['election','fa-vote-yea','انتخابات گروه','فرم و وضعیت انتخابات سیستمی فعال گروه را باز کنید.']
    ];
    items.forEach(([key,icon,title,desc])=>{const b=card(key,icon,title,desc);b.onclick=()=>key==='message'?openMessageComposer():key==='election'?openElection():openSheet(panel,key);grid.appendChild(b)});
    host.appendChild(section);
    return true;
}

let attempts=0;const timer=window.setInterval(()=>{attempts+=1;if(install()||attempts>140)window.clearInterval(timer)},75);
if(document.readyState!=='loading')install();
