<?php
$globalAdmins = Array("tjalsmajj1,jongrmde");

// create or resume the session.
session_start();

//Handle logout
if(isset($_GET["logout"])){
	unsetSession();
}

//Detect successfull login
if(isset($_SESSION["user"])){
  //Do nothing, user is logged in
}elseif(isset($_POST['fromForm']) && $_POST['fromForm'] == "login"){
  if(!ctype_alnum ($_POST['user']) || empty($_POST['pass'])){
    //Credentials were not valid
    die();
  }
  //Detect new login attempt
	$thisUser = $_POST['user'];
	$ldap['user'] = "cn=".$thisUser;
	$ldap['pass'] = $_POST['pass'];
	$ldap['host'] = 'ldaps://u-ldap.leidenuniv.nl';

	//Check for empty password
	if(empty($ldap['pass'])){
		showLogin($thisUser,"Please enter username and password");
		die();
	}

	if(!in_array($thisUser,$globalAdmins)){
		showLogin($thisUser,"You are not allowed to log in to this service");
		die();
	}


	//Try login
	if(ldapLogin($ldap,$thisUser)){
		//Login succeeded
	}else{
		//Login failed, show some message
		showLogin($thisUser,"Username or password incorrect");
		die();
	}

//No login attempt detected, show form
}else{

	//Redirect to HTTPS if not on HTTPS now
	if ($_SERVER['HTTPS'] !== "on") {
		$url = "https://". $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		header("Location: $url");
		exit;
	}
	showLogin();
	die();
}

function showLogin($userName = "",$errors = ""){
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
 <HTML>

 <HEAD>
 	<TITLE>Vrije computers - inloggen</TITLE>
         <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
         <META name="viewport" content="width=device,initial-scale=1.0;">

          <STYLE>
            .panel {
             font-family:tahoma,arial;
             font-size:11px;
             color:white;
             width:505px;
             height:287px;
             background-color:grey;
             margin:15% auto;
             padding:0;
           }
           A {
             color:white;
           }
           .head {
             padding-top:90px;
           }
           .head, .body {
             padding-right:20px;
             padding-left:79px;
             padding-bottom:10px;
           }
           .label {
             float:left;
             width:60px;
             margin-top:0.45em;
           }
           .name INPUT, .pw INPUT {
             width:200px;
           }
           .button INPUT {
             width:100px;
           }
           .button.login {
             float:left;
             margin-left:60px;
           }
           .button {
             margin-top:15px;
           }
           .error {
             margin:5px 0;
             color:red;
           }
           @media screen and (max-width:500px) {
             /* --- mobile --- */
             .panel {
               width:350px;
               height:280px;
               background-position:-3px -3px;
             }
             /*
             .panel .head .info A {
               display:none;
             }
			 */
             .name INPUT, .pw INPUT {
               width:160px;
             }
             .button INPUT {
               width:77px;
             }
             .button.login {
               margin-left:0;
               margin-right:5px;
             }
           }
         </STYLE>

 </HEAD>

 <BODY onload="document.forms.form1.user.focus()">
   <FORM name="form1" action="" method="POST">
     <DIV class="panel">
       <DIV class="head"><h3>Vrije computers</h3>
         <DIV class="info">Inloggen met ULCN.</DIV>
         <DIV class="error"><?php echo (!empty($errors) ? $errors : "");?></DIV>
       </DIV>
       <DIV class="body">
         <DIV class="name">
             <DIV class="label">Name:</DIV>
             <INPUT type="text" name="user" value="<?php echo (!empty($userName) ? $userName : "");?>">
         </DIV>
         <DIV class="pw">
             <DIV class="label">Password:</DIV>
             <INPUT type="password" name="pass">
         </DIV>
         <input type="hidden" name="fromForm" value="login"/>
         <DIV class="button login"><INPUT type="submit" value="Login"></DIV>
         <DIV class="button back"><INPUT type="button" onClick='history.back();return false;'title="Back" value="Back"></DIV>
       </DIV>
     </DIV>

   </FORM>
 </BODY>
 </HTML>

</body>
</html>

<?php
}

function unsetSession(){
	// Unset all of the session variables.
	$_SESSION = array();

	// If it's desired to kill the session, also delete the session cookie.
	// Note: This will destroy the session, and not just the session data!
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}
}

function ldapLogin($ldap,$thisUser){
	$ldap['baseDN'] = "ou=Persons,o=Services";

	$ldap['conn'] = ldap_connect( $ldap['host'] )or die("Could not connect to {$ldap['host']}" );

	$ldap['anonBind'] = ldap_bind($ldap['conn']);

	//Attempt anonymous bind to get DN for user
	if( !$ldap['anonBind'] ){
		/*
		* Anonymous bind failed, ULCN login was wrong or something else went wrong
		*/
		exit;
	}else{
		/*
		* Anonymous bind succeeded
		*/

		//Perform search for user (anonymously)
		$ldap['searchUserResultID'] = ldap_search ( $ldap['conn'] , $ldap['baseDN'] , $ldap['user'] , array("dn"), 0, 10 );

		//Parse searchresult(s) into an array
		$ldap['searchUserResultArray']=ldap_get_entries ($ldap['conn'], $ldap['searchUserResultID']);

		//Get search result count
		$ldap['searchUserResultCount']=$ldap['searchUserResultArray']["count"];

		//Get search DN for first result
		$ldap['userDN']=$ldap['searchUserResultArray'][0]["dn"];

		//Attemp user bind to authenticate user
		$ldap['userBind'] = ldap_bind($ldap['conn'], $ldap['userDN'], $ldap['pass']);

		//Check if bind as user succeeded
		if( !$ldap['userBind']){
			/*
			* Bind as user failed, ULCN login was wrong or something else went wrong
			*/
			return false;
		}else{
			/*
			* Bind as user succeeded
			*/
			$_SESSION["user"]=$thisUser;

			return true;
		}
	}

}

?>
