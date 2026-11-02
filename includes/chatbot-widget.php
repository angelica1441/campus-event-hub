<?php
// Campus Event Hub - Professional Chat Widget

$chatbotEndpoint = function_exists('app_url')
    ? app_url('chatbot.php')
    : 'chatbot.php';
?>

<style>
#ce-chat-launcher,
#ce-chat-panel,
#ce-chat-panel * {
    box-sizing: border-box;
}

#ce-chat-launcher {
    position: fixed;
    right: 25px;
    bottom: 25px;
    z-index: 9998;
    width: 62px;
    height: 62px;
    border: none;
    border-radius: 50%;
    color: #fff;
    background: linear-gradient(135deg,#214637,#16382d);
    box-shadow: 0 12px 32px rgba(22,56,45,.32);
    cursor: pointer;
    font-size: 26px;
    transition: .2s ease;
}

#ce-chat-launcher:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 38px rgba(22,56,45,.4);
}

#ce-chat-panel {
    position: fixed;
    right: 25px;
    bottom: 100px;
    z-index: 9999;
    display: none;
    flex-direction: column;
    width: min(420px,calc(100vw - 28px));
    height: min(650px,calc(100vh - 115px));
    min-height: 390px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    box-shadow: 0 22px 65px rgba(15,23,42,.2);
    font-family: Inter,Arial,sans-serif;
}

#ce-chat-panel.ce-open {
    display: flex;
    animation: ceChatAppear .2s ease-out;
}

@keyframes ceChatAppear {
    from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.ce-chat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 17px;
    color: #fff;
    background: linear-gradient(135deg,#214637,#16382d);
}

.ce-chat-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    border-radius: 15px;
    background: rgba(255,255,255,.19);
    font-size: 23px;
}

.ce-chat-heading {
    flex: 1;
    min-width: 0;
}

.ce-chat-heading strong {
    display: block;
    font-size: 15px;
    font-weight: 750;
}

.ce-chat-heading span {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.85);
    font-size: 11px;
}

.ce-chat-header-actions {
    display: flex;
    gap: 5px;
}

.ce-chat-header-actions button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    border-radius: 9px;
    color: #fff;
    background: rgba(255,255,255,.14);
    cursor: pointer;
    font-size: 17px;
}

.ce-chat-header-actions button:hover {
    background: rgba(255,255,255,.25);
}

#ce-chat-messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 17px 14px;
    background: #f8fafc;
    scroll-behavior: smooth;
}

.ce-message-row {
    display: flex;
    margin: 0 0 13px;
}

.ce-message-row.ce-user {
    justify-content: flex-end;
}

.ce-message-row.ce-bot {
    justify-content: flex-start;
}

.ce-message-content {
    max-width: 88%;
}

.ce-bubble {
    padding: 11px 13px;
    border-radius: 15px;
    font-size: 13px;
    line-height: 1.6;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.ce-bot .ce-bubble {
    color: #334155;
    background: #fff;
    border: 1px solid #e8edf2;
    border-top-left-radius: 5px;
}

.ce-user .ce-bubble {
    color: #fff;
    background: #214637;
    border-top-right-radius: 5px;
}

.ce-message-time {
    margin-top: 5px;
    padding: 0 3px;
    color: #94a3b8;
    font-size: 10px;
}

.ce-user .ce-message-time {
    text-align: right;
}

.ce-chat-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    max-height: 125px;
    overflow-y: auto;
    padding: 10px 14px;
    background: #fff;
    border-top: 1px solid #eef2f7;
}

.ce-suggestion {
    padding: 7px 10px;
    border: 1px solid #d8e8c4;
    border-radius: 20px;
    color: #214637;
    background: #f2f7ed;
    cursor: pointer;
    font-size: 11px;
    transition: .15s ease;
}

.ce-suggestion:hover {
    color: #fff;
    background: #214637;
}

.ce-chat-form {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 12px;
    background: #fff;
    border-top: 1px solid #eef2f7;
}

#ce-chat-input {
    flex: 1;
    min-width: 0;
    height: 43px;
    padding: 0 13px;
    border: 1px solid #dbe3eb;
    border-radius: 13px;
    outline: none;
    color: #0f172a;
    background: #f8fafc;
    font-family: inherit;
    font-size: 13px;
}

