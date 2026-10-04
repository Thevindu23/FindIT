document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    const user=await UI.session(true);
    if(!user) return;
    await UI.categories();
    const form=document.getElementById('lostItemForm');
    const key=crypto.randomUUID();
    form.hidden=false;
    form.elements.dateReported.max=UI.today();
    form.elements.dateReported.value=UI.today();
    form.elements.reporterContact.value=user.email;
    UI.setupImage();
    form.addEventListener('submit',event=>{
        event.preventDefault();
        UI.run(form.querySelector('[type=submit]'),async()=>{
            const data=Object.fromEntries(new FormData(form));
            data.requestKey=key;data.imageData=await UI.readImage(document.getElementById('imageUpload'));
            const saved=await UI.write('items/create.php','POST',data);
            location.href='item.html?id='+encodeURIComponent(saved.id)+'&created=true';
        });
    });
}));
