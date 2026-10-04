document.addEventListener('DOMContentLoaded',()=>UI.run(null,async()=>{
    if(!await Admin.init()) return;
    await UI.categories();
    const filters=document.getElementById('filters'),form=document.getElementById('edit-form'),dialog=document.getElementById('edit-dialog');
    let items=[],editing=null;
    UI.setupImage('edit-message');
    form.elements.dateReported.max=UI.today();
    function render(page=1) {
        const rows=UI.paginate(items,page,render);
        document.getElementById('item-rows').innerHTML=rows.length?rows.map(item=>'<tr><td><a href="item.html?id='+encodeURIComponent(item.id)+'">'+UI.escape(item.title)+'</a></td><td>'+UI.escape(item.category)+'</td><td>'+UI.escape(item.dateReported)+'</td><td>'+UI.badge(item.status)+'</td><td><div class="actions">'+(item.status!=='Resolved'?'<button data-action="edit" data-id="'+UI.escape(item.id)+'">Edit</button>':'')+(item.status==='Lost'||item.status==='Found'?'<button data-action="resolve" data-id="'+UI.escape(item.id)+'">Resolve</button>':'')+(item.rewardStatus!=='Paid (simulated)'?'<button class="danger" data-action="delete" data-id="'+UI.escape(item.id)+'">Remove</button>':'')+'</div></td></tr>').join(''):'<tr><td colspan="5">No matching reports.</td></tr>';
    }
    async function load(){items=await UI.api('items/list.php?'+UI.params(filters));render();}
    filters.addEventListener('submit',event=>{event.preventDefault();UI.run(filters.querySelector('button'),load);});
    document.getElementById('close-editor').addEventListener('click',()=>dialog.close());
    document.getElementById('item-rows').addEventListener('click',event=>{
        const button=event.target.closest('[data-action]');
        if(!button)return;
        const item=items.find(i=>i.id===button.dataset.id);
        if(button.dataset.action==='edit'){
            editing=item;form.reset();UI.message('','','edit-message');
            for(const key of ['title','category','location','description','reporterContact','dateReported','rewardAmount']) form.elements[key].value=item[key];
            form.elements.status.value=item.originalStatus;
            document.getElementById('image-preview').hidden=true;dialog.showModal();return;
        }
        if(!confirm(button.dataset.action==='delete'?'Remove this report and all its claims?':'Mark this item as returned and resolved?'))return;
        UI.run(button,async()=>{
            if(button.dataset.action==='delete')await UI.write('items/delete.php?id='+encodeURIComponent(item.id),'POST');
            else await UI.write('items/resolve.php?id='+encodeURIComponent(item.id),'POST');
            await load();UI.message('Item updated.','success');
        });
    });
    form.addEventListener('submit',event=>{
        event.preventDefault();
        UI.run(form.querySelector('[type=submit]'),async()=>{
            const data=Object.fromEntries(new FormData(form));
            data.imageData=await UI.readImage(document.getElementById('imageUpload'));
            await UI.write('items/update.php?id='+encodeURIComponent(editing.id),'POST',data);
            dialog.close();await load();UI.message('Report changes saved.','success');
        },'edit-message');
    });
    await load();
}));
