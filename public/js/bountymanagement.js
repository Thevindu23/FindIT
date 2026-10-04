document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    if(!await Admin.init())return;
    await UI.categories();
    const form=document.getElementById('filters');form.elements.bounty.checked=true;form.elements.bounty.disabled=true;
    let items=[];
    function render(page=1){
        const rows=UI.paginate(items,page,render);
        document.getElementById('bounty-rows').innerHTML=rows.length?rows.map(item=>'<tr><td><a href="item.html?id='+encodeURIComponent(item.id)+'">'+UI.escape(item.title)+'</a></td><td>'+UI.money(item.rewardAmount)+'</td><td>'+UI.badge(item.status)+'</td><td>'+UI.escape(item.rewardStatus)+'</td><td>'+(item.status==='Resolved'&&item.rewardStatus==='Unpaid'?'<button class="primary" data-id="'+UI.escape(item.id)+'">Record simulated payout</button>':'<a href="adminhome.html">Review claims</a>')+'</td></tr>').join(''):'<tr><td colspan="5">No matching rewards.</td></tr>';
    }
    async function load(){
        const params=UI.params(form);params.set('bounty','true');
        const [rows,stats]=await Promise.all([UI.api('items/list.php?'+params),UI.api('stats.php')]);
        items=rows;Admin.stats('reward-stats',stats,true);render();
    }
    form.addEventListener('submit',event=>{event.preventDefault();UI.run(form.querySelector('button'),load);});
    document.getElementById('bounty-rows').addEventListener('click',event=>{
        const button=event.target.closest('[data-id]');
        if(!button||!confirm('Record this reward as paid in the simulation? No money will be transferred.'))return;
        UI.run(button,async()=>{
            await UI.write('items/reward.php?id='+encodeURIComponent(button.dataset.id),'POST');
            await load();UI.message('Simulated payout recorded. No money was transferred.','success');
        });
    });
    await load();
}));
