<?php
session_start();
session_destroy();
header('Location: /CMP_Course_Module/login');
exit;
