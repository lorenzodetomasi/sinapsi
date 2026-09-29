<?php
/* The chooser of a new event moved to ../add/ (29 Sep 2026): the admin's
 * addresses are in English. The old one keeps working for bookmarks and old
 * links, and passes on whatever it was asked. */
$query = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: ../add/' . ($query !== '' ? '?' . $query : ''), true, 301);
exit;
