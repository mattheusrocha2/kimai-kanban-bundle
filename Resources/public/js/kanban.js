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

    function renderChecklist(items) {
        var list = document.getElementById('kanban-checklist-items');
        list.innerHTML = '';
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('data-item-id', item.id);

            var label = document.createElement('label');
            label.className = 'm-0 flex-grow-1';
            if (item.checked) label.style.textDecoration = 'line-through';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = item.checked;
            checkbox.className = 'mr-2 kanban-checklist-toggle';
            checkbox.setAttribute('data-item-id', item.id);

            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(item.text));

            var del = document.createElement('button');
            del.className = 'btn btn-xs btn-link text-danger kanban-checklist-delete';
            del.setAttribute('data-item-id', item.id);
            del.innerHTML = '<i class="fas fa-times"></i>';

            li.appendChild(label);
            li.appendChild(del);
            list.appendChild(li);
        });
    }

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
            post(urlFor(URLS.checklistCreateBase, currentTaskId), { text: text }).then(function (item) {
                input.value = '';
                var items = Array.from(document.querySelectorAll('#kanban-checklist-items li')).map(function (li) {
                    return {
                        id: li.getAttribute('data-item-id'),
                        text: li.querySelector('label').textContent.trim(),
                        checked: li.querySelector('input').checked
                    };
                });
                items.push({ id: item.id, text: item.text, checked: item.checked });
                renderChecklist(items);
            });
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('kanban-checklist-toggle')) {
            var itemId = e.target.getAttribute('data-item-id');
            post(urlFor(URLS.checklistToggleBase, itemId)).then(function (res) {
                e.target.closest('label').style.textDecoration = res.checked ? 'line-through' : 'none';
            });
        }
    });

    document.addEventListener('click', function (e) {
        var delItem = e.target.closest('.kanban-checklist-delete');
        if (delItem) {
            var itemId = delItem.getAttribute('data-item-id');
            post(urlFor(URLS.checklistDeleteBase, itemId)).then(function () {
                delItem.closest('li').remove();
            });
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
                e.stopPropagation();
                list.classList.add('kanban-list-dragging');
            });
            list.addEventListener('dragend', function () {
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
