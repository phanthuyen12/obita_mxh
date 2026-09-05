(function () {
    'use strict';

    var script = document.currentScript;
    var publicKey = script && script.getAttribute('data-public-key');
    if (!publicKey || document.querySelector('[data-kinghub-chat-root]')) return;

    var apiBase = new URL('/api/website-chat/v1', script.src).toString().replace(/\/$/, '');
    var storagePrefix = 'kinghub_chat_' + publicKey.slice(-12);
    var visitorId = localStorage.getItem(storagePrefix + '_visitor') || crypto.randomUUID();
    var token = localStorage.getItem(storagePrefix + '_token');
    localStorage.setItem(storagePrefix + '_visitor', visitorId);

    var host = document.createElement('div');
    host.setAttribute('data-kinghub-chat-root', '');
    document.body.appendChild(host);
    var root = host.attachShadow({ mode: 'open' });
    var state = { open: false, config: null, messages: [], sending: false, selectedImage: null };

    root.innerHTML = '<style>' +
        ':host{all:initial}.kh-launcher{position:fixed;right:20px;bottom:20px;z-index:2147483000;width:58px;height:58px;border:0;border-radius:999px;background:#2563eb;color:white;box-shadow:0 12px 35px #0003;cursor:pointer;font:700 24px system-ui}.kh-panel{position:fixed;right:20px;bottom:90px;z-index:2147483000;width:min(380px,calc(100vw - 24px));height:min(580px,calc(100vh - 120px));display:none;grid-template-rows:auto 1fr auto;border:1px solid #ddd;border-radius:18px;background:white;box-shadow:0 20px 60px #0003;overflow:hidden;font:14px/1.4 system-ui;color:#18181b}.kh-panel.open{display:grid}.kh-header{padding:16px;background:#2563eb;color:white}.kh-header strong{display:block;font-size:16px}.kh-status{font-size:12px;opacity:.85}.kh-messages{padding:14px;overflow:auto;background:#f8fafc;display:flex;flex-direction:column;gap:9px}.kh-message{max-width:82%;padding:9px 12px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere}.kh-message img{display:block;max-width:100%;margin-top:6px;border-radius:9px}.kh-in{align-self:flex-end;background:#2563eb;color:white;border-bottom-right-radius:4px}.kh-out{align-self:flex-start;background:white;border:1px solid #e5e7eb;border-bottom-left-radius:4px}.kh-welcome{color:#64748b;text-align:center;padding:20px}.kh-start{display:flex;flex-direction:column;gap:10px;margin:auto;padding:22px;width:100%;box-sizing:border-box}.kh-start p{margin:0 0 4px;color:#64748b}.kh-field{width:100%;box-sizing:border-box;border:1px solid #d4d4d8;border-radius:10px;padding:11px;font:inherit;color:inherit;resize:vertical}.kh-form{display:flex;align-items:flex-end;gap:8px;padding:12px;border-top:1px solid #e5e7eb;background:white}.kh-input{min-width:0;flex:1;resize:none;border:1px solid #d4d4d8;border-radius:10px;padding:10px;font:inherit;color:inherit}.kh-send,.kh-start-button{border:0;border-radius:10px;padding:11px 15px;background:#2563eb;color:white;font-weight:700;cursor:pointer}.kh-send:disabled,.kh-start-button:disabled{opacity:.5}.kh-file-label{width:38px;height:38px;display:grid;place-items:center;border:1px solid #d4d4d8;border-radius:10px;cursor:pointer;font-size:18px}.kh-file{display:none}.kh-file-name{position:absolute;left:12px;right:12px;bottom:62px;padding:7px 10px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;color:#1e40af;font-size:12px}.kh-error{padding:8px 12px;color:#b91c1c;background:#fef2f2;font-size:12px}@media(max-width:520px){.kh-panel{inset:0;width:100vw;height:100dvh;border-radius:0}.kh-launcher{right:16px;bottom:16px}.kh-panel.open+.kh-launcher{display:none}}' +
        '</style><section class="kh-panel" aria-label="Hỗ trợ trực tuyến"><header class="kh-header"><strong class="kh-title">Hỗ trợ trực tuyến</strong><span class="kh-status">Đang kết nối...</span></header><div class="kh-messages" aria-live="polite"></div><form class="kh-start"><p>Vui lòng để lại thông tin để bắt đầu trò chuyện.</p><input class="kh-field kh-email" type="email" maxlength="255" autocomplete="email" placeholder="Email của bạn" required><textarea class="kh-field kh-support-request" rows="4" maxlength="5000" placeholder="Nội dung bạn cần hỗ trợ" required></textarea><button class="kh-start-button" type="submit">Bắt đầu trò chuyện</button></form><form class="kh-form" hidden><label class="kh-file-label" title="Gửi hình ảnh (tối đa 5 MB)">📎<input class="kh-file" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></label><textarea class="kh-input" rows="1" maxlength="5000" placeholder="Nhập tin nhắn..." aria-label="Tin nhắn"></textarea><button class="kh-send" type="submit">Gửi</button></form></section><button class="kh-launcher" type="button" aria-label="Mở cửa sổ chat">💬</button>';

    var panel = root.querySelector('.kh-panel');
    var launcher = root.querySelector('.kh-launcher');
    var messages = root.querySelector('.kh-messages');
    var form = root.querySelector('.kh-form');
    var startForm = root.querySelector('.kh-start');
    var startButton = root.querySelector('.kh-start-button');
    var fileInput = root.querySelector('.kh-file');
    var input = root.querySelector('.kh-input');
    var sendButton = root.querySelector('.kh-send');

    function request(path, options) {
        options = options || {};
        options.headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
        if (!(options.body instanceof FormData)) options.headers['Content-Type'] = 'application/json';
        if (token) options.headers.Authorization = 'Bearer ' + token;
        return fetch(apiBase + path, options).then(function (response) {
            if (!response.ok) throw new Error(String(response.status));
            return response.json();
        });
    }

    function render() {
        messages.innerHTML = '';
        if (!state.messages.length) {
            var welcome = document.createElement('div');
            welcome.className = 'kh-welcome';
            welcome.textContent = state.config.branding.welcome_message;
            messages.appendChild(welcome);
        }
        state.messages.forEach(function (message) {
            var bubble = document.createElement('div');
            bubble.className = 'kh-message ' + (message.direction === 'inbound' ? 'kh-in' : 'kh-out');
            bubble.textContent = message.body || '';
            (message.attachments || []).forEach(function (attachment) {
                if (attachment.type !== 'image') return;
                var image = document.createElement('img');
                image.src = attachment.url;
                image.alt = attachment.file_name || 'Hình ảnh đính kèm';
                image.loading = 'lazy';
                bubble.appendChild(image);
            });
            messages.appendChild(bubble);
        });
        messages.scrollTop = messages.scrollHeight;
    }

    function ensureSession(email, supportRequest) {
        if (token) return Promise.resolve();
        return request('/sessions?public_key=' + encodeURIComponent(publicKey), {
            method: 'POST',
            body: JSON.stringify({ visitor_id: visitorId, email: email, support_request: supportRequest, locale: document.documentElement.lang || 'vi', context: { page_url: location.href, page_title: document.title } })
        }).then(function (payload) {
            token = payload.token;
            localStorage.setItem(storagePrefix + '_token', token);
            startForm.hidden = true;
            form.hidden = false;
        });
    }

    function poll() {
        if (!token) return;
        request('/messages').then(function (payload) {
            state.messages = payload.messages;
            render();
            root.querySelector('.kh-status').textContent = 'Sẵn sàng hỗ trợ';
        }).catch(function (error) {
            if (error.message === '401') {
                token = null;
                localStorage.removeItem(storagePrefix + '_token');
                startForm.hidden = false;
                form.hidden = true;
            }
        });
    }

    function showError() {
        root.querySelector('.kh-status').textContent = 'Mất kết nối — đang thử lại';
    }

    launcher.addEventListener('click', function () {
        state.open = !state.open;
        panel.classList.toggle('open', state.open);
        launcher.setAttribute('aria-label', state.open ? 'Đóng cửa sổ chat' : 'Mở cửa sổ chat');
        if (state.open) { (token ? input : root.querySelector('.kh-email')).focus(); poll(); }
    });

    startForm.addEventListener('submit', function (event) {
        event.preventDefault();
        var email = root.querySelector('.kh-email').value.trim();
        var supportRequest = root.querySelector('.kh-support-request').value.trim();
        if (!email || !supportRequest || state.sending) return;
        state.sending = true;
        startButton.disabled = true;
        ensureSession(email, supportRequest).then(function () {
            poll();
            input.focus();
        }).catch(showError).finally(function () {
            state.sending = false;
            startButton.disabled = false;
        });
    });

    fileInput.addEventListener('change', function () {
        var image = fileInput.files && fileInput.files[0];
        root.querySelectorAll('.kh-file-name').forEach(function (element) { element.remove(); });
        if (!image) { state.selectedImage = null; return; }
        if (!image.type.startsWith('image/') || image.size > 5 * 1024 * 1024) {
            fileInput.value = '';
            state.selectedImage = null;
            root.querySelector('.kh-status').textContent = 'Ảnh phải đúng định dạng và không quá 5 MB';
            return;
        }
        state.selectedImage = image;
        var label = document.createElement('div');
        label.className = 'kh-file-name';
        label.textContent = 'Đã chọn: ' + image.name;
        panel.appendChild(label);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = input.value.trim();
        if ((!body && !state.selectedImage) || state.sending) return;
        state.sending = true;
        sendButton.disabled = true;
        var payload = new FormData();
        payload.append('client_id', crypto.randomUUID());
        if (body) payload.append('body', body);
        if (state.selectedImage) payload.append('image', state.selectedImage);
        request('/messages', { method: 'POST', body: payload }).then(function () {
            input.value = '';
            fileInput.value = '';
            state.selectedImage = null;
            root.querySelectorAll('.kh-file-name').forEach(function (element) { element.remove(); });
            poll();
        }).catch(showError).finally(function () {
            state.sending = false;
            sendButton.disabled = false;
        });
    });

    request('/config?public_key=' + encodeURIComponent(publicKey)).then(function (payload) {
        state.config = payload;
        root.querySelector('.kh-title').textContent = payload.channel.name;
        root.querySelector('.kh-status').textContent = 'Sẵn sàng hỗ trợ';
        root.querySelectorAll('.kh-header,.kh-send,.kh-launcher').forEach(function (element) { element.style.background = payload.branding.primary_color; });
        if (payload.branding.position === 'left') root.querySelectorAll('.kh-panel,.kh-launcher').forEach(function (element) { element.style.right = 'auto'; element.style.left = '20px'; });
        render();
        startForm.hidden = Boolean(token);
        form.hidden = !token;
        if (token) poll();
        setInterval(poll, 3000);
    }).catch(function () { host.remove(); });
})();
