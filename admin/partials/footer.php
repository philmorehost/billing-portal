  </main></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.alert').forEach(a=>setTimeout(()=>a&&a.remove(),5000));
const gs=document.getElementById('gsearch');
if(gs)gs.addEventListener('keydown',e=>{if(e.key==='Enter'&&gs.value.trim())window.location.href='<?=BASE_URL?>/admin/search.php?q='+encodeURIComponent(gs.value.trim())});
document.addEventListener('click',e=>{const sb=document.getElementById('sidebar');if(sb&&sb.classList.contains('open')&&!sb.contains(e.target))sb.classList.remove('open')});
</script></body></html>
