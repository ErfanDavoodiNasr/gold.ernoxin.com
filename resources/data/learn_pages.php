<?php

// Merged learn article bodies — not under config/ so price/API requests never parse them.
$articles = require __DIR__ . '/learn_articles.php';
$inline = require __DIR__ . '/learn_inline_pages.php';

return $articles + $inline;
