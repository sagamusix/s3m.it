<?php

/*
 * users.php
 * =========
 * Purpose: User management
 *
 * Functions:
 * editUser($user)
 *     Shows the edit table for an user specified by the parameter $user
 * addUser()
 *     Register a new user, using the data received through a HTTP POST request.
 * updateUser()
 *     Update an existing user's details, using the data received through a HTTP POST request.
 * deleteUser($user, $doRedirect = TRUE)
 *     Delete the user specified by the parameter $user, and redirect to the user table if $doRedirect is TRUE.
 * displayUserManagement()
 *     Show a table with all users information the logged in user can see / edit.
 * userTable($row)
 *     Helper function for editUser() and displayUserManagement() which prints out a table for editing an user or adding a new one $row is NULL.
 * getRoleName($access)
 *     Returns a string which translates an integer $access value into a user role name (such as "Host").
 * canAddRole($role)
 *     Returns TRUE if the logged in user can add another user with the specified user role.
 * canEditUser($row)
 *     Returns TRUE if the logged in user can edit the user specified by the array $row.
 * canDeleteUser($row)
 *     Returns TRUE if the logged in user can delete the user specified by the array $row.
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

require("php/password.php");

switch($_GET["action"])
{
    case "mydetails":
        editUser($_SESSION["idhost"]);
        break;
    case "user":
        editUser($_GET["which"]);
        break;
    case "deluser":
        deleteUser($_GET["which"]);
        break;
    case "edituser":
        updateUser();
        break;
    case "adduser":
        addUser();
        break;
    case "users":
        displayUserManagement();
        break;
}


function editUser($user)
{
    global $mysqli;
    $user = intval($user);

    $result = $mysqli->query("SELECT * FROM `hosts` WHERE `idhost` = $user") or die('query failed');
    if($result->num_rows == 0)
    {
        $result->free();
        redirect(BASEDIR);
    }

    $row = $result->fetch_assoc();
    $result->free();
    if(canEditUser($row))
    {
        echo "<h2>Edit user details for ", htmlspecialchars($row["hostname"]), "</h2>";
        userTable($row);
        if(ACCESS >= ACCESS_FULLADMIN)
        {
            echo '<p><a href="{{BASE}}admin/listcompos/', $user, '">View compos hosted by this user</a></p>';
        }
    } else
    {
        redirect(BASEDIR);
    }
}


function addUser()
{
    global $mysqli;
    if(ACCESS < ACCESS_USERDB)
    {
        redirect(BASEDIR);
    }
    
    if($_POST["hostname"] == "")
    {
        redirect(BASEDIR . "admin/users/1#addusertable");
    }

    if($_POST["password"] == "" || $_POST["password"] != $_POST["password_rep"])
    {
        redirect(BASEDIR . "admin/users/2#addusertable");
    }
    
    if(!canAddRole($_POST["role"]))
    {
        redirect(BASEDIR . "admin/users/3#addusertable");
    }

    $stmt = $mysqli->prepare('SELECT * FROM `hosts` WHERE `hostname` = ?') or die('query failed');
    $stmt->bind_param('s', $_POST["hostname"]);
    $stmt->execute() or die('query failed');
    $result = $stmt->get_result();
    $userExists = ($result->num_rows > 0);
    $result->free();
    $stmt->close();
    
    if($userExists)
    {
        redirect(BASEDIR . "admin/users/4#addusertable");
    }
    
    $stmt = $mysqli->prepare('INSERT INTO `hosts` (`hostname`, `password`, `access_level`) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $_POST["hostname"], makepassword($_POST["password"]), intval($_POST["role"]));
    $stmt->execute() or die('query failed');
    $stmt->close();

    redirect(BASEDIR . "admin/user/" . $mysqli->insert_id);
}


function updateUser()
{
    global $mysqli;
    $user = intval($_POST["which"]);

    $result = $mysqli->query("SELECT * FROM `hosts` WHERE `idhost` = $user") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();
    if(!canEditUser($row))
    {
        redirect(BASEDIR);
    }

    if(!canAddRole($_POST["role"]) && ($_POST["role"] != $row["access_level"]))
    {
        echo "<p>Invalid role specified!</p>";
        editUser($user);
        return;
    }

    if($_POST["password"] != $_POST["password_rep"])
    {
        echo "<p>Passwords did not match!</p>";
        editUser($user);
        return;
    }
    
    $stmt = $mysqli->prepare("SELECT * FROM `hosts` WHERE (`idhost` != ?) AND (`hostname` = ?)") or die('query failed');
    $stmt->bind_param('is', $user, $_POST["hostname"]);
    $stmt->execute() or die('query failed');
    $result = $stmt->get_result();
    $userExists = ($result->num_rows > 0);
    $result->free();
    $stmt->close();
     
    if($userExists)
    {
        echo "<p>User name is already taken!</p>";
        editUser($user);
        return;
    }

    if(isset($_POST["password"]) && $_POST["password"] != '')
    {
        $password = makepassword($_POST["password"]);
    } else
    {
        $password = $row['password'];
    }
    
    $stmt = $mysqli->prepare('UPDATE `hosts` SET
        `hostname` = ?,
        `password` =  ?,
        `access_level` = ?
        WHERE `idhost` = ?') or die('query failed');
    $stmt->bind_param('ssii', $_POST["hostname"], $password, intval($_POST["role"]), $user);
    $stmt->execute() or die('query failed');
    $stmt->close();
    
    if($_POST["which"] == $_SESSION["idhost"])
    {
        redirect(BASEDIR . "admin/mydetails");
    } else
    {
        redirect(BASEDIR . "admin/users");
    }
}


function deleteUser($user, $doRedirect = TRUE)
{
    global $mysqli;
    $user = intval($user);

    $result = $mysqli->query("SELECT * FROM `hosts` WHERE `idhost` = $user") or die('query failed');
    $row = $result->fetch_assoc();
    $result->free();
    if(!canDeleteUser($row))
    {
        redirect(BASEDIR);
    }
    
    require("compo.php");  
    $result = $mysqli->query("SELECT * FROM `compos` WHERE `idhost` = $user") or die('query failed');
    while($row = $result->fetch_assoc())
    {
        deleteCompo($row["idcompo"], FALSE);
    }
    $result->free();
    
    $mysqli->query("DELETE FROM `hosts` WHERE `idhost` = $user") or die('query failed');

    if($doRedirect)
    {
        redirect(BASEDIR . "admin/users");
    }
}

function displayUserManagement()
{
    global $mysqli;
    if(ACCESS < ACCESS_USERDB)
    {
        redirect(BASEDIR);
    }

    echo '<h2>User management</h2>';
    
    $result = $mysqli->query("SELECT *, (SELECT COUNT(*) FROM `compos` B WHERE A.`idhost` = B.`idhost`) AS `num_compos` FROM `hosts` A ORDER BY `access_level` DESC, `idhost` ASC") or die('query failed');
    echo "<table>";
    while($row = $result->fetch_assoc())
    {
        echo "<tr><td style=\"width:28px;\">";
        if(canDeleteUser($row))
        {
            $nameJS = str_replace("'", "\\'", str_replace("\\", "\\\\", htmlspecialchars($row["hostname"])));
            echo '<a href="{{BASE}}admin/deluser/', $row["idhost"], '" onclick="return confirm(\'Delete ', $nameJS, '?\')"><img src="{{BASE}}img/user_delete.png" width="16" height="16" alt="Delete" title="Delete"></a>';
        }
        echo "</td><td style=\"width:20px;\">";
        $editOpen = "";
        $editClose = "";
        if(canEditUser($row))
        {
            $editOpen = '<a href="{{BASE}}admin/user/' . $row["idhost"] . '">';
            $editClose = '</a>';
            echo $editOpen, '<img src="{{BASE}}img/user_edit.png" width="16" height="16" alt="Edit" title="Edit">', $editClose;
        }
        echo "</td>";
        echo '<td>', $editOpen, '<strong>', htmlspecialchars($row["hostname"]), '</strong>' , $editClose, ($row["idhost"] == $_SESSION["idhost"]) ? " (you)" : "", '</td>
            <td><img src="{{BASE}}img/vcard.png" width="16" height="16" alt="Role:"> ', htmlspecialchars(getRoleName($row["access_level"])), '</td>';
        if(ACCESS >= ACCESS_FULLADMIN)
        {
            echo '<td>', $row["num_compos"], ' compos</td>';
        }
        echo "</tr>";
    }
    echo "</table>";
    $result->free();
    
    echo '<h2 id="addusertable">Add user</h2>';
    if(isset($_GET["which"]))
    {
        switch($_GET["which"])
        {
            case 1:
                echo '<p>User name is missing!</p>';
                break;
            case 2:
                echo '<p>No password specified or passwords did not match!</p>';
                break;
            case 3:
                echo '<p>Invalid role specified!</p>';
                break;
            case 4:
                echo '<p>User name is already taken!</p>';
                break;
        }
    }
    userTable(NULL);
    
}


function userTable($row)
{
    ?>
          <form action="{{BASE}}admin/<?php if(empty($row)) echo "adduser"; else echo "edituser"; ?>" method="post">
          
          <input type="hidden" name="which" value="<?php if(!empty($row)) echo $row["idhost"]; ?>">
          
          <div class="table-desc" style="background-image:url({{BASE}}img/user.png)"><label for="hostname">Name:</label></div>
          <div class="table-item"><input name="hostname" id="hostname" type="text" value="<?php echo htmlspecialchars($row["hostname"]); ?>" maxlength="<?php echo MAX_USERNAME_LENGTH; ?>" required style="width:50%"></div>

          <div class="table-desc" style="background-image:url({{BASE}}img/key.png)"><label for="password">Password:</label></div>
          <div class="table-item"><input name="password" id="password" type="password" required style="width:50%"></div>

          <div class="table-desc" style="background-image:url({{BASE}}img/key.png)"><label for="password_rep">Again:</label></div>
          <div class="table-item"><input name="password_rep" id="password_rep" type="password" required style="width:50%"></div>

          <div class="table-desc" style="background-image:url({{BASE}}img/vcard.png)"><label for="role">User Role:</label></div>
          <div class="table-item">
            <select name="role" id="role" size="1">
            <?php
            $roles = array(ACCESS_HOST, ACCESS_USERDB, ACCESS_FULLADMIN);
            foreach($roles as $role)
            {
                if(canAddRole($role) || (!empty($row) && $role == $row["access_level"]))
                {
                    echo '<option value="', $role, '"', ($role == $row["access_level"]) ? ' selected="selected"' : '', '>', htmlspecialchars(getRoleName($role)), '</option>';
                }
            }
            ?>
            </select>
          </div>

          <div class="table-desc">&nbsp;</div>
          <div class="table-item"><input type="submit"></div>
          </form>
    <?php
}


function getRoleName($access)
{
    if($access >= ACCESS_FULLADMIN)
    {
        return "Full Admin";
    } else if($access >= ACCESS_USERDB)
    {
        return "Advanced Host";
    } else if($access >= ACCESS_HOST)
    {
        return "Host";
    } else if($access >= ACCESS_MINADMIN)
    {
        return "Some kind of elevated person";
    } else
    {
        return "n00b";
    }
}


function canAddRole($role)
{
    $roles = array(ACCESS_HOST, ACCESS_USERDB, ACCESS_FULLADMIN);
    return (in_array($role, $roles) && ($role < ACCESS || ACCESS == ACCESS_FULLADMIN));
}


function canEditUser($row)
{
    return (ACCESS > $row["access_level"] || ACCESS == ACCESS_FULLADMIN || $row["idhost"] == $_SESSION["idhost"]);
}


function canDeleteUser($row)
{
    return (ACCESS >= ACCESS_FULLADMIN);
}