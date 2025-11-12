(function () {
  const pageType = document.body?.dataset?.page;
  if (!pageType) return;

  const NEWS_PATH = 'data/news.json';

  const formatDate = (value) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    const y = date.getFullYear();
    const m = `${date.getMonth() + 1}`.padStart(2, '0');
    const d = `${date.getDate()}`.padStart(2, '0');
    return `${y}.${m}.${d}`;
  };

  const escapeHtml = (value) =>
    value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');

  const toHtml = (text) =>
    text
      .split('\n')
      .map((line) => `<p>${escapeHtml(line)}</p>`)
      .join('');

  async function fetchNews() {
    const response = await fetch(NEWS_PATH, { cache: 'no-store' });
    if (!response.ok) {
      throw new Error('ニュースデータの取得に失敗しました');
    }
    const data = await response.json();
    return [...data].sort((a, b) => new Date(b.date) - new Date(a.date));
  }

  function renderHome(items) {
    const container = document.getElementById('latest-news');
    if (!container) return;
    const latest = items.slice(0, 3);
    if (!latest.length) {
      container.innerHTML = '<p class="news-empty">現在表示できるお知らせはありません。</p>';
      return;
    }

    container.innerHTML = '';
    latest.forEach((item) => {
      const article = document.createElement('article');
      article.className = 'news-card';
      article.innerHTML = `
        <time datetime="${item.date}" class="news-card-date">${formatDate(item.date)}</time>
        <h3 class="news-card-title">${item.title}</h3>
        <p class="news-card-excerpt">${item.excerpt}</p>
        <a class="news-card-link" href="news.html?id=${encodeURIComponent(item.id)}">詳しく見る</a>
      `;
      container.appendChild(article);
    });
  }

  function setupNewsPage(items) {
    const listEl = document.getElementById('news-list');
    const detailEl = document.getElementById('news-detail');
    const latestEl = document.getElementById('news-latest');
    if (!listEl || !detailEl || !latestEl) return;

    const renderList = () => {
      listEl.innerHTML = '';
      items.forEach((item) => {
        const article = document.createElement('article');
        article.className = 'news-card';
        article.innerHTML = `
          <time datetime="${item.date}" class="news-card-date">${formatDate(item.date)}</time>
          <h2 class="news-card-title">${item.title}</h2>
          <p class="news-card-excerpt">${item.excerpt}</p>
          <a class="news-card-link" href="?id=${encodeURIComponent(item.id)}" data-news-id="${item.id}">詳しく見る</a>
        `;
        listEl.appendChild(article);
      });
    };

    const renderLatest = () => {
      latestEl.innerHTML = '';
      items.slice(0, 5).forEach((item) => {
        const li = document.createElement('li');
        li.innerHTML = `
          <a href="?id=${encodeURIComponent(item.id)}" data-news-id="${item.id}">
            <time datetime="${item.date}">${formatDate(item.date)}</time>
            <span>${item.title}</span>
          </a>
        `;
        latestEl.appendChild(li);
      });
    };

    const showList = (skipHistory = false) => {
      listEl.hidden = false;
      detailEl.hidden = true;
      detailEl.dataset.currentId = '';
      if (!skipHistory) {
        const url = new URL(window.location.href);
        url.searchParams.delete('id');
        history.pushState({ view: 'list' }, '', url);
      }
      listEl.focus?.();
    };

    const showDetail = (id, skipHistory = false) => {
      const item = items.find((entry) => entry.id === id);
      if (!item) {
        showList(skipHistory);
        return;
      }

      listEl.hidden = true;
      detailEl.hidden = false;
      detailEl.dataset.currentId = item.id;
      detailEl.querySelector('[data-field="date"]').textContent = formatDate(item.date);
      detailEl.querySelector('[data-field="title"]').textContent = item.title;
      detailEl.querySelector('[data-field="content"]').innerHTML = toHtml(item.content);

      if (!skipHistory) {
        const url = new URL(window.location.href);
        url.searchParams.set('id', item.id);
        history.pushState({ view: 'detail', id: item.id }, '', url);
      }
      detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      detailEl.focus?.({ preventScroll: true });
    };

    listEl.addEventListener('click', (event) => {
      const link = event.target.closest('[data-news-id]');
      if (!link) return;
      event.preventDefault();
      showDetail(link.dataset.newsId);
    });

    latestEl.addEventListener('click', (event) => {
      const link = event.target.closest('[data-news-id]');
      if (!link) return;
      event.preventDefault();
      showDetail(link.dataset.newsId);
    });

    detailEl.querySelector('[data-action="back"]').addEventListener('click', () => {
      showList();
    });

    window.addEventListener('popstate', (event) => {
      const { state } = event;
      if (!state || state.view === 'list') {
        showList(true);
      } else if (state.view === 'detail') {
        showDetail(state.id, true);
      }
    });

    renderList();
    renderLatest();

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (id) {
      showDetail(id, true);
      history.replaceState({ view: 'detail', id }, '', window.location.href);
    } else {
      showList(true);
      history.replaceState({ view: 'list' }, '', window.location.href);
    }
  }

  fetchNews()
    .then((items) => {
      if (pageType === 'home') {
        renderHome(items);
      }
      if (pageType === 'news') {
        setupNewsPage(items);
      }
    })
    .catch((error) => {
      console.error(error);
      if (pageType === 'home') {
        const container = document.getElementById('latest-news');
        if (container) {
          container.innerHTML = '<p class="news-error">ニュースの取得に失敗しました。</p>';
        }
      }
      if (pageType === 'news') {
        const listEl = document.getElementById('news-list');
        if (listEl) {
          listEl.innerHTML = '<p class="news-error">ニュースの取得に失敗しました。</p>';
        }
      }
    });
})();
