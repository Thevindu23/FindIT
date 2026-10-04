<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_method('GET');
reply(categories());
