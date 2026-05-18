<?php
require_once '../includes/config.php';
unset($_SESSION['reseller_id']);
Auth::clientLogout();
