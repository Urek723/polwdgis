<?php
$pageTitle = 'Live Chat Support';
require_once 'layout.php';
?>
<style>
/* ── Chat Page Layout ──────────────────────────────────────── */
.chat-page-wrap {
  margin-top: var(--nav-h);
  max-width: 700px;
  margin-left: auto;
  margin-right: auto;
  padding: 20px 16px 100px;
  display: flex;
  flex-direction: column;
  height: calc(100vh - var(--nav-h));
  box-sizing: border-box;
}

/* Chat window */
.chat-window {
  flex: 1;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}

.chat-header {
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}
.chat-header-icon {
  width: 42px; height: 42px;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}
.chat-header-info { flex: 1; }
.chat-header-title { font-weight: 700; font-size: 15px; color: #fff; }
.chat-header-sub   { font-size: 11px; color: rgba(255,255,255,0.75); margin-top: 1px; }
.chat-status-dot {
  width: 9px; height: 9px;
  background: #00ff88;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.5);
  animation: statusPulse 2s infinite;
}
@keyframes statusPulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

/* Messages area */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
}

/* Individual messages */
.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}
.msg-row.user  { justify-content: flex-end; }
.msg-row.bot   { justify-content: flex-start; }

.msg-avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}
.msg-avatar.bot  { background: linear-gradient(135deg, var(--accent2), var(--accent)); }
.msg-avatar.user { background: var(--surface2); border: 1px solid var(--border); }

.msg-bubble {
  max-width: 78%;
  padding: 10px 14px;
  border-radius: 16px;
  font-size: 13px;
  line-height: 1.55;
  word-break: break-word;
}
.msg-row.user .msg-bubble {
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  color: #fff;
  border-radius: 16px 16px 4px 16px;
}
.msg-row.bot .msg-bubble {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 16px 16px 16px 4px;
  white-space: pre-wrap;
}

.msg-time {
  font-size: 10px;
  color: var(--muted);
  margin-top: 4px;
  text-align: right;
}
.msg-row.bot .msg-time { text-align: left; }

/* Typing indicator */
.typing-indicator {
  display: none;
  align-items: center;
  gap: 4px;
  padding: 10px 14px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 16px 16px 16px 4px;
  width: fit-content;
}
.typing-dot {
  width: 7px; height: 7px;
  background: var(--muted);
  border-radius: 50%;
  animation: typingBounce 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingBounce {
  0%, 60%, 100% { transform: translateY(0); }
  30%            { transform: translateY(-8px); }
}

/* Input area */
.chat-input-area {
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  background: var(--surface);
  flex-shrink: 0;
}
.chat-input-row {
  display: flex;
  gap: 8px;
  align-items: center;
}
.chat-input {
  flex: 1;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 10px 16px;
  font-family: 'Sora', sans-serif;
  font-size: 13px;
  color: var(--text);
  outline: none;
  transition: border-color 0.2s;
  resize: none;
  max-height: 100px;
  min-height: 42px;
  line-height: 1.4;
}
.chat-input:focus { border-color: var(--accent); }
.chat-send-btn {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border: none;
  border-radius: 50%;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: opacity 0.15s, transform 0.1s;
}
.chat-send-btn:hover { opacity: 0.9; transform: scale(1.05); }
.chat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

/* Quick reply chips */
.quick-replies {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 8px 16px 0;
}
.quick-reply {
  padding: 5px 12px;
  background: none;
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text2);
  font-size: 11px;
  cursor: pointer;
  font-family: 'Sora', sans-serif;
  transition: all 0.15s;
  white-space: nowrap;
}
.quick-reply:hover {
  border-color: var(--accent);
  color: var(--accent);
  background: rgba(0,212,255,0.06);
}

/* Bold in bot messages (manual **text** → <b>text</b>) */
.msg-bubble b { font-weight: 700; }
</style>

<div class="chat-page-wrap">
  <div class="chat-window">

    <!-- Header -->
    <div class="chat-header">
      <div class="chat-header-icon">🤖</div>
      <div class="chat-header-info">
        <div class="chat-header-title">Water District Support</div>
        <div class="chat-header-sub">Polomolok Water District — Virtual Assistant</div>
      </div>
      <div class="chat-status-dot" title="Online"></div>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
      <!-- Welcome message injected by JS -->
    </div>

    <!-- Typing indicator (outside scroll area for visibility) -->
    <div style="padding: 0 16px 8px; flex-shrink:0; display:flex;">
      <div class="typing-indicator" id="typingIndicator">
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
      </div>
    </div>

    <!-- Quick replies -->
    <div class="quick-replies" id="quickReplies">
      <button class="quick-reply" onclick="quickSend(this)">💧 No water</button>
      <button class="quick-reply" onclick="quickSend(this)">🔧 Report leak</button>
      <button class="quick-reply" onclick="quickSend(this)">📄 Billing inquiry</button>
      <button class="quick-reply" onclick="quickSend(this)">📋 Request status</button>
      <button class="quick-reply" onclick="quickSend(this)">🔌 New connection</button>
      <button class="quick-reply" onclick="quickSend(this)">📍 Office location</button>
    </div>

    <!-- Input -->
    <div class="chat-input-area">
      <div class="chat-input-row">
        <textarea
          id="chatInputField"
          class="chat-input"
          placeholder="Type your message here…"
          rows="1"
          onkeydown="handleKeydown(event)"
          oninput="autoResizeTextarea(this)"
        ></textarea>
        <button class="chat-send-btn" id="chatSendBtn" onclick="sendMessage()" title="Send">
          ➤
        </button>
      </div>
    </div>

  </div>
