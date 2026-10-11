<?php
require_once __DIR__ . '/config.php';
logout_user();
session_start();
flash('Signed out.');
redirect('index.php');