#ce-chat-input:focus {
    border-color: #5e866b;
    box-shadow: 0 0 0 3px rgba(16,185,129,.1);
}

#ce-chat-send {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 43px;
    height: 43px;
    border: none;
    border-radius: 13px;
    color: #fff;
    background: #214637;
    cursor: pointer;
    font-size: 19px;
}

#ce-chat-send:hover {
    background: #16382d;
}

#ce-chat-send:disabled {
    opacity: .55;
    cursor: not-allowed;
}

.ce-typing {
    display: inline-flex;
    gap: 4px;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid #e8edf2;
    border-radius: 14px;
    background: #fff;
}

.ce-typing span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #94a3b8;
    animation: ceTyping 1s infinite ease-in-out;
}

.ce-typing span:nth-child(2) {
    animation-delay: .15s;
}

.ce-typing span:nth-child(3) {
    animation-delay: .3s;
}

@keyframes ceTyping {
    0%,60%,100% {
        transform: translateY(0);
        opacity: .5;
    }
    30% {
        transform: translateY(-4px);
        opacity: 1;
    }
}

.ce-event-list {
    display: grid;
    gap: 9px;
    margin-top: 10px;
}

.ce-event-card {
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    background: #fff;
}

.ce-event-category {
    display: inline-block;
    margin-bottom: 7px;
    padding: 4px 8px;
    border-radius: 20px;
    color: #214637;
    background: #e5f1df;
    font-size: 10px;
    font-weight: 700;
}

.ce-event-title {
    margin-bottom: 7px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.ce-event-meta {
    margin-top: 5px;
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.ce-chat-footer {
    padding: 7px;
    color: #94a3b8;
    background: #fff;
    text-align: center;
    font-size: 10px;
}

@media(max-width:480px) {
    #ce-chat-launcher {
        right: 16px;
        bottom: 16px;
        width: 56px;
        height: 56px;
    }

    #ce-chat-panel {
        right: 10px;
        bottom: 82px;
        width: calc(100vw - 20px);
        height: min(590px,calc(100vh - 100px));
        min-height: 350px;
    }
}
</style>

<!-- CHAT LAUNCHER -->
<button
    id="ce-chat-launcher"
    type="button"
    aria-label="Open Campus Event Hub chatbot"
    aria-controls="ce-chat-panel"
    aria-expanded="false"
>💬</button>

<!-- CHAT PANEL -->
<section
    id="ce-chat-panel"
    role="dialog"
    aria-label="Campus Event Hub Assistant"
    aria-hidden="true"
>
    <header class="ce-chat-header">

        <div class="ce-chat-avatar">🎓</div>

        <div class="ce-chat-heading">
            <strong>Campus Event Assistant</strong>
            <span>Student support · Events and registration</span>
        </div>

        <div class="ce-chat-header-actions">
            <button
                id="ce-chat-reset"
                type="button"
                aria-label="Clear conversation"
                title="Clear conversation"
            >↻</button>

            <button
                id="ce-chat-close"
                type="button"
                aria-label="Close chat"
            >×</button>
        </div>

    </header>

    <!-- MESSAGES -->
    <div id="ce-chat-messages" aria-live="polite">

        <div class="ce-message-row ce-bot">
            <div class="ce-message-content">
                <div class="ce-bubble">Hello! 👋 Welcome to Campus Event Hub!

I'm your Campus Event Assistant. I'm here to help you explore campus events, check schedules, find venues, and understand the registration process.

