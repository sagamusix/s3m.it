<?php

/*
 * compo.php
 * =========
 * Purpose: Compo management for hosts
 *
 * Functions:
 * editCompo($compo)
 *     Shows the edit table for a compo specified by the parameter $compo
 * addCompo()
 *     Add a new compo, using the data received through a HTTP POST request.
 * updateCompo()
 *     Update an existing compo, using the data received through a HTTP POST request.
 * deleteCompo($compo, $doRedirect = TRUE)
 *     Delete the compo specified by the parameter $compo, and redirect to the host's compo overview if $doRedirect is TRUE.
 * listCompos($user)
 *     Show all compos run by the specified host.
 * printCompoList($compos)
 *     Helper function for listCompos(), prints a list of compos.
 * compoTable($row)
 *     Helper function for editCompo() and listCompos() which prints out a table for editing a compo or adding a new one if $row is NULL.
 * canListCompos($user)
 *     Returns TRUE if the logged in user can view the compos of another user specified by the parameter $user.
 * canEditCompo($row)
 *     Returns TRUE if the logged in user can edit the compo specified by the array $row.
 * canDeleteCompo($row)
 *     Returns TRUE if the logged in user can delete the compo specified by the array $row.
 * checkCompoPings($compo, $html)
 *     Check if anyone might still be uploading to the compo. $html = true if HTML output is wanted, otherwise = JS output
 * viewCompoAjax($compo, $ajax)
 *     Update the file list of a compo. $ajax is true if AJAX output is wanted, otherwise = full page output. Returns TRUE if any entries have been updated since the last page refresh.
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

if(ACCESS < ACCESS_HOST)
{
    redirect(BASEDIR);
}

require_once('archive.php');

switch($_GET['action'])
{
    case 'compo':
        if(isset($_GET['which']) && intval($_GET['which']) > 0)
        {
            editCompo(intval($_GET['which']));
        } else
        {
            listCompos($_SESSION['idhost']);
        }
        break;
    case 'listcompos':
        listCompos($_GET['which']);
        break;
    case 'addcompo':
        addCompo();
        break;
    case 'editcompo':
        updateCompo();
        break;
    case 'delcompo':
        deleteCompo($_GET['which']);
        break;
    case 'checkping':
        checkCompoPings($_GET['which'], FALSE);
        break;
    case 'ajaxcompo':
        viewCompoAjax($_GET['which'], TRUE);
        break;
}


function editCompo($compo)
{
    $compo = intval($compo);
    $result = mysql_query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    if(!canEditCompo($row))
    {
        redirect(BASEDIR);
    }

    echo "<h2>Edit compo &quot;", htmlspecialchars($row["name"]), "&quot;</h2>";
    compoTable($row);

    if($row["active"] != 0)
    {
        echo '<p>This compo is currently <strong>open</strong>. Point people to this URL so they can upload their tunes:</p>';
        echo '<p><input type="text" name="foo" value="{{BASE_ABS}}join/', $compo, '" style="width:100%;" onfocus="this.select()" /></p>';
    }

    echo '<h2>Uploaded files</h2>';
    echo '<div id="files-ajax">';
    if(viewCompoAjax($compo, FALSE))
    {
        // Unmark updated entries
        mysql_query("UPDATE `entries` SET `altered` = 0 WHERE `idcompo` = $compo") or die("query failed");
    }
    echo '</div>';
}


function addCompo()
{
    if(!isset($_POST["componame"]))
    {
        redirect(BASEDIR);
    }

    if($_POST["componame"] == "")
    {
        $_POST["componame"] = date("Y-m-d", time());
    }

    $result = mysql_query("INSERT INTO `compos` (`name`, `active`, `downloadable`, `idhost`) VALUES (
        '" . mysql_real_escape_string($_POST["componame"]) . "',
        '" . (intval($_POST["active"]) != 0 ? 1 : 0) . "',
        '" . (intval($_POST["active"]) != 0 ? 0 : 1) . "',
        '" . intval($_SESSION["idhost"]) . "')
    ") or die("query failed");
    //         '" . (intval($_POST["downloadable"]) != 0 ? 1 : 0) . "',

    redirect(BASEDIR . "admin/compo/" . mysql_insert_id());
}


function updateCompo()
{
    $compo = intval($_POST["which"]);

    $result = mysql_query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    if(!canEditCompo($row))
    {
        redirect(BASEDIR);
    }

    if($_POST["componame"] == "")
    {
        $_POST["componame"] = date("Y-m-d", time());
    }
    
    mysql_query("UPDATE `compos` SET
        `name` = '" . mysql_real_escape_string($_POST["componame"]) . "',
        `active` = '" . (intval($_POST["active"]) != 0 ? 1 : 0) . "',
        `downloadable` = '" . (intval($_POST["active"]) != 0 ? 0 : 1) . "'
        WHERE `idcompo` = $compo") or die("query failed");
    // `downloadable` = '" . (intval($_POST["downloadable"]) != 0 ? 1 : 0) . "'

    if(!$_POST["active"])
    {
        // Can't upload anymore, so get rid of any active pings.
        mysql_query("DELETE FROM `uploading` WHERE `idcompo` = $compo") or die("query failed");
    }

    // Recompress compo archive after closing compo
    if(!intval($row['downloadable']) && !intval($_POST["active"]))
    {
        $arc = new ArchiveFile(UPLOAD_DIR . $compo);
        $arc->Recompress();
        $arc->Close();
    }

    redirect(BASEDIR . "admin/compo/$compo");

}


function deleteCompo($compo, $doRedirect = TRUE)
{
    $compo = intval($compo);

    $result = mysql_query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    if(!canDeleteCompo($row))
    {
        redirect(BASEDIR);
    }

    require("file.php");
    deletePack($compo, FALSE);
    mysql_query("DELETE FROM `compos` WHERE `idcompo` = $compo") or die("query failed");

    if($doRedirect)
    {
        redirect(BASEDIR . "admin/compo");
    }
}


function listCompos($user)
{
    $user = intval($user);

    if(!canListCompos($user))
    {
        redirect(BASEDIR);
    }

    $result = mysql_query("SELECT *, (SELECT COUNT(*) FROM `entries` B WHERE A.`idcompo` = B.`idcompo`) AS `num_entries` FROM `compos` A WHERE `idhost` = $user ORDER BY `active` DESC, `start_date` DESC") or die("query failed");

    $active = array();
    $inactive = array();
    while($row = mysql_fetch_assoc($result))
    {
        if($row["active"] == 1)
        {
            array_push($active, $row);
        } else
        {
            array_push($inactive, $row);
        }
    }

    if(count($active))
    {
        echo "<h2>Running compos</h2>";
        printCompoList($active);
    }

    if(count($inactive))
    {
        echo "<h2>Closed compos</h2>";
        printCompoList($inactive);
    }

    // echo "<p>Please delete all compos after hosting!</p>";

    echo "<h2>New compo</h2>";
    compoTable(NULL);

}


function printCompoList($compos)
{
    echo "<ul>";
    foreach($compos as $compo)
    {
        $nameJS = str_replace("'", "\\'", str_replace("\\", "\\\\", htmlspecialchars($compo["name"])));
        echo '<li>
            <a href="{{BASE}}admin/delcompo/', $compo["idcompo"], '" onclick="return confirm(\'Delete ', $nameJS, '?\')"><img src="{{BASE}}img/delete.png" width="16" height="16" alt="Delete" title="Delete" /></a>
            <a href="{{BASE}}admin/compo/', $compo["idcompo"], '">', htmlspecialchars($compo["name"]), '</a> (', $compo["num_entries"], ' entries, started ', $compo["start_date"], ')',
            ($compo["active"] != 0 ? ' <img src="{{BASE}}img/accept.png" width="16" height="16" alt="Running" title="Compo is running" /> ' : ''),
            ($compo["downloadable"] != 0 ? ' <img src="{{BASE}}img/compress.png" width="16" height="16" alt="Downloadable" title="Votepack can be downloaded by users" />' : ''),
            '</li>';
    }
    echo "</ul>";

}


function compoTable($row)
{
    $embedJS = !empty($row) && $row["active"];
    ?>
    <form action="{{BASE}}admin/<?php if(empty($row)) echo "addcompo"; else echo "editcompo"; ?>" method="post"<?php if($embedJS) echo ' onsubmit="return checkClose()"'; ?>>
    
    <input type="hidden" name="which" value="<?php if(!empty($row)) echo $row["idcompo"]; ?>" />
    
    <div class="table-desc" style="background-image:url({{BASE}}img/page.png)"><label for="componame">Name:</label></div>
    <div class="table-item"><input name="componame" id="componame" type="text" value="<?php if(empty($row)) echo "#channel " . date("Y-m-d", time()); else echo htmlspecialchars($row["name"]); ?>" maxlength="<?php echo MAX_COMPONAME_LENGTH; ?>" style="width:50%" /></div>
    
    <div class="table-desc" style="background-image:url({{BASE}}img/accept.png)"><label for="checkactive">Running:</label></div>
    <div class="table-item"><label><input type="radio" id="checkactive" name="active" value="1" <?php if(empty($row) || $row["active"]) echo ' checked="checked"'; ?> /> Open (people can upload songs)</label></div>
    
    <div class="table-desc" style="background-image:url({{BASE}}img/compress.png)"><label for="checkdownloadable">Download:</label></div>
    <div class="table-item"><label><input type="radio" id="checkdownloadable" name="active" value="0" <?php if($row["downloadable"]) echo ' checked="checked"'; ?> /> Closed (people can download the pack)</label></div>
    
    <div class="table-desc">&nbsp;</div>
    <div class="table-item"><input type="submit" /></div>
    </form>
    <?php
    if($embedJS)
    {
        // Dynamic update of the file list + show active uploaders on close
    ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript">
    var closeUpload;
    function checkClose()
    {
        if(document.getElementById("checkdownloadable").checked == false)
        {
            return true;
        }

        $.ajaxSetup({async:false});
        $.post("{{BASE}}admin/checkping/<?php echo $row["idcompo"]; ?>",
            function(data)
            {
                if(data != "")
                {
                    closeUpload = confirm(
                        "Those people might still be uploading:\n"
                        + data
                        + "\nProceed anyway?");
                }
            }
        );
        return closeUpload;
    }

    var updateInterval = 3000;
    var lastUpdate;
    function checkPing()
    {
        $.ajaxSetup({async:true});
        $.post("{{BASE}}admin/ajaxcompo/<?php echo $row["idcompo"]; ?>",
            function(data)
            {
                $('#files-ajax').html(data);
                if(lastUpdate != $('#last-update').text())
                {
                    // Reset dynamic update interval
                    lastUpdate = $('#last-update').text();
                    updateInterval = 3000;
                } else
                {
                    // Increase dynamic update interval as nothing seems to be happening
                    updateInterval += 500;
                    if(updateInterval > 30000)
                    {
                        updateInterval = 30000;
                    }
                }
                window.setTimeout("checkPing()", updateInterval);
            }
        );
    }

    checkPing();
    </script>
    <?php
    }
}


function canListCompos($user)
{
    return (ACCESS == ACCESS_FULLADMIN || $user == $_SESSION["idhost"]);
}


function canEditCompo($row)
{
    return (ACCESS == ACCESS_FULLADMIN || $row["idhost"] == $_SESSION["idhost"]);
}


function canDeleteCompo($row)
{
    return (ACCESS == ACCESS_FULLADMIN || $row["idhost"] == $_SESSION["idhost"]);
}


function checkCompoPings($compo, $html)
{
    $compo = intval($compo);

    if(!$html)
    {
        $result = mysql_query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
        $row = mysql_fetch_assoc($result);
        if(!canEditCompo($row))
        {
            redirect(BASEDIR);
        }
        $delim = "\n";
        ob_end_clean();
    } else
    {
        $delim = ', ';
    }

    // Upload token "timeout" is a minute (pingback should happen every half minute, so this should be more than enough).
    mysql_query("DELETE FROM `uploading` WHERE `start` < " . (time() - 60)) or die("query failed");

    $result = mysql_query("SELECT * FROM `uploading` WHERE `idcompo` = $compo ORDER BY `author` ASC") or die("query failed");
    $first = TRUE;
    if(mysql_num_rows($result))
    {
        while($row = mysql_fetch_assoc($result))
        {
            if(!$first)
            {
                echo $delim;
            } else
            {
                if($html)
                    echo '<p id="uploaders">Currently uploading: ';
            }

            if($html)
                echo htmlspecialchars($row['author']);
            else
                echo $row['author'];
            $first = FALSE;
        }
    }

    if($html)
    {
        if(!$first)
            echo '</p>';
    } else
    {
        die();
    }
}

function viewCompoAjax($compo, $ajax)
{
    $compo = intval($compo);
    $result = mysql_query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    $downloadable = $row["downloadable"];
    if(!canEditCompo($row))
    {
        redirect(BASEDIR);
    }

    if($ajax)
    {
        ob_end_clean();
    }

    $alteredFiles = FALSE;
    $lastUpdate = 0;
    $result = mysql_query("SELECT * FROM `entries` WHERE `idcompo` = $compo ORDER BY `date` ASC") or die("query failed");

    // Show list of uploading people
    checkCompoPings($compo, TRUE);

    if(mysql_num_rows($result))
    {
        echo '<ul id="file-list">';
        while($row = mysql_fetch_assoc($result))
        {
            echo '<li>
                <span class="add-date">';

            if($row["altered"] != 0)
            {
                echo ' <span class="updated">updated!</span> ';
                $alteredFiles = TRUE;
            }

            $nameJS = str_replace("'", "\\'", str_replace("\\", "\\\\", htmlspecialchars($row["filename"])));

            echo $row['date'], '</span>
                <a href="', BASEDIR, 'admin/delfile/', $row["identry"], '" onclick="return confirm(\'Delete ', $nameJS, '?\')"><img src="', BASEDIR, 'img/page_delete.png" width="16" height="16" alt="Delete" title="Delete" /></a>
                <a href="', BASEDIR, 'admin/fetchfile/', $row["identry"], '" title="', htmlspecialchars($row["title"]), '"><strong>', htmlspecialchars($row["filename"]), '</strong></a>
                by <strong>', htmlspecialchars($row["author"]), '</strong></li>';

            $lastUpdate = $row['date'];
        }
        echo '<li><a href="', BASEDIR, 'admin/fetchpack/', $compo, '"><img src="', BASEDIR, 'img/compress.png" width="16" height="16" alt="" /> Download the whole pack (admin only)</a></li>';
        echo "</ul>";
        echo '<p><a href="', BASEDIR, 'admin/voting/', $compo, '"><img src="', BASEDIR, 'img/table.png" width="16" height="16" alt="" /> Enter <strong>votes</strong></a> using lazyvote</p>';

        if($downloadable != 0)
        {
            echo '<p>This pack is <strong>downloadable</strong>. Point people to this URL to get the votepack:</p>';
            echo '<p><input type="text" name="bar" value="', BASEDIR_ABSOLUTE, 'pack/', $compo, '" style="width:100%;" onfocus="this.select()" /></p>';
            echo '<p>Compo overview page:</p>';
            echo '<p><input type="text" name="bar" value="', BASEDIR_ABSOLUTE, 'compo/', $compo, '" style="width:100%;" onfocus="this.select()" /></p>';
        }

        echo '<p><a href="', BASEDIR, 'admin/delpack/', $compo, '" onclick="return confirm(\'Delete all files?\')"><img src="', BASEDIR, 'img/page_delete.png" width="16" height="16" alt="" /> Delete <strong>all</strong> files</a></p>';
    } else
    {
        echo "<p>No files have been uploaded so far.</p>";
    }

    if($ajax)
    {
        echo '<span id="last-update" style="display:none;">', $lastUpdate, '</span>';
        die();
    }

    return $alteredFiles;
}
?>