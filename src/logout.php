<?php
session_destroy();
header('Location: ' . BASE_URL . '/login');
exit;
