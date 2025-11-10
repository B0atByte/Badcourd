<?php
session_start();
session_destroy();
header('Location: /BARGAIN SPORT/auth/login.php');