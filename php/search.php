<?php

/*
 * search.php
 * ==========
 * Purpose: Search the compos for artists, song names, etc.
 *
 * Functions:
 * None.
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

require_once('util.php');

?>
<form method="get">
  <div class="table-desc" style="background-image:url({{BASE}}img/magnifier.png)"><label for="what">Search for:</label></div>
  <div class="table-item"><input name="what" id="what" value="<?php echo htmlspecialchars(isset($_GET["what"]) ? $_GET["what"] : ''); ?>" type="text" required style="width:70%"></div>

  <div class="table-desc" style="background-image:url({{BASE}}img/page.png)">Search in:</div>
  <div class="table-item">
     <input type="checkbox" name="songname" id="songname" value="1" <?php if(!isset($_GET['what']) || isset($_GET['songname'])) echo 'checked'; ?>><label for="songname">Song Name</label>
     <input type="checkbox" name="author" id="author" value="1" <?php if(!isset($_GET['what']) || isset($_GET['author'])) echo 'checked'; ?>><label for="author">Author</label>
     <input type="checkbox" name="componame" id="componame" value="1" <?php if(!isset($_GET['what']) || isset($_GET['componame'])) echo 'checked'; ?>><label for="componame">Compo Name</label>
  </div>

  <div class="table-desc">&nbsp;</div>
  <div class="table-item"><input type="submit"></div>

</form>
<?php

if(isset($_GET['what']) && $_GET['what'] != '')
{
    $what = $_GET['what'];
    $what = str_replace('%', '\%', $what);
    $what = str_replace('_', '\_', $what);
    $what = str_replace('*', '%', $what);
    $what = str_replace('?', '_', $what);
    $what = "%$what%";
    
    if(isset($_GET['componame']))
    {
        
        $stmt = $mysqli->prepare('
            SELECT COUNT(*) AS `num`, SUM(`points`) AS `points`, `name`, `entries`.`idcompo` AS `idcompo`, DATEDIFF(NOW(), `start_date`) AS `age`
                FROM `entries`, `compos`
                WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `compos`.`active` = 0 
                AND `compos`.`name` LIKE ? 
                GROUP BY `entries`.`idcompo`
                ORDER BY `num` DESC, `points` DESC
        ') or die('query failed');
        $stmt->bind_param('s', $what);
        $stmt->execute() or die('query failed');
        $result = $stmt->get_result();

        if($result->num_rows > 0)
        {
?>
        <h2>Compos</h2>

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
            while($row = $result->fetch_assoc())
            {
                if($row['points'] === NULL) $row['author'] = 'n/a';
                echo '<tr><td>', $row['num'], '</td>
                    <td>', $row['points'], '</td>
                    <td><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a></td>
                    <td>', $row['age'], ' days ago</td>
                    </tr>';
            }
?>
</tbody>
</table>
<?php
        }
        $result->free();
        $stmt->close();
    }

    if(isset($_GET['songname']) || isset($_GET['author']))
    {
        $paramType = '';
        
        $query = '
        SELECT `author`, `filename`, `title`, `points`, `place`, `name`, `entries`.`idcompo` AS `idcompo`
            FROM `entries`, `compos`
            WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `compos`.`active` = 0
            AND (0';
        if(isset($_GET['songname']))
        {
            $query .= " OR `title` LIKE ? OR `filename` LIKE ?";
            $paramType .= 'ss';
        }
        if(isset($_GET['author']))
        {
            $query .= " OR (`author` LIKE ? AND `points` IS NOT NULL)";
            $paramType .= 's';
        }
        $query .= ')
            ORDER BY `entries`.`idcompo` DESC, `title` ASC';

        $params[] = & $paramType;
        for($i = strlen($paramType); $i > 0; $i--)
        {
            $params[] = & $what;
        }

        $stmt = $mysqli->prepare($query) or die('query failed');
        call_user_func_array(array($stmt, 'bind_param'), $params);
        $stmt->execute() or die('query failed');
        $result = $stmt->get_result();

        if($result->num_rows > 0)
        {
?>
<h2>Entries</h2>

<script type="text/javascript">var basepath = "{{BASE}}";</script>
<script type="text/javascript" src="{{BASE}}js/chiptune2.js" async></script>
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

            while($row = $result->fetch_assoc())
            {
                if($row['points'] === NULL) $row['author'] = 'n/a';
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
<?php
        }
        $result->free();
        $stmt->close();
    }
}

?>

<script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ" crossorigin="anonymous"></script>
<script src="{{BASE}}js/jquery.tablesorter.min.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function()
	{
		$(".stats").tablesorter();
	}
);
</script>