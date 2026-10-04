document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    if(!await Admin.init()) return;
    async function load() {
        const [stats,claims]=await Promise.all([UI.api('stats.php'),UI.api('claims/list.php')]);
        Admin.stats('stats',stats);
        const pending=claims.filter(c=>c.status==='Pending');
        document.getElementById('pending-claims').innerHTML=pending.length?pending.map(claim=>'<article class="claim-card"><h3><a href="item.html?id='+encodeURIComponent(claim.itemId)+'">'+UI.escape(claim.itemTitle)+'</a></h3><p><strong>'+UI.escape(claim.finderName)+'</strong> | '+UI.escape(claim.finderContact)+'</p><p class="description">'+UI.escape(claim.note)+'</p><div class="actions"><button data-id="'+UI.escape(claim.id)+'" data-decision="Approved">Approve claim</button><button data-id="'+UI.escape(claim.id)+'" data-decision="Rejected" class="danger">Reject claim</button></div></article>').join(''):'<p>No claims are awaiting review.</p>';
    }
    document.getElementById('pending-claims').addEventListener('click',event=>{
        const button=event.target.closest('[data-decision]');
        if(!button || !confirm(button.dataset.decision==='Approved'?'Approve this claim and resolve the item? Other pending claims will be rejected.':'Reject this claim?')) return;
        UI.run(button,async()=>{
            await UI.write('claims/review.php?id='+encodeURIComponent(button.dataset.id),'POST',{status:button.dataset.decision});
            await load();UI.message('Claim review saved.','success');
        });
    });
    await load();
}));
