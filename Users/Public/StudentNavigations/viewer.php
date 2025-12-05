<?php
$file = $_GET['file'] ?? '';
$year = $_GET['year_completed'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Research Viewer</title>
    <style>
        html, body { margin: 0; height: 100%; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <iframe src="https://mozilla.github.io/pdf.js/web/viewer.html?file=<?php echo urlencode("serve_pdf.php?file={$file}&year={$year}"); ?>#toolbar=0"></iframe>
</body>
</html>
