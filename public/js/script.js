document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    await UI.header();
    await UI.categories();
    const form=document.getElementById('filters');
    form.elements.bounty.checked=new URLSearchParams(location.search).get('bounty')==='true';
    let items=[];
    function render(page=1) {
        document.getElementById('result-count').textContent=items.length+' matching reports';
        const visible=UI.paginate(items,page,render);
        document.getElementById('items').innerHTML=visible.length ? visible.map(item=>
            '<article class="item-card">'+UI.image(item.imageUrl)+'<div class="card-body">'+UI.badge(item.status)+'<h3>'+UI.escape(item.title)+'</h3><p class="muted">'+UI.escape(item.location)+' | '+UI.escape(item.dateReported)+'</p><p>'+UI.escape(item.description.slice(0,120))+'</p><p><strong>'+UI.money(item.rewardAmount)+'</strong> reward</p><a href="item.html?id='+encodeURIComponent(item.id)+'">View details</a></div></article>'
        ).join('') : '<p class="empty">No matching items. Try another search or report an item.</p>';
    }
    async function load() {
        UI.message('Loading reports...');
        items=await UI.api('items/list.php?'+UI.params(form));
        UI.message();render();
    }
    form.addEventListener('submit',event=>{event.preventDefault();UI.run(form.querySelector('button'),load);});
    await load();
}));
