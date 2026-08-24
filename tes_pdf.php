<?php

$pdf = __DIR__ . '/uploads/1786033831_min.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline');
header('Content-Length: ' . filesize($pdf));

readfile($pdf);
exit;