window.Admin={
    async init() {
        const user=await UI.session(true,true);
        if(!user) return false;
        document.getElementById('admin-name').textContent=user.name+' | '+user.email;
        document.getElementById('admin-content').hidden=false;
        document.getElementById('logout').addEventListener('click',event=>UI.run(event.currentTarget,async()=>{
            await UI.write('auth/logout.php','POST');location.href='adminlogin.html';
        }));
        return true;
    },
    stats(target,stats,rewardsOnly=false) {
        const fields=rewardsOnly?[['Unpaid rewards',UI.money(stats.unpaidRewards)],['Paid (simulated)',UI.money(stats.simulatedPaid)]]:[['Reports',stats.totalItems],['Active reports',stats.activeItems],['Resolved',stats.resolvedItems],['Pending claims',stats.pendingClaims]];
        document.getElementById(target).innerHTML=fields.map(([label,value])=>'<article class="stat-card"><span>'+label+'</span><strong>'+UI.escape(value)+'</strong></article>').join('');
    }
};
