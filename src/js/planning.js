(() => {
    let loading;
    function root() { return document.querySelector('.planning-page'); }
    function requests() {
        const data = root()?.querySelector('#planning-data');
        return data ? JSON.parse(data.textContent) : [];
    }
    function date(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
        const parsed = new Date(value + 'T12:00:00Z');
        return Number.isNaN(parsed.getTime()) || iso(parsed) !== value ? null : parsed;
    }
    function iso(value) { return value.toISOString().slice(0, 10); }
    function addDays(value, count) { const copy = new Date(value); copy.setUTCDate(copy.getUTCDate() + count); return copy; }
    const frenchDate = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    function displayDate(value) { return date(value) ? frenchDate.format(date(value)) : 'Non renseignée'; }

    function adjustToWorkingDay(d) {
        let cur = new Date(d);
        while (cur.getUTCDay() === 0 || cur.getUTCDay() === 6) {
            cur = addDays(cur, 1);
        }
        return cur;
    }

    function getHoursForRequest(req) {
        if (!req) return 24;
        const currentRoot = root();
        const hoursPerInch = parseFloat(currentRoot?.dataset?.hoursPerInch || '24') || 24;
        const inches = parseInt(req.pouces_total_iso || 0, 10);
        if (inches > 0) {
            return inches * hoursPerInch;
        }
        const h = parseFloat(req.heures_chiffrees || 0);
        return h > 0 ? h : 24;
    }

    function computeEndDate(startDateStr, hours) {
        if (!startDateStr) return '';
        const h = Number(hours);
        const days = (!h || h <= 0) ? 1 : Math.max(1, Math.ceil(h / 24));
        let cur = date(startDateStr);
        if (!cur) return startDateStr;
        while (cur.getUTCDay() === 0 || cur.getUTCDay() === 6) {
            cur = addDays(cur, 1);
        }
        let remaining = days - 1;
        while (remaining > 0) {
            cur = addDays(cur, 1);
            if (cur.getUTCDay() !== 0 && cur.getUTCDay() !== 6) {
                remaining--;
            }
        }
        return iso(cur);
    }

    function countWorkingDays(first, last) {
        let count = 0;
        for (let cur = new Date(first); cur <= last; cur = addDays(cur, 1)) {
            if (cur.getUTCDay() !== 0 && cur.getUTCDay() !== 6) count++;
        }
        return count;
    }

    // =========================================================================
    // SÉLECTION D'UNE AFFAIRE ET AFFICHAGE DU PANNEAU INFÉRIEUR (4 BLOCS)
    // =========================================================================

    const STANDARD_STEPS = [['decoupage', 'Découpage'], ['pliage', 'Pliage'], ['pointage', 'Pointage'], ['soudage', 'Soudage'], ['passivation', 'Passivation'], ['autre', 'Autre']];

    function selectAffaire(affaireId) {
        const idNum = Number(affaireId);
        if (!idNum) return;
        const reqList = requests();
        const req = reqList.find(item => Number(item.id) === idNum);
        if (!req) return;

        // Mettre à jour l'attribut data-selected-id
        const rootEl = root();
        if (rootEl) rootEl.dataset.selectedId = idNum;
        window.PlanningTimeline?.select(idNum);

        // Mise à jour visuelle des lignes et barres de Gantt
        $('.gantt-row').removeClass('is-selected');
        $('.gantt-bar').removeClass('is-selected');
        const activeRow = $(`.gantt-row[data-affaire-id="${idNum}"]`);
        activeRow.addClass('is-selected');
        activeRow.find('.gantt-bar').addClass('is-selected');

        // BLOC 1: INFORMATION GÉNÉRALE
        $('#detail-date-debut').text(displayDate(req.date_debut_planifiee));
        $('#detail-date-fin').text(displayDate(req.date_fin_planifiee));
        $('#detail-personnel').text(req.personnel_atelier_nom || 'Non affecté');
        $('#detail-matiere').text(req.matiere_libelle || 'Non précisée');
        
        const planContainer = $('#detail-plan').empty();
        if (req.plans && req.plans.length > 0) {
            const firstPlan = req.plans[0];
            const isPdf = /\.pdf$/i.test(firstPlan.name);
            const planLink = $('<a>')
                .attr('href', `actions/download_prefa_attachment.php?id=${req.id}&file=${encodeURIComponent(firstPlan.key)}`)
                .attr('data-name', firstPlan.name)
                .text(firstPlan.name)
                .appendTo(planContainer);
            if (isPdf) {
                planLink.addClass('prefa-pdf-link').attr('aria-haspopup', 'dialog');
            } else {
                planLink.attr('target', '_blank').attr('rel', 'noopener');
            }
            if (req.plans.length > 1) {
                $('<span>').text(` (+${req.plans.length - 1})`).appendTo(planContainer);
            }
        } else if (req.plan_bpe_iso) {
            $('<span>').text(req.plan_bpe_iso).appendTo(planContainer);
        } else {
            $('<span>').text('Aucun plan associé').appendTo(planContainer);
        }

        $('#btn-edit-general').attr('data-plan-id', req.id);

        // BLOC 2: DÉTAIL AVANCEMENT (MATRICE)
        renderAvancementMatrix(req);

        // BLOC 3: COMMENTAIRES
        renderCommentsList(req);

        // BLOC 4: LIVRAISON
        $('#detail-depart-livraison').text(displayDate(req.date_fin_prevue));
        $('#detail-reception-livraison').text(displayDate(req.date_livraison_prevue));
        $('#btn-edit-livraison').attr('data-plan-id', req.id);
        const panel = rootEl?.querySelector('#planning-details-panel');
        if (panel) panel.hidden = false;
    }

    function stepStatus(element, key) {
        const value = element.statuts?.[key] ?? (key === 'autre' ? 'RAS' : '');
        return ['OK', 'Non OK', 'RAS'].includes(value) ? value : 'Non renseigné';
    }

    function renderAvancementMatrix(req) {
        const headTr = $('#matrix-head tr').empty();
        $('<th>').text('Étape').appendTo(headTr);
        const elements = req.elements || [];
        elements.forEach(elem => {
            $('<th>').text(elem.reference || elem.libelle || 'Repère').appendTo(headTr);
        });
        const tbody = $('#matrix-body').empty();
        if (!elements.length) {
            $('<tr>').append($('<td>').text('Aucun élément / ISO associé à cette demande.')).appendTo(tbody);
            return;
        }
        STANDARD_STEPS.forEach(([key, label]) => {
            const tr = $('<tr>').appendTo(tbody);
            $('<td class="step-label">').text(label).appendTo(tr);
            elements.forEach(elem => {
                const status = stepStatus(elem, key);
                const statusClass = { OK: 'status-ok', 'Non OK': 'status-non-ok', RAS: 'status-ras' }[status] || 'status-unset';
                $('<td>').addClass(statusClass).text(status)
                    .attr('title', `${label} - ${elem.reference || 'Repère'}`).appendTo(tr);
            });
        });
    }
    function renderCommentsList(req) {
        const container = $('#comments-list').empty();
        const comments = req.commentaires || [];

        if (comments.length === 0) {
            $('<p class="planning-empty-text">').text('Aucun commentaire pour cette affaire.').appendTo(container);
            return;
        }

        comments.forEach(c => {
            const item = $('<div class="comment-item">').appendTo(container);
            $('<div class="comment-meta">').text(`Le ${c.date} par ${c.auteur}`).appendTo(item);
            $('<p class="comment-text">').text(c.texte).appendTo(item);
        });
    }

    // =========================================================================
    // EXPORTS CSV (AVANCEMENT & COMMENTAIRES)
    // =========================================================================

    function downloadCSV(filename, rows) {
        const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + rows.map(e => e.join(';')).join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportAvancement() {
        const currentId = Number(root()?.dataset?.selectedId || 0);
        const req = requests().find(item => Number(item.id) === currentId);
        if (!req) return;
        const elements = req.elements || [];
        const rows = [['Étape', ...elements.map(e => e.reference || e.libelle || 'Repère')]];
        if (!elements.length) rows.push(['Aucun élément / ISO associé à cette demande.']);
        else STANDARD_STEPS.forEach(([key, label]) => {
            rows.push([label, ...elements.map(elem => stepStatus(elem, key))]);
        });
        downloadCSV(`avancement_affaire_${req.reference_demande}_${req.nom_affaire || 'prefa'}.csv`, rows);
    }
    function exportCommentaires() {
        const currentId = Number(root()?.dataset?.selectedId || 0);
        const req = requests().find(item => Number(item.id) === currentId);
        if (!req) return;

        const comments = req.commentaires || [];
        const rows = [['Date', 'Auteur', 'Commentaire']];

        comments.forEach(c => {
            rows.push([`"${c.date}"`, `"${c.auteur.replace(/"/g, '""')}"`, `"${c.texte.replace(/"/g, '""')}"`]);
        });

        downloadCSV(`commentaires_affaire_${req.reference_demande}_${req.nom_affaire || 'prefa'}.csv`, rows);
    }

    // =========================================================================
    // RECHARGEMENT & SYNCHRONISATION
    // =========================================================================

    function reload(message = '', focusId = null, removed = false) {
        const current = root();
        const form = current?.querySelector('#planning-filters');
        if (!form) return;
        const params = new URLSearchParams(new FormData(form));
        if (loading) loading.abort();
        const scroll = focusId ? 0 : current.querySelector('.gantt-scroll-wrapper')?.scrollLeft || 0;
        current.setAttribute('aria-busy', 'true');
        loading = $.get('pages/planning.php?' + params.toString()).done(html => {
            if (root() !== current) return;
            $('#content').html(html);
            const next = root();
            window.PlanningTimeline?.mount(next);
            const calendar = next?.querySelector('.gantt-scroll-wrapper');
            if (calendar) calendar.scrollLeft = scroll;
            
            const targetId = Number(focusId || (!removed && current.dataset.selectedId) || 0);
            if (targetId && next?.querySelector(`[data-select-affaire="${targetId}"], [data-plan-id="${targetId}"], [data-view-plan-id="${targetId}"]`)) {
                selectAffaire(targetId);
                const block = next?.querySelector(`.gantt-bar[data-plan-id="${Number(targetId)}"]`);
                if (block) {
                    block.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            }
            if (message) $(next).find('#planning-page-message').removeClass('is-error is-success is-removed').addClass(removed ? 'is-removed' : 'is-success').text(message);
        }).fail((xhr, status) => {
            if (status !== 'abort' && root() === current) {
                $(current).find('#planning-page-message').addClass('is-error').text('Impossible de charger le planning. Réessayez.');
            }
        }).always(() => current.removeAttribute('aria-busy'));
    }

    function preview() {
        const form = document.getElementById('planning-edit-form');
        if (!form) return;
        const first = date(form.elements.start.value);
        const last = date(form.elements.end.value);
        form.elements.end.min = form.elements.start.value;
        form.elements.end.setCustomValidity(first && last && last < first ? 'La fin doit suivre le début du planning.' : '');
        const box = $('#planning-preview').empty();
        if (!first || !last) {
            box.html('<span class="planning-preview-placeholder"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Sélectionnez les dates de début et de fin.</span>');
            return;
        }
        if (last < first) {
            box.html('<span class="planning-preview-error"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> La date de fin doit être postérieure au début.</span>');
            return;
        }
        const workingDays = countWorkingDays(first, last);
        const $content = $('<div class="planning-preview-content"></div>');
        const $dates = $('<div class="planning-preview-dates"></div>');
        $dates.append($('<span class="preview-date-item"></span>').text(displayDate(form.elements.start.value)));
        $dates.append($('<span class="preview-arrow">➔</span>'));
        $dates.append($('<span class="preview-date-item"></span>').text(displayDate(form.elements.end.value)));
        $content.append($dates);
        $content.append($('<div class="planning-preview-badge"></div>').text(workingDays + ' j ouvré' + (workingDays > 1 ? 's' : '')));
        box.append($content);
    }

    // Initialiser au chargement la première affaire sélectionnée
    $(() => {
        const rootEl = root();
        if (rootEl && rootEl.dataset.selectedId) {
            selectAffaire(rootEl.dataset.selectedId);
        }
    });

    // Clic pour sélectionner une affaire
    $(document).on('click', '[data-select-affaire]', function () {
        selectAffaire(this.dataset.selectAffaire);
    });
    $(document).on('click', '[data-view-plan-id]', function () {
        selectAffaire(this.dataset.viewPlanId);
        const panel = document.getElementById('planning-details-panel');
        const page = root();
        if (!panel || !page) return;
        // Ne défiler que si le panneau n’est pas déjà visible, sinon la page saute à chaque clic.
        const cible = panel.getBoundingClientRect();
        const vue = page.getBoundingClientRect();
        if (cible.top < vue.top || cible.top > vue.bottom - 80) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    document.addEventListener('planning:select', event => selectAffaire(event.detail.id));
    document.addEventListener('planning:edit', event => {
        const button = root()?.querySelector(`.gantt-bar[data-plan-id="${Number(event.detail.id)}"]`);
        if (button) button.click();
    });

    // Boutons d'export
    $(document).on('click', '#btn-export-avancement', exportAvancement);
    $(document).on('click', '#btn-export-commentaires', exportCommentaires);

    // Clic pour modifier une planification (ouvre le modal)
    $(document).on('click', '[data-plan-id]', function (e) {
        const planId = Number(this.dataset.planId);
        selectAffaire(planId);

        // Si ce clic vient spécifiquement de l'étiquette ou sélection, ne pas ouvrir directement le modal
        if ($(this).hasClass('gantt-affaire-badge')) return;

        const request = requests().find(item => Number(item.id) === planId);
        const dialog = document.getElementById('planning-dialog');
        const form = document.getElementById('planning-edit-form');
        if (!request || !dialog || !form) return;
        form.reset();
        form.elements.id.value = request.id;
        form.elements.revision.value = request.planning_revision;

        let startDateStr = request.date_debut_planifiee;
        if (!startDateStr) {
            const rawValidation = request.date_validation ? request.date_validation.slice(0, 10) : '';
            const candidate = date(rawValidation) || date(document.getElementById('planning-start')?.value) || new Date();
            startDateStr = iso(adjustToWorkingDay(candidate));
        }

        const hours = getHoursForRequest(request);
        let endDateStr = request.date_fin_planifiee;
        if (!endDateStr) {
            endDateStr = computeEndDate(startDateStr, hours);
        }

        form.elements.start.value = startDateStr;
        form.elements.end.value = endDateStr;
        $('#planning-dialog-title').text('Demande ' + request.reference_demande);
        const durationDays = Math.ceil(hours / 24);
        const inches = parseInt(request.pouces_total_iso || 0, 10);
        
        const $desc = $('#planning-request-description').empty();
        const $chipDemandeur = $('<div class="planning-meta-chip"><span class="meta-label">Demandeur</span><strong class="meta-val"></strong></div>');
        $chipDemandeur.find('.meta-val').text(request.demandeur || '—');
        $desc.append($chipDemandeur);
        if (inches > 0) {
            const $chipChiffrage = $('<div class="planning-meta-chip"><span class="meta-label">Chiffrage</span><strong class="meta-val"></strong></div>');
            $chipChiffrage.find('.meta-val').text(inches + ' pouce' + (inches > 1 ? 's' : '') + ' ISO (' + durationDays + ' j)');
            $desc.append($chipChiffrage);
        }
        const $chipLivraison = $('<div class="planning-meta-chip"><span class="meta-label">Livraison max</span><strong class="meta-val"></strong></div>');
        $chipLivraison.find('.meta-val').text(displayDate(request.date_livraison_prevue));
        $desc.append($chipLivraison);

        $('#planning-save-message').removeClass('is-error is-success').text('');
        document.getElementById('planning-remove').hidden = !request.date_debut_planifiee;
        preview();
        dialog.showModal();
    });

    $(document).on('click', '[data-planning-close]', () => document.getElementById('planning-dialog')?.close());
    $(document).on('change input', '#planning-task-start', function () {
        const currentId = document.getElementById('planning-edit-form')?.elements.id?.value;
        const request = requests().find(item => Number(item.id) === Number(currentId));
        if (request && this.value) {
            const hours = getHoursForRequest(request);
            const calculatedEnd = computeEndDate(this.value, hours);
            if (calculatedEnd) {
                document.getElementById('planning-task-end').value = calculatedEnd;
            }
        }
        preview();
    });
    $(document).on('change input', '#planning-task-end', preview);

    function save(operation) {
        const form = document.getElementById('planning-edit-form');
        if (!form || (operation === 'save' && !form.reportValidity())) return;
        const current = root();
        const params = new URLSearchParams(new FormData(form));
        params.set('operation', operation);
        const buttons = $(form).find('button');
        buttons.prop('disabled', true);
        $('#planning-save-message').removeClass('is-error').text('Enregistrement…');
        $.ajax({ url: 'actions/save_planning.php', method: 'POST', data: params.toString(), dataType: 'json' }).done(result => {
            if (root() !== current) return;
            document.getElementById('planning-dialog')?.close();
            if (result.planning) {
                const first = date(result.planning.start);
                document.getElementById('planning-start').value = iso(addDays(first, -((first.getUTCDay() + 6) % 7)));
                const creatorFilter = document.getElementById('planning-creator-filter');
                if (creatorFilter.value && Number(creatorFilter.value) !== Number(result.planning.creator)) creatorFilter.value = result.planning.creator;
            }
            reload(result.message, result.planning?.id, operation === 'remove');
        }).fail(xhr => {
            if (root() === current) $('#planning-save-message').addClass('is-error').text(xhr.responseJSON?.message || 'Impossible d’enregistrer le planning.');
        }).always(() => buttons.prop('disabled', false));
    }

    $(document).on('submit', '#planning-edit-form', function (event) { event.preventDefault(); save('save'); });
    $(document).on('click', '#planning-remove', () => save('remove'));
    $(document).on('submit', '#planning-filters', function (event) { event.preventDefault(); reload(); });
    $(document).on('change', '#planning-filters input, #planning-filters select', () => reload());
    $(document).on('click', '#planning-refresh', () => reload());
    $(document).on('click', '[data-planning-go-to]', function () {
        const first = date(this.dataset.planningGoTo);
        if (!first) return;
        document.getElementById('planning-start').value = iso(addDays(first, -((first.getUTCDay() + 6) % 7)));
        reload('', Number(this.dataset.planningTarget));
    });
    $(document).on('click', '#planning-create-user', () => $('#btn-create-user').trigger('click'));
    $(document).on('click', '[data-planning-shift]', function () {
        const field = document.getElementById('planning-start');
        const current = date(field.value);
        if (!current) return;
        field.value = iso(addDays(current, Number(this.dataset.planningShift) * Number(document.getElementById('planning-period').value)));
        reload();
    });
    $(document).on('click', '[data-planning-today]', () => {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(item => [item.type, item.value]));
        const today = date(values.year + '-' + values.month + '-' + values.day);
        document.getElementById('planning-start').value = iso(addDays(today, -((today.getUTCDay() + 6) % 7)));
        reload();
    });
})();
