document.addEventListener('DOMContentLoaded',()=>{
    const form=document.getElementById('login-form'), register=form.dataset.register==='true';
    document.getElementById('show-password').addEventListener('click',event=>{
        const input=form.elements.password,shown=input.type==='password';
        input.type=shown?'text':'password';event.currentTarget.textContent=shown?'Hide password':'Show password';event.currentTarget.setAttribute('aria-pressed',String(shown));
    });
    form.addEventListener('submit',event=>{
        event.preventDefault();
        UI.run(form.querySelector('[type=submit]'),async()=>{
            const {user}=await UI.write('auth/'+(register?'register.php':'login.php'),'POST',Object.fromEntries(new FormData(form)));
            const next=new URLSearchParams(location.search).get('next');
            const safe=next && /^(?:report|item|adminhome|itemmanagement|bountymanagement)\.html(?:\?|$)/.test(next);
            location.href=safe?next:user.role==='admin'?'adminhome.html':'index.html';
        });
    });
});
