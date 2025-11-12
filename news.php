<?php
require_once __DIR__ . '/includes/news.php';

$newsItems = loadNewsItems();
$newsId = isset($_GET['id']) ? (string) $_GET['id'] : null;
$currentItem = $newsId ? findNewsItem($newsId, $newsItems) : null;

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'お知らせ一覧｜株式会社あかりハウジング';
if ($currentItem) {
    $pageTitle = sprintf('%s｜お知らせ｜株式会社あかりハウジング', $currentItem['title']);
}
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

<body class="news-page">
  <header class="header simple-header">
    <div class="header-inner">
      <div class="logo"><a href="index.php">株式会社あかりハウジング</a></div>
      <nav class="nav" aria-label="サブナビゲーション">
        <ul>
          <li><a href="index.php#about">あかりの強み</a></li>
          <li><a href="index.php#service">事業内容</a></li>
          <li><a href="index.php#case">施工事例</a></li>
          <li><a href="index.php#contact">お問い合わせ</a></li>
        </ul>
      </nav>
      <a href="admin.php" class="contact-btn">管理画面</a>
    </div>
  </header>

  <main class="news-main" aria-labelledby="page-title">
    <div class="page-hero">
      <div class="page-hero-inner">
        <p class="page-label">NEWS</p>
        <h1 id="page-title">お知らせ<?= $currentItem ? '｜' . h($currentItem['title']) : '' ?></h1>
        <p class="page-lead">イベントや最新情報、施工事例更新のお知らせを掲載しています。</p>
      </div>
    </div>

    <div class="news-container">
      <?php if ($currentItem): ?>
        <article class="news-article" aria-labelledby="news-title">
          <header class="news-article-header">
            <time datetime="<?= h($currentItem['date'] ?? '') ?>"><?= formatNewsDate($currentItem['date'] ?? '') ?></time>
            <h2 id="news-title"><?= h($currentItem['title'] ?? '') ?></h2>
          </header>
          <div class="news-article-body">
            <?php $content = $currentItem['content'] ?? ''; ?>
            <?php if ($content !== ''): ?>
              <?php foreach (preg_split('/\r?\n/', $content) as $paragraph): ?>
                <?php if (trim($paragraph) === '') { continue; } ?>
                <p><?= h($paragraph); ?></p>
              <?php endforeach; ?>
            <?php else: ?>
              <p>本文は準備中です。</p>
            <?php endif; ?>
          </div>
          <footer class="news-article-footer">
            <a class="btn secondary" href="news.php">一覧に戻る</a>
          </footer>
        </article>
      <?php else: ?>
        <section class="news-listing" aria-label="お知らせ一覧">
          <?php if ($newsId !== null && !$currentItem): ?>
            <p class="news-empty">指定されたお知らせは見つかりませんでした。最新の記事をご確認ください。</p>
          <?php endif; ?>
          <?php if (empty($newsItems)): ?>
            <p class="news-empty">現在公開中のお知らせはありません。</p>
          <?php else: ?>
            <ul>
              <?php foreach ($newsItems as $item): ?>
                <li>
                  <a href="news.php?id=<?= urlencode($item['id'] ?? '') ?>">
                    <span class="date"><?= formatNewsDate($item['date'] ?? '') ?></span>
                    <span class="title"><?= h($item['title'] ?? '') ?></span>
                    <span class="excerpt"><?= h($item['excerpt'] ?? '') ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <aside class="news-aside" aria-label="最新のお知らせ">
        <h2>最新情報</h2>
        <?php if (empty($newsItems)): ?>
          <p class="news-empty">まだお知らせは登録されていません。</p>
        <?php else: ?>
          <ul>
            <?php foreach (array_slice($newsItems, 0, 5) as $item): ?>
              <li>
                <a href="news.php?id=<?= urlencode($item['id'] ?? '') ?>">
                  <span class="date"><?= formatNewsDate($item['date'] ?? '') ?></span>
                  <span class="title"><?= h($item['title'] ?? '') ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </aside>
    </div>
  </main>

  <footer class="footer">
    <div class="footer-inner">
      <small>© <?= date('Y') ?> 株式会社あかりハウジング</small>
      <a href="tel:0450000000">TEL 045-000-0000</a>
    </div>
  </footer>
</body>

</html>
