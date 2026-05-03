document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.table-container table');
    // if (!table) return;
    if (table) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const headers = table.querySelectorAll('thead th');
        const searchInput = document.getElementById('tableSearch');
        const toggleSwitch = document.getElementById('compactViewToggle');
    
        const compactCols = [
            'EER_LOTTO_REC', 'EER_DATA_INCAR', 'EER_N_PRATICA',
            'EER_SCAD_INCA', 'EER_NOME_DEBIT', 'EER_PRATICA_CMP',
            'EER_DES_PRODOTTO_CMP', 'EER_ESITO', 'EER_NOTE_RIENTRO'
        ];
    
        function applyViewMode() {
            if (!toggleSwitch) return;
            const isCompact = toggleSwitch.checked;
    
            headers.forEach((th, index) => {
                if (index === 0) return;
    
                const colName = th.textContent.replace(/▲|▼/g, '').trim();
                const shouldHide = isCompact && !compactCols.includes(colName);
    
                th.classList.toggle('hidden-col', shouldHide);
    
                rows.forEach(row => {
                    if (row.children[index]) {
                        row.children[index].classList.toggle('hidden-col', shouldHide);
                    }
                });
            });
        }
    
        if (toggleSwitch) {
            toggleSwitch.addEventListener('change', applyViewMode);
            applyViewMode();
        }
    
        const targetCols = ['EER_N_PRATICA', 'EER_COD_FISC_CLI', 'EER_ESITO'];
        const searchIdx = Array.from(headers)
            .map((th, i) => targetCols.includes(th.textContent.trim()) ? i : -1)
            .filter(i => i !== -1);
    
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                rows.forEach(row => {
                    const cells = row.children;
                    const match = searchIdx.some(idx => cells[idx] && cells[idx].textContent.toLowerCase().includes(term));
                    row.style.display = match ? '' : 'none';
                });
            });
        }
    
        let currentSortCol = -1;
        let isAsc = true;
    
        headers.forEach((header, index) => {
            if (index === 0) return;
    
            header.style.cursor = 'pointer';
            header.title = 'Clicca per ordinare';
    
            header.addEventListener('click', () => {
                isAsc = currentSortCol === index ? !isAsc : true;
                currentSortCol = index;
    
                headers.forEach(th => {
                    const icon = th.querySelector('.sort-icon');
                    if (icon) icon.remove();
                });
    
                const icon = document.createElement('i');
                icon.className = `fa-solid sort-icon fa-sort-${isAsc ? 'up' : 'down'}`;
                icon.style.marginLeft = '8px';
                header.appendChild(icon);
    
                const sortedRows = rows.filter(r => r.style.display !== 'none').sort((a, b) => {
                    const aText = a.children[index].textContent.trim();
                    const bText = b.children[index].textContent.trim();
    
                    const aNum = parseFloat(aText);
                    const bNum = parseFloat(bText);
    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? aNum - bNum : bNum - aNum;
                    }
                    return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                });
    
                const hiddenRows = rows.filter(r => r.style.display === 'none');
                tbody.append(...sortedRows, ...hiddenRows);
            });
        });
    }
    const btnOpenManual = document.getElementById('btnOpenManual');
    const btnCloseManual = document.getElementById('btnCloseManual');
    const manualModal = document.getElementById('manualModal');
    console.log(btnOpenManual, manualModal, btnCloseManual);
    if (btnOpenManual && manualModal && btnCloseManual) {
        const closeModal = () => manualModal.classList.add('hidden');
        const openModal = () => manualModal.classList.remove('hidden');

        btnOpenManual.addEventListener('click', openModal);
        btnCloseManual.addEventListener('click', closeModal);

        manualModal.addEventListener('click', (e) => {
            if (e.target === manualModal) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !manualModal.classList.contains('hidden')) {
                closeModal();
            }
        });
    }
});