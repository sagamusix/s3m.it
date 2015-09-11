<h2>Hall of Fame</h2>

<table class="stats">
<thead>
    <tr>
        <th>Rank</th>
        <th><a href="{{BASE}}stats/name">Name</a></th>
        <th><a href="{{BASE}}stats/num"># Compos</a></th>
        <th><a href="{{BASE}}stats">Total Pts</a></th>
        <th><a href="{{BASE}}stats/place">Avg Place</a></th>
    </tr>
</thead>
<tbody>
<?php

/*
 * stats.php
 * ============
 * Purpose: Various statistics about compos
 *
 * Functions:
 * None.
 *
 */
 
require_once('util.php');
 
$result = mysql_query("
SELECT `author`, COUNT(*) AS `num_compos`, SUM(`points`) AS `total_points`, AVG(`place`) AS `avg_place`
    FROM `entries`
    GROUP BY `author`
    ORDER BY `total_points` DESC
") or die('query failed');

$i = 0;
while($row = mysql_fetch_assoc($result))
{
    if($row['avg_place'] == '')
    {
        // People who only competed in compos with no results
        continue;
    }

    $i++;
    echo '<tr><td>' . $i . '</td>
        <td>' . htmlspecialchars($row['author']) . '</td>
        <td>' . $row['num_compos'] . '</td>
        <td>' . $row['total_points'] . '</td>
        <td>' . $row['avg_place'] . '</td>
        </tr>';
}

?>
</tbody>
</table>

<h2>Entries with most points</h2>

<script type="text/javascript" src="{{BASE}}js/chiptune2.js"></script>
<script type="text/javascript">var basepath = "{{BASE}}";</script>
<table class="stats">
<thead>
    <tr>
        <th>Points</th>
        <th>Place</th>
        <th>Name</th>
        <th>Author</th>
        <th>Compo</th>
    </tr>
</thead>
<tbody>
<?php

$result = mysql_query("
SELECT `author`, `filename`, `title`, `points`, `place`, `name`, `entries`.`idcompo` AS `idcompo`
    FROM `entries`, `compos`
    WHERE `entries`.`idcompo` = `compos`.`idcompo`
    AND `points` IS NOT NULL
    ORDER BY `points` DESC
    LIMIT 0, 10
") or die('query failed');

while($row = mysql_fetch_assoc($result))
{
    echo '<tr><td>', $row['points'], '</td>
        <td>', ordinalize($row['place']), '</td>
        <td title="', htmlspecialchars($row["title"]), '"><a href="javascript:;" onclick="javascript:play(this)"><img src="{{BASE}}img/play.png" width="16" height="16" alt="Play" title="Play"></a> <a href="{{BASE}}pack/', $row['idcompo'], '/', urlencode($row['filename']), '">', htmlspecialchars($row['filename']), '</a></td>
        <td>', htmlspecialchars($row['author']), '</td>
        <td><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a></td>
        </tr>';
}

?>
</tbody>
</table>

<h2>Authors with most winner entries</h2>

<table class="stats">
<thead>
    <tr>
        <th>Author</th>
        <th>Wins (total)</th>
        <th>Wins (last month)</th>
    </tr>
</thead>
<tbody>
<?php

$result = mysql_query("
SELECT `author`, COUNT(*) AS `num`, (SELECT COUNT(*) FROM `entries` WHERE `author` = `e`.`author` AND `place` = 1 AND `date` > DATE_SUB(NOW(), INTERVAL 1 MONTH)) AS `num_recent`
    FROM `entries` AS `e`
    WHERE `place` = 1
    GROUP BY `author`
    ORDER BY `num` DESC
    LIMIT 0, 10
") or die('query failed');

while($row = mysql_fetch_assoc($result))
{
    echo '<tr><td>', htmlspecialchars($row['author']), '</td>
        <td>', $row['num'], '</td>
        <td>', $row['num_recent'], '</td>
        </tr>';
}

?>
</tbody>
</table>

<h2>Compos with most entries</h2>

<table class="stats">
<thead>
    <tr>
        <th>Entries</th>
        <th>Total Pts</th>
        <th>Compo</th>
        <th>When</th>
    </tr>
</thead>
<tbody>
<?php
$result = mysql_query("
SELECT COUNT(*) AS `num`, SUM(`points`) AS `points`, `name`, `entries`.`idcompo` AS `idcompo`, `start_date`, DATEDIFF(NOW(), `start_date`) AS `age`
    FROM `entries`, `compos`
    WHERE `entries`.`idcompo` = `compos`.`idcompo`
    GROUP BY `entries`.`idcompo`
    ORDER BY `num` DESC, `points` DESC
    LIMIT 0, 10
") or die('query failed');

while($row = mysql_fetch_assoc($result))
{
    echo '<tr><td>', $row['num'], '</td>
        <td>', $row['points'], '</td>
        <td><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a></td>
        <td title="', $row['start_date'], '">', $row['age'], ' days ago</td>
        </tr>';
}
?>
</tbody>
</table>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
<script src="{{BASE}}js/jquery.tablesorter.min.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function()
	{
		$(".stats").tablesorter();
	}
);
</script>