<h2>All Compos</h2>

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

/*
 * all-compos.php
 * ==============
 * Purpose: Display a list of all compos
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

$result = mysql_query("
SELECT COUNT(*) AS `num`, SUM(`points`) AS `points`, `name`, `entries`.`idcompo` AS `idcompo`, `start_date`, DATEDIFF(NOW(), `start_date`) AS `age`
    FROM `entries`, `compos`
    WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `downloadable` != 0
    GROUP BY `entries`.`idcompo`
    ORDER BY `start_date` DESC
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
