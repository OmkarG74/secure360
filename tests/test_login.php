<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/guard/login';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

require __DIR__ . '/../public/index.php';
