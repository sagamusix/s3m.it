<?php

/*
 * upload.php
 * =========
 * Purpose: Upload form for compo participants.
 *
 * Functions:
 * uploadForm()
 *     Displays the upload form for a given compo
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
$result = $mysqli->query('SELECT * FROM `compos`, `hosts` WHERE (`active` != 0) AND (`compos`.`idhost` = `hosts`.`idhost`) ORDER BY `start_date` DESC') or die('query failed');
while($row = $result->fetch_assoc())
{
    array_push($compos, $row);
    array_push($compoids, $row["idcompo"]);
}
$result->free();
$numCompos = count($compoids);

if(isset($_GET["compoid"]) && in_array($_GET["compoid"], $compoids))
{
    foreach($compos as $compo)
    {
        if($compo["idcompo"] == $_GET["compoid"])
        {
            uploadForm($compo);
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
    uploadForm($compos[0]);
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

    $result = $mysqli->query('
    SELECT `name`, `idcompo`, `downloadable`
        FROM `compos`
        WHERE `downloadable` != 0
        ORDER BY `start_date` DESC
        LIMIT 0, 5
    ') or die('query failed');
    
    $stmt1stPlace = $mysqli->prepare("SELECT `author` FROM `entries` WHERE (`idcompo` = ?) AND (`place` = 1)") or die('query failed');
    
    while($row = $result->fetch_assoc())
    {
        echo '<li><a href="{{BASE}}compo/', $row['idcompo'], '">', htmlspecialchars($row['name']), '</a>';

        // Display winners
        $stmt1stPlace->bind_param('i', $row['idcompo']);
        $stmt1stPlace->execute() or die('query failed');
        $result2 = $stmt1stPlace->get_result();
        $winners = $result2->num_rows;
        if($winners != 0)
        {
            echo ' (compo winner', ($winners != 1 ? 's' : ''), ': <strong>';
            $first = true;
            while($row = $result2->fetch_assoc())
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
        $result2->free();
        echo '</li>';
    }
    $result->free();
    $stmt1stPlace->close();
    echo '<li><a href="{{BASE}}compos">more compos...</a></li>';
    echo '</ul>';
}


function uploadForm($compo)
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

          <script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ" crossorigin="anonymous"></script>
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

          <input type="hidden" name="compo" value="<?php echo $compo['idcompo']; ?>">
          <input type="hidden" name="token" value="0" id="upload-token">
          <input type="hidden" name="<?php echo ini_get('session.upload_progress.name'); ?>" value="compo">

          <div class="table-desc" style="background-image:url({{BASE}}img/user.png)"><label for="author">Your Name:</label></div>
          <div class="table-item"><input name="author" id="author" value="<?php echo isset($_COOKIE['author']) ? htmlspecialchars($_COOKIE['author']) : ''; ?>" type="text" maxlength="<?php echo MAX_AUTHOR_LENGTH; ?>" required style="width:50%"> (required)</div>

          <div class="table-desc" style="background-image:url({{BASE}}img/page.png)"><label for="userfile">Choon:</label></div>
          <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE; ?>">
          <div class="table-item"><input name="userfile" id="userfile" type="file" maxlength="<?php echo MAX_UPLOAD_SIZE; ?>" required> (required, <?php echo intval(MAX_UPLOAD_SIZE * 10 / 1024 / 1024) / 10; ?> MiB max)</div>

          <div class="table-desc">&nbsp;</div>
          <div class="table-item"><input type="submit"></div>
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
