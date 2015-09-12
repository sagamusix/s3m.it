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
<form method="post" action="">
  <input type="hidden" name="sent" />
  <div class="table-desc" style="background-image:url({{BASE}}img/magnifier.png)"><label for="what">Search for:</label></div>
  <div class="table-item"><input name="what" id="what" value="<?php echo htmlspecialchars(isset($_POST["what"]) ? $_POST["what"] : ''); ?>" type="text" style="width:70%" /></div>

  <div class="table-desc" style="background-image:url({{BASE}}img/page.png)">Search in:</div>
  <div class="table-item">
     <input type="checkbox" name="songname" id="songname" value="1" <?php if(!isset($_POST['sent']) || isset($_POST['songname'])) echo 'checked="checked"'; ?> /><label for="songname">Song Name</label>
     <input type="checkbox" name="author" id="author" value="1" <?php if(!isset($_POST['sent']) || isset($_POST['author'])) echo 'checked="checked"'; ?> /><label for="author">Author</label>
     <input type="checkbox" name="componame" id="componame" value="1" <?php if(!isset($_POST['sent']) || isset($_POST['componame'])) echo 'checked="checked"'; ?> /><label for="componame">Compo Name</label>
  </div>

  <div class="table-desc">&nbsp;</div>
  <div class="table-item"><input type="submit" /></div>

</form>
<?php

if(isset($_POST['what']) && $_POST['what'] != '')
{
    $what = $_POST['what'];
    $what = str_replace('%', '\%', $what);
    $what = str_replace('_', '\_', $what);
    $what = str_replace('*', '%', $what);
    $what = str_replace('?', '_', $what);
    $what = "'%" . mysql_real_escape_string($what) . "%'";
    
    if(isset($_POST['componame']))
    {
        $result = mysql_query("
            SELECT COUNT(*) AS `num`, SUM(`points`) AS `points`, `name`, `entries`.`idcompo` AS `idcompo`, DATEDIFF(NOW(), `start_date`) AS `age`
                FROM `entries`, `compos`
                WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `compos`.`active` = 0 
                AND `compos`.`name` LIKE $what 
                GROUP BY `entries`.`idcompo`
                ORDER BY `num` DESC, `points` DESC
            ") or die('query failed');
        if(mysql_num_rows($result) > 0)
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
            while($row = mysql_fetch_assoc($result))
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
    }

    if(isset($_POST['songname']) || isset($_POST['author']))
    {
        $query = "
        SELECT `author`, `filename`, `title`, `points`, `place`, `name`, `entries`.`idcompo` AS `idcompo`
            FROM `entries`, `compos`
            WHERE `entries`.`idcompo` = `compos`.`idcompo` AND `compos`.`active` = 0
            AND (0";
        if(isset($_POST['songname'])) $query .= " OR `title` LIKE $what OR `filename` LIKE $what";
        if(isset($_POST['author'])) $query .= " OR (`author` LIKE $what AND `points` IS NOT NULL)";
        $query .= ")
            ORDER BY `entries`.`idcompo` DESC, `title` ASC";
        $result = mysql_query($query) or die('query failed');

        if(mysql_num_rows($result) > 0)
        {
?>
<h2>Entries</h2>

<script type="text/javascript">var basepath = "{{BASE}}";</script>
<script type="text/javascript" src="{{BASE}}js/chiptune2.js"></script>
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

            while($row = mysql_fetch_assoc($result))
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
    }
}

?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
<script src="{{BASE}}js/jquery.tablesorter.min.js" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function()
	{
		$(".stats").tablesorter();
	}
);
</script>