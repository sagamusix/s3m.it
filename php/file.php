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
    global $mysqli;
    $compo = intval($_POST["compo"]);

    if(isset($_POST["token"]) && $_POST["token"] > 0)
    {
        // Remove upload token
        $stmt = $mysqli->prepare('DELETE FROM `uploading` WHERE
            `idupload` = ? AND
            `author` = ? AND
            `idcompo` = ?
        ') or die('query failed');
        $stmt->bind_param('isi', intval($_POST["token"]), $_POST["author"], $compo);
        $stmt->execute() or die('query failed');
    }

    $result = $mysqli->query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`active` != 0)") or die('query failed');
    $isClosed = ($result->num_rows == 0); 
    $result->free();
    if($isClosed)
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
    $modTitle = getModTitle($_FILES['userfile']['tmp_name'], $db_filename);

    $insert = TRUE;
    // duplicate filename?
    $stmt = $mysqli->prepare('SELECT * FROM `entries` WHERE (`idcompo` = ?) AND (`filename` = ?)') or die('query failed');
    $stmt->bind_param('is', $compo, $db_filename);
    $stmt->execute() or die('query failed');
    $result = $stmt->get_result();

    if($result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        //if(isset($_SESSION["upload-" . $row["identry"]]) && $_SESSION["upload-" . $row["identry"]] == $_POST["author"])
        if($row["author"] == $_POST["author"])
        {
            // replace file
            $entryID = $row["identry"];
            $stmtRep = $mysqli->prepare('UPDATE `entries` SET
                `title` = ?,
                `altered` = 1,
                `date` = CURRENT_TIMESTAMP
                WHERE `identry` = ?') or die('query failed');
            $stmtRep->bind_param('si', $modTitle, $entryID);
            $stmtRep->execute() or die('query failed');
            $stmtRep->close();

            @unlink(UPLOAD_DIR . $entryID);
            $arc->PrepareReplace($db_filename);
            
            $insert = FALSE;
        } else
        {
            // this is not ours, invent new filename
            $db_filename = substr(dechex(mt_rand(0, 255)) . '-' . $db_filename, 0, MAX_FILENAME_LENGTH);
        }
    }
    $result->free();
    $stmt->close();

    if($insert)
    {
        $stmt = $mysqli->prepare('INSERT INTO `entries` (`author`, `filename`, `title`, `idcompo`, `altered`) VALUES (?, ?, ?, ?, 0)') or die('query failed');
        $stmt->bind_param('sssi', $_POST["author"], $db_filename, $modTitle, $compo);
        $stmt->execute() or die('query failed');
        $entryID = $stmt->insert_id;
        $stmt->close();
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
    global $mysqli;
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
            $stmt = $mysqli->prepare('DELETE FROM `uploading` WHERE
                `idupload` = ? AND
                `author` = ? AND
                `idcompo` = ?
            ') or die('query failed');
            $stmt->bind_param('isi', intval($_POST["token"]), $_POST["author"], intval($_POST["compo"]));
            $stmt->execute() or die('query failed');
            $stmt->close();
        } else
        {
            // Update token
            $stmt = $mysqli->prepare('UPDATE `uploading` SET
                `start` = ?
                WHERE
                `idupload` = ? AND
                `author` = ? AND
                `idcompo` = ?
            ') or die('query failed');
            $stmt->bind_param('iisi', time(), intval($_POST["token"]), $_POST["author"], intval($_POST["compo"]));
            $stmt->execute() or die('query failed');
            $stmt->close();
        }
        echo intval($_POST["token"]);
    } else
    {
        $stmt = $mysqli->prepare('INSERT INTO `uploading` (`author`, `start`, `idcompo`) VALUES (?, ?, ?)');
        $stmt->bind_param('sii', $_POST["author"], time(), intval($_POST["compo"]));
        $stmt->execute() or die('query failed');
        echo $stmt->insert_id;
        $stmt->close();
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
    global $mysqli;
    $file = intval($file);

    if(!canFetchFile($file))
    {
        redirect(BASEDIR);
    }

    $result = $mysqli->query("SELECT * FROM `entries` WHERE `identry` = $file") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();

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
    global $mysqli;
    $file = intval($file);

    if(!canDeleteFile($file))
    {
        redirect(BASEDIR);
    }

    $result = $mysqli->query("SELECT * FROM `entries` WHERE `identry` = $file") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();

    $arc = new ArchiveFile(UPLOAD_DIR . $row["idcompo"]);
    if($arc->Open() === FALSE)
    {
        echo "<p>Can't update the pack, please contact the technical support!</p>";
        return;
    }
    $arc->Delete($row["filename"]);
    $arc->Close();

    @unlink(UPLOAD_DIR . $file);
    $mysqli->query("DELETE FROM `entries` WHERE `identry` = $file") or die('query failed');

    redirect(BASEDIR . "admin/compo/" . $row["idcompo"]);
}

function fetchResults($compo)
{
    global $mysqli;
    $compo = intval($compo);

    /*if(!canFetchPack($compo))
    {
        redirect(BASEDIR);
    }*/

    $txtName = UPLOAD_DIR . $compo . ".txt";

	if(!file_exists($txtName))
		redirect(BASEDIR);

    $result = $mysqli->query("SELECT `name` FROM `compos` WHERE `idcompo` = $compo") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();
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
    global $mysqli;
    $compo = intval($compo);

    if(!canFetchPack($compo))
    {
        redirect(BASEDIR);
    }

    if($file != '')
    {
        $arc = new ArchiveFile(UPLOAD_DIR . $compo);
        $arc->Extract($file);
        $arc->Close();
        die();
    }

    $arcName = ArchiveFile::FileName(UPLOAD_DIR . $compo);
    
    $result = $mysqli->query("SELECT `name` FROM `compos` WHERE `idcompo` = $compo") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();
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
    global $mysqli;
    $compo = intval($compo);

    if(!canDeletePack($compo))
    {
        redirect(BASEDIR);
    }

    $result = $mysqli->query("SELECT * FROM `entries` WHERE `idcompo` = $compo") or die('query failed');
    while($row = $result->fetch_assoc())
    {
        @unlink(UPLOAD_DIR . $row["identry"]);
    }
    $result->free();
    @unlink(UPLOAD_DIR . $compo . ".7z");

    $mysqli->query("DELETE FROM `entries` WHERE `idcompo` = $compo") or die('query failed');

    if($doRedirect)
    {
        redirect(BASEDIR . "admin/compo/$compo");
    }
}


function showCompoDirectory($compo)
{
    global $mysqli;
    $compo = intval($compo);
    if(canFetchPack($compo))
    {
        $result = $mysqli->query("SELECT `compos`.`name` AS `name`, `hosts`.`hostname` AS `hostname` FROM `compos`, `hosts` WHERE (`idcompo` = $compo) AND (`compos`.`idhost` = `hosts`.`idhost`)") or die('query failed');
        $row = $result->fetch_assoc();
        $result->free();

        echo '<h2>Compo details for &quot;', htmlspecialchars($row["name"]), '&quot;</h2>';
        echo '<p>Compo host: <strong>', htmlspecialchars($row["hostname"]), '</strong></p>';

        $result = $mysqli->query("SELECT `author` FROM `entries` WHERE (`idcompo` = $compo) AND (`place` = 1)") or die('query failed');
        $winners = $result->num_rows;
        if($winners != 0)
        {
            echo '<p>Compo winner', ($winners != 1 ? 's' : ''), ': <strong>';
            $first = true;
            while($row = $result->fetch_assoc())
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
        $result->free();

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
        
        $result = $mysqli->query("SELECT * FROM `entries` WHERE (`idcompo` = $compo) AND (`points` IS NOT NULL) ORDER BY `points` DESC, `author` ASC") or die('query failed');
        if($result->num_rows != 0)
        {
            echo '<script type="text/javascript">var basepath = "{{BASE}}";</script>';
            echo '<script type="text/javascript" src="{{BASE}}js/chiptune2.js" async></script>';
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
            while($row = $result->fetch_assoc())
            {
                echo '<tr><td>', $row['place'], ($row['place'] <= 3 ? ' <img src="{{BASE}}img/medal' . $row['place'] . '.png" width="16" height="16" alt="">' : ''), '</td>
                    <td>', htmlspecialchars($row['author']), '</td>
                    <td><a href="javascript:;" onclick="javascript:play(this)"><img src="{{BASE}}img/play.png" width="16" height="16" alt="Play" title="Play"></a> <a href="{{BASE}}pack/', $compo, '/', urlencode($row['filename']), '">', htmlspecialchars($row['filename']), '</a></td>
                    <td>', htmlspecialchars($row['points']), '</td>
                    </tr>'; 
            }
            echo '</tbody></table>';
        }
        $result->free();
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
    global $mysqli;
    if(ACCESS == ACCESS_FULLADMIN)
    {
        return true;
    }
    $file = intval($file);
    $result = $mysqli->query("SELECT * FROM `compos` A, `entries` B WHERE (B.`identry` = $file) AND (A.`idcompo` = B.`idcompo`) AND (A.`idhost` = " . intval($_SESSION["idhost"]) . ")") or die('query failed');
    $canDelete = ($result->num_rows > 0);
    $result->free();
    return $canDelete;
}


function canFetchPack($compo)
{
    global $mysqli;
    $compo = intval($compo);

    if(canDeletePack($compo))
    {
        return true;
    } else
    {
        // guest mode
        $result = $mysqli->query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`downloadable` != '0')") or die('query failed');
        $canFetch = ($result->num_rows > 0);
        $result->free();
        return $canFetch;
    }
}


function canDeletePack($compo)
{
    global $mysqli;
    if(ACCESS == ACCESS_FULLADMIN)
    {
        return true;
    }
    $compo = intval($compo);
    $result = $mysqli->query("SELECT * FROM `compos` WHERE (`idcompo` = $compo) AND (`idhost` = " . intval(@$_SESSION["idhost"]) . ")") or die('query failed');
    $canDelete = ($result->num_rows > 0);
    $result->free();
    return $canDelete;
}