<?php
require dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');
reply(['user' => $user]);
