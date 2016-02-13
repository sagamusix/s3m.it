<?php

/*
 * upload.php
 * =========
 * Purpose: Upload form for compo participants.
 *
 * Functions:
 * listHosts()
 *     Displays a list of open compos. 
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

?>
          <p>
            Yo, welcome to the web2.0 compo upload shizzle. You can upload your compo choons here.
          </p>
<?php

$compos = array();
$compoids = array();
$result = mysql_query("SELECT * FROM `compos`, `hosts` WHERE (`active` != 0) AND (`compos`.`idhost` = `hosts`.`idhost`) ORDER BY `start_date` DESC") or die("query failed");
while($row = mysql_fetch_assoc($result))
{
    array_push($compos, $row);
    array_push($compoids, $row["idcompo"]);
}
$numCompos = count($compoids);

if(isset($_GET["compoid"]) && in_array($_GET["compoid"], $compoids))
{
    foreach($compos as $compo)
    {
        if($compo["idcompo"] == $_GET["compoid"])
        {
            uploadform($compo);
            break;
        }
    }
} else if(isset($_GET["compoid"]) && $numCompos > 0)
{
?>
          <p>
            Uploading is closed for this compo.
          </p>
<?php
} else if($numCompos == 1)
{
    uploadform($compos[0]);
} else if($numCompos > 0)
{
    listHosts();
} else
{
?>
          <p>
            ...well, actually you can't, because there is <strong>no compo</strong> running.
          </p>

<?php
}

if(!isset($_GET["compoid"]))
{
    echo '<h2>Check out the latest compos</h2>
        <ul>';

    $result = mysql_query("
    SELECT `name`, `idcompo`, `downloadable`
        FROM `compos`
        WHERE `downloadable` != 0
        ORDER BY `start_date` DESC
        LIMIT 0, 5
    ") or die('query failed');
    
    while($row = mysql_fetch_assoc($result))
    {
        echo '<li><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a>';

        // Display winners
        $result2 = mysql_query("SELECT `author` FROM `entries` WHERE (`idcompo` = {$row['idcompo']}) AND (`place` = 1)") or die("query failed");
        $winners = mysql_num_rows($result2);
        if($winners != 0)
        {
            echo ' (compo winner', ($winners != 1 ? 's' : ''), ': <strong>';
            $first = true;
            while($row = mysql_fetch_assoc($result2))
            {
                if(!$first)
                {
                    echo ', ';
                }
                echo htmlspecialchars($row['author']);
                $first = false; 
            }
            echo '</strong>)';
        }
        echo '</li>';
    }
    echo '<li><a href="{{BASE}}compos">more compos...</a></li>';
    echo '</ul>';
}


function uploadform($compo)
{

    if(intval($compo["active"]) != 0)
    {
?>

          <h2>
            Ready, set...
          </h2>
          <p>
            You are participating in the <strong><?php echo htmlspecialchars($compo["name"]); ?></strong> compo hosted by <strong><?php echo htmlspecialchars($compo["hostname"]); ?></strong>.
          </p>

<?php
          if(isset($_SESSION["compo-{$compo["idcompo"]}"]))
          {
              echo '<p>Do you want to replace your previous entry? You can do so by uploading a new file with the same name.</p>';
          }
?>

          <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
          <script type="text/javascript">
          function validateUpload()
          {
              if(document.getElementById("author").value == "")
              {
                  alert("Please enter your name!");
                  document.getElementById("author").focus();
                  return false;
              }
              document.getElementById("loader").style.visibility = "visible";

              ping(0);

              return true;
          }

          function ping(cancel)
          {
              // Announce uploading status every 30 seconds
              $.ajaxSetup({async:false});
              $.post("{{BASE}}ping", {
                "author": document.getElementById("author").value,
                "compo": <?php echo $compo["idcompo"]; ?>,
                "token": document.getElementById("upload-token").value,
                "cancel": cancel,
                },
                function(data) {
                    var vals = data.split("\n");
                    document.getElementById("upload-token").value = vals[0];
                    if(vals.length > 1)
                    {
                        document.getElementById("loader").innerHTML = vals[1] + "%";
                    }
                }
              );
              if(!cancel)
              {
                  window.setTimeout("ping(0)", 30000);
              }
          }
          
          function cancelPing()
          {
              if(document.getElementById("upload-token").value != "0")
              {
                  ping(1);
              }
          }
          window.onunload = cancelPing;
          </script>

          <form action="{{BASE}}fileupload" enctype="multipart/form-data" method="post" onsubmit="return validateUpload()">

          <input type="hidden" name="compo" value="<?php echo $compo['idcompo']; ?>" />
          <input type="hidden" name="token" value="0" id="upload-token" />
          <input type="hidden" name="<?php echo ini_get('session.upload_progress.name'); ?>" value="compo" />

          <div class="table-desc" style="background-image:url({{BASE}}img/user.png)"><label for="author">Your Name:</label></div>
          <div class="table-item"><input name="author" id="author" value="<?php echo isset($_COOKIE['author']) ? htmlspecialchars($_COOKIE['author']) : ''; ?>" type="text" maxlength="<?php echo MAX_AUTHOR_LENGTH; ?>" style="width:50%" /> (required)</div>

          <div class="table-desc" style="background-image:url({{BASE}}img/page.png)"><label for="userfile">Choon:</label></div>
          <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE; ?>" />
          <div class="table-item"><input name="userfile" id="userfile" type="file" maxlength="<?php echo MAX_UPLOAD_SIZE; ?>" /> (required, <?php echo intval(MAX_UPLOAD_SIZE * 10 / 1024 / 1024) / 10; ?> MiB max)</div>

          <div class="table-desc">&nbsp;</div>
          <div class="table-item"><input type="submit" /></div>
          </form>

          <div id="loader">
          </div>
<?php
    }
}

function listHosts()
{
    global $compos;

?>
          <h2>
            Pick a compo!
          </h2>

          <ul>
<?php
    foreach($compos as $compo)
    {
        echo "<li><a href=\"{{BASE}}join/{$compo["idcompo"]}\"><strong>", htmlspecialchars($compo["name"]), "</strong>, hosted by <strong>", htmlspecialchars($compo["hostname"]), "</strong></a></li>\n";
    }
    echo "</ul>\n";
}

?>
