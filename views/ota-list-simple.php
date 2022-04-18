<html>
<head>
<title>LineageOTA Builds for <?php echo $branding['name']; ?></title>
</head>
<body>
<h1>Currently available builds for <?php echo $branding['name']; ?></h1>
<?php
    foreach( $builds as $build ) {
        echo "<a href=" . $build['url'] . "'>" . $build['filename'] . '</a><br>' . PHP_EOL;
    }
?>
</body>
</html>