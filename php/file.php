<?php

/*
 * file.php
 * ========
 * Purpose: Functions for uploading, downloading and deleting files and packs.
 *
 * Functions:
 * processUpload()
 *     Process uploaded files received through a HTTP POST request.
 * uploadError($message)
 *     Display an error message and stop the uploading process.
 * processUploadPing()
 *     Process a "ping" event, i.e. the client notifies the server that it's still uploading. 
 * safeFilename($filename)
 *     Make a filename safe, i.e. remove illegal characters.
 * fetchFile($file)
 *     Offer a compo pack file specified by its ID $file for download.
 * deleteFile($file)
 *     Delete a file from a compo pack, specified by its ID $file.
 * fetchPack($compo, $file = '')
 *     Offer a compo pack specified by the compo's ID $compo.
 * deletePack($compo, $doRedirect = TRUE)
 *     Delete a compo pack and redirect if $doRedirect is TRUE.
 * showCompoDirectory($compo)
 *     Show a "directory listing" with the pack + results file
 * canFetchFile($file)
 *     Returns TRUE if the logged in user can download a compo pack file.
 * canDeleteFile($file)
 *     Returns TRUE if the logged in user can delete a compo pack file.
 * canFetchPack($compo)
 *     Returns TRUE if the (logged in) user can download a compo pack.
 * canDeletePack($compo)
 *     Returns TRUE if the (logged in) user can delete a compo pack.
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

require_once('modinfo.php');
require_once('archive.php');

// Guest activities
if(isset($_GET["fileupload"]))
{
    processUpload();
    return;
} else if(isset($_GET["getpack"]))
{
    fetchPack($_GET["getpack"], isset($_GET["file"]) ? $_GET["file"] : '');
} else if(isset($_GET["getresults"]))
{
    fetchResults($_GET["getresults"]);
} else if(isset($_GET["getcompo"]))
{
    showCompoDirectory($_GET["getcompo"]);
    return;
} else if(isset($_GET['uploadping']))
{
    processUploadPing();
    return;
}


if(ACCESS < ACCESS_HOST)
{
    redirect(BASEDIR);
}


switch($_GET["action"])
{
    case "fetchfile":
        fetchFile($_GET["which"]);
        break;
    case "delfile":
        deleteFile($_GET["which"]);
        break;
    case "fetchpack":
        fetchPack($_GET["which"]);
        break;
    case "delpack":
        deletePack($_GET["which"]);
        break;
}


function processUpload()
{
    $compo = intval($_POST["compo"]);

    if(isset($_POST["token"]) && $_POST["token"] > 0)
    {
        // Remove upload token
        mysql_query("DELETE FROM `uploading` WHERE
            `idupload` = '" . intval($_POST["token"]) . "' AND
            `author` = '" . mysql_real_escape_string($_POST["author"]) . "' AND
            `idcompo` = '" . $compo . "'
        ") or die("query failed");
    }

    $result = mysql_query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`active` != 0)") or die("query failed");
    if(mysql_num_rows($result) == 0)
    {
        uploadError("Sorry, but uploading for this compo is closed.");
        return;
    }

    if($_POST["author"] == "")
    {
        uploadError("You forgot to enter your name!");
        return;
    } else if($_FILES['userfile']['size'] > MAX_UPLOAD_SIZE || $_FILES['userfile']['error'] === UPLOAD_ERR_INI_SIZE)
    {
        uploadError("Your <s>penis</s> file is too big!");
        return;
    } else if($_FILES['userfile']['size'] < 100)
    {
        uploadError("Your <s>penis</s> file is too small!");
        return;
    }

    setcookie("author", $_POST["author"], time() + 60 * 60 * 24 * 365, "/");

    $arc = new ArchiveFile(UPLOAD_DIR . $compo);
    if($arc->Open() === FALSE)
    {
        echo "<p>Can't update the pack, please contact the technical support!</p>";
        return;
    }

    $safeName = safeFilename($_FILES['userfile']['name']);
    $lastDot = strrpos($safeName, '.');
    if(strlen($safeName) <= MAX_FILENAME_LENGTH || $lastDot === FALSE)
    {
        $db_filename = substr($safeName, 0, MAX_FILENAME_LENGTH);
    } else
    {
        // Need to trim filename
        $extension = substr($safeName, $lastDot);
        $db_filename = substr($safeName, 0, MAX_FILENAME_LENGTH - strlen($extension)) . $extension;
    }

    // Get mod title
    $modTitle = mysql_real_escape_string(getModTitle($_FILES['userfile']['tmp_name'], $db_filename));

    $insert = TRUE;
    // duplicate filename?
    $result = mysql_query("SELECT * FROM `entries` WHERE (`idcompo` = $compo) AND (`filename` = '" . mysql_real_escape_string($db_filename) . "')") or die("query failed");
    if(mysql_num_rows($result) > 0)
    {
        $row = mysql_fetch_assoc($result);
        //if(isset($_SESSION["upload-" . $row["identry"]]) && $_SESSION["upload-" . $row["identry"]] == $_POST["author"])
        if($row["author"] == $_POST["author"])
        {
            // replace file
            $entryID = $row["identry"];
            mysql_query("UPDATE `entries` SET
                `title` = '$modTitle',
                `altered` = 1,
                `date` = CURRENT_TIMESTAMP
                WHERE `identry` = $entryID") or die("query failed");

            @unlink(UPLOAD_DIR . $entryID);
            $arc->PrepareReplace($db_filename);
            
            $insert = FALSE;
        } else
        {
            // this is not ours, invent new filename
            $db_filename = substr(dechex(mt_rand(0, 255)) . '-' . $db_filename, 0, MAX_FILENAME_LENGTH);
        }
    }

    if($insert)
    {
        mysql_query("INSERT INTO `entries` (`author`, `filename`, `title`, `idcompo`, `altered`) VALUES (
            '" . mysql_real_escape_string($_POST["author"]) . "',
            '" . mysql_real_escape_string($db_filename) . "',
            '$modTitle',
            '" . $compo . "',
            '0'
            )") or die("query failed");
        $entryID = mysql_insert_id();
    }

    $_SESSION["upload-$entryID"] = $_POST["author"];
    $_SESSION["compo-$compo"] = TRUE;

    if(move_uploaded_file($_FILES['userfile']['tmp_name'], UPLOAD_DIR . $db_filename))
    {
        $arc->Add(UPLOAD_DIR . $db_filename);
        $arc->Close();
        @unlink(UPLOAD_DIR . $db_filename);

        echo '<h2>...go!</h2>';
        if($insert)
        {
            echo '<p>OK, ', htmlspecialchars($_POST["author"]), ', all done. Good luck!</p>';
        } else
        {
            echo '<p>OK, ', htmlspecialchars($_POST["author"]), ', your file has been <strong>updated</strong>. Good luck!</p>';
        }
        echo '<p>If you need to replace your file, upload it using exactly the same file name (', htmlspecialchars($db_filename), ') and handle (', htmlspecialchars($_POST["author"]), ') as this one.</p>';
    } else
    {
        $arc->Close();
        uploadError("Captain, the machinery failed! Please contact the technical support!");
        return;
    }

}


function uploadError($message)
{
    @unlink($_FILES['userfile']['tmp_name']);
    echo "<h2>Guru Meditation</h2>";
    echo "<p>$message</p>";
}


function processUploadPing()
{
    if(!isset($_POST["author"]) || !isset($_POST["compo"]) || !isset($_POST["token"]) || !isset($_POST["cancel"]))
    {
        return;
    }
    
    ob_end_clean();

    if($_POST["token"] > 0)
    {
        if($_POST["cancel"] == 1)
        {
            // Remove token (f.e. when client closes window)
            mysql_query("DELETE FROM `uploading` WHERE
                `idupload` = '" . intval($_POST["token"]) . "' AND
                `author` = '" . mysql_real_escape_string($_POST["author"]) . "' AND
                `idcompo` = '" . intval($_POST["compo"]) . "'
            ") or die("query failed");
        } else
        {
            // Update token
            mysql_query("UPDATE `uploading` SET
                `start` = '" . time() . "'
                WHERE
                `idupload` = '" . intval($_POST["token"]) . "' AND
                `author` = '" . mysql_real_escape_string($_POST["author"]) . "' AND
                `idcompo` = '" . intval($_POST["compo"]) . "'
            ") or die("query failed");
        }
        echo intval($_POST["token"]);
    } else
    {
        mysql_query("INSERT INTO `uploading` (`author`, `start`, `idcompo`) VALUES (
            '" . mysql_real_escape_string($_POST["author"]) . "',
            '" . time() . "',
            '" . intval($_POST["compo"]) . "'
            )") or die("query failed");
        echo mysql_insert_id();
    }
    $progressKey = ini_get('session.upload_progress.prefix') . @$_POST[ini_get('session.upload_progress.name')];
    if(isset($_SESSION[$progressKey]) && isset($_SESSION[$progressKey]["content_length"]) && isset($_SESSION[$progressKey]["bytes_processed"]))
    {
        echo '\n' . intval(100 * $_SESSION[$progressKey]["bytes_processed"] / $_SESSION[$progressKey]["content_length"]);
    } 
    die();
}


function safeFilename($filename)
{
    // Replace \/:*?"<>| and non-printable characters
    $filename = preg_replace('/([\\\\\\/\:*?"<>|\x00-\x1F])/u', '', $filename);
    if($filename == "")
    {
        $filename = "unnamed";
    }
    return $filename;
}


function fetchFile($file)
{
    $file = intval($file);

    if(!canFetchFile($file))
    {
        redirect(BASEDIR);
    }

    $result = mysql_query("SELECT * FROM `entries` WHERE `identry` = $file") or die("query failed");
    $row = mysql_fetch_assoc($result);

    $arc = new ArchiveFile(UPLOAD_DIR . $row["idcompo"]);
    if($arc->Open() === FALSE)
    {
        echo "<p>Can't unpack the pack, please contact the technical support!</p>";
        return;
    }
    
    $arc->Extract($row["filename"]);
    $arc->Close();
    die();
}


function deleteFile($file)
{
    $file = intval($file);

    if(!canDeleteFile($file))
    {
        redirect(BASEDIR);
    }

    $result = mysql_query("SELECT * FROM `entries` WHERE `identry` = $file") or die("query failed");
    $row = mysql_fetch_assoc($result);

    $arc = new ArchiveFile(UPLOAD_DIR . $row["idcompo"]);
    if($arc->Open() === FALSE)
    {
        echo "<p>Can't update the pack, please contact the technical support!</p>";
        return;
    }
    $arc->Delete($row["filename"]);
    $arc->Close();

    @unlink(UPLOAD_DIR . $file);
    mysql_query("DELETE FROM `entries` WHERE `identry` = $file") or die("query failed");

    redirect(BASEDIR . "admin/compo/" . $row["idcompo"]);
}

function fetchResults($compo)
{
    $compo = intval($compo);

    /*if(!canFetchPack($compo))
    {
        redirect(BASEDIR);
    }*/

    $txtName = UPLOAD_DIR . $compo . ".txt";

	if(!file_exists($txtName))
		redirect(BASEDIR);

    $result = mysql_query("SELECT `name` FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    $compoName = safeFilename($row["name"]);

    ob_end_clean();
    header('Content-Disposition: inline; filename="' . $compoName . '.txt"');
    header("Content-type: text/plain; charset=utf-8");
    header("Content-Length: " . filesize($txtName));
    readfile($txtName);
    die();
}


