(function () {
  const pageType = document.body?.dataset?.page;
  if (pageType !== 'admin') return;

  const endpoint = window.AppScriptConfig?.endpoint ?? '';
  const accessForm = document.getElementById('access-form');
  const accessStatus = document.getElementById('access-status');
  const accessInput = document.getElementById('access-key');
  const editor = document.getElementById('editor');
  const editorStatus = document.getElementById('editor-status');
  const newsListEl = document.getElementById('news-editor-list');
  const newsForm = document.getElementById('news-form');
  const editorActions = editor?.querySelector('.editor-actions');
  const backButton = newsForm?.querySelector('[data-action="cancel"]');

  if (!accessForm || !editor || !newsListEl || !newsForm || !editorActions || !backButton) {
    console.warn('管理画面の初期化に必要な要素が見つかりません。');
    return;
  }

  let accessKey = '';
  let newsItems = [];
  let editingOriginalId = null;

  const formatDate = (value) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const y = date.getFullYear();
    const m = `${date.getMonth() + 1}`.padStart(2, '0');
    const d = `${date.getDate()}`.padStart(2, '0');
    return `${y}-${m}-${d}`;
  };

  const sortNews = (items) => [...items].sort((a, b) => new Date(b.date) - new Date(a.date));

  const setAccessStatus = (message, state = 'info') => {
    accessStatus.textContent = message;
    accessStatus.dataset.state = state;
  };

  const setEditorStatus = (message, state = 'info') => {
    editorStatus.textContent = message;
    editorStatus.dataset.state = state;
  };

  const callScript = async (payload) => {
    if (!endpoint || endpoint.includes('your-script-id')) {
      throw new Error('Apps Script の Web アプリ URL を config/appsscript.js に設定してください。');
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error('Apps Script との通信に失敗しました。');
    }

    let result = null;
    try {
      result = await response.json();
    } catch (error) {
      throw new Error('Apps Script から予期しない応答が返されました。');
    }

    if (result && result.error) {
      throw new Error(result.error);
    }

    return result ?? {};
  };

  const renderList = () => {
    newsListEl.innerHTML = '';

    if (!newsItems.length) {
      const empty = document.createElement('p');
      empty.className = 'editor-empty';
      empty.textContent = 'まだお知らせが登録されていません。';
      newsListEl.appendChild(empty);
      return;
    }

    sortNews(newsItems).forEach((item) => {
      const card = document.createElement('article');
      card.className = 'editor-item';
      card.innerHTML = `
        <div class="editor-item-meta">
          <time datetime="${item.date}">${formatDate(item.date)}</time>
          <span>${item.id}</span>
        </div>
        <h3 class="editor-item-title">${item.title}</h3>
        <p class="editor-item-excerpt">${item.excerpt}</p>
        <div class="editor-item-actions">
          <button type="button" class="btn secondary" data-action="edit" data-id="${item.id}">編集</button>
          <button type="button" class="btn danger" data-action="delete" data-id="${item.id}">削除</button>
        </div>
      `;
      newsListEl.appendChild(card);
    });
  };

  const openForm = (item = null) => {
    if (item) {
      editingOriginalId = item.id;
      newsForm.querySelector('#news-id').value = item.id;
      newsForm.querySelector('#news-date').value = formatDate(item.date);
      newsForm.querySelector('#news-title').value = item.title;
      newsForm.querySelector('#news-excerpt').value = item.excerpt;
      newsForm.querySelector('#news-content').value = item.content;
      newsForm.querySelector('#form-heading').textContent = 'お知らせを編集';
    } else {
      editingOriginalId = null;
      newsForm.reset();
      newsForm.querySelector('#form-heading').textContent = 'お知らせを追加';
      const today = new Date();
      newsForm.querySelector('#news-date').value = formatDate(today.toISOString());
    }

    newsForm.hidden = false;
    newsForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const closeForm = () => {
    newsForm.hidden = true;
    newsForm.reset();
    editingOriginalId = null;
  };

  const persistNews = async () => {
    setEditorStatus('保存しています...', 'pending');
    const payload = {
      action: 'saveNews',
      accessKey,
      news: sortNews(newsItems)
    };
    await callScript(payload);
    newsItems = sortNews(newsItems);
    setEditorStatus('JSON を保存しました。', 'success');
    renderList();
  };

  const loadNews = async () => {
    setEditorStatus('ニュースを読み込んでいます...', 'pending');
    const result = await callScript({ action: 'fetchNews', accessKey });
    if (!Array.isArray(result.news)) {
      throw new Error('ニュースデータの形式が正しくありません。');
    }
    newsItems = result.news;
    newsItems = sortNews(newsItems);
    renderList();
    setEditorStatus('最新データを取得しました。', 'success');
  };

  accessForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const key = accessInput.value.trim();
    if (!key) {
      setAccessStatus('アクセスキーを入力してください。', 'error');
      return;
    }

    setAccessStatus('認証中です...', 'pending');
    accessForm.querySelector('button[type="submit"]').disabled = true;

    try {
      const result = await callScript({ action: 'verifyKey', accessKey: key });
      if (!result.valid) {
        setAccessStatus('アクセスキーが一致しません。', 'error');
        return;
      }

      accessKey = key;
      setAccessStatus('認証に成功しました。', 'success');
      accessForm.hidden = true;
      editor.hidden = false;
      await loadNews();
    } catch (error) {
      console.error(error);
      setAccessStatus(error.message, 'error');
    } finally {
      accessForm.querySelector('button[type="submit"]').disabled = false;
    }
  });

  editorActions.addEventListener('click', async (event) => {
    const action = event.target.dataset.action;
    if (!action) return;

    if (action === 'create') {
      openForm();
    }
    if (action === 'refresh') {
      try {
        await loadNews();
      } catch (error) {
        console.error(error);
        setEditorStatus(error.message, 'error');
      }
    }
  });

  newsListEl.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const { action, id } = button.dataset;
    if (action === 'edit') {
      const item = newsItems.find((entry) => entry.id === id);
      if (item) {
        openForm(item);
      }
      return;
    }

    if (action === 'delete') {
      const item = newsItems.find((entry) => entry.id === id);
      if (!item) return;
      const confirmed = window.confirm(`「${item.title}」を削除しますか？`);
      if (!confirmed) return;
      try {
        newsItems = newsItems.filter((entry) => entry.id !== id);
        await persistNews();
      } catch (error) {
        console.error(error);
        setEditorStatus(error.message, 'error');
      }
    }
  });

  backButton.addEventListener('click', () => {
    closeForm();
  });

  newsForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(newsForm);
    const item = {
      id: formData.get('id').trim(),
      date: formData.get('date'),
      title: formData.get('title').trim(),
      excerpt: formData.get('excerpt').trim(),
      content: formData.get('content').trim()
    };

    if (!item.id || !item.date || !item.title || !item.excerpt || !item.content) {
      setEditorStatus('すべての項目を入力してください。', 'error');
      return;
    }

    if (editingOriginalId) {
      const index = newsItems.findIndex((entry) => entry.id === editingOriginalId);
      if (index !== -1) {
        newsItems[index] = item;
      } else {
        newsItems.push(item);
      }
    } else if (newsItems.some((entry) => entry.id === item.id)) {
      setEditorStatus('同じ ID のお知らせが存在します。別の ID を設定してください。', 'error');
      return;
    } else {
      newsItems.push(item);
    }

    try {
      await persistNews();
      closeForm();
    } catch (error) {
      console.error(error);
      setEditorStatus(error.message, 'error');
    }
  });
})();
