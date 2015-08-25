<?php

/*
 * login.php
 * =========
 * Purpose: Loggin the user in or out.
 *
 * Functions:
 * none
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

$oldSession = isset($_SESSION["login_rand"]) ? $_SESSION["login_rand"] : '';
$_SESSION["login_rand"] = mt_rand();
unset($_SESSION["idhost"]);

require_once("php/password.php");

if(isset($_GET["logout"]))
{
    redirect(BASEDIR);
}

if(isset($_POST["login"]) && ($_POST["login"] == $oldSession) && isset($_POST["hostname"]) && isset($_POST["password"]))
{
    $result = mysql_query("SELECT * FROM `hosts` WHERE `hostname` = '" . mysql_real_escape_string($_POST["hostname"]) . "'") or die("query failed");
    $row = mysql_fetch_assoc($result);
    if(mysql_num_rows($result) == 0 || makepassword($_POST["password"], $row["password"]) != $row["password"])
    {
        echo "<p>User does not exist or wrong password!</p>";
    } else
    {
        $_SESSION["idhost"] = $row["idhost"];
        redirect(BASEDIR . "admin/compo");
    }
}

?>
          <h2>
            Login
          </h2>

          <form action="{{BASE}}login" method="post">
          
          <input type="hidden" name="login" value="<?php echo $_SESSION["login_rand"]; ?>" />
          
          <div class="table-desc" style="background-image:url({{BASE}}img/user.png)"><label for="hostname">Name:</label></div>
          <div class="table-item"><input name="hostname" id="hostname" type="text" value="<?php if(isset($_POST["hostname"])) echo htmlspecialchars($_POST["hostname"]); ?>" maxlength="<?php echo MAX_USERNAME_LENGTH; ?>" style="width:50%" /></div>

          <div class="table-desc" style="background-image:url({{BASE}}img/key.png)"><label for="password">Password:</label></div>
          <div class="table-item"><input name="password" id="password" type="password" style="width:50%" /></div>

          <div class="table-desc">&nbsp;</div>
          <div class="table-item"><input type="submit" /></div>
          </form>

<?php
?>