How can I assist you today?</div>
            </div>
        </div>

    </div>

    <!-- SUGGESTED QUESTIONS -->
    <div class="ce-chat-suggestions" id="ce-chat-suggestions">

        <button class="ce-suggestion" type="button">
            Upcoming events
        </button>

        <button class="ce-suggestion" type="button">
            Event categories
        </button>

        <button class="ce-suggestion" type="button">
            Event schedules
        </button>

        <button class="ce-suggestion" type="button">
            Event venues
        </button>

        <button class="ce-suggestion" type="button">
            Event organizers
        </button>

        <button class="ce-suggestion" type="button">
            How do I register?
        </button>

        <button class="ce-suggestion" type="button">
            Past events
        </button>

        <button class="ce-suggestion" type="button">
            Can I cancel registration?
        </button>

        <button class="ce-suggestion" type="button">
            My registrations
        </button>

        <button class="ce-suggestion" type="button">
            Login or account help
        </button>

        <button class="ce-suggestion" type="button">
            What can you help with?
        </button>

    </div>

    <!-- MESSAGE INPUT -->
    <form class="ce-chat-form" id="ce-chat-form">

        <input
            type="text"
            id="ce-chat-input"
            placeholder="Ask about events, schedules, registration..."
            autocomplete="off"
            maxlength="1000"
            aria-label="Type your message"
            required
        >

        <button
            id="ce-chat-send"
            type="submit"
            aria-label="Send message"
        >➤</button>

    </form>

    <div class="ce-chat-footer">
        Campus Event Hub • Student Support
    </div>

</section>

