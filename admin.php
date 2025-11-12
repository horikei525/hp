<?php
require_once __DIR__ . '/includes/news.php';

$firebaseConfig = @require __DIR__ . '/config/firebase.php';
if (!is_array($firebaseConfig)) {
    $firebaseConfig = [];
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$pageTitle = '管理画面｜株式会社あかりハウジング';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle); ?></title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
</head>

<body class="admin-page">
  <header class="header simple-header">
    <div class="header-inner">
      <div class="logo"><a href="index.php">株式会社あかりハウジング</a></div>
      <nav class="nav" aria-label="サブナビゲーション">
        <ul>
          <li><a href="index.php#news">お知らせ</a></li>
          <li><a href="news.php">一覧を見る</a></li>
        </ul>
      </nav>
      <a href="index.php#contact" class="contact-btn">サイトへ戻る</a>
    </div>
  </header>

  <main class="admin-main" aria-labelledby="admin-title">
    <div class="page-hero">
      <div class="page-hero-inner">
        <p class="page-label">ADMIN</p>
        <h1 id="admin-title">お知らせ管理</h1>
        <p class="page-lead">Firebase Authentication（Googleログイン）で認証し、お知らせの登録・更新・削除を行います。</p>
      </div>
    </div>

    <section class="admin-card" aria-label="ログイン情報">
      <div class="admin-auth">
        <p class="auth-status" data-auth-status>未ログイン</p>
        <div class="auth-actions">
          <button type="button" class="btn primary" data-login-button>Googleでログイン</button>
          <button type="button" class="btn secondary" data-logout-button hidden>ログアウト</button>
        </div>
        <p class="auth-note">※FirebaseプロジェクトのWebアプリ設定から発行したAPIキー・クライアントIDを config/firebase.php に設定してください。</p>
      </div>
    </section>

    <section class="admin-layout" aria-label="お知らせ編集">
      <div class="admin-form">
        <h2>お知らせ編集フォーム</h2>
        <form id="news-form">
          <input type="hidden" name="id" />
          <div class="form-group">
            <label for="news-date">日付 <span class="required">必須</span></label>
            <input type="date" id="news-date" name="date" required />
            <p class="form-error" data-error-for="date"></p>
          </div>
          <div class="form-group">
            <label for="news-title">タイトル <span class="required">必須</span></label>
            <input type="text" id="news-title" name="title" placeholder="例：新モデルハウス内覧会のお知らせ" required />
            <p class="form-error" data-error-for="title"></p>
          </div>
          <div class="form-group">
            <label for="news-excerpt">概要 <span class="required">必須</span></label>
            <textarea id="news-excerpt" name="excerpt" rows="3" placeholder="50〜80文字を目安に概要を入力してください" required></textarea>
            <p class="form-error" data-error-for="excerpt"></p>
          </div>
          <div class="form-group">
            <label for="news-content">本文 <span class="required">必須</span></label>
            <textarea id="news-content" name="content" rows="8" placeholder="改行ごとに段落として表示されます" required></textarea>
            <p class="form-error" data-error-for="content"></p>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn primary">保存する</button>
            <button type="button" class="btn ghost" data-reset-form>新規作成モード</button>
          </div>
          <p class="form-status" data-form-status></p>
        </form>
      </div>

      <div class="admin-list" aria-live="polite">
        <div class="list-header">
          <h2>お知らせ一覧</h2>
          <p class="list-description">公開中のお知らせを選択すると編集フォームに読み込みます。</p>
        </div>
        <ul class="admin-news-list" data-news-list>
          <li class="empty">読み込み中...</li>
        </ul>
      </div>
    </section>

    <section class="admin-card" aria-label="設定メモ">
      <h2>設定手順メモ</h2>
      <ol class="admin-steps">
        <li>Firebase ConsoleでWebアプリを作成し、Google認証を有効化します。</li>
        <li>「Authentication」→「Sign-in method」でGoogleを有効化し、必要に応じてドメインを許可します。</li>
        <li>config/firebase.php の各値（apiKey、authDomain、projectId、appId、clientIdなど）をFirebaseの設定値に置き換えます。</li>
        <li>admin.phpを開いてGoogleアカウントでログインし、お知らせの登録・更新・削除を行ってください。</li>
      </ol>
    </section>
  </main>

  <footer class="footer">
    <div class="footer-inner">
      <small>© <?= date('Y') ?> 株式会社あかりハウジング</small>
      <a href="tel:0450000000">TEL 045-000-0000</a>
    </div>
  </footer>

  <script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/11.1.0/firebase-app.js";
    import { getAuth, GoogleAuthProvider, signInWithPopup, signOut, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.1.0/firebase-auth.js";

    const firebaseConfig = <?= json_encode($firebaseConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    if (!firebaseConfig.apiKey || firebaseConfig.apiKey === 'YOUR_FIREBASE_API_KEY') {
      console.warn('Firebaseの設定が未完了です。config/firebase.php を更新してください。');
    }

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    auth.useDeviceLanguage();

    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: 'select_account' });

    const loginButton = document.querySelector('[data-login-button]');
    const logoutButton = document.querySelector('[data-logout-button]');
    const statusLabel = document.querySelector('[data-auth-status]');
    const newsList = document.querySelector('[data-news-list]');
    const form = document.getElementById('news-form');
    const formStatus = document.querySelector('[data-form-status]');

    const errors = {
      date: document.querySelector('[data-error-for="date"]'),
      title: document.querySelector('[data-error-for="title"]'),
      excerpt: document.querySelector('[data-error-for="excerpt"]'),
      content: document.querySelector('[data-error-for="content"]'),
    };

    let currentUser = null;
    let cachedNews = [];

    onAuthStateChanged(auth, (user) => {
      currentUser = user;
      updateAuthUI();
      if (user) {
        fetchNews();
      } else {
        resetNewsList();
        form.reset();
      }
    });

    loginButton.addEventListener('click', async () => {
      try {
        await signInWithPopup(auth, provider);
      } catch (error) {
        console.error('ログインに失敗しました', error);
        alert('ログインに失敗しました。ブラウザのポップアップ設定をご確認ください。');
      }
    });

    logoutButton.addEventListener('click', async () => {
      try {
        await signOut(auth);
      } catch (error) {
        console.error('ログアウトに失敗しました', error);
      }
    });

    document.querySelector('[data-reset-form]').addEventListener('click', () => {
      form.reset();
      form.querySelector('input[name="id"]').value = '';
      formStatus.textContent = '新規作成モードになりました。';
      clearErrors();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearErrors();

      if (!currentUser) {
        alert('まずはGoogleアカウントでログインしてください。');
        return;
      }

      try {
        formStatus.textContent = '送信中...';
        const idToken = await currentUser.getIdToken();
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const response = await fetch('api/news.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            idToken,
            payload,
          }),
        });

        const result = await response.json();
        if (!response.ok) {
          if (result && result.errors) {
            renderErrors(result.errors);
            formStatus.textContent = '入力内容を確認してください。';
          } else {
            formStatus.textContent = result.error || '保存に失敗しました。';
          }
          return;
        }

        formStatus.textContent = result.updated ? 'お知らせを更新しました。' : 'お知らせを登録しました。';
        await fetchNews();
        if (!result.updated) {
          form.reset();
        }
      } catch (error) {
        console.error(error);
        formStatus.textContent = '通信中にエラーが発生しました。';
      }
    });

    function updateAuthUI() {
      const controls = form.querySelectorAll('input, textarea, button');
      if (currentUser) {
        statusLabel.textContent = `${currentUser.displayName || currentUser.email} としてログイン中`;
        loginButton.hidden = true;
        logoutButton.hidden = false;
        controls.forEach((el) => { el.disabled = false; });
      } else {
        statusLabel.textContent = '未ログイン';
        loginButton.hidden = false;
        logoutButton.hidden = true;
        controls.forEach((el) => { el.disabled = true; });
      }
    }

    async function fetchNews() {
      if (!currentUser) return;

      try {
        const response = await fetch('api/news.php');
        const result = await response.json();
        if (!response.ok) {
          throw new Error(result.error || 'Failed to load news.');
        }
        cachedNews = Array.isArray(result.items) ? result.items : [];
        renderNewsList();
      } catch (error) {
        console.error(error);
        newsList.innerHTML = '<li class="error">お知らせの読み込みに失敗しました。</li>';
      }
    }

    function renderNewsList() {
      if (!cachedNews.length) {
        newsList.innerHTML = '<li class="empty">お知らせは登録されていません。</li>';
        return;
      }

      newsList.innerHTML = '';
      cachedNews.forEach((item) => {
        const li = document.createElement('li');
        li.innerHTML = `
          <button type="button" class="news-row" data-id="${item.id}">
            <span class="date">${item.date}</span>
            <span class="title">${escapeHtml(item.title)}</span>
            <span class="excerpt">${escapeHtml(item.excerpt)}</span>
          </button>
          <button type="button" class="delete" data-delete-id="${item.id}">削除</button>
        `;
        newsList.appendChild(li);
      });

      newsList.querySelectorAll('[data-id]').forEach((button) => {
        button.addEventListener('click', () => {
          const id = button.dataset.id;
          const target = cachedNews.find((item) => item.id === id);
          if (target) {
            fillForm(target);
          }
        });
      });

      newsList.querySelectorAll('[data-delete-id]').forEach((button) => {
        button.addEventListener('click', async () => {
          if (!confirm('本当に削除しますか？')) return;
          await deleteNews(button.dataset.deleteId);
        });
      });
    }

    async function deleteNews(id) {
      if (!currentUser) return;

      try {
        const idToken = await currentUser.getIdToken();
        const response = await fetch('api/news.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ idToken, id }),
        });

        const result = await response.json();
        if (!response.ok) {
          throw new Error(result.error || '削除に失敗しました。');
        }

        formStatus.textContent = 'お知らせを削除しました。';
        await fetchNews();
        form.reset();
      } catch (error) {
        console.error(error);
        formStatus.textContent = error.message;
      }
    }

    function fillForm(item) {
      form.querySelector('input[name="id"]').value = item.id;
      form.querySelector('input[name="date"]').value = item.date;
      form.querySelector('input[name="title"]').value = item.title;
      form.querySelector('textarea[name="excerpt"]').value = item.excerpt;
      form.querySelector('textarea[name="content"]').value = item.content;
      formStatus.textContent = '編集モードになりました。';
    }

    function resetNewsList() {
      newsList.innerHTML = '<li class="empty">ログインするとお知らせ一覧が表示されます。</li>';
    }

    function renderErrors(errorMap) {
      Object.entries(errorMap).forEach(([key, message]) => {
        if (errors[key]) {
          errors[key].textContent = message;
        }
      });
    }

    function clearErrors() {
      Object.values(errors).forEach((element) => {
        element.textContent = '';
      });
    }

    function escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = value ?? '';
      return div.innerHTML;
    }

    updateAuthUI();
  </script>
</body>

</html>
