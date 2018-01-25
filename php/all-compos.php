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

$result = $mysqli->query('
SELECT COUNT(*) AS `num`, SUM(`points`) AS `points`, `name`, `entries`.`idcompo` AS `idcompo`, DATE(`start_date`) AS `when`, DATEDIFF(NOW(), `start_date`) AS `age`
    FROM `entries`, `compos`
    WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `downloadable` != 0
    GROUP BY `entries`.`idcompo`
    ORDER BY `start_date` DESC
') or die('query failed');

while($row = $result->fetch_assoc())
{
    $age = $row['age'];
    if($age < 365)
    {
        $age .= 'd';
    } else
    {
        $age = intval($age / 365) . 'y ' . ($age % 365) . 'd';
    }

    echo '<tr><td>', $row['num'], '</td>
        <td>', $row['points'], '</td>
        <td><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a></td>
        <td title="', $row['when'], '">', $age, ' ago</td>
        </tr>';
}
$result->free();
?>
</tbody>
</table> 
