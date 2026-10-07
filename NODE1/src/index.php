<?php
// Landing: selalu ke katalog publik (semua peran via SSO; login.php hanya darurat).
header("Location: katalog.php", true, 302);
exit();
