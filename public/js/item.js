document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    await UI.header();
    const id=new URLSearchParams(location.search).get('id');
    if(!id) throw new Error('No item was selected. Return to the item list.');
    const form=document.getElementById('claim-form');
    async function load() {
        const item=await UI.api('items/details.php?id='+encodeURIComponent(id));
        document.title=item.title+' | FindIT';
        document.getElementById('item-detail').innerHTML='<div class="detail-layout">'+UI.image(item.imageUrl,'detail-image')+'<div>'+UI.badge(item.status)+'<h1>'+UI.escape(item.title)+'</h1><p>'+UI.escape(item.category)+' | '+UI.escape(item.dateReported)+'</p><p><strong>Location:</strong> '+UI.escape(item.location)+'</p><p><strong>Reward:</strong> '+UI.money(item.rewardAmount)+'</p><p><strong>Reported by:</strong> '+UI.escape(item.reporterName)+'</p><p><strong>Contact:</strong> '+(UI.user?UI.escape(item.reporterContact):'<a href="adminlogin.html?next='+encodeURIComponent('item.html?id='+id)+'">Login to view contact details</a>')+'</p><p class="description">'+UI.escape(item.description)+'</p></div></div>';
        const section=document.getElementById('claims');
        section.hidden=!item.claims.length;
        section.innerHTML='<h2>'+ (item.isOwner || UI.user?.role==='admin'?'Claims for this report':'Your claims')+'</h2>'+item.claims.map(claim=>'<article class="claim-card"><strong>'+UI.escape(claim.finderName)+'</strong> | '+UI.badge(claim.status)+'<p>'+UI.escape(claim.finderContact)+'</p><p class="description">'+UI.escape(claim.note)+'</p></article>').join('');
        document.getElementById('claim-section').hidden=!UI.user || item.isOwner || item.status==='Resolved' || item.claims.some(c=>c.status==='Pending');
        if(UI.user) form.elements.finderContact.value=UI.user.email;
    }
    form.addEventListener('submit',event=>{
        event.preventDefault();
        UI.run(form.querySelector('button'),async()=>{
            await UI.write('claims/create.php?id='+encodeURIComponent(id),'POST',Object.fromEntries(new FormData(form)));
            form.reset();await load();UI.message('Claim saved. An administrator can now review it.','success');
        });
    });
    await load();
    if(new URLSearchParams(location.search).get('created')==='true') UI.message('Your report was saved successfully.','success');
}));
