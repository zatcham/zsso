<?php

// Session
session_name("_zsso_session");
session_start();

// Import composer
require_once ("../../vendor/autoload.php");

// Import config
require_once ("../../include/config.php");

// Load functions
require_once ("../../include/functions.php");