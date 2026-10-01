<?php
session_start();

// Hapus semua data session
session_unset();
session_destroy();


header("Location: login.php"); 
exit();
?>