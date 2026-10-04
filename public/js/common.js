/* Shared browser helpers. Application records and sessions live on the server. */
window.UI = {
    user: null,
    async api(path, options = {}) {
        let response;
        try {
            response = await fetch('api/' + path, {
                ...options, credentials: 'same-origin',
                headers: {'Content-Type':'application/json', ...options.headers},
                signal: AbortSignal.timeout(15000)
            });
        } catch {
            throw new Error('Cannot reach the server. Your changes were not confirmed. Check the connection and try again.');
        }
        let data;
        try { data = await response.json(); } catch { throw new Error('The server returned an unexpected response. Please retry.'); }
        if (!response.ok) {
            const error = new Error(data.error || 'Request failed.');
            error.status = response.status;
            throw error;
        }
        return data;
    },
    write(path, method, body = {}) { return this.api(path, {method, body:JSON.stringify(body)}); },
    escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); },
    money(value) { return new Intl.NumberFormat('en-LK',{style:'currency',currency:'LKR'}).format(value); },
    message(value = '', type = '', id = 'message') {
        const node=document.getElementById(id);
        if(node) {node.textContent=value;node.className='message '+type;}
    },
    async run(button, fn, messageId='message') {
        if(button?.disabled) return;
        if(button) button.disabled=true;
        this.message('','',messageId);
        try { await fn(); } catch(error) {this.message(error.message,'error',messageId);}
        finally {if(button) button.disabled=false;}
    },
    async session(required=false, admin=false) {
        const {user}=await this.api('auth/session.php');
        this.user=user;
        if(required && !user) {
            location.href='adminlogin.html?next='+encodeURIComponent(location.pathname.split('/').pop()+location.search);
            return null;
        }
        if(admin && user?.role !== 'admin') throw new Error('Administrator access is required. Return to the public website.');
        return user;
    },
    async header() {
        const user=await this.session();
        const container=document.getElementById('auth-buttons');
        if(!container) return;
        container.innerHTML=user
            ? '<span>'+this.escape(user.name)+'</span>'+(user.role==='admin'?'<a href="adminhome.html">Dashboard</a>':'')+'<button type="button" id="public-logout">Logout</button>'
            : '<a href="signup.html">Sign up</a><a href="adminlogin.html">Login</a>';
        document.getElementById('public-logout')?.addEventListener('click',event=>this.run(event.currentTarget,async()=>{
            await this.write('auth/logout.php','POST');location.href='index.html';
        }));
    },
    async categories() {
        const categories=await this.api('categories.php');
        document.querySelectorAll('[data-categories]').forEach(select=>{
            select.innerHTML=select.querySelector('option')?.outerHTML || '';
            categories.forEach(category=>select.add(new Option(category,category)));
        });
    },
    params(form) {
        const data=new FormData(form), params=new URLSearchParams();
        for(const [key,value] of data) params.set(key,value);
        if(data.has('bounty')) params.set('bounty','true');
        return params;
    },
    badge(status) {return '<span class="badge" data-status="'+this.escape(status)+'">'+this.escape(status)+'</span>';},
    image(url, className='') {
        if(typeof url !== 'string' || !(/^(https?:\/\/|data:image\/(?:png|jpeg|webp);base64,)/i.test(url))) return '<div class="image-placeholder">No image supplied</div>';
        return '<img class="'+className+'" src="'+this.escape(url)+'" alt="Reported item" loading="lazy">';
    },
    paginate(items, page, render) {
        const total=Math.max(1,Math.ceil(items.length/8)), current=Math.max(1,Math.min(page,total));
        const node=document.getElementById('pagination');
        node.replaceChildren();
        const previous=document.createElement('button'),next=document.createElement('button'),label=document.createElement('span');
        previous.textContent='Previous';next.textContent='Next';
        previous.disabled=current===1;next.disabled=current===total;
        label.textContent=items.length+' items | Page '+current+' of '+total;
        previous.addEventListener('click',()=>render(current-1));next.addEventListener('click',()=>render(current+1));
        node.append(previous,label,next);
        return items.slice((current-1)*8,current*8);
    },
    async readImage(input) {
        const file=input.files[0];
        if(!file) return undefined;
        if(!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size>1024*1024) throw new Error('Select a PNG, JPEG or WebP image no larger than 1 MB.');
        return new Promise((resolve,reject)=>{
            const reader=new FileReader();
            reader.onload=()=>resolve(reader.result);
            reader.onerror=()=>reject(new Error('Could not read the selected image.'));
            reader.readAsDataURL(file);
        });
    },
    setupImage(messageId='message') {
        const input=document.getElementById('imageUpload'), preview=document.getElementById('image-preview');
        input.addEventListener('change',()=>this.run(null,async()=>{
            preview.hidden=true;
            const data=await this.readImage(input);
            if(data){preview.src=data;preview.hidden=false;}
        },messageId));
    },
    today() {
        return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Colombo',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
    }
};
// Broken remote images should not leave unusable image controls.
document.addEventListener('error',event=>{
    if(event.target.tagName==='IMG') {
        const placeholder=document.createElement('div');
        placeholder.className='image-placeholder';placeholder.textContent='Image unavailable';
        event.target.replaceWith(placeholder);
    }
},true);
