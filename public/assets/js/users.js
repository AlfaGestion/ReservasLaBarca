(function () {
    const state = window.userRegisterState || {};
    const isEmbedded = Boolean(state.embedded || window.top !== window.self);
    const initialUserId = String(state.selectedUserId || new URLSearchParams(window.location.search).get('user_id') || '');

    const selectUser = document.getElementById('selectUser');
    const formUsers = document.getElementById('formUsers');
    const formButtons = document.getElementById('formButtons');
    const buttonEdit = document.getElementById('buttonEdit');
    const feedbackBox = document.getElementById('userRegisterFeedback');
    const editFormContainer = document.getElementById('formselectUser');
    const titleElement = document.getElementById('userFormTitle');
    const previewElement = document.getElementById('userManagementPreviewText');
    const statusBadgeElement = document.getElementById('userRegisterStatusBadge');
    const cancelButton = document.getElementById('cancelUserManagement');

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#39;',
            };

            return map[character] || character;
        });
    }

    function setFeedback(type, message) {
        if (!feedbackBox) {
            if (type === 'danger') {
                window.alert(message);
            }

            return;
        }

        feedbackBox.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <small>${escapeHtml(message)}</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
    }

    function clearFeedback() {
        if (feedbackBox) {
            feedbackBox.innerHTML = '';
        }
    }

    function setModeLabels(mode) {
        const isEditMode = mode === 'edit';

        if (titleElement) {
            titleElement.textContent = isEditMode ? 'Editar usuario' : 'Crear usuario nuevo';
        }

        if (previewElement) {
            previewElement.textContent = isEditMode ? 'Modo edicion' : 'Nuevo usuario';
        }

        if (statusBadgeElement) {
            statusBadgeElement.textContent = isEditMode ? 'Editando usuario' : 'Nuevo usuario';
            statusBadgeElement.classList.toggle('customer-status-badge--success', isEditMode);
            statusBadgeElement.classList.toggle('customer-status-badge--secondary', !isEditMode);
        }
    }

    function syncCsrfTokens(payload) {
        const csrf = payload?.csrf;
        if (!csrf?.name || !csrf?.hash) {
            return;
        }

        document.querySelectorAll(`input[name="${csrf.name}"]`).forEach((input) => {
            input.value = csrf.hash;
        });
    }

    function notifyParent(action, payload = {}) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'user-management-saved',
                action,
                userId: payload.userId ?? null,
                message: payload.message || '',
            }, window.location.origin);
        }
    }

    function showCreateMode() {
        if (formUsers) {
            formUsers.classList.remove('d-none');
        }

        if (formButtons) {
            formButtons.classList.add('d-none');
        }

        if (editFormContainer) {
            editFormContainer.innerHTML = '';
        }

        setModeLabels('create');
    }

    function showEditMode() {
        if (formUsers) {
            formUsers.classList.add('d-none');
        }

        if (formButtons) {
            formButtons.classList.remove('d-none');
        }

        setModeLabels('edit');
    }

    async function getUser(id) {
        const response = await fetch(`${baseUrl}/getUser/${id}`);
        const responseData = await response.json();

        if (!response.ok || responseData.error) {
            throw new Error(responseData.message || 'No se pudo cargar el usuario.');
        }

        return responseData;
    }

    function fillForm(user) {
        if (!editFormContainer) {
            return;
        }

        const superadmin = String(user.superadmin) === '1' ? 'checked' : '';

        editFormContainer.innerHTML = `
            <div class="form-group has-feedback mb-3 d-flex align-items-center justify-content-center">
                <input type="text" name="user" value="${escapeHtml(user.user)}" id="userEdit" class="form-control" placeholder="Usuario">
            </div>

            <div class="form-group has-feedback mb-3 d-flex align-items-center justify-content-center">
                <input type="password" name="password" id="passwordEdit" class="form-control" placeholder="Contrasena">
            </div>

            <div class="form-group has-feedback mb-3 d-flex align-items-center justify-content-center">
                <input type="password" name="repeat_password" id="repeatPasswordEdit" class="form-control" placeholder="Repetir contrasena">
            </div>

            <div class="form-group has-feedback mb-3 d-flex align-items-center justify-content-center">
                <input type="text" name="name" class="form-control" value="${escapeHtml(user.name)}" id="nameEdit" placeholder="Nombre">
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="superadmin" id="superadminRadioEdit" ${superadmin}>
                <label class="form-check-label" for="superadminRadioEdit">
                    Superadmin
                </label>
            </div>
        `;
    }

    async function handleCreateSubmit(event) {
        if (!formUsers || !isEmbedded) {
            return;
        }

        event.preventDefault();
        setFeedback('info', 'Guardando usuario...');

        try {
            const response = await fetch(formUsers.action || `${baseUrl}/auth/register`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: new FormData(formUsers),
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            syncCsrfTokens(payload);

            if (!response.ok || payload?.error) {
                setFeedback('danger', payload?.message || 'No se pudo crear el usuario.');
                return;
            }

            setFeedback('success', payload.message || 'Usuario creado correctamente.');
            notifyParent('create', {
                userId: payload?.user?.id ?? null,
                message: payload?.message || 'Usuario creado correctamente.',
            });
        } catch (error) {
            console.error(error);
            setFeedback('danger', 'No se pudo crear el usuario.');
        }
    }

    async function editUser(data) {
        try {
            const response = await fetch(`${baseUrl}/editUser`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });

            const responseData = await response.json();
            syncCsrfTokens(responseData);

            if (!response.ok || responseData.error) {
                setFeedback('danger', responseData.message || 'No se pudo actualizar el usuario.');
                return;
            }

            if (isEmbedded) {
                setFeedback('success', responseData.message || 'Usuario actualizado correctamente.');
                notifyParent('edit', {
                    userId: responseData?.user?.id ?? data.id ?? null,
                    message: responseData?.message || 'Usuario actualizado correctamente.',
                });
                return;
            }

            window.alert(responseData.message || 'Usuario actualizado correctamente.');
            window.location.reload();
        } catch (error) {
            console.error(error);
            setFeedback('danger', 'No se pudo actualizar el usuario.');
        }
    }

    async function handleEditAction(event) {
        if (!buttonEdit) {
            return;
        }

        event.preventDefault();

        const currentUserId = selectUser?.value || initialUserId;
        const userEdit = document.getElementById('userEdit');
        const passwordEdit = document.getElementById('passwordEdit');
        const repeatPasswordEdit = document.getElementById('repeatPasswordEdit');
        const nameEdit = document.getElementById('nameEdit');
        const superadminRadio = document.getElementById('superadminRadioEdit');

        if (!currentUserId) {
            setFeedback('danger', 'Seleccioná un usuario para editar.');
            return;
        }

        if (!userEdit || !passwordEdit || !repeatPasswordEdit || !nameEdit || !superadminRadio) {
            setFeedback('danger', 'No se pudo preparar el formulario de edición.');
            return;
        }

        if (passwordEdit.value === '' || repeatPasswordEdit.value === '') {
            setFeedback('danger', 'Debés completar la contraseña.');
            return;
        }

        if (passwordEdit.value !== repeatPasswordEdit.value) {
            setFeedback('danger', 'Las contraseñas no coinciden.');
            return;
        }

        await editUser({
            id: currentUserId,
            user: userEdit.value,
            password: passwordEdit.value,
            name: nameEdit.value,
            superadmin: superadminRadio.checked,
        });
    }

    document.addEventListener('change', async (event) => {
        if (!selectUser || event.target !== selectUser) {
            return;
        }

        clearFeedback();

        const userId = String(selectUser.value || '');
        if (!userId) {
            showCreateMode();
            return;
        }

        showEditMode();

        try {
            const user = await getUser(userId);
            fillForm(user.data || {});
        } catch (error) {
            console.error(error);
            setFeedback('danger', error.message || 'No se pudo cargar el usuario.');
            showCreateMode();

            if (selectUser) {
                selectUser.value = '';
            }
        }
    });

    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            clearFeedback();

            if (selectUser) {
                selectUser.value = '';
                selectUser.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                showCreateMode();
            }
        });
    }

    document.addEventListener('click', (event) => {
        if (!buttonEdit || event.target !== buttonEdit) {
            return;
        }

        handleEditAction(event);
    });

    if (formUsers) {
        formUsers.addEventListener('submit', handleCreateSubmit);
    }

    if (initialUserId && selectUser) {
        selectUser.value = String(initialUserId);
        selectUser.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
        showCreateMode();
    }
})();
