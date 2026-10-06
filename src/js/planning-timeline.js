(() => {
    let active = null;
    const day = 86400000;
    const utcDate = value => new Date(value + 'T00:00:00Z');
    const addDays = (value, amount) => new Date(value.getTime() + amount * day);
    const formatDate = value => new Intl.DateTimeFormat('fr-FR', { timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric' }).format(value);

    function mount(root = document.querySelector('.planning-page')) {
        if (active?.root === root) return;
        if (active) {
            active.chart.destroy();
            active = null;
        }
        const host = root?.querySelector('.planning-timeline');
        if (!host || !window.vis?.Timeline) return;
        const rows = JSON.parse(host.querySelector('.planning-timeline-data').textContent);
        const first = utcDate(host.dataset.timelineStart);
        const days = Number(host.dataset.timelineDays);
        const last = addDays(first, days);

        // Group requests by creator / chargé d'affaires
        const groupsMap = new Map();
        rows.forEach(row => {
            const hasCreator = row.creator_id !== undefined && row.creator_id !== null && row.creator_id !== '';
            const groupId = hasCreator ? ('creator_' + row.creator_id) : String(row.id);
            const groupName = (hasCreator && row.creator_name) ? row.creator_name : row.name;
            if (!groupsMap.has(groupId)) {
                groupsMap.set(groupId, {
                    id: groupId,
                    name: groupName,
                    hasCreator: hasCreator,
                    requests: []
                });
            }
            groupsMap.get(groupId).requests.push(row);
        });

        const groups = Array.from(groupsMap.values()).map((group, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'planning-timeline-label';
            button.dataset.groupId = group.id;
            button.dataset.selectAffaire = group.requests[0]?.id || group.id;
            button.setAttribute('aria-pressed', 'false');
            button.title = `${group.name} (${group.requests.length} demande${group.requests.length > 1 ? 's' : ''})`;

            const nameEl = document.createElement('span');
            nameEl.className = 'planning-timeline-name';
            nameEl.textContent = group.name;

            const badgeEl = document.createElement('span');
            badgeEl.className = 'planning-timeline-number';
            if (!group.hasCreator && group.requests.length === 1) {
                badgeEl.textContent = `#${group.requests[0].id}`;
            } else {
                badgeEl.textContent = `${group.requests.length} aff.`;
            }

            button.append(badgeEl, nameEl);
            return { id: group.id, content: button, order: index };
        });

        const items = rows.map(row => {
            const hasCreator = row.creator_id !== undefined && row.creator_id !== null && row.creator_id !== '';
            const groupId = hasCreator ? ('creator_' + row.creator_id) : String(row.id);
            const content = document.createElement('button');
            content.type = 'button';
            content.className = 'planning-timeline-task';
            content.dataset.selectAffaire = row.id;

            const displayLabel = row.nom_affaire 
                ? `#${row.id} · ${row.nom_affaire}` 
                : (row.name && row.name !== row.creator_name ? `#${row.id} · ${row.name}` : `#${row.id}`);

            content.textContent = displayLabel;
            content.title = `Demande #${row.id}${row.nom_affaire ? ' · ' + row.nom_affaire : ''} · ${formatDate(utcDate(row.start))} au ${formatDate(utcDate(row.end))}${row.urgent ? ' · Urgente' : ''}`;
            return {
                id: row.id,
                group: groupId,
                type: 'range',
                content,
                start: utcDate(row.start),
                end: addDays(utcDate(row.end), 1),
                className: row.urgent ? 'planning-timeline-urgent' : '',
            };
        });

        host.hidden = false;
        const minPanDate = addDays(first, -365);
        const maxPanDate = addDays(last, 365);
        const maxZoomLimit = Math.max(days * 6, 180) * day;

        const canvasEl = host.querySelector('.planning-timeline-canvas');
        const chart = new vis.Timeline(canvasEl, items, groups, {
            start: first, end: last,
            min: minPanDate, max: maxPanDate,
            width: '100%', maxHeight: 460, autoResize: true,
            orientation: { axis: 'top', item: 'top' },
            groupOrder: 'order',
            stack: true,
            groupHeightMode: 'auto',
            margin: { axis: 10, item: { horizontal: 0, vertical: 8 } },
            editable: false, selectable: true, multiselect: false,
            moveable: true, zoomable: true,
            verticalScroll: true, horizontalScroll: false, zoomKey: 'ctrlKey',
            zoomMin: Math.min(days, 3) * day, zoomMax: maxZoomLimit,
            timeAxis: { scale: 'day', step: 1 }, showWeekScale: true,
            showCurrentTime: false,
            moment: value => vis.moment(value).utc(),
            locale: 'fr',
            format: { minorLabels: { day: 'D', weekday: 'D' }, majorLabels: { day: '[S]WW', weekday: '[S]WW' } },
        });

        // Enable Shift + mouse wheel to smoothly pan horizontally
        canvasEl.addEventListener('wheel', (e) => {
            if (e.shiftKey) {
                e.preventDefault();
                const curWin = chart.getWindow();
                const step = (e.deltaY || e.deltaX) > 0 ? day * 2 : -day * 2;
                chart.setWindow(new Date(curWin.start.getTime() + step), new Date(curWin.end.getTime() + step), { animation: false });
            }
        }, { passive: false });

        active = { root, host, chart, ids: new Set(rows.map(row => row.id)), groupsMap };
        // Use Paris's calendar date without treating planned dates as timestamps.
        const parisToday = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        chart.addCustomTime(addDays(utcDate(parisToday), 0.5), 'planning-today');
        chart.setCustomTimeTitle('Aujourd’hui', 'planning-today');
        const emptyNotice = host.querySelector('.planning-timeline-empty');
        if (emptyNotice) emptyNotice.hidden = rows.length > 0;
        host.querySelectorAll('[data-timeline-zoom]').forEach(button => {
            button.disabled = false;
            button.addEventListener('click', () => button.dataset.timelineZoom === 'in' ? chart.zoomIn(0.35, { animation: false }) : chart.zoomOut(0.35, { animation: false }));
        });
        host.querySelector('[data-timeline-reset]')?.addEventListener('click', () => chart.setWindow(first, last, { animation: false }));
        chart.on('click', event => {
            const itemId = Number(event.item);
            if (itemId && active?.ids.has(itemId)) {
                root.dispatchEvent(new CustomEvent('planning:select', { detail: { id: itemId }, bubbles: true }));
                return;
            }
            const grpId = event.group ? String(event.group) : null;
            if (grpId && active?.groupsMap?.has(grpId)) {
                const firstReqId = active.groupsMap.get(grpId).requests[0]?.id;
                if (firstReqId) {
                    root.dispatchEvent(new CustomEvent('planning:select', { detail: { id: firstReqId }, bubbles: true }));
                }
            }
        });
        chart.on('doubleClick', event => {
            const itemId = Number(event.item);
            if (itemId && active?.ids.has(itemId)) {
                root.dispatchEvent(new CustomEvent('planning:edit', { detail: { id: itemId }, bubbles: true }));
            }
        });
        root.querySelector('[data-gantt-fallback]').hidden = true;
    }

    window.PlanningTimeline = {
        mount,
        getWindow: () => active?.chart.getWindow(),
        has: id => active?.ids.has(Number(id)) || false,
        select(id) {
            const numId = Number(id);
            if (!active?.ids.has(numId)) return;
            active.chart.setSelection([numId]);
            active.host.querySelectorAll('.planning-timeline-label').forEach(button => {
                const grpId = button.dataset.groupId;
                const grp = active.groupsMap?.get(grpId);
                const isSelected = grp 
                    ? grp.requests.some(r => Number(r.id) === numId)
                    : (Number(button.dataset.selectAffaire) === numId);
                button.setAttribute('aria-pressed', String(isSelected));
            });
        },
    };
    // Planning pages are inserted and replaced through AJAX.
    const content = document.getElementById('content');
    if (content) new MutationObserver(() => mount()).observe(content, { childList: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => mount());
    else mount();
})();
