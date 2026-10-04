<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');
$_SESSION = [];
$options = session_get_cookie_params();
unset($options['lifetime']);
$options['expires'] = time() - 3600;
setcookie(session_name(), '', $options);
session_destroy();
reply(['success' => true]);
