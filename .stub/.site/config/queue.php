<?php
$queue = require base_path('config' . DIRECTORY_SEPARATOR . 'queue.php');

return array_merge($queue, []);
