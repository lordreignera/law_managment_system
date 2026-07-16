const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const initialsFor = (name) => String(name || 'U')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() || '')
    .join('');

const attachmentHtml = (attachments = []) => {
    if (!attachments.length) {
        return '';
    }

    const items = attachments.map((attachment) => `
        <div class="kfms-chat-attachment">
            <i class="mdi mdi-file-document-outline"></i>
            <span>
                <strong>${escapeHtml(attachment.name)}</strong>
                <small>${Number((attachment.size || 0) / 1024).toFixed(1)} KB</small>
            </span>
            <a href="${escapeHtml(attachment.download_url)}" title="Download ${escapeHtml(attachment.name)}">
                <i class="mdi mdi-download"></i>
            </a>
        </div>
    `).join('');

    return `<div class="kfms-chat-attachments">${items}</div>`;
};

const renderStaffMessage = (payload, isMine) => `
    <article class="kfms-chat-message ${isMine ? 'is-mine' : ''}" data-message-id="${payload.id}">
        <span class="kfms-chat-avatar">${escapeHtml(initialsFor(payload.sender_name))}</span>
        <div>
            <header>
                <strong>${escapeHtml(payload.sender_name)}</strong>
                <time>${escapeHtml(payload.sent_at_display || '')}</time>
            </header>
            ${payload.body ? `<p>${escapeHtml(payload.body)}</p>` : ''}
            ${attachmentHtml(payload.attachments || [])}
        </div>
    </article>
`;

const renderClientMessage = (payload, isMine) => `
    <article class="${isMine ? 'is-mine' : ''}" data-message-id="${payload.id}">
        <strong>${escapeHtml(payload.sender_name)}</strong>
        ${payload.body ? `<p>${escapeHtml(payload.body)}</p>` : ''}
        <time>${escapeHtml(payload.sent_at_display || '')}</time>
    </article>
`;

document.addEventListener('DOMContentLoaded', () => {
    if (!window.Echo) {
        return;
    }

    document.querySelectorAll('[data-realtime-conversation]').forEach((thread) => {
        const conversationId = thread.dataset.realtimeConversation;
        const currentUserId = Number(thread.dataset.currentUser);

        if (!conversationId || thread.dataset.realtimeBound === '1') {
            return;
        }

        thread.dataset.realtimeBound = '1';

        window.Echo.private(`conversations.${conversationId}`)
            .listen('.message.sent', (payload) => {
                if (!payload?.id || thread.querySelector(`[data-message-id="${payload.id}"]`)) {
                    return;
                }

                const isMine = Number(payload.sender_id) === currentUserId;
                const html = thread.classList.contains('kfms-chat-thread')
                    ? renderStaffMessage(payload, isMine)
                    : renderClientMessage(payload, isMine);

                thread.insertAdjacentHTML('beforeend', html);
                thread.scrollTop = thread.scrollHeight;
            });
    });
});