<script>
(function () {
    'use strict';

    const endpoint = <?= json_encode(
        $chatbotEndpoint,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;

    const launcher = document.getElementById('ce-chat-launcher');
    const panel = document.getElementById('ce-chat-panel');
    const closeButton = document.getElementById('ce-chat-close');
    const resetButton = document.getElementById('ce-chat-reset');
    const form = document.getElementById('ce-chat-form');
    const input = document.getElementById('ce-chat-input');
    const sendButton = document.getElementById('ce-chat-send');
    const messages = document.getElementById('ce-chat-messages');
    const suggestions = document.getElementById('ce-chat-suggestions');

    if (
        !launcher || !panel || !closeButton ||
        !resetButton || !form || !input ||
        !sendButton || !messages || !suggestions
    ) {
        console.error('Campus Event Hub chatbot: required elements are missing.');
        return;
    }

    let waiting = false;

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    // ADD MESSAGE
    function addMessage(text, sender) {

        const row = document.createElement('div');

        row.className =
            'ce-message-row ' +
            (sender === 'user' ? 'ce-user' : 'ce-bot');

        const content = document.createElement('div');
        content.className = 'ce-message-content';

        const bubble = document.createElement('div');
        bubble.className = 'ce-bubble';
        bubble.textContent = text;

        const time = document.createElement('div');
        time.className = 'ce-message-time';

        time.textContent = new Date().toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit'
        });

        content.appendChild(bubble);
        content.appendChild(time);

        row.appendChild(content);
        messages.appendChild(row);

        scrollToBottom();

        return row;
    }

    // DISPLAY EVENT CARDS
    function addEvents(events) {

        if (!Array.isArray(events) || events.length === 0) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'ce-message-row ce-bot';

        const content = document.createElement('div');
        content.className = 'ce-message-content';

        const list = document.createElement('div');
        list.className = 'ce-event-list';

        events.forEach(function (event) {

            const card = document.createElement('article');
            card.className = 'ce-event-card';

            const category = document.createElement('div');
            category.className = 'ce-event-category';
            category.textContent = event.category || 'Campus Event';

            const title = document.createElement('div');
            title.className = 'ce-event-title';
            title.textContent = event.title || 'Untitled event';

            card.appendChild(category);
            card.appendChild(title);

            if (event.date) {
                const date = document.createElement('div');
                date.className = 'ce-event-meta';
                date.textContent = '📅 ' + event.date;
                card.appendChild(date);
            }

            if (event.start_time) {
                const time = document.createElement('div');
                time.className = 'ce-event-meta';

                let timeText = event.start_time;

                if (event.end_time) {
                    timeText += ' – ' + event.end_time;
                }

                time.textContent = '🕒 ' + timeText;
                card.appendChild(time);
            }

            if (event.venue) {
                const venue = document.createElement('div');
                venue.className = 'ce-event-meta';
                venue.textContent = '📍 ' + event.venue;
                card.appendChild(venue);
            }

            if (event.organizer) {
                const organizer = document.createElement('div');
                organizer.className = 'ce-event-meta';
                organizer.textContent = '👤 ' + event.organizer;
                card.appendChild(organizer);
            }

            if (event.description) {
                const description = document.createElement('div');
                description.className = 'ce-event-meta';
                description.textContent = event.description;
                card.appendChild(description);
            }

            list.appendChild(card);
        });

        content.appendChild(list);
        row.appendChild(content);
        messages.appendChild(row);

        scrollToBottom();
    }

    // TYPING INDICATOR
    function addTyping() {

        const row = document.createElement('div');
        row.className = 'ce-message-row ce-bot';
        row.id = 'ce-chat-typing';

        const content = document.createElement('div');
        content.className = 'ce-message-content';

        const typing = document.createElement('div');
        typing.className = 'ce-typing';

        const dot1 = document.createElement('span');
        const dot2 = document.createElement('span');
        const dot3 = document.createElement('span');

        typing.appendChild(dot1);
        typing.appendChild(dot2);
        typing.appendChild(dot3);

        content.appendChild(typing);
        row.appendChild(content);
        messages.appendChild(row);

        scrollToBottom();
    }

    function removeTyping() {
        const typing = document.getElementById('ce-chat-typing');

        if (typing) {
            typing.remove();
        }
    }

    // WAITING STATE
    function setWaiting(state) {
        waiting = state;
        sendButton.disabled = state;
        input.disabled = state;
        sendButton.textContent = state ? '…' : '➤';
    }

    // OPEN CHAT
    function openChat() {
        panel.classList.add('ce-open');
        panel.setAttribute('aria-hidden', 'false');
        launcher.setAttribute('aria-expanded', 'true');

        input.focus();
        scrollToBottom();
    }

    // CLOSE CHAT
    function closeChat() {
        panel.classList.remove('ce-open');
        panel.setAttribute('aria-hidden', 'true');
        launcher.setAttribute('aria-expanded', 'false');
    }

    // SEND MESSAGE TO PHP BACKEND
    async function sendMessage(rawMessage) {

        const question = String(rawMessage || '').trim();

        if (!question || waiting) {
            return;
        }

        addMessage(question, 'user');

        input.value = '';

        setWaiting(true);
        addTyping();

        try {

            const response = await fetch(endpoint, {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    message: question
                }),

                credentials: 'same-origin'
            });

            const raw = await response.text();

            let data;

            try {
                data = JSON.parse(raw);
            } catch (error) {
                console.error('Invalid chatbot response:', raw);
                throw new Error('Invalid response from chatbot server.');
            }

            if (!response.ok) {
                console.error('Chatbot HTTP error:', response.status, data);

                throw new Error(
                    data.reply || 'Chatbot request failed.'
                );
            }

            if (typeof data.reply !== 'string') {
                throw new Error('Chatbot reply is missing.');
            }

            removeTyping();

            addMessage(data.reply, 'bot');

            addEvents(data.events);

        } catch (error) {

            console.error('Campus Event Hub chatbot error:', error);

            removeTyping();

            addMessage(
                'Sorry, I cannot connect to the chatbot right now. Please check your internet connection, chatbot.php path, and database connection. You may try again in a moment.',
                'bot'
            );

        } finally {

            setWaiting(false);

            input.focus();
        }
    }

    // LAUNCHER BUTTON
    launcher.addEventListener('click', function () {

        if (panel.classList.contains('ce-open')) {
            closeChat();
        } else {
            openChat();
        }

    });

    // CLOSE BUTTON
    closeButton.addEventListener('click', closeChat);

    // RESET CHAT
    resetButton.addEventListener('click', function () {

        if (waiting) {
            return;
        }

        messages.innerHTML = '';

        addMessage(
            "Hello! Welcome to Campus Event Hub!\n\nI'm your Campus Event Assistant. I can help you explore campus events, check schedules, find venues, and understand the registration process.\n\nHow can I assist you today?",
            'bot'
        );

        input.value = '';
        input.focus();
    });

    // FORM SUBMISSION
    form.addEventListener('submit', function (event) {

        event.preventDefault();

        sendMessage(input.value);
    });

    // CLICK SUGGESTED QUESTIONS
    suggestions.addEventListener('click', function (event) {

        const button = event.target.closest('.ce-suggestion');

        if (!button || waiting) {
            return;
        }

        sendMessage(button.textContent.trim());
    });

    // ESCAPE TO CLOSE
    document.addEventListener('keydown', function (event) {

        if (
            event.key === 'Escape' &&
            panel.classList.contains('ce-open')
        ) {
            closeChat();
        }

    });

})();
</script>