function fetchPack($compo, $file = '')
{
    $compo = intval($compo);

    if(!canFetchPack($compo))
    {
        redirect(BASEDIR);
    }

    if($file != '')
    {
        $arc = new ArchiveFile(UPLOAD_DIR . $compo);
        $arc->Extract($file);
    }

    $arcName = ArchiveFile::FileName(UPLOAD_DIR . $compo);
    
    $result = mysql_query("SELECT `name` FROM `compos` WHERE `idcompo` = $compo") or die("query failed");
    $row = mysql_fetch_assoc($result);
    $compoName = safeFilename($row["name"]);

    ob_end_clean();
    header('Content-Disposition: attachment; filename="' . $compoName . '.7z"');
    header("Content-type: application/octet-stream");
    header("Content-Length: " . filesize($arcName));
    readfile($arcName);
    die();
}


function deletePack($compo, $doRedirect = TRUE)
{
    $compo = intval($compo);

    if(!canDeletePack($compo))
    {
        redirect(BASEDIR);
    }

    $result = mysql_query("SELECT * FROM `entries` WHERE `idcompo` = $compo") or die("query failed");
    while($row = mysql_fetch_assoc($result))
    {
        @unlink(UPLOAD_DIR . $row["identry"]);
    }
    @unlink(UPLOAD_DIR . $compo . ".7z");

    mysql_query("DELETE FROM `entries` WHERE `idcompo` = $compo") or die("query failed");

    if($doRedirect)
    {
        redirect(BASEDIR . "admin/compo/$compo");
    }
}


