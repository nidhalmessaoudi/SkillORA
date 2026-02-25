(function () {
    var root = document.getElementById('skillora-chat-widget');
    if (!root || root.getAttribute('data-chat-init') === '1') {
        return;
    }
    root.setAttribute('data-chat-init', '1');

    var toggleButton = document.getElementById('skillora-chat-toggle');
    var panel = document.getElementById('skillora-chat-panel');
    var closeButton = document.getElementById('skillora-chat-close');
    var reminder = document.getElementById('skillora-chat-reminder');
    var historyToggleButton = document.getElementById('skillora-chat-history-toggle');
    var historyPanel = document.getElementById('skillora-chat-history');
    var historyList = document.getElementById('skillora-chat-history-list');
    var newConversationButton = document.getElementById('skillora-chat-new-conversation');
    var messagesBox = document.getElementById('skillora-chat-messages');
    var form = document.getElementById('skillora-chat-form');
    var input = document.getElementById('skillora-chat-input');
    var sendButton = form ? form.querySelector('.skillora-chat__send') : null;
    var endpoint = root.getAttribute('data-endpoint') || '/api/skillora/chat';

    if (!toggleButton || !panel || !messagesBox || !form || !input || !sendButton) {
        return;
    }

    var ARCHIVE_KEY = 'skillora_chat_archive_v2';
    var MAX_MESSAGES_PER_CONVERSATION = 30;
    var MAX_CONVERSATIONS = 30;
    var reminderTimer = null;
    var historyMode = false;
    var currentConversation = createNewConversation();

    showReminder(4500);
    renderMessages([]);
    renderHistoryList();

    toggleButton.addEventListener('click', function () {
        togglePanel();
    });

    if (closeButton) {
        closeButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            closePanel();
        });
    }

    root.addEventListener('click', function (event) {
        var closeTarget = event.target && event.target.closest ? event.target.closest('#skillora-chat-close') : null;
        if (closeTarget) {
            event.preventDefault();
            event.stopPropagation();
            closePanel();
        }
    });

    if (historyToggleButton) {
        historyToggleButton.addEventListener('click', function () {
            if (historyMode) {
                showChatMode();
            } else {
                showHistoryMode();
            }
        });
    }

    if (newConversationButton) {
        newConversationButton.addEventListener('click', function () {
            currentConversation = createNewConversation();
            showChatMode();
            renderMessages([]);
            input.focus();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.altKey && (event.key === 's' || event.key === 'S')) {
            event.preventDefault();
            togglePanel();
            return;
        }

        if (event.key === 'Escape') {
            closePanel();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });

    function createNewConversation() {
        var now = new Date().toISOString();
        return {
            id: 'conv_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
            createdAt: now,
            updatedAt: now,
            messages: [],
        };
    }

    function loadArchive() {
        try {
            var raw = localStorage.getItem(ARCHIVE_KEY);
            if (!raw) {
                return [];
            }

            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .filter(function (conv) {
                    return conv
                        && typeof conv.id === 'string'
                        && Array.isArray(conv.messages);
                })
                .slice(0, MAX_CONVERSATIONS)
                .map(function (conv) {
                    var messages = conv.messages
                        .filter(function (msg) {
                            return msg
                                && (msg.role === 'user' || msg.role === 'assistant')
                                && typeof msg.content === 'string';
                        })
                        .slice(-MAX_MESSAGES_PER_CONVERSATION);

                    return {
                        id: conv.id,
                        createdAt: typeof conv.createdAt === 'string' ? conv.createdAt : new Date().toISOString(),
                        updatedAt: typeof conv.updatedAt === 'string' ? conv.updatedAt : new Date().toISOString(),
                        messages: messages,
                    };
                });
        } catch (e) {
            return [];
        }
    }

    function saveArchive(archive) {
        try {
            localStorage.setItem(ARCHIVE_KEY, JSON.stringify(archive.slice(0, MAX_CONVERSATIONS)));
        } catch (e) {
            // noop
        }
    }

    function persistCurrentConversation() {
        if (!currentConversation.messages.length) {
            return;
        }

        currentConversation.updatedAt = new Date().toISOString();
        currentConversation.messages = currentConversation.messages.slice(-MAX_MESSAGES_PER_CONVERSATION);

        var archive = loadArchive().filter(function (conv) {
            return conv.id !== currentConversation.id;
        });

        archive.unshift({
            id: currentConversation.id,
            createdAt: currentConversation.createdAt,
            updatedAt: currentConversation.updatedAt,
            messages: currentConversation.messages.slice(),
        });

        saveArchive(archive);
    }

    function renderHistoryList() {
        if (!historyList) {
            return;
        }

        var archive = loadArchive();
        historyList.innerHTML = '';

        if (!archive.length) {
            var empty = document.createElement('p');
            empty.className = 'skillora-chat__history-empty';
            empty.textContent = 'Aucune conversation enregistrée.';
            historyList.appendChild(empty);
            return;
        }

        archive.forEach(function (conv) {
            var firstUserMessage = conv.messages.find(function (m) {
                return m.role === 'user' && m.content.trim() !== '';
            });
            var preview = firstUserMessage ? firstUserMessage.content.trim() : 'Conversation';
            if (preview.length > 70) {
                preview = preview.slice(0, 67) + '...';
            }

            var date = new Date(conv.updatedAt);
            var dateLabel = isNaN(date.getTime()) ? '' : date.toLocaleString();

            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'skillora-chat__history-item';

            var title = document.createElement('span');
            title.className = 'skillora-chat__history-item-title';
            title.textContent = preview;

            var meta = document.createElement('span');
            meta.className = 'skillora-chat__history-item-meta';
            meta.textContent = dateLabel;

            item.appendChild(title);
            item.appendChild(meta);

            item.addEventListener('click', function () {
                openArchivedConversation(conv.id);
            });

            historyList.appendChild(item);
        });
    }

    function openArchivedConversation(conversationId) {
        var archive = loadArchive();
        var found = archive.find(function (conv) {
            return conv.id === conversationId;
        });

        if (!found) {
            return;
        }

        currentConversation = {
            id: found.id,
            createdAt: found.createdAt,
            updatedAt: found.updatedAt,
            messages: found.messages.slice(-MAX_MESSAGES_PER_CONVERSATION),
        };

        showChatMode();
        renderMessages(currentConversation.messages);
        scrollMessagesToBottom();
    }

    function renderMessages(messages) {
        messagesBox.innerHTML = '';
        messages.forEach(function (message) {
            appendMessage(message.role, message.content, false);
        });
    }

    function appendMessage(role, text, pending) {
        var item = document.createElement('div');
        item.className = 'skillora-chat__msg ' + (role === 'user' ? 'skillora-chat__msg--user' : 'skillora-chat__msg--assistant');
        if (pending) {
            item.classList.add('skillora-chat__msg--pending');
            item.setAttribute('data-pending', '1');
        }
        item.textContent = text;
        messagesBox.appendChild(item);
        scrollMessagesToBottom();
        return item;
    }

    function pushMessage(role, content) {
        currentConversation.messages.push({
            role: role,
            content: content,
            at: new Date().toISOString(),
        });

        if (currentConversation.messages.length > MAX_MESSAGES_PER_CONVERSATION) {
            currentConversation.messages = currentConversation.messages.slice(-MAX_MESSAGES_PER_CONVERSATION);
        }

        persistCurrentConversation();
        renderHistoryList();
    }

    function scrollMessagesToBottom() {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function isOpen() {
        return !panel.hidden;
    }

    function openPanel() {
        hideReminder();
        panel.hidden = false;
        toggleButton.setAttribute('aria-expanded', 'true');
        if (historyMode) {
            showHistoryMode();
        } else {
            showChatMode();
        }
        setTimeout(function () {
            if (!historyMode) {
                input.focus();
            }
            scrollMessagesToBottom();
        }, 0);
    }

    function closePanel() {
        panel.hidden = true;
        toggleButton.setAttribute('aria-expanded', 'false');
        showReminder(4500);
    }

    function togglePanel() {
        if (isOpen()) {
            closePanel();
        } else {
            openPanel();
        }
    }

    function showHistoryMode() {
        historyMode = true;
        if (historyPanel) {
            historyPanel.hidden = false;
        }
        messagesBox.hidden = true;
        form.hidden = true;
        if (historyToggleButton) {
            historyToggleButton.textContent = 'Retour';
        }
        renderHistoryList();
    }

    function showChatMode() {
        historyMode = false;
        if (historyPanel) {
            historyPanel.hidden = true;
        }
        messagesBox.hidden = false;
        form.hidden = false;
        if (historyToggleButton) {
            historyToggleButton.textContent = 'Historique';
        }
    }

    function hideReminder() {
        if (!reminder) {
            return;
        }
        if (reminderTimer) {
            clearTimeout(reminderTimer);
            reminderTimer = null;
        }
        reminder.hidden = true;
    }

    function showReminder(duration) {
        if (!reminder) {
            return;
        }
        if (reminderTimer) {
            clearTimeout(reminderTimer);
            reminderTimer = null;
        }
        reminder.hidden = false;
        if (duration && duration > 0) {
            reminderTimer = setTimeout(function () {
                reminder.hidden = true;
            }, duration);
        }
    }

    function setSendingState(sending) {
        sendButton.disabled = sending;
        input.disabled = sending;
    }

    function sendMessage() {
        var text = input.value.trim();
        if (!text) {
            return;
        }

        if (!isOpen()) {
            openPanel();
        }
        showChatMode();

        appendMessage('user', text, false);
        pushMessage('user', text);

        input.value = '';
        setSendingState(true);

        var pendingMessage = appendMessage('assistant', '…', true);

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: text })
        })
            .then(function (response) {
                return response.json()
                    .catch(function () { return null; })
                    .then(function (data) {
                        return { ok: response.ok, data: data };
                    });
            })
            .then(function (payload) {
                var reply = payload && payload.data && typeof payload.data.reply === 'string' ? payload.data.reply.trim() : '';
                if (!payload.ok || !reply) {
                    throw new Error('chat-error');
                }
                pendingMessage.classList.remove('skillora-chat__msg--pending');
                pendingMessage.textContent = reply;
                pushMessage('assistant', reply);
            })
            .catch(function () {
                pendingMessage.classList.remove('skillora-chat__msg--pending');
                pendingMessage.textContent = 'Erreur, réessaie.';
                pushMessage('assistant', 'Erreur, réessaie.');
            })
            .finally(function () {
                setSendingState(false);
                input.focus();
                scrollMessagesToBottom();
            });
    }
})();
