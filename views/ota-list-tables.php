<html>
<head>
<title>LineageOTA Builds for <?php echo $branding['name']; ?></title>
<link rel="stylesheet" href="views/ota-list-tables.css">
</head>
<body>
<h1>Currently available builds for <?php echo $branding['name']; ?></h1>
<ul>
<?php
    foreach( $sortedBuilds as $model => $build ) {
        if( array_key_exists( $model, $deviceNames ) ) {
            echo '<li><a href="#' . $model . '">' . $model . '</a> (' . $vendorNames[$model] . ' ' . $deviceNames[$model] . ')</li>' . PHP_EOL;
        } else {
            echo '<li><a href="#' . $model . '">' . $model . '</a></li>' . PHP_EOL;
        }
    }
?>
</ul>

<p><hr /></p>

<?php
    foreach( $sortedBuilds as $model => $builds ) {
        if( array_key_exists( $model, $deviceNames ) ) {
            echo '<h2 id="' . $model . '">' . $model . '</h2>' . PHP_EOL;
            echo '<h3>(' . $vendorNames[$model] . ' ' . $deviceNames[$model] . ')</h3>' . PHP_EOL;
        } else {
            echo '<h2 id="' . $model . '">' . $model . '</h2>' . PHP_EOL;
        }
?>
<table>
  	<thead>
		<tr>
			<th>Filename</th>
			<th>Source</th>
			<th>Date<br>(Y//M/D)</th>
			<th>Channel</th>
			<th>Version</th>
			<th>MD5 Checksum</th>
		</tr>
	</thead>

	<tbody>
<?php
        foreach( $builds as $build ) {
            $source = '<a href="' . $branding['LocalURL'] . '">local</a>';
            if( strstr( $build['url'], 'github.com' ) ) { $source = '<a href="' . $branding['GithubURL'] . '">Github</a>'; }

            echo "\t\t<tr>" . PHP_EOL;
            echo "\t\t\t<td><a href=" . $build['url'] . "'>" . $build['filename'] . '</a></td>' . PHP_EOL;
            echo "\t\t\t<td>" . $source . '</td>' . PHP_EOL;
            echo "\t\t\t<td>" . date( 'Y/m/d', $build['timestamp'] ) . '</td>' . PHP_EOL;
            echo "\t\t\t<td>" . $build['channel'] . '</td>' . PHP_EOL;
            echo "\t\t\t<td>" . $build['version'] . '</td>' . PHP_EOL;
            echo "\t\t\t<td>" . $build['md5sum'] . '</td>' . PHP_EOL;
            echo "\t\t</tr>" . PHP_EOL;
        }
?>
  	</tbody>
</table>

<p><hr /></p>

<?php
    }
?>

</body>
</html>