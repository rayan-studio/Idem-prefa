(() => {
    let active = null;
    const day = 86400000;
    const utcDate = value => new Date(value + 'T00:00:00Z');
    const addDays = (value, amount) => new Date(value.getTime() + amount * day);
    const formatDate = value => new Intl.DateTimeFormat('fr-FR', { timeZone: 'UTC', day: '2-digit', month: '2-digit', year: 'numeric' }).format(value);

    function mount(root = document.querySelector('.planning-page')) {
        if (active?.root === root) return;
        if (active) {
            active.observer?.disconnect();
            active.chart.destroy();
            active = null;
        }
        const host = root?.querySelector('.planning-timeline');
        if (!host || !window.vis?.Timeline) return;
        const rows = JSON.parse(host.querySelector('.planning-timeline-data').textContent);
        const first = utcDate(host.dataset.timelineStart);
        const days = Number(host.dataset.timelineDays);
        const last = addDays(first, days);

        // Une ligne par affaire ; la couleur de la barre porte le demandeur.
        const groupsMap = new Map();
        rows.forEach(row => {
            groupsMap.set(String(row.id), { id: String(row.id), name: row.nom_affaire || row.name, requests: [row] });
        });

        const groups = Array.from(groupsMap.values()).map((group, index) => {
            const row = group.requests[0];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'planning-timeline-label';
            button.dataset.groupId = group.id;
            button.dataset.selectAffaire = row.id;
            button.setAttribute('aria-pressed', 'false');
            button.title = `${row.reference_demande} · ${group.name} · ${row.creator_name}`;

            const nameEl = document.createElement('span');
            nameEl.className = 'planning-timeline-name';
            nameEl.textContent = group.name;

            const badgeEl = document.createElement('span');
            badgeEl.className = 'planning-timeline-number';
            badgeEl.textContent = row.reference_demande;

            button.append(badgeEl, nameEl);
            return { id: group.id, content: button, order: index };
        });

        const items = rows.map(row => {
            const content = document.createElement('button');
            content.type = 'button';
            content.className = 'planning-timeline-task';
            content.dataset.selectAffaire = row.id;

            // La barre reste nue : l'affaire est déjà nommée à gauche et la couleur renvoie
            // à la légende. Le demandeur est porté par l'infobulle et le nom accessible.
            const resume = `Demande ${row.reference_demande}${row.nom_affaire ? ' · ' + row.nom_affaire : ''} · ${row.creator_name} · ${formatDate(utcDate(row.start))} au ${formatDate(utcDate(row.end))}${row.urgent ? ' · Urgente' : ''}`;
            content.setAttribute('aria-label', resume);
            content.title = resume;
            return {
                id: row.id,
                group: String(row.id),
                type: 'range',
                content,
                start: utcDate(row.start),
                end: addDays(utcDate(row.end), 1),
                className: 'planning-person-' + (row.creator_slot ?? 0) + (row.urgent ? ' planning-timeline-urgent' : ''),
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

        // Le contenu des groupes (boutons) n'est mesurable qu'une fois la mise en page faite :
        // sans ce redessin différé la timeline reste sous-dimensionnée pendant une seconde.
        requestAnimationFrame(() => chart.redraw());

        // autoResize compare largeur ET hauteur du conteneur toutes les secondes et redessine
        // dès que l'une bouge. Or la hauteur est dictée par la timeline elle-même et retombe
        // à 2 px près d'un redessin à l'autre : le sondage se relançait indéfiniment et toute
        // la page tremblait. On coupe le sondage périodique et on ne réagit qu'à la largeur
        // (fenêtre : l'écouteur posé par vis ; barre latérale : l'observer ci-dessous).
        if (chart.watchTimer) {
            clearInterval(chart.watchTimer);
            chart.watchTimer = undefined;
        }
        let largeur = host.offsetWidth;
        const observer = new ResizeObserver(() => {
            if (host.offsetWidth === largeur) return;
            largeur = host.offsetWidth;
            chart._onResize?.();
        });
        observer.observe(host);

        active = { root, host, chart, observer, ids: new Set(rows.map(row => row.id)), groupsMap };
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
