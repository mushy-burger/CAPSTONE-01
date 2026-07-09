(function () {
  var root = document.getElementById('chatbotWidget');
  if (!root) return;

  var baseUrl = root.dataset.baseUrl || '';
  var launcher = document.getElementById('chatbotLauncher');
  var panel = document.getElementById('chatbotPanel');
  var closeBtn = document.getElementById('chatbotClose');
  var messages = document.getElementById('chatbotMessages');
  var form = document.getElementById('chatbotForm');
  var input = document.getElementById('chatbotInput');
  var sendBtn = document.getElementById('chatbotSend');

  function addBubble(text, role, isError) {
    var bubble = document.createElement('div');
    bubble.className = 'chatbot-bubble ' + (role === 'user' ? 'user' : 'bot') + (isError ? ' is-error' : '');
    bubble.textContent = text;
    messages.appendChild(bubble);
    messages.scrollTop = messages.scrollHeight;
    return bubble;
  }

  launcher.addEventListener('click', function () {
    panel.classList.add('is-open');
    launcher.setAttribute('aria-expanded', 'true');
    input.focus();
  });

  closeBtn.addEventListener('click', function () {
    panel.classList.remove('is-open');
    launcher.setAttribute('aria-expanded', 'false');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;

    addBubble(text, 'user');
    input.value = '';
    sendBtn.disabled = true;

    var typingBubble = addBubble('Typing…', 'bot');
    typingBubble.classList.add('typing');

    fetch(baseUrl + 'api/chatbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        typingBubble.remove();
        if (data.ok) {
          addBubble(data.reply, 'bot');
        } else {
          addBubble(data.message || 'Something went wrong. Please try again.', 'bot', true);
        }
      })
      .catch(function () {
        typingBubble.remove();
        addBubble('Could not reach the assistant. Check your connection and try again.', 'bot', true);
      })
      .finally(function () {
        sendBtn.disabled = false;
      });
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });
})();