function showCompoDirectory($compo)
{
    $compo = intval($compo);
    if(canFetchPack($compo))
    {
        $result = mysql_query("SELECT `compos`.`name` AS `name`, `hosts`.`hostname` AS `hostname` FROM `compos`, `hosts` WHERE (`idcompo` = $compo) AND (`compos`.`idhost` = `hosts`.`idhost`)") or die("query failed");
        $row = mysql_fetch_assoc($result);

        echo '<h2>Compo details for &quot;', htmlspecialchars($row["name"]), '&quot;</h2>';
        echo '<p>Compo host: <strong>', htmlspecialchars($row["hostname"]), '</strong></p>';

        $result = mysql_query("SELECT `author` FROM `entries` WHERE (`idcompo` = $compo) AND (`place` = 1)") or die("query failed");
        $winners = mysql_num_rows($result);
        if($winners != 0)
        {
            echo '<p>Compo winner', ($winners != 1 ? 's' : ''), ': <strong>';
            $first = true;
            while($row = mysql_fetch_assoc($result))
            {
                if(!$first)
                {
                    echo ', ';
                }
                echo htmlspecialchars($row['author']);
                $first = false; 
            }
            echo '</strong></p>';
        }

        echo '<ul>';
        if(file_exists(UPLOAD_DIR . $compo . '.7z'))
        {
            echo '<li><a href="{{BASE}}pack/', $compo, '">Songs</a></li>';
        }
        if(file_exists(UPLOAD_DIR . $compo . '.txt'))
        {
            echo '<li><a href="{{BASE}}results/', $compo, '.txt">Results</a></li>';
        }
        echo '</ul>';
        
        $result = mysql_query("SELECT * FROM `entries` WHERE (`idcompo` = $compo) AND (`points` IS NOT NULL) ORDER BY `points` DESC, `author` ASC") or die("query failed");
        if(mysql_num_rows($result) != 0)
        {
            echo '<h2>Entries</h2>';
            echo'<table class="stats">
                <thead>
                    <tr>
                        <th>Place</th>
                        <th>Author</th>
                        <th>Name</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>';
            while($row = mysql_fetch_assoc($result))
            {
                echo '<tr><td>', $row['place'], ($row['place'] <= 3 ? ' <img src="{{BASE}}img/medal' . $row['place'] . '.png" width="16" height="16" alt="">' : ''), '</td>
                    <td>', htmlspecialchars($row['author']), '</td>
                    <td><a href="{{BASE}}pack/', $compo, '/', urlencode($row['filename']), '">', htmlspecialchars($row['filename']), '</a></td>
                    <td>', htmlspecialchars($row['points']), '</td>
                    </tr>'; 
            }
            echo '</tbody></table>';
        }
    } else
    {
        redirect(BASEDIR);
    }
}


function canFetchFile($file)
{
    return canDeleteFile($file);
}


function canDeleteFile($file)
{
    if(ACCESS == ACCESS_FULLADMIN)
    {
        return true;
    }
    $file = intval($file);
    $result = mysql_query("SELECT * FROM `compos` A, `entries` B WHERE (B.`identry` = $file) AND (A.`idcompo` = B.`idcompo`) AND (A.`idhost` = " . intval($_SESSION["idhost"]) . ")") or die("query failed");
    if(mysql_num_rows($result) > 0)
    {
        return true;
    }
    return false;
}


function canFetchPack($compo)
{
    $compo = intval($compo);

    if(canDeletePack($compo))
    {
        return true;
    } else
    {
        // guest mode
        $result = mysql_query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`downloadable` != '0')") or die("query failed");
        return (mysql_num_rows($result) > 0);
    }
}


function canDeletePack($compo)
{
    if(ACCESS == ACCESS_FULLADMIN)
    {
        return true;
    }
    $compo = intval($compo);
    $result = mysql_query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`idhost` = " . intval($_SESSION["idhost"]) . ")") or die("query failed");
    if(mysql_num_rows($result) > 0)
    {
        return true;
    }
    return false;
}


?>