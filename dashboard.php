<?php
require_once __DIR__ . "/config/functions.php";
require_login();
redirect(dashboard_path());
