document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf-token-data')?.dataset?.csrf || '';

    // Toast auto-dismiss
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(function (toast) {
        setTimeout(function () {
            toast.style.transform = 'translateY(20px)';
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4000);
    });

    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (sidebar && toggleBtn) {
        const isMobile = window.innerWidth <= 768;
        if (!isMobile) {
            const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            if (collapsed) sidebar.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                let backdrop = document.querySelector('.sidebar-backdrop');
                if (sidebar.classList.contains('open')) {
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.className = 'sidebar-backdrop active';
                        backdrop.addEventListener('click', function () {
                            sidebar.classList.remove('open');
                            backdrop.classList.remove('active');
                            setTimeout(function () { backdrop.remove(); }, 300);
                        });
                        document.body.appendChild(backdrop);
                    }
                } else {
                    if (backdrop) {
                        backdrop.classList.remove('active');
                        setTimeout(function () { backdrop.remove(); }, 300);
                    }
                }
            } else {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                const backdrop = document.querySelector('.sidebar-backdrop');
                if (backdrop) backdrop.remove();
                const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
                if (collapsed) sidebar.classList.add('collapsed');
                else sidebar.classList.remove('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        });
    }

    // ---------- Drag & Drop Reordering ----------
    const taskList = document.querySelector('.task-list');
    if (taskList) {
        Sortable.create(taskList, {
            handle: '.task-drag-handle',
            animation: 200,
            ghostClass: 'task-ghost',
            onEnd: function (evt) {
                const ids = [];
                taskList.querySelectorAll('.task-item').forEach(function (el) {
                    ids.push(el.dataset.taskId);
                });
                sendReorder('tasks', ids, 0);
            }
        });
    }

    // Subtask reordering within each subtask list
    document.querySelectorAll('.subtask-list').forEach(function (list) {
        Sortable.create(list, {
            animation: 150,
            ghostClass: 'subtask-ghost',
            handle: '.subtask-toggle',
            onEnd: function (evt) {
                const taskId = list.dataset.taskId;
                const ids = [];
                list.querySelectorAll('.subtask-item').forEach(function (el) {
                    ids.push(el.dataset.subId);
                });
                sendReorder('subtasks', ids, taskId);
            }
        });
    });

    function sendReorder(type, ids, taskId) {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('type', type);
        formData.append('task_id', taskId);
        ids.forEach(function (id) { formData.append('ids[]', id); });

        fetch('tasks/reorder.php', {
            method: 'POST',
            body: formData
        }).catch(function () {});
    }

    // ---------- Subtask Add ----------
    document.querySelectorAll('.subtask-add-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const input = form.querySelector('.subtask-input');
            const title = input.value.trim();
            if (!title) return;

            const taskId = form.dataset.taskId;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('action', 'add');
            fd.append('task_id', taskId);
            fd.append('title', title);

            fetch('tasks/subtask.php', {
                method: 'POST',
                body: fd
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    const list = form.parentElement.querySelector('.subtask-list');
                    const item = document.createElement('div');
                    item.className = 'subtask-item';
                    item.dataset.subId = data.id;
                    item.innerHTML =
                        '<button class="subtask-toggle" data-id="' + data.id + '" title="Mark complete">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                        '</button>' +
                        '<span class="subtask-title">' + escapeHtml(data.title) + '</span>' +
                        '<button class="subtask-delete" data-id="' + data.id + '" title="Delete subtask">' +
                        '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
                        '</button>';
                    list.appendChild(item);
                    input.value = '';
                    // Init sortable on the new item
                    if (!list.sortableInstance) {
                        list.sortableInstance = Sortable.create(list, {
                            animation: 150,
                            ghostClass: 'subtask-ghost',
                            handle: '.subtask-toggle',
                            onEnd: function (evt) {
                                const ids = [];
                                list.querySelectorAll('.subtask-item').forEach(function (el) {
                                    ids.push(el.dataset.subId);
                                });
                                sendReorder('subtasks', ids, taskId);
                            }
                        });
                    }
                    attachSubtaskEvents(item, list);
                }
            })
            .finally(function () { btn.disabled = false; });
        });
    });

    // ---------- Subtask toggle & delete ----------
    document.querySelectorAll('.subtask-list').forEach(function (list) {
        list.querySelectorAll('.subtask-item').forEach(function (item) {
            attachSubtaskEvents(item, list);
        });
    });

    function attachSubtaskEvents(item, list) {
        const toggleBtn = item.querySelector('.subtask-toggle');
        const delBtn = item.querySelector('.subtask-delete');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const id = this.dataset.id;
                const isComplete = this.classList.contains('checked');
                const action = isComplete ? 'uncomplete' : 'complete';

                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('action', action);
                fd.append('id', id);

                fetch('tasks/subtask.php', {
                    method: 'POST',
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        this.classList.toggle('checked');
                        const title = item.querySelector('.subtask-title');
                        title.classList.toggle('completed');
                    }
                }.bind(this));
            });
        }

        if (delBtn) {
            delBtn.addEventListener('click', function () {
                const id = this.dataset.id;
                if (!confirm('Delete this subtask?')) return;

                const fd = new FormData();
                fd.append('csrf_token', csrfToken);
                fd.append('action', 'delete');
                fd.append('id', id);

                fetch('tasks/subtask.php', {
                    method: 'POST',
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        item.remove();
                    }
                });
            });
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
