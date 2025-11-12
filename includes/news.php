<?php

if (!defined('NEWS_SOURCE')) {
    define('NEWS_SOURCE', __DIR__ . '/../data/news.json');
}

if (!function_exists('loadNewsItems')) {
    function loadNewsItems(string $path = NEWS_SOURCE): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }

        usort($items, static function ($a, $b) {
            return strtotime($b['date'] ?? 0) <=> strtotime($a['date'] ?? 0);
        });

        return $items;
    }
}

if (!function_exists('findNewsItem')) {
    function findNewsItem(string $id, ?array $items = null): ?array
    {
        $items = $items ?? loadNewsItems();

        foreach ($items as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }

        return null;
    }
}

if (!function_exists('saveNewsItems')) {
    function saveNewsItems(array $items, string $path = NEWS_SOURCE): bool
    {
        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return file_put_contents($path, $json . PHP_EOL) !== false;
    }
}

if (!function_exists('generateNewsId')) {
    function generateNewsId(string $title, string $date): string
    {
        $normalized = trim($title);
        $normalized = preg_replace('/[\s　]+/u', '-', $normalized);

        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $normalized);
        if ($slug === false) {
            $slug = $normalized;
        }

        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '', $slug);
        if ($slug === '') {
            $slug = 'news';
        }

        $timestamp = strtotime($date) ?: time();
        $datePart = date('Ymd', $timestamp);

        return sprintf('%s-%s', $datePart, $slug);
    }
}

if (!function_exists('formatNewsDate')) {
    function formatNewsDate(string $date): string
    {
        try {
            $dateTime = new DateTime($date);
            return $dateTime->format('Y.m.d');
        } catch (Exception $e) {
            return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
        }
    }
}

