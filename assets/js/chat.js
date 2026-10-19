/* 5 Star Chat — frontend (with mobile support) */
(function () {
    const API = window.BASE_URL + 'api/';
    let currentConv = 0;
    let lastMsgId = 0;
    let pollTimer = null;

    function getCsrf() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    async function apiCall(endpoint, opts = {}) {
        const res = await fetch(API + endpoint, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(opts.headers || {}) },
            ...opts
        });
        return res.json();
    }

    /* ============ CONVERSATIONS LIST ============ */
    async function loadConversations() {
        const data = await apiCall('chat-list.php');
        const list = document.getElementById('chatList');
        if (!data.success) { list.innerHTML = '<div class="p-3 text-danger small">Error loading</div>'; return; }

        if (data.conversations.length === 0) {
            list.innerHTML = '<div class="p-4 text-center text-muted small"><i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>No conversations yet.<br>Click <strong>+</strong> to start one.</div>';
            return;
        }

        list.innerHTML = data.conversations.map(c => {
            const initials = (c.other.display || 'C').split(' ').map(x=>x[0]).slice(0,2).join('').toUpperCase();
            const isActive = c.id == currentConv ? 'active' : '';
            let preview = c.last_msg || 'No messages yet';
            if (c.last_type === 'system') preview = '⚙️ ' + preview;
            if (c.type === 'order' && c.order_number) preview = '📦 ' + c.order_number + ' · ' + preview;
            const time = c.last_message_at ? new Date(c.last_message_at.replace(' ','T')).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) : '';
            const badge = c.unread > 0 ? `<span class="badge text-bg-danger">${c.unread}</span>` : '';

            return `<div class="chat-item ${isActive}" data-id="${c.id}">
                <div class="d-flex gap-2 align-items-start">
                    <div class="avatar">${initials}</div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between">
                            <div class="name">${escapeHtml(c.other.display)}</div>
                            <div class="meta">${time}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="preview">${escapeHtml(preview).substring(0,45)}</div>
                            ${badge}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');

        list.querySelectorAll('.chat-item').forEach(el => {
            el.addEventListener('click', () => openConversation(el.dataset.id));
        });
    }

    /* ============ OPEN CONVERSATION ============ */
    async function openConversation(id) {
        currentConv = id;
        lastMsgId = 0;

        // Mobile: slide in the message panel
        if (window.innerWidth <= 767) {
            const wrap = document.querySelector('.chat-wrap');
            if (wrap) wrap.classList.add('mobile-conversation-open');
        }

        document.getElementById('chatMessages').innerHTML = '<div class="text-center text-muted p-4">Loading...</div>';

        const data = await apiCall('chat-fetch.php?conversation_id=' + id + '&last_id=0');
        if (!data.success) {
            document.getElementById('chatMessages').innerHTML = '<div class="p-4 text-danger">Access denied</div>';
            return;
        }

        renderHead(data);
        renderMessages(data.messages, true);
        document.getElementById('chatInput').style.display = 'block';
        markRead(id);

        // update chat list to clear badge
        loadConversations();

        // start polling
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollMessages, 3000);
    }

    function renderHead(data) {
        const others = data.participants.filter(p => p.id != data.me);
        const name = others.length === 1 ? others[0].display : (data.conversation.title || 'Group Chat');
        const orderTag = data.conversation.order_number ? ` · <span class="badge text-bg-primary">${data.conversation.order_number}</span>` : '';
        document.getElementById('chatHead').innerHTML = `
            <div class="d-flex align-items-center">
                <button type="button" class="chat-back-btn" onclick="window.closeMobileChat()" aria-label="Back">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div>
                    <div class="fw-bold">${escapeHtml(name)}${orderTag}</div>
                    <div class="text-muted small">${data.participants.length} participant(s)</div>
                </div>
            </div>`;
    }

    function renderMessages(messages, replace = false) {
        const box = document.getElementById('chatMessages');
        if (replace) box.innerHTML = '';
        if (messages.length === 0 && replace) {
            box.innerHTML = '<div class="chat-empty text-center text-muted">No messages yet. Say hi!</div>';
            return;
        }

        let maxId = lastMsgId;
        messages.forEach(m => {
            if (m.id > maxId) maxId = m.id;
            const mine = m.sender_id == window.CURRENT_USER_ID;
            const cls = m.message_type === 'system' ? 'system' : (mine ? 'mine' : 'other');
            const time = new Date(m.created_at.replace(' ','T')).toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
            let bubble = escapeHtml(m.message);
            if (m.message_type === 'image') {
                bubble = `<img src="${window.BASE_URL}${m.message}" alt="Image">`;
            }

            box.insertAdjacentHTML('beforeend', `
                <div class="msg ${cls}">
                    ${!mine && m.message_type !== 'system' ? `<div class="info mb-1">${escapeHtml(m.sender_name)}</div>` : ''}
                    <div class="bubble">${bubble}</div>
                    <div class="info">${time}</div>
                </div>`);
        });
        lastMsgId = maxId;
        box.scrollTop = box.scrollHeight;
    }

    async function pollMessages() {
        if (!currentConv) return;
        const data = await apiCall('chat-fetch.php?conversation_id=' + currentConv + '&last_id=' + lastMsgId);
        if (data.success && data.messages.length > 0) {
            renderMessages(data.messages, false);
            markRead(currentConv);
        }
        loadConversations();
    }

    function markRead(id) {
        const fd = new FormData();
        fd.append('csrf_token', getCsrf());
        fd.append('conversation_id', id);
        fetch(API + 'chat-read.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    /* ============ SEND MESSAGE ============ */
    document.getElementById('sendForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('msgInput');
        const text = input.value.trim();
        if (!text || !currentConv) return;

        const fd = new FormData();
        fd.append('csrf_token', getCsrf());
        fd.append('conversation_id', currentConv);
        fd.append('message', text);

        const data = await apiCall('chat-send.php', { method: 'POST', body: fd });
        if (data.success) {
            input.value = '';
            await pollMessages();
        } else {
            alert(data.message || 'Failed to send');
        }
    });

    /* ============ NEW CHAT MODAL ============ */
    async function loadContacts() {
        const sel = document.getElementById('contactSelect');
        const contacts = window.CHAT_CONTACTS || [];
        if (contacts.length === 0) { sel.innerHTML = '<option value="">No contacts</option>'; return; }
        sel.innerHTML = '<option value="">— Select —</option>' + contacts.map(c =>
            `<option value="${c.id}">${escapeHtml(c.display)} (${escapeHtml(c.role_label)})</option>`
        ).join('');
    }

    document.getElementById('startChatBtn').addEventListener('click', async () => {
        const uid = document.getElementById('contactSelect').value;
        if (!uid) return alert('Select a contact');
        const fd = new FormData();
        fd.append('csrf_token', getCsrf());
        fd.append('user_id', uid);
        const data = await apiCall('chat-start.php', { method: 'POST', body: fd });
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
            await loadConversations();
            openConversation(data.conversation_id);
        } else {
            alert(data.message || 'Failed');
        }
    });

    /* ============ SEARCH ============ */
    document.getElementById('chatSearch').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.chat-item').forEach(el => {
            const name = el.querySelector('.name')?.textContent.toLowerCase() || '';
            const preview = el.querySelector('.preview')?.textContent.toLowerCase() || '';
            const match = name.includes(q) || preview.includes(q);
            el.style.display = match ? '' : 'none';
        });
    });

    /* ============ HELPERS ============ */
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    /* ============ MOBILE BACK BUTTON (inside IIFE) ============ */
    function closeMobileChat() {
        const wrap = document.querySelector('.chat-wrap');
        if (wrap) wrap.classList.remove('mobile-conversation-open');
        currentConv = 0;
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        loadConversations();
    }
    // Expose globally so onclick in HTML can call it
    window.closeMobileChat = closeMobileChat;

    // ESC key closes on mobile
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth <= 767) {
            closeMobileChat();
        }
    });

    /* ============ INIT ============ */
    document.addEventListener('DOMContentLoaded', () => {
        loadContacts();
        loadConversations();
    });
})();