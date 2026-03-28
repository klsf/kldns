(function () {
    const { createApp } = window.Vue;
    const { ElLoading, ElMessage, ElMessageBox } = window.ElementPlus;

    function getQueryParam(name) {
        return decodeURIComponent(
            (new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(window.location.href) || [, ''])[1]
                .replace(/\+/g, '%20')
        ) || '';
    }

    function getCsrfToken() {
        const token = document.head.querySelector('meta[name="csrf-token"]');

        if (!token) {
            console.error('CSRF token not found.');
            return '';
        }

        return token.content;
    }

    function appendValue(formData, key, value) {
        if (value === undefined || value === null) {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach(function (item) {
                appendValue(formData, key + '[]', item);
            });
            return;
        }

        if (Object.prototype.toString.call(value) === '[object Object]') {
            Object.keys(value).forEach(function (childKey) {
                appendValue(formData, key + '[' + childKey + ']', value[childKey]);
            });
            return;
        }

        formData.append(key, value);
    }

    function toFormData(payload) {
        if (!payload) {
            return new FormData();
        }

        if (payload instanceof FormData) {
            return payload;
        }

        if (payload instanceof HTMLFormElement) {
            return new FormData(payload);
        }

        const formData = new FormData();

        if (typeof payload === 'string') {
            new URLSearchParams(payload).forEach(function (value, key) {
                formData.append(key, value);
            });
            return formData;
        }

        Object.keys(payload).forEach(function (key) {
            appendValue(formData, key, payload[key]);
        });

        return formData;
    }

    function mergePayloads(firstPayload, secondPayload) {
        const merged = new FormData();

        [firstPayload, secondPayload].forEach(function (payload) {
            const current = toFormData(payload);
            current.forEach(function (value, key) {
                merged.append(key, value);
            });
        });

        return merged;
    }

    function showMessage(message, type) {
        return ElMessage({
            message: message || '操作完成',
            type: type || 'info',
            dangerouslyUseHTMLString: true,
            duration: 2400,
            showClose: true
        });
    }

    function showAlert(message, title, options) {
        return ElMessageBox.alert(message || '操作完成', title || '提示', Object.assign({
            dangerouslyUseHTMLString: true,
            confirmButtonText: '确定'
        }, options || {}));
    }

    function showConfirm(message, title, options) {
        return ElMessageBox.confirm(message || '确认继续？', title || '提示', Object.assign({
            dangerouslyUseHTMLString: true,
            confirmButtonText: '确定',
            cancelButtonText: '取消',
            type: 'warning'
        }, options || {}));
    }

    async function post(url, params1, params2) {
        const loading = ElLoading.service({
            lock: true,
            text: '处理中...',
            background: 'rgba(8, 19, 31, 0.22)'
        });

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: mergePayloads(params1, params2)
            });

            if (response.status === 419) {
                await showAlert('页面已过期，请刷新页面后重试。', '会话失效', {
                    closeOnClickModal: false,
                    closeOnPressEscape: false
                });
                window.location.reload();
                return null;
            }

            if (!response.ok) {
                throw new Error(response.status + ' ' + response.statusText);
            }

            const contentType = response.headers.get('content-type') || '';

            if (contentType.indexOf('application/json') > -1) {
                return response.json();
            }

            return response.text();
        } catch (error) {
            await showAlert('网络出错了，请稍后再试。<br>' + error.message, '请求失败');
            throw error;
        } finally {
            loading.close();
        }
    }

    function getForm(id) {
        return document.getElementById(id);
    }

    function getFormData(id) {
        const form = getForm(id);
        return form ? new FormData(form) : new FormData();
    }

    function refreshCaptcha(id) {
        const image = document.getElementById(id);

        if (image) {
            image.src = '/captcha?_=' + Math.random();
        }
    }

    function openModalById(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.style.display = 'block';
        modal.classList.add('show');
        modal.setAttribute('aria-modal', 'true');
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');

        if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }

    function closeModalById(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');

        if (!document.querySelector('.modal.show')) {
            document.body.classList.remove('modal-open');
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }

    function activateCurrentSidebarLink() {
        const pathname = window.location.pathname;
        document.querySelectorAll('.sidebar-panel a').forEach(function (link) {
            if (link.getAttribute('href') === pathname) {
                const item = link.closest('.menu-item');
                if (item) {
                    item.classList.add('active');
                }
            }
        });
    }

    function toggleSidebar() {
        document.body.classList.toggle('sidebar-open');
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
    }

    function registerGlobalInteractions() {
        document.addEventListener('click', function (event) {
            const modalTrigger = event.target.closest('[data-toggle="modal"]');
            if (modalTrigger) {
                event.preventDefault();
                const target = modalTrigger.getAttribute('href') || modalTrigger.dataset.target;
                if (target && target.charAt(0) === '#') {
                    openModalById(target.slice(1));
                }
                return;
            }

            const dismissTrigger = event.target.closest('[data-dismiss="modal"]');
            if (dismissTrigger) {
                event.preventDefault();
                const modal = dismissTrigger.closest('.modal');
                if (modal) {
                    closeModalById(modal.id);
                }
                return;
            }

            const dropdownTrigger = event.target.closest('.topbar .dropdown-toggle');
            const activeDropdown = document.querySelector('.topbar .dropdown.is-open');

            if (dropdownTrigger) {
                event.preventDefault();
                const dropdown = dropdownTrigger.closest('.dropdown');

                if (!dropdown) {
                    return;
                }

                if (activeDropdown && activeDropdown !== dropdown) {
                    activeDropdown.classList.remove('is-open');
                }

                dropdown.classList.toggle('is-open');
                return;
            }

            if (activeDropdown && !event.target.closest('.topbar .dropdown')) {
                activeDropdown.classList.remove('is-open');
            }

            if (event.target.classList.contains('modal')) {
                closeModalById(event.target.id);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const activeModal = document.querySelector('.modal.show');
                if (activeModal) {
                    closeModalById(activeModal.id);
                }
            }
        });

        activateCurrentSidebarLink();
    }

    function createVuePage(selector, options) {
        const app = createApp(options);
        app.config.compilerOptions.delimiters = ['@{{', '}}'];
        app.use(window.ElementPlus);

        app.config.globalProperties.$post = post;
        app.config.globalProperties.$message = function (message, type) {
            return showMessage(message, type);
        };
        app.config.globalProperties.$alert = function (message, title, options) {
            return showAlert(message, title, options);
        };
        app.config.globalProperties.$confirm = function (message, title, options) {
            return showConfirm(message, title, options);
        };
        app.config.globalProperties.$confirmAction = function (message, callback, title, options) {
            return showConfirm(message, title, options).then(callback).catch(function () {
                return null;
            });
        };
        app.config.globalProperties.$form = function (id) {
            return getFormData(id);
        };
        app.config.globalProperties.$query = getQueryParam;
        app.config.globalProperties.$refreshCaptcha = refreshCaptcha;
        app.config.globalProperties.$openModal = openModalById;
        app.config.globalProperties.$closeModal = closeModalById;

        return app.mount(selector);
    }

    window.$_GET = getQueryParam;
    window.$post = post;
    window.toggleSidebar = toggleSidebar;
    window.closeSidebar = closeSidebar;
    window.openModalById = openModalById;
    window.closeModalById = closeModalById;
    window.createVuePage = createVuePage;

    registerGlobalInteractions();
})();
