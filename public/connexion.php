<?php
require_once __DIR__ . '/config.php';
applySecurityHeaders();
http_response_code(404);
exit('Not Found');
