<div class="text-center mt-3 mb-3" style="font-weight:bold">อัปเดตล่าสุด: <?=date('d/m/Y H:i')?> น.</div>


<!-- Loading Overlay -->
<div id="loading-overlay">
  <div class="spinner"></div>
  <div style="font-weight: 500; color: var(--primary-color);">กำลังโหลดข้อมูล...</div>
</div>

<script>
(function(){
  // 1. Remark Logic (เดิม)
  const boxes = document.querySelectorAll('.box');
  boxes.forEach(box => {
    const ym = box.getAttribute('data-ym');
    box.querySelectorAll('tbody tr').forEach(tr => {
      const doc = tr.getAttribute('data-doc');
      const cell = tr.querySelector('.remark');
      if (!cell || !ym || !doc) return; 
      
      const key = `remark:${ym}:${doc}`;
      const val = localStorage.getItem(key);
      if (val !== null) cell.textContent = val;

      cell.addEventListener('input', () => {
        localStorage.setItem(key, cell.textContent.trim());
      });
    });
  });

  // 2. Loading State
  const forms = document.querySelectorAll('form');
  const overlay = document.getElementById('loading-overlay');
  forms.forEach(f => {
    f.addEventListener('submit', () => {
      if(overlay) overlay.style.display = 'flex';
    });
  });

  // 3. Auto Add "Copy" Button to Tables
  const tables = document.querySelectorAll('table.table');
  tables.forEach((table, idx) => {
    // สร้างปุ่ม Copy
    const btn = document.createElement('button');
    btn.className = 'btn-copy';
    btn.innerHTML = '<i class="fas fa-copy"></i> Copy Table';
    btn.type = 'button';
    
    // สร้าง Wrapper เพื่อวางปุ่ม
    const wrapper = document.createElement('div');
    wrapper.style.textAlign = 'right';
    wrapper.style.marginBottom = '5px';
    wrapper.appendChild(btn);
    
    // แทรกก่อนตาราง
    table.parentNode.insertBefore(wrapper, table);

    // Event Click
    btn.addEventListener('click', () => {
      const range = document.createRange();
      range.selectNode(table);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
      
      try {
        document.execCommand('copy');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.color = 'green';
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.style.color = '';
        }, 2000);
      } catch (err) {
        alert('Failed to copy');
      }
      window.getSelection().removeAllRanges();
    });
  });

})();
</script>

</body>
</html>