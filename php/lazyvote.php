<?php

/*
 * lazyvote.php
 * ============
 * Purpose: Vote management
 * Notes: By coda 
 *
 * Functions:
 * none
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

if(ACCESS < ACCESS_HOST)
{
    redirect(BASEDIR);
}

require_once('compo.php');
require_once('archive.php');

$compo = intval($_GET['which']);

$result = $mysqli->query("SELECT * FROM `compos` WHERE `idcompo` = $compo") or die('query failed');
$row = $result->fetch_assoc();
$result->free();
// can the current user edit votes for this compo?
if(!canEditCompo($row))
{
    // no compo
    redirect(BASEDIR);
}
$compo_name = htmlspecialchars($row['name']);
$isActive = $row['active'];

$result = $mysqli->query("SELECT * FROM `entries` WHERE `idcompo` = $compo ORDER BY `identry` ASC") or die('query failed');
$songs = array();
$songindex = 0;
while($row = $result->fetch_assoc())
{
    $songindex++;
    $row['id'] = $songindex;
    $row['points'] = 0;
    $row['penalty'] = 0;
    $songs[] = $row;
}
$result->free();

$numEntries = $songindex;

function votecmp($a, $b)
{
    if($a['points'] == $b['points']) return 0;
    return ($a['points'] > $b['points']) ? -1 : 1;
}

// results mode
if((isset($_POST['votes']) && is_array($_POST['votes'])) || (isset($_POST['penalty']) && is_array($_POST['penalty'])))
{
    foreach($_POST['votes'] as $voter)
    {
        if(is_array($_POST['voter_' . $voter]))
        {
            $points = $numEntries;
            foreach($_POST['voter_' . $voter] as $doot)
            {
                $songs[intval($doot) - 1]['points'] += $points;
                $points--;
            }
        }
        else
        {
            die("{$voter}'s votes are missing!");
        }
    }

    if(is_array($_POST['penalty']))
    {
        foreach($_POST['penalty'] as $pen)
        {
            $songs[intval($pen) - 1]['points'] -= $numEntries;
            $songs[intval($pen) - 1]['penalty'] = 1;
        }
    }

    uasort($songs, 'votecmp');
    $place = 0;
    $lastscore = 9000;
    
    ob_end_clean();
    ob_start();
    //header('Content-type: text/plain; charset=utf-8');
    print "** $compo_name RESULTS! **\n";
    print "--\n";
    foreach($songs as $song)
    {
        if($song['points'] != $lastscore)
        {
            $lastscore = $song['points'];
            $place++;
        }

        // I guess we can afford firing some UPDATE statements at the DB at this point...
        $stmt = $mysqli->prepare('UPDATE `entries` SET
            `points` = ?,
            `place` = ?
            WHERE `identry` = ?') or die('query failed');
        $stmt->bind_param('iii', $song['points'], $place, $song['identry']);
        $stmt->execute() or die('query failed');
        $stmt->close();

        print "[PLACE $place -=> {$song['filename']} <=- Done by -=> {$song['author']} <=- with ";
        if($song['penalty']) print ($song['points'] + $numEntries) . "-$numEntries=";
        print "{$song['points']}pts ]\n";
    }
    
    
    print "--\n";
    if(is_array($_POST['votes']))
    foreach($_POST['votes'] as $voter)
    {
        $place = 0;
        print "--=> " . urldecode($voter) . " <=- Has voted the following:\n";
        foreach($_POST['voter_' . $voter] as $doot)
        {
                $place++;
                $song =  $songs[intval($doot) - 1]['filename'];
                print '[\_Place -=>' . " $place <=-=> {$song}_/]\n";
        }
    }
    if(is_array($_POST['penalty']))
    foreach($_POST['penalty'] as $doot)
    {
        $author = $songs[intval($doot) - 1]['author'];
        print "--=> " . $author . " <=- Did not vote\n";
    }
    print "\n\n";
    print "-----<O>-----\n";
    print "This results were generated with LazyVote. (c) 2010 coda and Saga Musix. http://wiki.s3m.us";
    $text = ob_get_clean();
    $outFileName = UPLOAD_DIR . $compo . '.txt';
    file_put_contents($outFileName, $text);
    @chmod($outFileName, 0755);
    
    $arc = new ArchiveFile(UPLOAD_DIR . $compo);
    if(!$arc->OpenWrite())
    {
        die("cannot open pack for writing results file!");
    }
    $resultsTxt = UPLOAD_DIR . 'results.txt';
    $arc->PrepareReplace($resultsTxt);
    if(copy($outFileName, $resultsTxt)) $arc->Add($resultsTxt);
    @unlink($resultsTxt);
    $arc->Close();

    redirect(BASEDIR . "results/$compo.txt");
}

?>
  <script src="https://code.jquery.com/jquery-1.12.4.min.js" integrity="sha384-nvAa0+6Qg9clwYCGGPpDQLVpLNn0fRaROjHqs13t4Ggj3Ez50XnGQqc/r8MhnRDZ" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha384-Dziy8F2VlJQLMShA6FHWNul/veM9bCkRUaLqr199K94ntO5QUrLJBEbYegdSkkqX" crossorigin="anonymous"></script>
  <script type="text/javascript">var BASEDIR = "{{BASE}}";</script>
  <script src="{{BASE}}js/lazyvote.js" type="text/javascript"></script>
    <h2>Entries</h2>
<?php
    if($isActive) echo '<p>Warning: Uploading is still active for this compo! First disable uploading before processing votes!</p>';
?>
    <table>
        <tr><th>Filename</th><th>Title</th><th>Author</th><th title="Uncheck if votes have been processed for this author. Otherwise they will be penalized for not having voted.">+v</th>
<?php
        $songindex = 0;
        foreach($songs as $song)
        {
            $songindex++;
?>
        <tr>
        <td id="filename_<?php echo $songindex; ?>"><?php echo htmlspecialchars($song['filename']); ?></td>
        <td id="title_<?php echo $songindex; ?>"><?php echo htmlspecialchars($song['title']); ?></td>
        <td id="author_<?php echo $songindex; ?>"><label for="penalty_<?php echo $songindex; ?>"><?php echo htmlspecialchars($song['author']); ?></label></td>
        <td><input type="checkbox" id="penalty_<?php echo $songindex; ?>" checked></td>
        </tr>
<?php
        }
?>
    </table>

        <h2>Votes</h2>
<?php
        if(file_exists(UPLOAD_DIR . $compo . '.txt'))
        {
            echo '<p><a href="{{BASE}}results/', $compo, '.txt">View previous results</a></p>';
        }
?>
        <div style="width:700px; overflow: auto;">
        <table id="matrix">
        <tr id="matrix_headers"></tr>
<?php
        $songindex = 0;
        foreach($songs as $song)
        {
            $songindex++;
            echo '<tr id="matrix_row_', $songindex, '"></tr>';
        }
?>
        </table>
        </div>
        <button id="resultsbutton">RESULTS!</button>

        <h2>Add Voter</h2>
        <div class="table-desc" style="background-image:url({{BASE}}img/table.png)">Paste here:</div>
        <div class="table-item"><textarea id="paster" rows="5" cols="60"></textarea></div>
        <div class="table-desc" style="background-image:url({{BASE}}img/user.png)">Name:</div>
        <div class="table-item"><input type="text" id="voter" style="width: 50%"></div>

    <p>Drag the items to adjust votes after pasting:</p>

    <ul id="votesorter">
<?php
        $songindex = 0;
        foreach($songs as $song)
        {
            $songindex++;
            echo '<li id="vote_', $songindex, '">', htmlspecialchars($song['filename']), ' (', htmlspecialchars($song['title']), ') by ', htmlspecialchars($song['author']), '</li>';
        }
?>
    </ul>
    <button id="votebutton">Vote it!</button>

    <!--<p>You can also use <a href="https://sites.google.com/site/modshrinewiki/lazyslash-compo-manager">lazyslash compo manager</a> to manage the entries and votes.</p>-->