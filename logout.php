<?php
require_once __DIR__ . '/includes/functions.php';

logout_member();
header('Location: index.php');
exit;
