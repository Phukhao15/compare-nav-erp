<div class="text-center mt-3 mb-3" style="font-weight:bold">อัปเดตล่าสุด: <?=date('d/m/Y H:i')?> น.</div>

</div> <script>
(function(){
  const boxes = document.querySelectorAll('.box');
  boxes.forEach(box => {
    const ym = box.getAttribute('data-ym');
    box.querySelectorAll('tbody tr').forEach(tr => {
      const doc = tr.getAttribute('data-doc');
      const cell = tr.querySelector('.remark');
      if (!cell || !ym || !doc) return; // เพิ่มการป้องกัน
      
      const key = `remark:${ym}:${doc}`;
      const val = localStorage.getItem(key);
      if (val !== null) cell.textContent = val;

      cell.addEventListener('input', () => {
        localStorage.setItem(key, cell.textContent.trim());
      });
    });
  });
})();
</script>

</body>
</html>