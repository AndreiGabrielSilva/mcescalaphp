<?php
require_once __DIR__ . '/includes/helpers.php';
startSession();
session_destroy();
header('Location: index.php');
exit;
