(function () {
    'use strict';

    var URLS = window.KANBAN_URLS || {};
    var currentTaskId = null;
    var timerInterval = null;

    function urlFor(base, id) {
        return base.replace(/\/0(\/|$)/, '/' + id + '$1');
    }

    function urlFor2(base, id1, id2) {
        return urlFor(urlFor(base, id1), id2);
    }

    function post(url, data) {
        var body = new URLSearchParams(data || {});
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (err) { throw err; });
            }
            return res.json();
        });
    }

    function get(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); });
    }

    // No Content-Type header here on purpose: the browser sets
    // "multipart/form-data; boundary=..." itself from the FormData body.
    function postFile(url, file) {
        var body = new FormData();
        body.append('file', file);
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (err) { throw err; });
            }
            return res.json();
        });
    }

    function formatDuration(seconds) {
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    function updateCardTime(taskId, seconds) {
        document.querySelectorAll('.kanban-card-time[data-task-id="' + taskId + '"] .kanban-time-value').forEach(function (el) {
            el.textContent = formatDuration(seconds);
        });
    }

    // ---------- description markdown ----------
    // Minimal, dependency-free markdown -> HTML renderer. HTML is escaped
    // *before* any markdown tag is generated, so user input can never inject
    // markup of its own (safe against XSS).

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function markdownInline(text) {
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
        text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        text = text.replace(/_([^_]+)_/g, '<em>$1</em>');
        text = text.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
        return text;
    }

    function markdownToHtml(markdown) {
        if (!markdown) {
            return '';
        }

        var lines = escapeHtml(markdown).split(/\r?\n/);
        var html = [];
        var paragraph = [];
        var inUl = false;
        var inOl = false;

        function closeLists() {
            if (inUl) { html.push('</ul>'); inUl = false; }
            if (inOl) { html.push('</ol>'); inOl = false; }
        }

        function flushParagraph() {
            if (paragraph.length) {
                html.push('<p>' + paragraph.join(' ') + '</p>');
                paragraph = [];
            }
        }

        lines.forEach(function (line) {
            if (/^\s*$/.test(line)) {
                closeLists();
                flushParagraph();
                return;
            }

            var header = line.match(/^(#{1,6})\s+(.*)$/);
            if (header) {
                closeLists();
                flushParagraph();
                var level = header[1].length;
                html.push('<h' + level + '>' + markdownInline(header[2]) + '</h' + level + '>');
                return;
            }

            if (/^\s*([-*]\s*){3,}$/.test(line)) {
                closeLists();
                flushParagraph();
                html.push('<hr>');
                return;
            }

            var ul = line.match(/^\s*[-*]\s+(.*)$/);
            if (ul) {
                flushParagraph();
                if (inOl) { html.push('</ol>'); inOl = false; }
                if (!inUl) { html.push('<ul>'); inUl = true; }
                html.push('<li>' + markdownInline(ul[1]) + '</li>');
                return;
            }

            var ol = line.match(/^\s*\d+\.\s+(.*)$/);
            if (ol) {
                flushParagraph();
                if (inUl) { html.push('</ul>'); inUl = false; }
                if (!inOl) { html.push('<ol>'); inOl = true; }
                html.push('<li>' + markdownInline(ol[1]) + '</li>');
                return;
            }

            var quote = line.match(/^\s*&gt;\s?(.*)$/);
            if (quote) {
                closeLists();
                flushParagraph();
                html.push('<blockquote>' + markdownInline(quote[1]) + '</blockquote>');
                return;
            }

            closeLists();
            paragraph.push(markdownInline(line));
        });

        closeLists();
        flushParagraph();

        return html.join('\n');
    }

    function renderDescriptionPreview() {
        var textarea = document.getElementById('kanban-task-description');
        var preview = document.getElementById('kanban-task-description-preview');
        if (!textarea || !preview) {
            return;
        }
        preview.innerHTML = markdownToHtml(textarea.value);
    }

    function setDescriptionMode(mode) {
        var textarea = document.getElementById('kanban-task-description');
        var preview = document.getElementById('kanban-task-description-preview');
        var editTab = document.getElementById('kanban-description-tab-edit');
        var previewTab = document.getElementById('kanban-description-tab-preview');
        if (!textarea || !preview || !editTab || !previewTab) {
            return;
        }

        if (mode === 'preview') {
            renderDescriptionPreview();
            textarea.classList.add('d-none');
            preview.classList.remove('d-none');
            previewTab.classList.add('active');
            editTab.classList.remove('active');
        } else {
            textarea.classList.remove('d-none');
            preview.classList.add('d-none');
            editTab.classList.add('active');
            previewTab.classList.remove('active');
        }
    }

    (function initDescriptionTabs() {
        var editTab = document.getElementById('kanban-description-tab-edit');
        var previewTab = document.getElementById('kanban-description-tab-preview');
        if (!editTab || !previewTab) {
            return;
        }
        editTab.addEventListener('click', function (e) {
            e.preventDefault();
            setDescriptionMode('edit');
        });
        previewTab.addEventListener('click', function (e) {
            e.preventDefault();
            setDescriptionMode('preview');
        });
    })();

    // ---------- non-blocking toast (replaces alert()) ----------

    function showToast(message, isError) {
        var el = document.getElementById('kanban-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'kanban-toast';
            document.body.appendChild(el);
        }
        el.textContent = message;
        el.className = 'kanban-toast' + (isError ? ' kanban-toast-error' : '') + ' kanban-toast-visible';
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(function () {
            el.classList.remove('kanban-toast-visible');
        }, 4000);
    }

    // ---------- two-click confirm (replaces confirm()) ----------
    // First click arms the button ("Confirm?"); a second click within 4s
    // triggers the action. Clicking elsewhere, or letting it time out, disarms it.

    function armConfirm(button, confirmLabel, onConfirm) {
        if (button.classList.contains('kanban-armed')) {
            onConfirm();
            disarmConfirm(button);
            return;
        }

        document.querySelectorAll('.kanban-armed').forEach(function (btn) { disarmConfirm(btn); });

        button.classList.add('kanban-armed');
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = confirmLabel;
        button._disarmTimer = setTimeout(function () { disarmConfirm(button); }, 4000);
    }

    function disarmConfirm(button) {
        button.classList.remove('kanban-armed');
        if (button.dataset.originalHtml !== undefined) {
            button.innerHTML = button.dataset.originalHtml;
        }
        clearTimeout(button._disarmTimer);
    }

    // ---------- list & task creation ----------

    document.addEventListener('click', function (e) {
        if (e.target.closest('#new-list-submit')) {
            var input = document.getElementById('new-list-title');
            var title = input.value.trim();
            if (!title) return;
            post(URLS.listCreate, { title: title }).then(function () { window.location.reload(); });
        }

        var addTaskBtn = e.target.closest('.kanban-add-task');
        if (addTaskBtn) {
            var listId = addTaskBtn.getAttribute('data-list-id');
            addTaskBtn.classList.add('d-none');
            var form = document.querySelector('.kanban-add-task-form[data-list-id="' + listId + '"]');
            if (form) {
                form.classList.remove('d-none');
                form.querySelector('.kanban-add-task-input').focus();
            }
        }

        var cancelAddTaskBtn = e.target.closest('.kanban-add-task-cancel');
        if (cancelAddTaskBtn) {
            hideAddTaskForm(cancelAddTaskBtn.getAttribute('data-list-id'));
        }

        var submitAddTaskBtn = e.target.closest('.kanban-add-task-submit');
        if (submitAddTaskBtn) {
            var subListId = submitAddTaskBtn.getAttribute('data-list-id');
            var form2 = document.querySelector('.kanban-add-task-form[data-list-id="' + subListId + '"]');
            var textarea = form2.querySelector('.kanban-add-task-input');
            var taskTitle = textarea.value.trim();
            if (!taskTitle) return;
            post(urlFor(URLS.taskCreateBase, subListId), { title: taskTitle }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                showToast(err.error || 'Não foi possível criar a tarefa.', true);
            });
        }

        var deleteListBtn = e.target.closest('.kanban-list-delete');
        if (deleteListBtn) {
            armConfirm(deleteListBtn, '<i class="fas fa-trash"></i>?', function () {
                var dlId = deleteListBtn.getAttribute('data-list-id');
                post(urlFor(URLS.listDeleteBase, dlId)).then(function () { window.location.reload(); });
            });
        }

        var colorToggleBtn = e.target.closest('.kanban-list-color-toggle');
        if (colorToggleBtn) {
            var ctListId = colorToggleBtn.getAttribute('data-list-id');
            var picker = document.querySelector('.kanban-list-color-picker[data-list-id="' + ctListId + '"]');
            if (picker) {
                document.querySelectorAll('.kanban-list-color-picker').forEach(function (p) {
                    if (p !== picker) p.classList.add('d-none');
                });
                picker.classList.toggle('d-none');
            }
        }

        var listSwatch = e.target.closest('.kanban-list-color-picker .kanban-color-swatch');
        if (listSwatch) {
            var swListId = listSwatch.closest('.kanban-list-color-picker').getAttribute('data-list-id');
            var color = listSwatch.getAttribute('data-color') || '';
            post(urlFor(URLS.listColorBase, swListId), { color: color }).then(function () {
                window.location.reload();
            });
        }
    });

    function hideAddTaskForm(listId) {
        var form = document.querySelector('.kanban-add-task-form[data-list-id="' + listId + '"]');
        var btn = document.querySelector('.kanban-add-task[data-list-id="' + listId + '"]');
        if (form) {
            form.classList.add('d-none');
            form.querySelector('.kanban-add-task-input').value = '';
        }
        if (btn) btn.classList.remove('d-none');
    }

    document.querySelectorAll('.kanban-list-title').forEach(function (el) {
        el.addEventListener('blur', function () {
            var listId = el.getAttribute('data-list-id');
            var title = el.textContent.trim();
            if (title) {
                post(urlFor(URLS.listRenameBase, listId), { title: title });
            }
        });
    });

    // ---------- start / pause (board + list view, outside modal) ----------

    document.addEventListener('click', function (e) {
        var startBtn = e.target.closest('.kanban-task-start');
        var pauseBtn = e.target.closest('.kanban-task-pause');

        if (startBtn) {
            var sId = startBtn.getAttribute('data-task-id');
            post(urlFor(URLS.taskStartBase, sId)).then(function () { window.location.reload(); })
                .catch(function (err) { showToast(err.error || 'Não foi possível iniciar a tarefa.', true); });
        }

        if (pauseBtn) {
            var pId = pauseBtn.getAttribute('data-task-id');
            post(urlFor(URLS.taskPauseBase, pId)).then(function () { window.location.reload(); });
        }
    });

    // ---------- task modal ----------

    function openTaskModal(taskId) {
        get(urlFor(URLS.taskShowBase, taskId)).then(function (task) {
            currentTaskId = task.id;
            document.getElementById('kanban-task-id').value = task.id;
            document.getElementById('kanban-task-title').value = task.title;
            document.getElementById('kanban-task-description').value = task.description || '';
            setDescriptionMode('edit');
            document.getElementById('kanban-task-start-date').value = task.startDate || '';
            document.getElementById('kanban-task-due-date').value = task.dueDate || '';
            document.getElementById('kanban-task-end-date').value = task.endDate || '';
            document.getElementById('kanban-task-done').checked = !!task.isDone;
            document.getElementById('kanban-task-time').textContent = formatDuration(task.totalWorkedSeconds);

            toggleTimerButtons(task.isRunning);
            stopLocalTimer();
            if (task.isRunning) {
                startLocalTimer(task.totalWorkedSeconds);
            }

            renderChecklist(task.checklist || []);
            updateChecklistProgress(task.checklistProgress);
            renderAttachments(task.attachments || []);
            renderTimeLog(task.timeLog || []);
            var todayInput = document.getElementById('kanban-log-date');
            if (todayInput && !todayInput.value) {
                todayInput.value = new Date().toISOString().slice(0, 10);
            }

            showModal();
        });
    }

    // ---------- tiny modal open/close (no dependency on Bootstrap/jQuery JS) ----------

    function getModalBackdrop() {
        var backdrop = document.getElementById('kanban-modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'kanban-modal-backdrop';
            backdrop.className = 'kanban-modal-backdrop';
            document.body.appendChild(backdrop);
        }
        return backdrop;
    }

    function showModal() {
        var modal = document.getElementById('kanban-task-modal');
        modal.classList.add('show');
        modal.style.display = 'block';
        getModalBackdrop().classList.add('show');
        document.body.classList.add('kanban-modal-open');
    }

    function hideModal() {
        var modal = document.getElementById('kanban-task-modal');
        modal.classList.remove('show');
        modal.style.display = 'none';
        getModalBackdrop().classList.remove('show');
        document.body.classList.remove('kanban-modal-open');
        stopLocalTimer();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#kanban-task-modal [data-dismiss="modal"]')) {
            hideModal();
        }
        if (e.target.id === 'kanban-modal-backdrop') {
            hideModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('kanban-task-modal').classList.contains('show')) {
            hideModal();
        }
    });

    function toggleTimerButtons(running) {
        document.getElementById('kanban-task-start-btn').classList.toggle('d-none', running);
        document.getElementById('kanban-task-pause-btn').classList.toggle('d-none', !running);
    }

    function startLocalTimer(baseSeconds) {
        var started = Date.now();
        timerInterval = setInterval(function () {
            var elapsed = baseSeconds + Math.floor((Date.now() - started) / 1000);
            document.getElementById('kanban-task-time').textContent = formatDuration(elapsed);
            updateCardTime(currentTaskId, elapsed);
        }, 1000);
    }

    function stopLocalTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function buildChecklistItem(item, isChild) {
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.setAttribute('data-item-id', item.id);

        var label = document.createElement('label');
        label.className = 'm-0 flex-grow-1 d-flex align-items-center';
        if (item.checked) label.style.textDecoration = 'line-through';

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = item.checked;
        checkbox.className = 'kanban-checklist-toggle';
        checkbox.setAttribute('data-item-id', item.id);

        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(item.text));

        var actions = document.createElement('div');
        actions.className = 'd-flex align-items-center';

        if (!isChild) {
            var addChild = document.createElement('button');
            addChild.type = 'button';
            addChild.className = 'kanban-checklist-subtoggle';
            addChild.setAttribute('data-item-id', item.id);
            addChild.title = window.KANBAN_ADD_SUBITEM_LABEL || 'Add sub-item';
            addChild.innerHTML = '<i class="fas fa-list-ul"></i>';
            actions.appendChild(addChild);
        }

        var del = document.createElement('button');
        del.className = 'btn btn-xs btn-link text-danger kanban-checklist-delete';
        del.setAttribute('data-item-id', item.id);
        del.innerHTML = '<i class="fas fa-times"></i>';
        actions.appendChild(del);

        li.appendChild(label);
        li.appendChild(actions);
        return li;
    }

    function renderChecklist(items) {
        var list = document.getElementById('kanban-checklist-items');
        list.innerHTML = '';
        items.forEach(function (item) {
            list.appendChild(buildChecklistItem(item, false));

            var children = item.children || [];
            if (children.length) {
                var childList = document.createElement('ul');
                childList.className = 'list-group kanban-checklist-children';
                childList.setAttribute('data-parent-id', item.id);
                children.forEach(function (child) {
                    childList.appendChild(buildChecklistItem(child, true));
                });
                list.appendChild(childList);
            }

            var addChildRow = document.createElement('div');
            addChildRow.className = 'input-group input-group-sm kanban-checklist-add-child d-none';
            addChildRow.setAttribute('data-parent-id', item.id);

            var addChildInput = document.createElement('input');
            addChildInput.type = 'text';
            addChildInput.className = 'form-control kanban-checklist-child-text';
            addChildInput.placeholder = window.KANBAN_SUBITEM_PLACEHOLDER || 'Add sub-item';

            var addChildBtnWrap = document.createElement('div');
            addChildBtnWrap.className = 'input-group-append';
            var addChildBtn = document.createElement('button');
            addChildBtn.type = 'button';
            addChildBtn.className = 'btn btn-outline-secondary kanban-checklist-child-add';
            addChildBtn.setAttribute('data-parent-id', item.id);
            addChildBtn.innerHTML = '<i class="fas fa-plus"></i>';
            addChildBtnWrap.appendChild(addChildBtn);

            addChildRow.appendChild(addChildInput);
            addChildRow.appendChild(addChildBtnWrap);
            list.appendChild(addChildRow);
        });
    }

    function updateChecklistProgress(progress) {
        var bar = document.getElementById('kanban-checklist-progress-bar');
        var label = document.getElementById('kanban-checklist-progress-label');
        if (!bar || !label) {
            return;
        }
        var total = (progress && progress.total) || 0;
        var done = (progress && progress.done) || 0;
        var percent = total > 0 ? Math.round((done / total) * 100) : 0;
        bar.style.width = percent + '%';
        label.textContent = percent + '%';
    }

    function refreshChecklist() {
        if (!currentTaskId) {
            return;
        }
        get(urlFor(URLS.taskShowBase, currentTaskId)).then(function (task) {
            renderChecklist(task.checklist || []);
            updateChecklistProgress(task.checklistProgress);
        });
    }

    // ---------- attachments ----------

    function renderAttachments(attachments) {
        var list = document.getElementById('kanban-attachment-list');
        if (!list) {
            return;
        }
        list.innerHTML = '';
        attachments.forEach(function (attachment) {
            var item = document.createElement('div');
            item.className = 'kanban-attachment-item';
            item.setAttribute('data-attachment-id', attachment.id);

            var link = document.createElement('a');
            link.href = attachment.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.title = attachment.originalName;

            var img = document.createElement('img');
            img.src = attachment.url;
            img.alt = attachment.originalName;
            link.appendChild(img);

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'kanban-attachment-remove';
            del.setAttribute('data-attachment-id', attachment.id);
            del.title = window.KANBAN_REMOVE_LABEL || 'Remove';
            del.innerHTML = '&times;';

            item.appendChild(link);
            item.appendChild(del);
            list.appendChild(item);
        });
    }

    function refreshAttachments() {
        if (!currentTaskId) {
            return;
        }
        get(urlFor(URLS.taskShowBase, currentTaskId)).then(function (task) {
            renderAttachments(task.attachments || []);
        });
    }

    function uploadAttachments(files) {
        if (!currentTaskId || !files || !files.length) {
            return;
        }
        var list = document.getElementById('kanban-attachment-list');
        var placeholders = [];

        Array.from(files).forEach(function (file) {
            if (file.type.indexOf('image/') !== 0) {
                return;
            }

            var placeholder = document.createElement('div');
            placeholder.className = 'kanban-attachment-item kanban-attachment-uploading';
            placeholder.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            if (list) {
                list.appendChild(placeholder);
            }
            placeholders.push(placeholder);

            postFile(urlFor(URLS.attachmentUploadBase, currentTaskId), file)
                .then(function () {
                    refreshAttachments();
                })
                .catch(function (err) {
                    placeholder.remove();
                    showToast(err.error || 'kanban.attachment.upload_failed', true);
                });
        });
    }

    var attachmentAddBtn = document.getElementById('kanban-attachment-add-btn');
    var attachmentInput = document.getElementById('kanban-attachment-input');
    if (attachmentAddBtn && attachmentInput) {
        attachmentAddBtn.addEventListener('click', function () {
            attachmentInput.click();
        });
        attachmentInput.addEventListener('change', function () {
            uploadAttachments(attachmentInput.files);
            attachmentInput.value = '';
        });
    }

    document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.kanban-attachment-remove');
        if (!removeBtn) {
            return;
        }
        var attachmentId = removeBtn.getAttribute('data-attachment-id');
        post(urlFor(URLS.attachmentDeleteBase, attachmentId)).then(function () {
            removeBtn.closest('.kanban-attachment-item').remove();
        });
    });

    // Paste a screenshot straight from the clipboard while the task modal is open.
    document.addEventListener('paste', function (e) {
        var modal = document.getElementById('kanban-task-modal');
        if (!modal || !modal.classList.contains('show') || !e.clipboardData) {
            return;
        }
        var files = Array.from(e.clipboardData.items || [])
            .filter(function (i) { return i.kind === 'file' && i.type.indexOf('image/') === 0; })
            .map(function (i) { return i.getAsFile(); })
            .filter(Boolean);
        if (files.length) {
            uploadAttachments(files);
        }
    });

    function formatLogDateTime(iso) {
        // "2026-08-19T10:00" -> "19/08 10:00"
        var parts = iso.split('T');
        var dateParts = parts[0].split('-');
        return dateParts[2] + '/' + dateParts[1] + ' ' + (parts[1] || '');
    }

    function renderTimeLog(entries) {
        var list = document.getElementById('kanban-time-log-items');
        if (!list) return;
        list.innerHTML = '';
        entries.forEach(function (entry) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('data-timesheet-id', entry.id);

            var label = document.createElement('span');
            if (entry.running) {
                label.innerHTML = formatLogDateTime(entry.begin) + ' → <em>' + (window.KANBAN_RUNNING_LABEL || '...') + '</em>';
            } else {
                label.textContent = formatLogDateTime(entry.begin) + ' → ' + formatLogDateTime(entry.end) + ' (' + formatDuration(entry.duration) + ')';
            }

            li.appendChild(label);

            if (!entry.running) {
                var del = document.createElement('button');
                del.className = 'btn btn-xs btn-link text-danger kanban-log-delete';
                del.setAttribute('data-timesheet-id', entry.id);
                del.innerHTML = '<i class="fas fa-times"></i>';
                li.appendChild(del);
            }

            list.appendChild(li);
        });
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('.kanban-card-title, .kanban-open-task');
        if (openBtn) {
            e.preventDefault();
            var card = openBtn.closest('[data-task-id]');
            openTaskModal(card.getAttribute('data-task-id'));
        }
    });

    var saveBtn = document.getElementById('kanban-task-save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var id = document.getElementById('kanban-task-id').value;
            post(urlFor(URLS.taskUpdateBase, id), {
                title: document.getElementById('kanban-task-title').value,
                description: document.getElementById('kanban-task-description').value,
                startDate: document.getElementById('kanban-task-start-date').value,
                dueDate: document.getElementById('kanban-task-due-date').value,
                endDate: document.getElementById('kanban-task-end-date').value,
                isDone: document.getElementById('kanban-task-done').checked ? 1 : 0
            }).then(function () { window.location.reload(); })
                .catch(function (err) { showToast(err.error || 'Não foi possível salvar a tarefa.', true); });
        });
    }

    var deleteBtn = document.getElementById('kanban-task-delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            armConfirm(deleteBtn, '<i class="fas fa-trash"></i> ' + (deleteBtn.dataset.confirmLabel || 'Confirmar?'), function () {
                var id = document.getElementById('kanban-task-id').value;
                post(urlFor(URLS.taskDeleteBase, id)).then(function () { window.location.reload(); });
            });
        });
    }

    var startModalBtn = document.getElementById('kanban-task-start-btn');
    if (startModalBtn) {
        startModalBtn.addEventListener('click', function () {
            post(urlFor(URLS.taskStartBase, currentTaskId)).then(function (task) {
                toggleTimerButtons(true);
                stopLocalTimer();
                startLocalTimer(task.totalWorkedSeconds);
            }).catch(function (err) { showToast(err.error || 'Não foi possível iniciar a tarefa.', true); });
        });
    }

    var pauseModalBtn = document.getElementById('kanban-task-pause-btn');
    if (pauseModalBtn) {
        pauseModalBtn.addEventListener('click', function () {
            post(urlFor(URLS.taskPauseBase, currentTaskId)).then(function (task) {
                toggleTimerButtons(false);
                stopLocalTimer();
                document.getElementById('kanban-task-time').textContent = formatDuration(task.totalWorkedSeconds);
                updateCardTime(currentTaskId, task.totalWorkedSeconds);
            });
        });
    }

    // ---------- checklist ----------

    var addChecklistBtn = document.getElementById('kanban-checklist-add');
    if (addChecklistBtn) {
        addChecklistBtn.addEventListener('click', function () {
            var input = document.getElementById('kanban-checklist-new-text');
            var text = input.value.trim();
            if (!text) return;
            post(urlFor(URLS.checklistCreateBase, currentTaskId), { text: text }).then(function () {
                input.value = '';
                refreshChecklist();
            });
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('kanban-checklist-toggle')) {
            var itemId = e.target.getAttribute('data-item-id');
            post(urlFor(URLS.checklistToggleBase, itemId)).then(function (res) {
                e.target.closest('label').style.textDecoration = res.checked ? 'line-through' : 'none';
                updateChecklistProgress(res.progress);
            });
        }
    });

    document.addEventListener('click', function (e) {
        var delItem = e.target.closest('.kanban-checklist-delete');
        if (delItem) {
            var itemId = delItem.getAttribute('data-item-id');
            post(urlFor(URLS.checklistDeleteBase, itemId)).then(function (res) {
                updateChecklistProgress(res.progress);
                refreshChecklist();
            });
            return;
        }

        var subtoggle = e.target.closest('.kanban-checklist-subtoggle');
        if (subtoggle) {
            var parentId = subtoggle.getAttribute('data-item-id');
            var row = document.querySelector('.kanban-checklist-add-child[data-parent-id="' + parentId + '"]');
            if (row) {
                row.classList.toggle('d-none');
                if (!row.classList.contains('d-none')) {
                    row.querySelector('.kanban-checklist-child-text').focus();
                }
            }
            return;
        }

        var addChildBtn = e.target.closest('.kanban-checklist-child-add');
        if (addChildBtn) {
            var pId = addChildBtn.getAttribute('data-parent-id');
            var row2 = addChildBtn.closest('.kanban-checklist-add-child');
            var textInput = row2.querySelector('.kanban-checklist-child-text');
            var childText = textInput.value.trim();
            if (!childText) return;
            post(urlFor(URLS.checklistChildCreateBase, pId), { text: childText }).then(function (res) {
                textInput.value = '';
                updateChecklistProgress(res.progress);
                refreshChecklist();
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList.contains('kanban-checklist-child-text')) {
            e.preventDefault();
            e.target.closest('.kanban-checklist-add-child').querySelector('.kanban-checklist-child-add').click();
        }
    });

    // ---------- manual time log ----------

    var addLogBtn = document.getElementById('kanban-log-time-add');
    if (addLogBtn) {
        addLogBtn.addEventListener('click', function () {
            var date = document.getElementById('kanban-log-date').value;
            var startTime = document.getElementById('kanban-log-start').value;
            var endTime = document.getElementById('kanban-log-end').value;
            if (!date || !startTime || !endTime) {
                showToast('Preencha data, hora de início e hora de fim.', true);
                return;
            }
            post(urlFor(URLS.logTimeBase, currentTaskId), { date: date, startTime: startTime, endTime: endTime })
                .then(function (task) {
                    document.getElementById('kanban-log-start').value = '';
                    document.getElementById('kanban-log-end').value = '';
                    document.getElementById('kanban-task-time').textContent = formatDuration(task.totalWorkedSeconds);
                    updateCardTime(currentTaskId, task.totalWorkedSeconds);
                    renderTimeLog(task.timeLog || []);
                })
                .catch(function (err) { showToast(err.error || 'Não foi possível registrar o horário.', true); });
        });
    }

    document.addEventListener('click', function (e) {
        var delLog = e.target.closest('.kanban-log-delete');
        if (delLog) {
            var timesheetId = delLog.getAttribute('data-timesheet-id');
            post(urlFor2(URLS.logDeleteBase, currentTaskId, timesheetId)).then(function (task) {
                document.getElementById('kanban-task-time').textContent = formatDuration(task.totalWorkedSeconds);
                updateCardTime(currentTaskId, task.totalWorkedSeconds);
                renderTimeLog(task.timeLog || []);
            }).catch(function (err) { showToast(err.error || 'Não foi possível remover o registro.', true); });
        }
    });

    // ---------- drag & drop ----------

    document.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            card.classList.add('kanban-dragging');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('kanban-dragging');
        });
    });

    document.querySelectorAll('.kanban-cards').forEach(function (container) {
        container.addEventListener('dragover', function (e) {
            if (document.querySelector('.kanban-list-dragging')) return; // a column drag, not a card
            e.preventDefault();
            container.classList.add('kanban-drag-over');
            var dragging = document.querySelector('.kanban-dragging');
            var after = getDragAfterElement(container, e.clientY);
            if (!dragging) return;
            if (after == null) {
                container.appendChild(dragging);
            } else {
                container.insertBefore(dragging, after);
            }
        });

        container.addEventListener('dragleave', function () {
            container.classList.remove('kanban-drag-over');
        });

        container.addEventListener('drop', function () {
            if (document.querySelector('.kanban-list-dragging')) return;
            container.classList.remove('kanban-drag-over');
            var dragging = document.querySelector('.kanban-dragging');
            if (!dragging) return;
            var taskId = dragging.getAttribute('data-task-id');
            var listId = container.getAttribute('data-list-id');
            var position = Array.from(container.children).indexOf(dragging);
            post(urlFor(URLS.taskMoveBase, taskId), { listId: listId, position: position });
        });
    });

    function getDragAfterElement(container, y) {
        var cards = Array.from(container.querySelectorAll('.kanban-card:not(.kanban-dragging)'));
        return cards.reduce(function (closest, child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: -Infinity }).element;
    }

    // ---------- column (list) drag & drop, i.e. reordering whole lists ----------

    var kanbanBoardEl = document.getElementById('kanban-board');
    if (kanbanBoardEl) {
        document.querySelectorAll('.kanban-list[data-list-id]').forEach(function (list) {
            list.addEventListener('dragstart', function (e) {
                // dragstart bubbles up from any draggable descendant (a card,
                // in particular) — without this guard, dragging a card was
                // mistakenly read as "the whole column is being dragged".
                if (e.target !== list) return;
                e.stopPropagation();
                list.classList.add('kanban-list-dragging');
            });
            list.addEventListener('dragend', function (e) {
                if (e.target !== list) return;
                list.classList.remove('kanban-list-dragging');
            });
        });

        kanbanBoardEl.addEventListener('dragover', function (e) {
            var draggingList = document.querySelector('.kanban-list-dragging');
            if (!draggingList) return;
            e.preventDefault();
            var newListPlaceholder = kanbanBoardEl.querySelector('.kanban-list-new');
            var after = getListDragAfterElement(kanbanBoardEl, e.clientX);
            if (after == null) {
                kanbanBoardEl.insertBefore(draggingList, newListPlaceholder);
            } else {
                kanbanBoardEl.insertBefore(draggingList, after);
            }
        });

        kanbanBoardEl.addEventListener('drop', function (e) {
            var draggingList = document.querySelector('.kanban-list-dragging');
            if (!draggingList) return;
            e.preventDefault();
            var listId = draggingList.getAttribute('data-list-id');
            var lists = Array.from(kanbanBoardEl.querySelectorAll('.kanban-list[data-list-id]'));
            var position = lists.indexOf(draggingList);
            post(urlFor(URLS.listReorderBase, listId), { position: position });
        });
    }

    function getListDragAfterElement(container, x) {
        var lists = Array.from(container.querySelectorAll('.kanban-list[data-list-id]:not(.kanban-list-dragging)'));
        return lists.reduce(function (closest, child) {
            var box = child.getBoundingClientRect();
            var offset = x - box.left - box.width / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: -Infinity }).element;
    }
})();
