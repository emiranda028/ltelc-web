<?php
require dirname(__DIR__) . '/lib/bootstrap.php';
unset($_SESSION['staff_id']);
session_regenerate_id(true);
redirect('panel/login.php');
