(() => {
    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('id-ID')
        .trim();

    const style = document.createElement('style');
    style.textContent = `
        .table-search {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 12px;
            flex-wrap: wrap;
        }
        .table-search input {
            flex: 1 1 240px;
            min-width: 180px;
            max-width: 420px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }
        .table-search-count {
            color: #64748b;
            font-size: 13px;
        }
        .table-search-empty {
            color: #64748b;
            text-align: center;
        }
    `;
    document.head.append(style);

    document.querySelectorAll('table').forEach((table) => {
        const body = table.tBodies[0];
        const header = table.tHead?.rows[0];

        if (!body || !header || (table.id === 'tabelSiswa' && document.getElementById('cari'))) {
            return;
        }

        const rows = Array.from(body.rows).filter((row) =>
            row.cells.length > 0 && !row.querySelector('td[colspan]')
        );
        if (rows.length === 0) {
            return;
        }

        const excludedColumns = new Set();
        Array.from(header.cells).forEach((cell, index) => {
            const title = normalize(cell.textContent).replace(/[.:\s]/g, '');
            if (title === 'aksi' || title === 'no' || title === 'nomor') {
                excludedColumns.add(index);
            }
        });

        const section = table.closest('section, .panel, .card, .section');
        const heading = section?.querySelector('h2, h3, h4')?.textContent.trim();
        const label = heading ? ` pada ${heading}` : '';
        const toolbar = document.createElement('div');
        toolbar.className = 'table-search';

        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = `Cari data${label}...`;
        input.setAttribute('aria-label', `Cari data${label}`);
        input.autocomplete = 'off';

        const count = document.createElement('span');
        count.className = 'table-search-count';
        count.setAttribute('aria-live', 'polite');

        toolbar.append(input, count);
        table.parentElement.insertBefore(toolbar, table);

        const emptyRow = body.insertRow();
        emptyRow.style.display = 'none';
        const emptyCell = emptyRow.insertCell();
        emptyCell.colSpan = header.cells.length;
        emptyCell.className = 'table-search-empty';
        emptyCell.textContent = 'Data yang dicari tidak ditemukan.';

        const update = () => {
            const query = normalize(input.value);
            let visible = 0;

            rows.forEach((row) => {
                const searchableText = Array.from(row.cells)
                    .filter((_, index) => !excludedColumns.has(index))
                    .map((cell) => cell.textContent)
                    .join(' ');
                const matches = normalize(searchableText).includes(query);
                row.style.display = matches ? '' : 'none';
                if (matches) {
                    visible += 1;
                }
            });

            emptyRow.style.display = visible === 0 ? 'table-row' : 'none';
            count.textContent = query
                ? `Menampilkan ${visible} dari ${rows.length} data`
                : `Total ${rows.length} data`;
        };

        input.addEventListener('input', update);
        update();
    });
})();