</div>

<script>
const CHATBOT_API     = '../../backend/chatbot/send_message.php';
let chatSessionToken  = localStorage.getItem('chat_session_token') || '';
let isSending         = false;

// ── Markdown-lite: convert **bold** to <b>bold</b> ───────────
function formatBotText(text) {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<b>$1</b>')
    .replace(/\n/g, '<br>');
}

// ── Render a message bubble ───────────────────────────────────
function appendMessage(sender, text, animate = true) {
  const container = document.getElementById('chatMessages');
  const row = document.createElement('div');
  row.className = `msg-row ${sender}`;

  const now = new Date();
  const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  const content = sender === 'bot'
    ? `<div class="msg-avatar bot">🤖</div>
       <div>
         <div class="msg-bubble">${formatBotText(text)}</div>
         <div class="msg-time">${timeStr}</div>
       </div>`
    : `<div>
         <div class="msg-bubble">${escapeHtml(text)}</div>
         <div class="msg-time">${timeStr}</div>
       </div>
       <div class="msg-avatar user">👤</div>`;

  row.innerHTML = content;

  if (animate) {
    row.style.opacity = '0';
    row.style.transform = sender === 'bot' ? 'translateX(-10px)' : 'translateX(10px)';
    row.style.transition = 'opacity 0.25s, transform 0.25s';
    container.appendChild(row);
    requestAnimationFrame(() => {
      row.style.opacity = '1';
      row.style.transform = 'none';
    });
  } else {
    container.appendChild(row);
  }

  scrollToBottom();
}

function scrollToBottom() {
  const el = document.getElementById('chatMessages');
  el.scrollTop = el.scrollHeight;
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}

// ── Send a message ────────────────────────────────────────────
async function sendMessage() {
  if (isSending) return;

  const input = document.getElementById('chatInputField');
  const text  = input.value.trim();
  if (!text) return;

  // Clear input immediately
  input.value = '';
  input.style.height = 'auto';

  appendMessage('user', text);
  showTyping(true);
  disableSend(true);
  isSending = true;

  // Hide quick replies after first message
  document.getElementById('quickReplies').style.display = 'none';

  try {
    const resp = await fetch(CHATBOT_API, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    JSON.stringify({ message: text, session_token: chatSessionToken }),
    });

    const data = await resp.json();

    showTyping(false);

    if (data.success && data.reply) {
      chatSessionToken = data.session_token || chatSessionToken;
      localStorage.setItem('chat_session_token', chatSessionToken);
      appendMessage('bot', data.reply);
    } else {
      appendMessage('bot', data.error || "I'm sorry, something went wrong. Please try again.");
    }

  } catch (err) {
    showTyping(false);
    appendMessage('bot', "⚠️ Network error. Please check your connection and try again.");
  } finally {
    isSending = false;
    disableSend(false);
    document.getElementById('chatInputField').focus();
  }
}

function quickSend(btn) {
  const text = btn.textContent.trim().replace(/^[^\s]+\s/, ''); // strip emoji prefix
  document.getElementById('chatInputField').value = text;
  sendMessage();
}

// ── UI helpers ────────────────────────────────────────────────
function showTyping(show) {
  const el = document.getElementById('typingIndicator');
  el.style.display = show ? 'flex' : 'none';
  if (show) scrollToBottom();
}

function disableSend(disabled) {
  document.getElementById('chatSendBtn').disabled = disabled;
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

function autoResizeTextarea(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

// ── Load existing session messages ────────────────────────────
async function loadSessionHistory() {
  if (!chatSessionToken) return;
  // Simply greet again — history display is optional
}

// ── Welcome message ───────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
  const welcomeText = "👋 Hello, <?= htmlspecialchars($_SESSION['consumer_name'] ?? 'there') ?>!\n\n"
    + "I'm your Polomolok Water District virtual assistant. "
    + "I'm here to help with water service inquiries, billing questions, "
    + "reporting issues, and more.\n\n"
    + "Type your question below or tap one of the quick options to get started!";

  setTimeout(() => appendMessage('bot', welcomeText, true), 300);
});
</script>