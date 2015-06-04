<?php
/*
* LE CHAT-PHP - a PHP Chat based on LE CHAT - Main program
*
* Copyright (C) 2015 Daniel Winzen <d@winzen4.de>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if($_SERVER['REQUEST_METHOD']=='HEAD') exit; // ignore HEAD requests
date_default_timezone_set('UTC');
$A=array();// All registered members
$C=array();// Configuration
$F=array();// Fonts
$G=array();// Guests: display names
$H=array();// HTML-stuff
$I=array();// Translations
$L=array();// Languages
$M=array();// Members: display names
$P=array();// All present users
$U=array();// This user data
$countmods=0;// Present moderators
$memcached;// Memcached connection
$mysqli;// MySQL database connection
load_fonts();
load_config();
load_lang();
load_html();
check_db();

// set session variable to cookie if cookies are enabled
if(!isSet($_REQUEST['session']) && isSet($_COOKIE[$C['cookiename']])){
	$_REQUEST['session']=$_COOKIE[$C['cookiename']];
}

//  main program: decide what to do based on queries
if(!isSet($_REQUEST['action'])){
	send_login();
}elseif($_REQUEST['action']=='view'){
	check_session();
	send_messages();
}elseif($_REQUEST['action']=='redirect' && !empty($_GET['url'])){
	send_redirect();
}elseif($_REQUEST['action']=='wait'){
	send_waiting_room();
}elseif($_REQUEST['action']=='post'){
	check_session();
	if(isSet($_REQUEST['kick']) && isSet($_REQUEST['sendto']) && valid_nick($_REQUEST['sendto'])){
		if($U['status']>=5 || ($C['memkick'] && $countmods==0 && $U['status']>=3)){
			if(isSet($_REQUEST['what']) && $_REQUEST['what']=='purge') kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], true);
			else kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], false);
		}
	}elseif(isSet($_REQUEST['message']) && isSet($_REQUEST['sendto'])){
		validate_input();
	}
	send_post();
}elseif($_REQUEST['action']=='login'){
	check_login();
	send_frameset();
}elseif($_REQUEST['action']=='controls'){
	check_session();
	send_controls();
}elseif($_REQUEST['action']=='delete'){
	check_session();
	if($_REQUEST['what']=='all') del_all_messages($U['nickname']);
	if($_REQUEST['what']=='last') del_last_message();
	send_post();
}elseif($_REQUEST['action']=='profile'){
	check_session();
	if(isSet($_REQUEST['do']) && $_REQUEST['do']=='save') save_profile();
	send_profile();
}elseif($_REQUEST['action']=='logout'){
	kill_session();
	send_logout();
}elseif($_REQUEST['action']=='colours'){
	check_session();
	send_colours();
}elseif($_REQUEST['action']=='notes'){
	check_session();
	if(!$U['status']>=5) send_login();
	send_notes('staff');
}elseif($_REQUEST['action']=='help'){
	check_session();
	send_help();
}elseif($_REQUEST['action']=='admnotes'){
	check_session();
	if(!$U['status']>=6) send_login();
	send_notes('admin');
}elseif($_REQUEST['action']=='admin'){
	check_session();
	if(!$U['status']>=5) send_login();
	if(!isSet($_REQUEST['do'])){
		send_admin();
	}elseif($_REQUEST['do']=='clean'){
		if($_REQUEST['what']=='choose') send_choose_messages();
		if($_REQUEST['what']=='selected') clean_selected();
		if($_REQUEST['what']=='room') clean_room();
		send_admin();
	}elseif($_REQUEST['do']=='kick'){
		if(!isSet($_REQUEST['name'])) send_admin();
		if(isSet($_REQUEST['what']) && $_REQUEST['what']=='purge') kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
		else kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
		send_admin();
	}elseif($_REQUEST['do']=='logout'){
		if(!isSet($_REQUEST['name'])) send_admin();
		logout_chatter($_REQUEST['name']);
		send_admin();
	}elseif($_REQUEST['do']=='sessions'){
		send_sessions();
	}elseif($_REQUEST['do']=='register'){
		register_guest(3);
		check_session();
		send_admin();
	}elseif($_REQUEST['do']=='superguest'){
		register_guest(2);
		check_session();
		send_admin();
	}elseif($_REQUEST['do']=='status'){
		change_status();
	}elseif($_REQUEST['do']=='regnew'){
		register_new();
	}elseif($_REQUEST['do']=='approve'){
		approve_session();
		send_approve_waiting();
	}elseif($_REQUEST['do']=='guestaccess'){
		if(isSet($_REQUEST['set']) && preg_match('/^[0123]$/', $_REQUEST['set'])){
			update_setting('guestaccess', $_REQUEST['set']);
		}
	}elseif($_REQUEST['do']=='filter'){
		manage_filter();
		send_filter();
	}elseif($_REQUEST['do']=='globalpass'){
		if(isSet($_REQUEST['globalpass'])){
			update_setting('globalpass', $_REQUEST['globalpass']);
		}
		if(isSet($_REQUEST['englobalpass']) && preg_match('/^[012]$/', $_REQUEST['englobalpass'])){
			update_setting('englobalpass', $_REQUEST['englobalpass']);
		}
	}
	send_admin();
}elseif($_REQUEST['action']=='setup'){
	if(!$C['memcached'] || !$num_tables=$memcached->get("$C[dbname]-$C[prefix]num-tables")){
		$tables=array("$C[prefix]captcha", "$C[prefix]filter", "$C[prefix]ignored", "$C[prefix]members", "$C[prefix]messages", "$C[prefix]notes", "$C[prefix]sessions", "$C[prefix]settings");
		$num_tables=0;
		$result=mysqli_query($mysqli, 'SHOW TABLES');
		while($tmp=mysqli_fetch_array($result, MYSQLI_NUM)){
			if(in_array($tmp[0],$tables)) ++$num_tables;
		}
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]num-tables", $num_tables, 60);
	}
	if($num_tables<7) send_init();
	update_db();
	if(!valid_admin()) send_alogin();
	if(!isSet($_REQUEST['do'])){
	}elseif($_REQUEST['do']=='guestaccess'){
		if(isSet($_REQUEST['set']) && preg_match('/^[0123]$/', $_REQUEST['set'])){
			update_setting('guestaccess', $_REQUEST['set']);
		}
	}elseif($_REQUEST['do']=='messages'){
		update_messages();
	}elseif($_REQUEST['do']=='rules'){
		$_REQUEST['rulestxt']=preg_replace("/\r\n/", '<br>', $_REQUEST['rulestxt']);
		$_REQUEST['rulestxt']=preg_replace("/\n/", '<br>', $_REQUEST['rulestxt']);
		$_REQUEST['rulestxt']=preg_replace("/\r/", '<br>', $_REQUEST['rulestxt']);
		update_setting('rulestxt', $_REQUEST['rulestxt']);
	}elseif($_REQUEST['do']=='globalpass'){
		if(isSet($_REQUEST['globalpass'])){
			update_setting('globalpass', $_REQUEST['globalpass']);
		}
		if(isSet($_REQUEST['englobalpass']) && preg_match('/^[012]$/', $_REQUEST['englobalpass'])){
			update_setting('englobalpass', $_REQUEST['englobalpass']);
		}
	}elseif($_REQUEST['do']=='dateformat'){
		if(isSet($_REQUEST['dateformat'])){
			update_setting('dateformat', $_REQUEST['dateformat']);
		}
	}elseif($_REQUEST['do']=='captcha'){
		if(isSet($_REQUEST['captcha'])){
			update_setting('captcha', $_REQUEST['captcha']);
		}
	}
	send_setup();
}elseif($_REQUEST['action']=='init'){
	init_chat();
}else{
	send_login();
}
exit;

//  html output subs

function print_credits(){
	global $C, $I;
	echo '<small>';
	if($C['showcredits']){
		echo "<h2>$I[contributors]</h2>";
		echo 'Programming - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
		echo 'German - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
		echo 'English - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
	}
	echo "<br><br><a target=\"_blank\" href=\"https://github.com/DanWin/le-chat-php\">LE CHAT-PHP - $C[version]</a></small></center>";
}

function print_stylesheet($arg1=''){
	echo "<style type=\"text/css\">input,select,textarea{color:#FFFFFF;background-color:#000000;}a img{width:15%}a:hover img{width:35%}$arg1</style>";
}

function print_end(){
	echo '</body></html>';
	exit;
}

function frmpst($arg1='', $arg2=''){
	global $H, $U;
	$string="<$H[form]>".hidden('action', $arg1).hidden('session', $U['session']);
	if(!empty($arg2)){
		$string.=hidden('what', $arg2).@hidden('sendto', $_REQUEST['sendto']).@hidden('multi', $_REQUEST['multi']);
	}
	return $string;
}

function frmadm($arg1=''){
	global $H, $U;
	return "<$H[form]>".hidden('action', 'admin').hidden('do', $arg1).hidden('session', $U['session']);
}

function hidden($arg1='', $arg2=''){
	return "<input type=\"hidden\" name=\"$arg1\" value=\"$arg2\">";
}

function submit($arg1='', $arg2=''){
	return "<input type=\"submit\" value=\"$arg1\" $arg2>";
}

function thr(){
	echo '<tr><td><hr></td></tr>';
}

function print_start($css='', $ref='', $url=''){
	global $H;
	header('Content-Type: text/html; charset=UTF-8'); header('Pragma: no-cache'); header('Cache-Control: no-cache'); header('Expires: 0');
	if(!empty($url)) header("Refresh: $ref; URL=$url");
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	if(!empty($url)) echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
	print_stylesheet($css);
	echo "</head>$H[begin_body]";
}

function send_redirect(){
	if(preg_match('~^http(s)?://~', $_GET['url'])){
		header("Refresh: 0; URL=$_GET[url]");
		echo "<html><head><meta http-equiv=\"Refresh\" content=\"0; url=$_GET[url]\"></head><body><p>Redirecting to: <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url']).'</a>.</p></body></html>';
	}else{
		$url=preg_replace('~(.*)://~', 'http://', $_GET['url']);
		echo '<html><head></head><body>';
		echo "<p>Non-http link requested: <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url']).'</a>.</p>';
		echo "<p>If it's not working, try this one: <a href=\"$url\">".htmlspecialchars($url).'</a>.</p>';
		echo '</body></html>';
	}
}

function send_captcha(){
	global $C, $I, $memcached, $mysqli;
	$difficulty=get_setting('captcha');
	if($difficulty==0) return;
	$length=strlen($C['captchachars'])-1;
	$code='';
	for($i=0;$i<5;++$i){
		$code.=$C['captchachars'][rand(0, $length)];
	}
	$randid=rand(0, 99999999);
	$time=time();
	if($C['memcached']){
		$memcached->set("$C[dbname]-$C[prefix]captcha-$randid", $code, $C['captchatime']);
	}else{
		$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]captcha` (`id`, `time`, `code`) VALUES (?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'iis', $randid, $time, $code);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	echo "<tr><td align=\"left\">$I[copy]";
	if($difficulty==1){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty==2){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$line=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<4;++$i){
			imageline($im, 0, rand(0, 24), 55, rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, rand(0, 55), rand(0, 24), $dots);
		}
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty==3){
		$im=imagecreatetruecolor(150, 200);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$line=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<10;++$i){
			imageline($im, 0, rand(0, 200), 150, rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, rand(0, 150), rand(0, 200), $dots);
		}
		for($i=0;$i<5;++$i){
			imagechar($im, 5, rand(10, 140), rand(10, 180), $C['captchachars'][rand(0, $length)], $fg);
		}
		$x=$y=array();
		$follow=imagecolorallocate($im, 200, 0, 0);
		for($i=0;$i<5;++$i){
			$x[]=rand(10, 140);
			$y[]=rand(10, 180);
			imagechar($im, 5, $x[$i], $y[$i], $code[$i], $fg);
		}
		imagearc($im, $x[0]+4, $y[0]+8, 16, 16, 0, 360, $follow);
		for($i=0;$i<4;++$i){
			imageline($im, $x[$i]+4, $y[$i]+8, $x[$i+1]+4, $y[$i+1]+8, $follow);
		}
		echo '<img width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td align="right">'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off"></td></tr>';
}

function send_setup(){
	global $C, $H, $I, $U;
	$ga=get_setting('guestaccess');
	print_start();
	echo "<center><h2>$I[setup]</h2><table cellspacing=\"0\">";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[guestacc]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'guestaccess').hidden('session', $U['session']).'<table cellspacing="0">';
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set1" value="1"';
	if($ga==1) echo ' checked';
	echo "><label for=\"set1\">&nbsp;$I[guestallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set2" value="2"';
	if($ga==2) echo ' checked';
	echo "><label for=\"set2\">&nbsp;$I[guestwait]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set3" value="3"';
	if($ga==3) echo ' checked';
	echo "><label for=\"set3\">&nbsp;$I[adminallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set0" value="0"';
	if($ga==0) echo ' checked';
	echo "><label for=\"set0\">&nbsp;$I[guestdisallow]</label></td><td>&nbsp;</td></tr>";
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	$englobal=get_setting('englobalpass');
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[globalloginpass]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'globalpass').hidden('session', $U['session']).'<table cellspacing="0">';
	echo '<tr><td><select name="englobalpass" style="text-align:center;">';
	echo '<option value="0"'; if($englobal==0) echo ' selected'; echo ">$I[disabled]</option>";
	echo '<option value="1"'; if($englobal==1) echo ' selected'; echo ">$I[enabled]</option>";
	echo '<option value="2"'; if($englobal==2) echo ' selected'; echo ">$I[onlyguests]</option>";
	echo '</select></td><td>&nbsp;</td>';
	echo '<td><input type="text" name="globalpass" value="'.htmlspecialchars(get_setting('globalpass')).'"></td><td>&nbsp;</td>';
	echo '<td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[sysmessages]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'messages').hidden('session', $U['session']).'<table cellspacing="0">';
	echo "<tr><td>&nbsp;$I[msgenter]</td><td>&nbsp;<input type=\"text\" name=\"msgenter\" value=\"".get_setting('msgenter').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgexit]</td><td>&nbsp;<input type=\"text\" name=\"msgexit\" value=\"".get_setting('msgexit').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgmemreg]</td><td>&nbsp;<input type=\"text\" name=\"msgmemreg\" value=\"".get_setting('msgmemreg').'"></td></tr>';
	if($C['suguests']) echo "<tr><td>&nbsp;$I[msgsureg]</td><td>&nbsp;<input type=\"text\" name=\"msgsureg\" value=\"".get_setting('msgsureg').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgkick]</td><td>&nbsp;<input type=\"text\" name=\"msgkick\" value=\"".get_setting('msgkick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgmultikick]</td><td>&nbsp;<input type=\"text\" name=\"msgmultikick\" value=\"".get_setting('msgmultikick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgallkick]</td><td>&nbsp;<input type=\"text\" name=\"msgallkick\" value=\"".get_setting('msgallkick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgclean]</td><td>&nbsp;<input type=\"text\" name=\"msgclean\" value=\"".get_setting('msgclean').'"></td></tr>';
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[rules]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'rules').hidden('session', $U['session']).'<table cellspacing="0">';
	echo '<tr><td colspan=2><textarea name="rulestxt" rows="4" cols="60">'.htmlspecialchars(get_setting('rulestxt')).'</textarea></td></tr>';
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[dateformat]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'dateformat').hidden('session', $U['session']).'<table cellspacing="0">';
	echo '<tr><td><input type="text" name="dateformat" value="'.htmlspecialchars(get_setting('dateformat')).'"></td><td>&nbsp;</td>';
	echo '<td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[captcha]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'captcha').hidden('session', $U['session']).'<table cellspacing="0">';
	echo '<tr><td><select name="captcha" style="text-align:center;">';
	$captcha=get_setting('captcha');
	echo '<option value="0"'; if($captcha==0) echo ' selected'; echo ">$I[disabled]</option>";
	echo '<option value="1"'; if($captcha==1) echo ' selected'; echo ">$I[simple]</option>";
	echo '<option value="2"'; if($captcha==2) echo ' selected'; echo ">$I[moderate]</option>";
	echo '<option value="3"'; if($captcha==3) echo ' selected'; echo ">$I[extreme]</option>";
	echo '</select></td><td>&nbsp;</td>';
	echo '<td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "</table><$H[form]>".hidden('action', 'logout').hidden('session', $U['session']).submit($I['logout']).'</form>';
	print_credits();
	print_end();
}

function send_init(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[init]</h2>";
	echo "<$H[form]>".hidden('action', 'init')."<table cellspacing=\"0\" width=\"1\"><tr><td align=center><h3>$I[sulogin]</h3><table cellspacing=\"0\">";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo '</table></td></tr><tr><td align="center"><br>'.submit($I['initbtn']).'</td></tr></table></form>';
	print_credits();
	print_end();
}

function send_update(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[dbupdate]</h2><br><$H[form]>".hidden('action', 'setup').submit($I['initgosetup']).'</form><br>';
	print_credits();
	print_end();
}

function send_alogin(){
	global $C, $H, $I;
	print_start();
	echo "<center><$H[form]>".hidden('action', 'setup').'<table>';
	echo "<tr><td align=\"left\">$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\"></td></tr>";
	echo "<tr><td align=\"left\">$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	send_captcha();
	echo '<tr><td colspan="2" align="right">'.submit($I['login']).'</td></tr></table></form>';
	print_credits();
	print_end();
}

function send_admin($arg=''){
	global $C, $H, $I, $P, $U;
	$ga=get_setting('guestaccess');
	print_start();
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"&\">$I[allguests]</option>";
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($user[1]>0 && $user[1]<$U['status']) $chlist.="<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
	}
	$chlist.='</select>';
	echo "<center><h2>$I[admfunc]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[cleanmsgs]</b></td><td align=\"right\">";
	echo frmadm('clean').'<table cellspacing="0"><tr><td>&nbsp;</td><td><input type="radio" name="what" id="room" value="room"></td>';
	echo "<td align=\"left\"><label for=\"room\">$I[room]</label></td><td>&nbsp;</td><td><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked></td>";
	echo "<td align=\"left\"><label for=\"choose\">$I[selection]</label></td><td>&nbsp;</td><td>";
	echo submit($I['clean']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table cellspacing="0" width="100%"><tr><td align="left">'.sprintf($I['kickchat'], $C['kickpenalty']).'</td></tr><tr><td align="right">';
	echo frmadm('kick')."<table cellspacing=\"0\"><tr><td align=\"left\">$I[kickmsg]</td><td align=\"right\"><input type=\"text\" name=\"kickmessage\" size=\"30\"></td><td>&nbsp;</td><td>&nbsp;</td></tr>";
	echo "<tr><td align=\"left\"><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\"><label for=\"purge\">&nbsp;$I[kickpurge]</label></td><td align=\"right\">$chlist</td><td>";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[logoutinact]</b></td><td align=\"right\">";
	echo frmadm('logout')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"right\">$chlist</td><td align=\"right\">";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[viewsess]</b></td><td align=\"right\">";
	echo frmadm('sessions').'<table cellspacing="0"><tr><td>&nbsp;</td><td>'.submit($I['view']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[filter]</b></td><td align=\"right\">";
	echo frmadm('filter').'<table cellspacing="0"><tr><td>&nbsp;</td><td>'.submit($I['view']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[guestacc]</b></td><td align=\"right\">";
	echo frmadm('guestaccess').'<table cellspacing="0">';
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set1" value="1"';
	if($ga==1) echo ' checked';
	echo "><label for=\"set1\">&nbsp;$I[guestallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set2" value="2"';
	if($ga==2) echo ' checked';
	echo "><label for=\"set2\">&nbsp;$I[guestwait]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set3" value="3"';
	if($ga==3) echo ' checked';
	echo "><label for=\"set3\">&nbsp;$I[adminallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set0" value="0"';
	if($ga==0) echo ' checked';
	echo "><label for=\"set0\">&nbsp;$I[guestdisallow]</label></td><td>&nbsp;</td></tr>";
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	$englobal=get_setting('englobalpass');
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[globalloginpass]</b></td><td align=\"right\">";
	echo frmadm('globalpass').'<table cellspacing="0"><tr><td><select name="englobalpass" style="text-align:center;">';
	echo '<option value="0"'; if($englobal==0) echo ' selected'; echo ">$I[disabled]</option>";
	echo '<option value="1"'; if($englobal==1) echo ' selected'; echo ">$I[enabled]</option>";
	echo '<option value="2"'; if($englobal==2) echo ' selected'; echo ">$I[onlyguests]</option>";
	echo '</select></td><td>&nbsp;</td>';
	echo '<td>&nbsp;</td><td><input type="text" name="globalpass" value="'.htmlspecialchars(get_setting('globalpass')).'"></td>';
	echo '<td>&nbsp;</td><td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	if($C['suguests']){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[addsuguest]</b></td><td align=\"right\">";
		echo frmadm('superguest')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[1]==1) echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
		echo '</select></td><td valign="bottom">'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[admmembers]</b></td></tr><tr><td align=\"right\">";
		echo frmadm('status')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\" align=\"right\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		print_memberslist();
		echo "</select><select name=\"set\" size=\"1\"><option value=\"\">$I[choose]</option><option value=\"-\">$I[memdel]</option><option value=\"0\">$I[memdeny]</option>";
		if($C['suguests']) echo "<option value=\"2\">$I[memsuguest]</option>";
		echo "<option value=\"3\">$I[memreg]</option>";
		echo "<option value=\"5\">$I[memmod]</option>";
		echo "<option value=\"6\">$I[memsumod]</option>";
		if($U['status']>=8) echo "<option value=\"7\">$I[memadm]</option>";
		echo '</select></td><td valign="bottom">'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[regguest]</b></td><td align=\"right\">";
		echo frmadm('register')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[1]==1) echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
		echo '</select></td><td valign="bottom">'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[regmem]</b></td></tr><tr><td align=\"right\">";
		echo frmadm('regnew')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"left\">$I[nick]</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[pass]</td><td><input type=\"text\" name=\"pass\" size=\"20\"></td><td valign=\"bottom\">";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table>$H[backtochat]</center>";
	print_end();
}

function send_sessions(){
	global $H, $I, $U;
	$lines=parse_sessions();
	print_start();
	echo "<center><h1>$I[sessact]</h1><table border=\"0\" cellpadding=\"5\">";
	echo "<thead valign=\"middle\"><tr><th align=\"left\"><b>$I[sessnick]</b></th><th align=\"center\"><b>$I[sesstimeout]</b></th><th align=\"left\"><b>$I[sessua]</b></th></tr></thead><tbody valign=\"middle\">";
	foreach($lines as $temp){
		if($temp['status']!=0 && $temp['entry']==0){
			if($temp['status']==1 || $temp['status']==2) $s='&nbsp;(G)';
			elseif($temp['status']==3) $s='';
			elseif($temp['status']==5 || $temp['status']==6) $s='&nbsp;(M)';
			elseif($temp['status']>=7) $s='&nbsp;(A)';
			echo '<tr><td align="left">'.style_this($temp['nickname'].$s, $temp['fontinfo']).'</td><td align="center">'.get_timeout($temp['lastpost'], $temp['status']).'</td><td align="left">';
			if($U['status']>$temp['status'] || $U['session']==$temp['session']) echo $temp['useragent'];
			else echo '-</td></tr>';
		}
	}
	echo "</tbody></table><br>$H[backtochat]</center>";
	print_end();
}

function manage_filter(){
	global $C, $I, $memcached, $mysqli;
	if(isSet($_REQUEST['id'])){
		$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
		if(isSet($_REQUEST['regex']) && $_REQUEST['regex']==1){
			if(!is_int(@preg_match("/$_REQUEST[match]/", ''))) send_filter($I['incorregex']);
			$reg=1;
		}else{
			$_REQUEST['match']=preg_replace('/([^\w\d])/', "\\\\$1", $_REQUEST['match']);
			$reg=0;
		}
		if(isSet($_REQUEST['allowinpm']) && $_REQUEST['allowinpm']==1) $pm=1;
		else $pm=0;
		if(isSet($_REQUEST['kick']) && $_REQUEST['kick']==1) $kick=1;
		else $kick=0;
		if(preg_match('/^[0-9]*$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]filter` WHERE `id`=?");
				mysqli_stmt_bind_param($stmt, 'i', $_REQUEST['id']);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]filter");
			}else{
				$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]filter` SET `match`=?, `replace`=?, `allowinpm`=?, `regex`=?, `kick`=? WHERE `id`=?");
				mysqli_stmt_bind_param($stmt, 'ssiiii', $_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $_REQUEST['id']);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
				if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]filter");
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]filter` (`match`, `replace`, `allowinpm`, `regex`, `kick`) VALUES (?, ?, ?, ?, ?)");
			mysqli_stmt_bind_param($stmt, 'ssiii', $_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]filter");
		}
	}
}

function send_filter($arg=''){
	global $C, $H, $I, $U, $memcached, $mysqli;
	print_start();
	echo "<center><h2>$I[filter]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	echo "<tr><th><table cellspacing=\"0\" width=\"100%\"><tr><td style=\"width:8em\"><center><b>$I[fid]</b></center></td>";
	echo "<td style=\"width:12em\"><center><b>$I[match]</b></center></td>";
	echo "<td style=\"width:12em\"><center><b>$I[replace]</b></center></td>";
	echo "<td style=\"width:9em\"><center><b>$I[allowpm]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[regex]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[kick]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[apply]</b></center></td></tr></table></th></tr>";
	if($C['memcached']) $filters=$memcached->get("$C[dbname]-$C[prefix]filter");
	if(!$C['memcached'] || $memcached->getResultCode()!=Memcached::RES_SUCCESS){
		$filters=array();
		$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]filter`");
		while($filter=mysqli_fetch_array($result, MYSQLI_ASSOC)) $filters[]=$filter;
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]filter", $filters);
	}
	foreach($filters as $filter){
		if($filter['allowinpm']==1) $check=' checked';
		else $check='';
		if($filter['regex']==1) $checked=' checked';
		else $checked='';
		if($filter['kick']==1) $checkedk=' checked';
		else $checkedk='';
		if($filter['regex']==0) $filter['match']=preg_replace('/(\\\\(.))/', "$2", $filter['match']);
		echo '<tr><td>'.frmadm('filter').hidden('id', $filter['id']);
		echo "<table cellspacing=\"0\" width=\"100%\"><tr><td style=\"width:8em\"><b>$I[filter] $filter[id]:</b></td>";
		echo "<td style=\"width:12em\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:9em\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm-$filter[id]\" value=\"1\"$check><label for=\"allowinpm-$filter[id]\">$I[allowpm]</label></td>";
		echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"regex\" id=\"regex-$filter[id]\" value=\"1\"$checked><label for=\"regex-$filter[id]\">$I[regex]</label></td>";
		echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"kick\" id=\"kick-$filter[id]\" value=\"1\"$checkedk><label for=\"kick-$filter[id]\">$I[kick]</label></td>";
		echo '<td align="right" style="width:5em">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>'.frmadm('filter').hidden('id', '+');
	echo "<table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\" style=\"width:8em\"><b>$I[newfilter]</b></td>";
	echo "<td style=\"width:12em\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:9em\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm\" value=\"1\"><label for=\"allowinpm\">$I[allowpm]</label></td>";
	echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\"><label for=\"regex\">$I[regex]</label></td>";
	echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"1\"><label for=\"kick\">$I[kick]</label></td>";
	echo '<td align="right" style="width:5em">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]</center>";
	print_end();
}

function send_frameset(){
	global $H, $I, $U;
	header('Content-Type: text/html; charset=UTF-8'); header('Pragma: no-cache'); header('Cache-Control: no-cache'); header('Expires: 0');
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	print_stylesheet();
	if(isSet($_COOKIE['test'])){
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls\"><noframes>$H[begin_body]$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}else{
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&amp;session=$U[session]\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&amp;session=$U[session]\"><noframes>$H[begin_body]$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}
	exit;
}

function send_messages(){
	global $C, $I, $U;
	if(isSet($_COOKIE[$C['cookiename']])){
		$url="$_SERVER[SCRIPT_NAME]?action=view";
	}else{
		$url="$_SERVER[SCRIPT_NAME]?action=view&amp;session=$U[session]";
	}
	print_start('', $U['refresh'], $url);
	echo '<a name="top"></a>';
	print_chatters();
	echo "<table cellspacing=\"0\" width=\"100%\"><tr><td valign=\"top\" align=\"right\"><a href=\"$url#bottom\">$I[bottom]</a></td></tr></table>";
	print_messages();
	echo "<a name=\"bottom\"></a><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"right\"><a href=\"$url#top\">$I[top]</a></td></tr></table>";
	print_end();
}

function send_notes($type){
	global $C, $H, $I, $U, $mysqli;
	print_start();
	$text='';
	if($type=='staff') echo "<center><h2>$I[staffnotes]</h2><p>";
	else echo "<center><h2>$I[adminnotes]</h2><p>";
	if(isset($_REQUEST['text'])){
		$time=time();
		$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]notes` (`type`, `lastedited`, `editedby`, `text`) VALUES (?, ?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'siss', $type, $time, $U['nickname'], $_REQUEST['text']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		echo "<b>$I[notessaved]</b> ";
	}
	$dateformat=get_setting('dateformat');
	$stmt=mysqli_prepare($mysqli, "SELECT `lastedited`, `editedby`, `text` FROM `$C[prefix]notes` WHERE `type`=? ORDER BY `lastedited` DESC LIMIT 1");
	mysqli_stmt_bind_param($stmt, 's', $type);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $lastedited, $editedby, $text);
	if(mysqli_stmt_fetch($stmt)) printf($I['lastedited'], $editedby, date($dateformat, $lastedited));
	mysqli_stmt_close($stmt);
	echo "</p><$H[form]>";
	if($type=='staff') echo hidden('action', 'notes');
	else echo hidden('action', 'admnotes');
	echo hidden('session', $U['session'])."<textarea name=\"text\" rows=\"$U[notesboxheight]\" cols=\"$U[notesboxwidth]\">".htmlspecialchars($text).'</textarea><br>';
	echo submit($I['savenotes']).'</form></center></body></html>';
}

function send_approve_waiting(){
	global $C, $H, $I, $U, $mysqli;
	print_start('admin');
	echo "<center><h2>$I[waitingroom]</h2>";
	$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]sessions` WHERE `entry`!='0' AND `status`='1' ORDER BY `entry`");
	if(mysqli_num_rows($result)>0){
		echo "<$H[form]>".hidden('action', 'admin').hidden('do', 'approve').hidden('session', $U['session']).'<table cellpadding="5">';
		echo "<thead align=\"left\"><tr><th><b>$I[sessnick]</b></th><th><b>$I[sessua]</b></th></tr></thead><tbody align=\"left\" valign=\"middle\">";
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			echo '<tr>'.hidden('alls[]', $temp['nickname'])."<td><input type=\"checkbox\" name=\"csid[]\" id=\"$temp[nickname]]\" value=\"$temp[nickname]\"><label for=\"$temp[nickname]\">&nbsp;$temp[displayname]</label></td><td>$temp[useragent]</td></tr>";
		}
		echo "</tbody></table><br><table><tr><td><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked></td><td><label for=\"allowchecked\">$I[allowchecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\"></td><td><label for=\"allowall\">$I[allowall]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\"></td><td><label for=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\"></td><td><label for=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\" align=\"center\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8" align="center">'.submit($I['butallowdeny']).'</td></tr></table></form>';
	}else{
		echo "$I[waitempty]<br>";
	}
	echo "<br>$H[backtochat]</center>";
	print_end();
}

function send_waiting_room(){
	global $C, $H, $I, $M, $U, $countmods, $mysqli;
	parse_sessions();
	$ga=get_setting('guestaccess');
	if($ga==3 && $countmods>0) $wait=false;
	else $wait=true;
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
	$timeleft=$C['entrywait']-(time()-$U['entry']);
	if($wait && ($timeleft<=0 || count($M)==0 || $ga==1)){
		$U['entry']=0;
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `entry`=? WHERE `session`=?");
		mysqli_stmt_bind_param($stmt, 'is', $U['entry'], $U['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		send_frameset();
	}elseif(!$wait && $U['entry']==0){
		send_frameset();
	}else{
		if(isSet($_COOKIE['test'])){
			header("Refresh: $C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait");
			echo "<!DOCTYPE html><html><head>$H[meta_html]<meta http-equiv=\"Refresh\" content=\"$C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait\">";
		}else{
			header("Refresh: $C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]");
			echo "<!DOCTYPE html><html><head>$H[meta_html]<meta http-equiv=\"Refresh\" content=\"$C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]\">";
		}
		print_stylesheet();
		if($wait){
			echo "</head>$H[begin_body]<center><h2>$I[waitingroom]</h2><p>".sprintf($I['waittext'], $U['displayname'], $timeleft).'</p><br><p>'.sprintf($I['waitreload'], $C['defaultrefresh']).'</p><br><br>';
		}else{
			echo "</head>$H[begin_body]<center><h2>$I[waitingroom]</h2><p>".sprintf($I['admwaittext'], $U['displayname']).'</p><br><p>'.sprintf($I['waitreload'], $C['defaultrefresh']).'</p><br><br>';
		}
		echo "<hr><form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\">".hidden('action', 'wait').hidden('session', $U['session']).submit($I['reload']).'</form><br>';
		echo "<h2>$I[rules]</h2><b>".get_setting('rulestxt').'</b></center>';
		print_end();
	}
}

function send_choose_messages(){
	global $H, $I, $U;
	print_start();
	echo frmadm('clean').hidden('what', 'selected').submit($I['delselmes'], ' style="background-color:#660000;color:#FFFFFF;"').'<br><br>';
	print_messages($U['status']);
	echo "</form><br>$H[backtochat]";
	print_end();
}

function send_post(){
	global $C, $I, $P, $U, $countmods;
	$U['postid']=substr(time(), -6);
	print_start();
	echo '<center><table cellspacing="0"><tr><td align="center">'.frmpst('post').hidden('postid', $U['postid']).@hidden('multi', $_REQUEST['multi']);
	echo "<table cellspacing=\"0\"><tr><td valign=\"top\">$U[displayname]</td><td valign=\"top\">:</td>";
	if(!isSet($U['rejected'])) $U['rejected']='';
	if(isSet($_REQUEST['multi']) && $_REQUEST['multi']=='on'){
		echo "<td valign=\"top\"><textarea name=\"message\" wrap=\"virtual\" rows=\"$U[boxheight]\" cols=\"$U[boxwidth]\" style=\"$U[style]\">$U[rejected]</textarea></td>";
	}else{
		echo "<td valign=\"top\"><input type=\"text\" name=\"message\" value=\"$U[rejected]\" size=\"$U[boxwidth]\" maxlength=\"$C[maxmessage]\" style=\"$U[style]\"></td>";
	}
	echo '<td valign="top">'.submit($I['talkto']).'</td><td valign="top"><select name="sendto" size="1">';
	echo '<option '; if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='*') echo 'selected '; echo "value=\"*\">-$I[toall]-</option>";
	if($U['status']>=3){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='?') echo 'selected ';
		echo "value=\"?\">-$I[tomem]-</option>";
	}
	if($U['status']>=5){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='#') echo 'selected ';
		echo "value=\"#\">-$I[tostaff]-</option>";
	}
	if($U['status']>=6){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='&') echo 'selected ';
		echo "value=\"&\">-$I[toadmin]-</option>";
	}
	$ignored=array();
	$ignore=get_ignored();
	foreach($ignore as $ign){
		if($ign['ignored']==$U['nickname']) $ignored[]=$ign['by'];
		if($ign['by']==$U['nickname']) $ignored[]=$ign['ignored'];
	}
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($U['nickname']!==$user[0] && !in_array($user[0], $ignored)){
			echo '<option ';
			if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']==$user[0]) echo 'selected ';
			echo "value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
	}
	echo '</select>';
	if($U['status']>=5 || ($C['memkick'] && $countmods==0 && $U['status']>=3)){
		echo "<input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\"><label for=\"kick\">&nbsp;$I[kick]</label>";
		echo "<input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked><label for=\"what\">&nbsp;$I[alsopurge]</label>";
	}
	echo '</td></tr></table></form></td></tr><tr><td height="8"></td></tr><tr><td align="center"><table cellspacing="0"><tr><td>';
	echo frmpst('delete', 'last').submit($I['dellast']).'</form></td><td>'.frmpst('delete', 'all').submit($I['delall']).'</form></td><td width="10"></td><td>';
	if(isSet($_REQUEST['multi']) && $_REQUEST['multi']=='on'){
		$switch=$I['switchsingle'];
		$multi='';
	}else{
		$switch=$I['switchmulti'];
		$multi='on';
	}
	echo frmpst('post').@hidden('sendto', $_REQUEST['sendto']).hidden('multi', $multi).submit($switch).'</form></td>';
	echo '</tr></table></td></tr></table></center>';
	print_end();
}

function send_help(){
	global $C, $H, $I, $U;
	print_start();
	echo "<h2>$I[rules]</h2>".get_setting('rulestxt')."<br><br><hr><h2>$I[help]</h2>$I[helpguest]";
	if($C['imgembed']) echo "<br>$I[helpembed]";
	if($U['status']>=3){
		echo "<br>$I[helpmem]<br>";
		if($U['status']>=5){
			echo "<br>$I[helpmod]<br>";
			if($U['status']>=7) echo "<br>$I[helpadm]<br>";
		}
	}
	echo "<br><hr><center>$H[backtochat]";
	print_credits();
	print_end();
}

function send_profile($arg=''){
	global $C, $F, $H, $I, $P, $U;
	print_start();
	echo "<center><$H[form]>".hidden('action', 'profile').hidden('do', 'save').hidden('session', $U['session'])."<h2>$I[profile]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	$ignored=array();
	$ignore=get_ignored();
	foreach($ignore as $ign){
		if($ign['by']==$U['nickname']) $ignored[]=$ign['ignored'];
	}
	if(count($ignored)>0){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[unignore]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($ignored as $ign){
			$style='';
			foreach($P as $user){
				if($ign==$user[0]){
					$style=" style=\"$user[2]\"";
					break;
				}
			}
			echo "<option value=\"$ign\"$style>$ign</option>";
		}
		echo '</select></td></tr></table></td></tr></table></td></tr>';
		thr();
	}
	if(count($P)-count($ignored)>1){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[ignore]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($U['nickname']!==$user[0] && !in_array($user[0], $ignored)){
				echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
			}
		}
		echo '</select></td></tr></table></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[refreshrate]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" name=\"refresh\" size=\"3\" maxlength=\"3\" value=\"$U[refresh]\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[fontcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]\" target=\"view\">$I[viewexample]</a>)</td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" size=\"7\" maxlength=\"6\" value=\"$U[colour]\" name=\"colour\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[bgcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]\" target=\"view\">$I[viewexample]</a>)</td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" size=\"7\" maxlength=\"6\" value=\"$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[fontface]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"font\" size=\"1\"><option value=\"\">* $I[roomdefault] *</option>";
		foreach($F as $name=>$font){
			echo '<option style="'.get_style($font).'" ';
			if(preg_match("/$font/", $U['fontinfo'])) echo 'selected ';
			echo "value=\"$name\">$name</option>";
		}
		echo '</select></td><td>&nbsp;</td><td><input type="checkbox" name="bold" id="bold" value="on"';
		if(preg_match('/<i?bi?>/', $U['fontinfo'])) echo ' checked';
		echo "></td><td><label for=\"bold\"><b>$I[bold]</b></label></td><td>&nbsp;</td><td><input type=\"checkbox\" name=\"italic\" id=\"italic\" value=\"on\"";
		if(preg_match('/<b?ib?>/', $U['fontinfo'])) echo ' checked';
		echo "></td><td><label for=\"italic\"><i>$I[italic]</i></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo "<tr><td align=\"center\">$U[displayname]&nbsp;: ".style_this($I['fontexample'], $U['fontinfo']).'</td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[timestamps]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo '<tr><td>&nbsp;</td><td><input type="checkbox" name="timestamps" id="timestamps" value="on"';
	if($U['timestamps']) echo ' checked';
	echo "></td><td><label for=\"timestamps\"><b>$I[enabled]</b></label></td></tr></table></td></tr></table></td></tr>";
	thr();
	if($C['imgembed']){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[embed]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo '<tr><td>&nbsp;</td><td><input type="checkbox" name="embed" id="embed" value="on"';
		if($U['embed'] && isSet($_COOKIE[$C['cookiename']])) echo ' checked';
		echo "></td><td><label for=\"embed\"><b>$I[enabled]</b></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	if($U['status']>=5 && $C['incognito']){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[incognito]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo '<tr><td>&nbsp;</td><td><input type="checkbox" name="incognito" id="incognito" value="on"';
		if($U['incognito']) echo ' checked';
		echo "></td><td><label for=\"incognito\"><b>$I[enabled]</b></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[pbsize]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"text\" name=\"boxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[boxwidth]\"></td>";
	echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"text\" name=\"boxheight\" size=\"3\" maxlength=\"3\" value=\"$U[boxheight]\"></td>";
	echo '</tr></table></td></tr></table></td></tr>';
	thr();
	if($U['status']>=5){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[nbsize]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"text\" name=\"notesboxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxwidth]\"></td>";
		echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"text\" name=\"notesboxheight\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxheight]\"></td>";
		echo '</tr></table></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=2){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[changepass]</b></td></tr>";
		echo "<tr><td align=\"right\"><table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"left\">$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo '<tr><td align="center">'.submit($I['savechanges'])."</td></tr></table></form><br>$H[backtochat]</center>";
	print_end();
}

function send_controls(){
	global $H, $I, $U;
	print_start();
	echo '<center><table cellspacing="0"><tr>';
	echo "<td><$H[form] target=\"post\">".hidden('action', 'post').hidden('session', $U['session']).submit($I['reloadpb']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'view').hidden('session', $U['session']).submit($I['reloadmsgs']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'profile').hidden('session', $U['session']).submit($I['chgprofile']).'</form></td>';
	if($U['status']>=5) echo "<td><$H[form] target=\"view\">".hidden('action', 'admin').hidden('session', $U['session']).submit($I['adminbtn']).'</form></td>';
	if($U['status']>=6) echo "<td><$H[form] target=\"view\">".hidden('action', 'admnotes').hidden('session', $U['session']).submit($I['admnotes']).'</form></td>';
	if($U['status']>=5) echo "<td><$H[form] target=\"view\">".hidden('action', 'notes').hidden('session', $U['session']).submit($I['notes']).'</form></td>';
	if($U['status']>=3) echo "<td><$H[form] target=\"_blank\">".hidden('action', 'login').hidden('session', $U['session']).submit($I['clone']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'help').hidden('session', $U['session']).submit($I['randh']).'</form></td>';
	echo "<td><$H[form] target=\"_parent\">".hidden('action', 'logout').hidden('session', $U['session']).submit($I['exit']).'</form></td>';
	echo '</tr></table></center>';
	print_end();
}

function send_logout(){
	global $H, $I, $U;
	print_start();
	echo '<center><h1>'.sprintf($I['bye'], $U['displayname'])."</h1>$H[backtologin]</center>";
	print_end();
}

function send_colours(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[colourtable]</h2><tt>";
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X', $red).sprintf('%02X', $green).sprintf('%02X', $blue);
				echo "<font color=\"#$hcol\"><b>$hcol</b></font> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo "</tt><$H[form]>".hidden('action', 'profile').hidden('session', $_REQUEST['session']).submit($I['backtoprofile'], ' style="background-color:#004400;color:#FFFFFF;"').'</form></center>';
	print_end();
}

function send_login(){
	global $C, $H, $I, $L;
	setcookie('test', '1');
	print_start();
	$ga=get_setting('guestaccess');
	$englobal=get_setting('englobalpass');
	echo "<center><h1>$C[chatname]</h1><$H[form] target=\"_parent\">".hidden('action', 'login');
	if($englobal==1 && isSet($_POST['globalpass'])) echo hidden('globalpass', $_POST['globalpass']);
	echo '<table border="2" width="1" rules="none">';
	if($englobal!=1 || (isSet($_POST['globalpass']) && $_POST['globalpass']==get_setting('globalpass'))){
		echo "<tr><td align=\"left\">$I[nick]</td><td align=\"right\"><input type=\"text\" name=\"nick\" size=\"15\"></td></tr>";
		echo "<tr><td align=\"left\">$I[pass]</td><td align=\"right\"><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
		send_captcha();
		if($ga!=0){
			if($englobal!=0) echo "<tr><td align=\"left\">$I[globalloginpass]</td><td align=\"right\"><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
			echo "<tr><td colspan=\"2\" align=\"center\">$I[choosecol]<br><select style=\"text-align:center;\" name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
			print_colours();
			echo '</select></td></tr>';
		}else{
			echo "<tr><td colspan=\"2\" align=\"center\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2" align="center">'.submit($I['enter']).'</td></tr></table></form>';
		get_nowchatting();
		echo "<h2>$I[rules]</h2><b>".get_setting('rulestxt').'</b><br>';
	}else{
		echo "<tr><td align=\"left\">$I[globalloginpass]</td><td align=\"right\"><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
		if($ga==0) echo "<tr><td colspan=\"2\" align=\"center\">$I[noguests]</td></tr>";
		echo '<tr><td colspan="2" align="center">'.submit($I['enter']).'</td></tr></table></form>';
	}
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}
	echo '</p>';
	print_credits();
	print_end();
}

function send_error($err){
	global $H, $I;
	print_start('body{color:#FF0033;}');
	echo "<h2>$I[error] $err</h2>$H[backtologin]";
	print_end();
}

function print_chatters(){
	global $C, $G, $H, $I, $M, $U, $mysqli;
	echo '<table cellspacing="0"><tr>';
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=mysqli_query($mysqli, "SELECT COUNT(*) FROM `$C[prefix]sessions` WHERE `entry`!='0' AND `status`='1' ORDER BY `entry`");
		$temp=mysqli_fetch_array($result, MYSQLI_NUM);
		if($temp[0]>0) echo "<td valign=\"top\"><$H[form]>".hidden('action', 'admin').hidden('do', 'approve').hidden('session', $_REQUEST['session']).submit(sprintf($I['approveguests'], $temp[0])).'</form></td><td>&nbsp;</td>';
	}
	if(!empty($M)){
		echo "<td valign=\"top\"><b>$I[members]</b></td><td>&nbsp;</td><td valign=\"top\">".implode(' &nbsp; ', $M).'</td>';
		if(!empty($G)) echo '<td>&nbsp;&nbsp;</td>';
	}
	if(!empty($G)) echo "<td valign=\"top\"><b>$I[guests]</b></td><td>&nbsp;</td><td valign=\"top\">".implode(' &nbsp; ', $G).'</td>';
	echo '</tr></table>';
}

function print_memberslist(){
	global $A;
	read_members();
	array_multisort(array_map('strtolower', array_keys($A)), SORT_ASC, SORT_STRING, $A);
	foreach($A as $member){
		echo "<option value=\"$member[0]\" style=\"$member[2]\">$member[0]";
		if($member[1]==0) echo ' (!)';
		elseif($member[1]==2) echo ' (G)';
		elseif($member[1]==5 || $member[1]==6) echo ' (M)';
		elseif($member[1]>=7) echo ' (A)';
		echo '</option>';
	}
}

//  session management

function create_session($setup){
	global $C, $I, $U, $memcached, $mysqli;
	$U['nickname']=cleanup_nick($_REQUEST['nick']);
	$U['passhash']=md5(sha1(md5($U['nickname'].$_REQUEST['pass'])));
	if(isSet($_REQUEST['colour'])) $U['colour']=$_REQUEST['colour'];
	else $U['colour']=$C['coltxt'];
	$U['status']=1;
	check_member();
	add_user_defaults();
	if($setup) $U['incognito']=true;
	if(get_setting('captcha')>0 && ($U['status']==1 || !$C['dismemcaptcha'])){
		if(!isSet($_REQUEST['challenge'])) send_error($I['wrongcaptcha']);
		if(!$C['memcached']){
			$stmt=mysqli_prepare($mysqli, "SELECT `$C[prefix]code` FROM `captcha` WHERE `id`=?");
			mysqli_stmt_bind_param($stmt, 'i', $_REQUEST['challenge']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_bind_result($stmt, $code);
			if(!mysqli_stmt_fetch($stmt)) send_error($I['captchatime']);
			mysqli_stmt_close($stmt);
		}else{
			if(!$code=$memcached->get("$C[dbname]-$C[prefix]captcha-$_REQUEST[challenge]")) send_error($I['captchatime']);
			$memcached->delete("$C[dbname]-$C[prefix]captcha-$_REQUEST[challenge]");
		}
		if($_REQUEST['captcha']!=$code) send_error($I['wrongcaptcha']);
		$timeout=time()-$C['captchatime'];
		$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]captcha` WHERE `id`=? OR `time`<?");
		mysqli_stmt_bind_param($stmt, 'ii', $_REQUEST['challenge'], $timeout);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	if($U['status']==1){
		if(!valid_nick($U['nickname'])) send_error(sprintf($I['invalnick'], $C['maxname']));
		if(!valid_pass($_REQUEST['pass'])) send_error(sprintf($I['invalpass'], $C['minpass']));
		if(get_setting('guestaccess')==0) send_error($I['noguests']);
		if(get_setting('englobalpass')!=0 && isSet($_REQUEST['globalpass']) && $_REQUEST['globalpass']!=get_setting('globalpass')) send_error($I['wrongpass']);
	}
	write_new_session();
}

function write_new_session(){
	global $C, $I, $U, $mysqli;
	// read and update current sessions
	$lines=parse_sessions();
	$sids; $inuse=false; $reentry=false;
	foreach($lines as $temp){
		$sids[$temp['session']]=true;// collect all existing ids
		if($temp['nickname']==$U['nickname']){// nick already here?
			if($U['passhash']==$temp['passhash']){
				$U=$temp;
				add_user_defaults();
				setcookie($C['cookiename'], $U['session']);
				$reentry=true;
				break;
			}else{
				$inuse=true;
				break;
			}
		}
	}
	// create new session:
	if(!$inuse && !$reentry){
		do{
			$U['session']=md5(time().rand().$U['nickname']);
		}while(isSet($sids[$U['session']]));// check for hash collision
		$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]sessions` (`session`, `nickname`, `displayname`, `status`, `refresh`, `fontinfo`, `style`, `lastpost`, `passhash`, `postid`, `boxwidth`, `boxheight`, `useragent`, `bgcolour`, `notesboxwidth`, `notesboxheight`, `entry`, `timestamps`, `embed`, `incognito`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'sssiissisiiissiiiiii', $U['session'], $U['nickname'], $U['displayname'], $U['status'], $U['refresh'], $U['fontinfo'], $U['style'], $U['lastpost'], $U['passhash'], $U['postid'], $U['boxwidth'], $U['boxheight'], $U['useragent'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		setcookie($C['cookiename'], $U['session']);
		if($C['msglogin'] && $U['status']>=3 && !$U['incognito']) add_system_message(sprintf(get_setting('msgenter'), $U['displayname']));
	}elseif($inuse){
		send_error($I['wrongpass']);
	}elseif($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function approve_session(){
	global $C, $mysqli;
	if(isSet($_REQUEST['what'])){
		if($_REQUEST['what']=='allowchecked' && isSet($_REQUEST['csid'])){
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `entry`='0' WHERE `nickname`=?");
			foreach($_REQUEST['csid'] as $nick){
				mysqli_stmt_bind_param($stmt, 's', $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='allowall' && isSet($_REQUEST['alls'])){
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `entry`='0' WHERE `nickname`=?");
			foreach($_REQUEST['alls'] as $nick){
				mysqli_stmt_bind_param($stmt, 's', $nick);
				mysqli_stmt_execute($stmt);
			}
				mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='denychecked' && isSet($_REQUEST['csid'])){
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `lastpost`='".(60*($C['kickpenalty']-$C['guestsexpire'])+time())."', `status`='0', `kickmessage`=? WHERE `nickname`=? AND `status`='1'");
			foreach($_REQUEST['csid'] as $nick){
				mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['kickmessage'], $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='denyall' && isSet($_REQUEST['alls'])){
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `lastpost`='".(60*($C['kickpenalty']-$C['guestsexpire'])+time())."', `status`='0', `kickmessage`=? WHERE `nickname`=? AND `status`='1'");
			foreach($_REQUEST['alls'] as $nick){
				mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['kickmessage'], $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}
	}
}

function check_login(){
	global $C, $I, $M, $U, $mysqli;
	$ga=get_setting('guestaccess');
	if(isSet($_POST['session'])){
		$stmt=mysqli_prepare($mysqli, "SELECT `session`, `nickname`, `displayname`, `status`, `refresh`, `fontinfo`, `style`, `lastpost`, `passhash`, `postid`, `boxwidth`, `boxheight`, `useragent`, `kickmessage`, `bgcolour`, `notesboxheight`, `notesboxwidth`, `entry`, `timestamps`, `embed`, `incognito` FROM `$C[prefix]sessions` WHERE `session`=?");
		mysqli_stmt_bind_param($stmt, 's', $_POST['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $U['session'], $U['nickname'], $U['displayname'], $U['status'], $U['refresh'], $U['fontinfo'], $U['style'], $U['lastpost'], $U['passhash'], $U['postid'], $U['boxwidth'], $U['boxheight'], $U['useragent'], $U['kickmessage'], $U['bgcolour'], $U['notesboxheight'], $U['notesboxwidth'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito']);
		if(mysqli_stmt_fetch($stmt)){
			mysqli_stmt_close($stmt);
			if($U['status']==0){
				setcookie($C['cookiename'], false);
				send_error("$I[kicked]<br>$U[kickmessage]");
			}else{
				setcookie($C['cookiename'], $U['session']);
			}
		}else{
			mysqli_stmt_close($stmt);
			setcookie($C['cookiename'], false);
			send_error($I['expire']);

		}
	}elseif(get_setting('englobalpass')==1 && ((!isSet($_POST['globalpass']) || $_POST['globalpass']!=get_setting('globalpass')) || !(isSet($_REQUEST['nick']) && isSet($_REQUEST['pass'])))){
		send_login();
	}else{
		create_session(false);
	}
	if($U['status']==1){
		if(($ga==2 || $ga==3) && count($M)>0){
			$U['entry']=time();
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `entry`=? WHERE `session`=?");
			mysqli_stmt_bind_param($stmt, 'is', $U['entry'], $U['session']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$_REQUEST['session']=$U['session'];
			send_waiting_room();
		}
	}
}

function kill_session(){
	global $C, $I, $U, $memcached, $mysqli;
	parse_sessions();
	setcookie($C['cookiename'], false);
	if(!isSet($U['session'])) send_error($I['expire']);
	if($U['status']==0) send_error("$I[kicked]<br>$U[kickmessage]");
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]sessions` WHERE `session`=?");
	mysqli_stmt_bind_param($stmt, 's', $U['session']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($U['status']==1){
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `poster`='' WHERE `poster`=? AND `poststatus`='9'");
		mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `recipient`='' WHERE `recipient`=? AND `poststatus`='9'");
		mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]ignored` WHERE `ignored`=? OR `by`=?");
		mysqli_stmt_bind_param($stmt, 'ss', $U['nickname'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]ignored");
	}
	elseif($C['msglogout'] && $U['status']>=3 && !$U['incognito']) add_system_message(sprintf(get_setting('msgexit'), $U['displayname']));
}

function kick_chatter($names, $mes, $purge){
	global $C, $P, $U, $mysqli;
	$lonick='';
	$lines=parse_sessions();
	$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `lastpost`='".(60*($C['kickpenalty']-$C['guestsexpire'])+time())."', `status`='0', `kickmessage`=? WHERE `session`=? AND `status`!='0'");
	$i=0;
	foreach($names as $name){
		foreach($lines as $temp){
			if(($temp['nickname']==$U['nickname'] && $U['nickname']==$name) || ($U['status']>$temp['status'] && (($temp['nickname']==$name && $temp['status']>0) || ($name=='&' && $temp['status']==1)))){
				mysqli_stmt_bind_param($stmt, 'ss', $mes, $temp['session']);
				mysqli_stmt_execute($stmt);
				if($purge) del_all_messages($temp['nickname']);
				$lonick.="$temp[displayname], ";
				++$i;
				unset($P[$name]);
			}
		}
	}
	mysqli_stmt_close($stmt);
	if($C['msgkick']){
		if(!empty($lonick)){
			if($names[0]=='&'){
				add_system_message(get_setting('msgallkick'));
			}else{
				$lonick=preg_replace('/\,\s$/','',$lonick);
				if($i>1){
					add_system_message(sprintf(get_setting('msgmultikick'), $lonick));
				}else{
					add_system_message(sprintf(get_setting('msgkick'), $lonick));
				}
			}
		}
	}
	if(!empty($lonick)) return true;
	return false;
}

function logout_chatter($names){
	global $C, $P, $U, $memcached, $mysqli;
	$lines=parse_sessions();
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]sessions` WHERE `session`=? AND `status`<? AND `status`!='0'");
	$stmt1=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `poster`='' WHERE `poster`=? AND `poststatus`='9'");
	$stmt2=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `recipient`='' WHERE `recipient`=? AND `poststatus`='9'");
	$stmt3=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]ignored` WHERE `ignored`=? OR `by`=?");
	foreach($names as $name){
		foreach($lines as $temp){
			if($temp['nickname']==$name || ($name=='&' && $temp['status']==1)){
				mysqli_stmt_bind_param($stmt, 'si', $temp['session'], $U['status']);
				mysqli_stmt_execute($stmt);
				if($temp['status']==1){
					mysqli_stmt_bind_param($stmt1, 's', $temp['nickname']);
					mysqli_stmt_bind_param($stmt2, 's', $temp['nickname']);
					mysqli_stmt_bind_param($stmt3, 'ss', $temp['nickname'], $temp['nickname']);
					mysqli_stmt_execute($stmt1);
					mysqli_stmt_execute($stmt2);
					mysqli_stmt_execute($stmt3);
					if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]ignored");
				}
				unset($P[$name]);
			}
		}
	}
	mysqli_stmt_close($stmt);
	mysqli_stmt_close($stmt1);
	mysqli_stmt_close($stmt2);
	mysqli_stmt_close($stmt3);
}

function update_session(){
	global $C, $U, $mysqli;
	if($U['postid']==$_REQUEST['postid']){// ignore double post=reload from browser or proxy
		$_REQUEST['message']='';
	}elseif(time()-$U['lastpost']<=1){// time between posts too short, reject!
		$U['rejected']=$_REQUEST['message'];
		$_REQUEST['message']='';
	}else{// valid post
		$U['postid']=substr($_REQUEST['postid'], 0, 6);
		$U['lastpost']=time();
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `lastpost`=?, `postid`=? WHERE `session`=?");
		mysqli_stmt_bind_param($stmt, 'iis', $U['lastpost'], $U['postid'], $U['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
}

function check_session(){
	global $C, $I, $U;
	parse_sessions();
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function get_nowchatting(){
	global $G, $I, $M, $P;
	parse_sessions();
	echo sprintf($I['curchat'], count($P)).'<br>'.implode(' &nbsp; ', $M).' &nbsp; '.implode(' &nbsp; ', $G);
}

function parse_sessions(){
	global $C, $G, $M, $P, $U, $countmods, $memcached, $mysqli;
	$result=mysqli_query($mysqli, "SELECT `nickname`, `status`, `session` FROM `$C[prefix]sessions` WHERE (`lastpost`<'".(time()-60*$C['guestsexpire'])."' AND `status`<='2') OR (`lastpost`<'".(time()-60*$C['sessionexpire'])."' AND `status`>'2')");
	if(mysqli_num_rows($result)>0){
		$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]sessions` WHERE `nickname`=?");
		$stmt1=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `poster`='' WHERE `poster`=? AND `poststatus`='9'");
		$stmt2=mysqli_prepare($mysqli, "UPDATE `$C[prefix]messages` SET `recipient`='' WHERE `recipient`=? AND `poststatus`='9'");
		$stmt3=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]ignored` WHERE `ignored`=? OR `by`=?");
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			mysqli_stmt_bind_param($stmt, 's', $temp['nickname']);
			mysqli_stmt_execute($stmt);
			if($temp['status']<=1){
				mysqli_stmt_bind_param($stmt1, 's', $temp['nickname']);
				mysqli_stmt_bind_param($stmt2, 's', $temp['nickname']);
				mysqli_stmt_bind_param($stmt3, 'ss', $temp['nickname'], $temp['nickname']);
				mysqli_stmt_execute($stmt1);
				mysqli_stmt_execute($stmt2);
				mysqli_stmt_execute($stmt3);
				if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]ignored");
			}
		}
		mysqli_stmt_close($stmt);
		mysqli_stmt_close($stmt1);
		mysqli_stmt_close($stmt2);
		mysqli_stmt_close($stmt3);
	}
	$lines=array();
	$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]sessions` ORDER BY `status` DESC, `lastpost` DESC");
	while($line=mysqli_fetch_array($result, MYSQLI_ASSOC)) $lines[]=$line;
	if(isSet($_REQUEST['session'])){
		foreach($lines as $temp){
			if($temp['session']==$_REQUEST['session']){
				$U=$temp;
				add_user_defaults();
				break;
			}
		}
	}
	$countmods=0;
	$G=array();
	$M=array();
	$P=array();
	foreach($lines as $temp){
		if($temp['entry']==0){
			if($temp['status']==1 || $temp['status']==2){
				$P[$temp['nickname']]=[$temp['nickname'], $temp['status'], $temp['style']];
				$G[]=$temp['displayname'];
			}elseif($temp['status']>2){
				if(!$temp['incognito']){
					$P[$temp['nickname']]=[$temp['nickname'], $temp['status'], $temp['style']];
					$M[]=$temp['displayname'];
				}
				if($temp['status']>=5) ++$countmods;
			}
		}
	}
	return $lines;
}

//  member handling

function check_member(){
	global $C, $I, $U, $mysqli;
	$stmt=mysqli_prepare($mysqli, "SELECT `nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `fontface`, `fonttags`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `lastlogin`, `timestamps`, `embed`, `incognito` FROM `$C[prefix]members` WHERE `nickname`=?");
	mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $temp['nickname'], $temp['passhash'], $temp['status'], $temp['refresh'], $temp['colour'], $temp['bgcolour'], $temp['fontface'], $temp['fonttags'], $temp['boxwidth'], $temp['boxheight'], $temp['notesboxwidth'], $temp['notesboxheight'], $temp['lastlogin'], $temp['timestamps'], $temp['embed'], $temp['incognito']);
	if(mysqli_stmt_fetch($stmt)){
		if($temp['passhash']==$U['passhash']){
			mysqli_stmt_close($stmt);
			$U=$temp;
			$time=time();
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]members` SET `lastlogin`=? WHERE `nickname`=?");
			mysqli_stmt_bind_param($stmt, 'is', $time, $U['nickname']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}else{
			mysqli_stmt_close($stmt);
			send_error($I['wrongpass']);
		}
	}
}

function read_members(){
	global $A, $C, $F, $memcached, $mysqli;
	if($C['memcached']) $A=$memcached->get("$C[dbname]-$C[prefix]members");
	if(!$C['memcached'] || $memcached->getResultCode()!=Memcached::RES_SUCCESS){
		$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]members`");
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			$A[$temp['nickname']][0]=$temp['nickname'];
			$A[$temp['nickname']][1]=$temp['status'];
			$A[$temp['nickname']][2]=@get_style("#$temp[colour] {$F[$temp['fontface']]} <$temp[fonttags]>");
		}
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]members", $A);
	}
}

function register_guest($status){
	global $A, $C, $I, $P, $U, $memcached, $mysqli;
	if(empty($_REQUEST['name'])) send_admin();
	if(!isSet($P[$_REQUEST['name']])) send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	read_members();
	if(isSet($A[$_REQUEST['name']])) send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	$stmt=mysqli_prepare($mysqli, "SELECT `session`, `nickname`, `displayname`, `passhash`, `refresh`, `fontinfo`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `timestamps`, `embed`, `incognito` FROM `$C[prefix]sessions` WHERE `nickname`=? AND `status`='1'");
	mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $reg['session'], $reg['nickname'], $reg['displayname'], $reg['passhash'], $reg['refresh'], $reg['fontinfo'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['timestamps'], $reg['embed'], $reg['incognito']);
	if(mysqli_stmt_fetch($stmt)){
		mysqli_stmt_close($stmt);
		$reg['status']=$status;
		if(preg_match('/#([a-f0-9]{6})/i', $reg['fontinfo'], $match)) $reg['colour']=$match[1];
		else $reg['colour']=$C['coltxt'];
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `status`=? WHERE `session`=?");
		mysqli_stmt_bind_param($stmt, 'is', $reg['status'], $reg['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}else{
		mysqli_stmt_close($stmt);
		send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	}
	$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]members` (`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `regedby`, `timestamps`, `embed`, `incognito`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	mysqli_stmt_bind_param($stmt, 'ssiissiiiisiii', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['incognito']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]members");
	if($reg['status']==3) add_system_message(sprintf(get_setting('msgmemreg'), $reg['displayname']));
	else add_system_message(sprintf(get_setting('msgsureg'), $reg['displayname']));
}

function register_new(){
	global $A, $C, $I, $P, $U, $memcached, $mysqli;
	$_REQUEST['name']=cleanup_nick($_REQUEST['name']);
	if(empty($_REQUEST['name'])) send_admin();
	if(isSet($P[$_REQUEST['name']])) send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	if(!valid_nick($_REQUEST['name'])) send_admin(sprintf($I['invalnick'], $C['maxname']));
	if(!valid_pass($_REQUEST['pass'])) send_admin(sprintf($I['invalpass'], $C['minpass']));
	read_members();
	if(isSet($A[$_REQUEST['name']])) send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	$reg=array(
		'nickname'	=>$_REQUEST['name'],
		'passhash'	=>md5(sha1(md5($_REQUEST['name'].$_REQUEST['pass']))),
		'status'	=>3,
		'refresh'	=>$C['defaultrefresh'],
		'colour'	=>$C['coltxt'],
		'bgcolour'	=>$C['colbg'],
		'boxwidth'	=>$C['boxwidth'],
		'boxheight'	=>$C['boxheight'],
		'notesboxwidth'	=>$C['notesboxwidth'],
		'notesboxheight'=>$C['notesboxheight'],
		'regedby'	=>$U['nickname'],
		'timestamps'	=>$C['timestamps'],
		'embed'		=>$C['embed'],
		'incognito'	=>false
	);
	$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]members` (`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`,`notesboxwidth`, `notesboxheight`, `regedby`, `timestamps`, `embed`, `incognito`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	mysqli_stmt_bind_param($stmt, 'ssiissiiiisiii', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['regedby'], $reg['timestamps'], $reg['embed'], $reg['incognito']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]members");
	send_admin(sprintf($I['successreg'], $reg['nickname']));
}

function change_status(){
	global $C, $I, $U, $memcached, $mysqli;
	if(empty($_REQUEST['name'])) send_admin();
	if($U['status']<=$_REQUEST['set'] || !preg_match('/^[023567\-]$/', $_REQUEST['set'])) send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
	$stmt=mysqli_prepare($mysqli, "SELECT * FROM `$C[prefix]members` WHERE `nickname`=? AND `status`<?");
	mysqli_stmt_bind_param($stmt, 'si', $_REQUEST['name'], $U['status']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)>0){
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
		if($_REQUEST['set']=='-'){
			$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]members` WHERE `nickname`=?");
			mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `status`='1' WHERE `nickname`=?");
			mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]members");
			send_admin(sprintf($I['succdel'], $_REQUEST['name']));
		}else{
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]members` SET `status`=? WHERE `nickname`=?");
			mysqli_stmt_bind_param($stmt, 'is', $_REQUEST['set'], $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `status`=? WHERE `nickname`=?");
			mysqli_stmt_bind_param($stmt, 'is', $_REQUEST['set'], $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]members");
			send_admin(sprintf($I['succchg'], $_REQUEST['name']));
		}
	}else{
		send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
	}
}

function amend_profile(){
	global $C, $F, $U;
	if(isSet($_REQUEST['refresh'])) $U['refresh']=$_REQUEST['refresh'];
	else $U['refresh']=$C['defaultrefresh'];
	if($U['refresh']<20) $U['refresh']=20;
	if($U['refresh']>150) $U['refresh']=150;
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour'])) $U['colour']=$_REQUEST['colour'];
	else $U['colour']=$C['coltxt'];
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['bgcolour'])) $U['bgcolour']=$_REQUEST['bgcolour'];
	else $U['bgcolour']=$C['colbg'];
	$U['fonttags']='';
	if($U['status']>=3 && isSet($_REQUEST['bold'])) $U['fonttags'].='b';
	if($U['status']>=3 && isSet($_REQUEST['italic'])) $U['fonttags'].='i';
	if($U['status']>=3 && isSet($F[$_REQUEST['font']])) $U['fontface']=$_REQUEST['font'];
	@$U['fontinfo']="#$U[colour] {$F[$U['fontface']]} <$U[fonttags]>";
	if(!isSet($U['fontinfo'])) $U['fontinfo']='';
	$U['style']=get_style($U['fontinfo']);
	$U['displayname']=style_this($U['nickname'], $U['fontinfo']);
	if($_REQUEST['boxwidth']>0) $U['boxwidth']=$_REQUEST['boxwidth'];
	if($_REQUEST['boxheight']>0) $U['boxheight']=$_REQUEST['boxheight'];
	if(isSet($_REQUEST['notesboxwidth']) && $_REQUEST['notesboxwidth']>0) $U['notesboxwidth']=$_REQUEST['notesboxwidth'];
	if(isSet($_REQUEST['notesboxheight']) && $_REQUEST['notesboxheight']>0) $U['notesboxheight']=$_REQUEST['notesboxheight'];
	if(isSet($_REQUEST['timestamps'])) $U['timestamps']=true;
	else $U['timestamps']=false;
	if(isSet($_REQUEST['embed'])) $U['embed']=true;
	else $U['embed']=false;
	if($U['status']>=5 && isSet($_REQUEST['incognito'])) $U['incognito']=true;
	else $U['incognito']=false;
	if($U['boxwidth']>=1000) $U['boxwidth']=40;
	if($U['boxheight']>=1000) $U['boxheight']=3;
	if($U['notesboxwidth']>=1000) $U['notesboxwidth']=80;
	if($U['notesboxheight']>=1000) $U['notesboxheight']=30;
}

function save_profile(){
	global $C, $I, $U, $memcached, $mysqli;
	if(!isSet($_REQUEST['oldpass'])) $_REQUEST['oldpass']='';
	if(!isSet($_REQUEST['newpass'])) $_REQUEST['newpass']='';
	if(!isSet($_REQUEST['confirmpass'])) $_REQUEST['confirmpass']='';
	if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
		send_profile($I['noconfirm']);
	}elseif(!empty($_REQUEST['newpass'])){
		$U['oldhash']=md5(sha1(md5($U['nickname'].$_REQUEST['oldpass'])));
		$U['newhash']=md5(sha1(md5($U['nickname'].$_REQUEST['newpass'])));
	}else{
		$U['oldhash']=$U['newhash']=$U['passhash'];
	}
	if($U['passhash']!==$U['oldhash']) send_profile($I['wrongpass']);
	$U['passhash']=$U['newhash'];
	amend_profile();
	$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]sessions` SET `refresh`=?, `displayname`=?, `fontinfo`=?, `style`=?, `passhash`=?, `boxwidth`=?, `boxheight`=?, `bgcolour`=?, `notesboxwidth`=?, `notesboxheight`=?, `timestamps`=?, `embed`=?, `incognito`=? WHERE `session`=?");
	mysqli_stmt_bind_param($stmt, 'issssiisiiiiis', $U['refresh'], $U['displayname'], $U['fontinfo'], $U['style'], $U['passhash'], $U['boxwidth'], $U['boxheight'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['session']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($U['status']>=2){
		$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]members` SET `passhash`=?, `refresh`=?, `colour`=?, `bgcolour`=?, `fontface`=?, `fonttags`=?, `boxwidth`=?, `boxheight`=?, `notesboxwidth`=?, `notesboxheight`=?, `timestamps`=?, `embed`=?, `incognito`=? WHERE `nickname`=?");
		mysqli_stmt_bind_param($stmt, 'sissssiiiiiiis', $U['passhash'], $U['refresh'], $U['colour'], $U['bgcolour'], $U['fontface'], $U['fonttags'], $U['boxwidth'], $U['boxheight'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]members");
	}
	if(!empty($_REQUEST['unignore'])){
		$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]ignored` WHERE `ignored`=? AND `by`=?");
		mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['unignore'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]ignored");
	}
	if(!empty($_REQUEST['ignore'])){
		$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]ignored` (`ignored`,`by`) VALUES (?, ?)");
		mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['ignore'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		if($C['memcached']) $memcached->delete("$C[dbname]-$C[prefix]ignored");
	}
	send_profile($I['succprofile']);
}

function add_user_defaults(){
	global $C, $F, $H, $U;
	if(isSet($_SERVER['HTTP_USER_AGENT'])) $U['useragent']=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
	else $U['useragent']='';
	if(!isSet($U['refresh'])) $U['refresh']=$C['defaultrefresh'];
	if(!isSet($U['fontinfo'])){
		if(!preg_match('/^[a-f0-9]{6}$/i', $U['colour'])){
			$U['colour']=$C['coltxt'];
			do{
				$U['colour']=sprintf('%02X', rand(0, 256)).sprintf('%02X', rand(0, 256)).sprintf('%02X', rand(0, 256));
			}while(abs(greyval($U['colour'])-greyval($C['colbg']))<75);
		}
		$U['fontinfo']="#$U[colour]";
		@$U['fontinfo'].=" {$F[$U['fontface']]} <$U[fonttags]>";
	}
	if(!isSet($U['bgcolour']) || !preg_match('/^[a-f0-9]{6}$/i', $U['bgcolour'])) $U['bgcolour']=$C['colbg'];
	$H['begin_body']="<body bgcolor=\"#$U[bgcolour]\" text=\"#$C[coltxt]\" link=\"#$C[collnk]\" alink=\"#$C[colact]\" vlink=\"#$C[colvis]\">";
	if(!isSet($U['colour'])){
		preg_match('/([0-9a-f]{6})/i', $U['fontinfo'], $matches);
		$U['colour']=$matches[0];
	}
	if(!isSet($U['style'])) $U['style']=get_style($U['fontinfo']);
	if(!isSet($U['boxwidth'])) $U['boxwidth']=40;
	if(!isSet($U['boxheight'])) $U['boxheight']=3;
	if(!isSet($U['notesboxwidth'])) $U['notesboxwidth']=80;
	if(!isSet($U['notesboxheight'])) $U['notesboxheight']=30;
	if(!isSet($U['timestamps'])) $U['timestamps']=$C['timestamps'];
	if(!isSet($U['embed'])) $U['embed']=$C['embed'];
	if(!isSet($U['incognito'])) $U['incognito']=false;
	if(!isSet($U['lastpost'])) $U['lastpost']=time();
	if(!isSet($U['entry'])) $U['entry']=0;
	if(!isSet($U['postid'])) $U['postid']='OOOOOO';
	if(!isSet($U['displayname'])) $U['displayname']=style_this($U['nickname'], $U['fontinfo']);
}

// message handling

function validate_input(){
	global $C, $P, $U;
	$U['message']=substr($_REQUEST['message'], 0, $C['maxmessage']);
	$U['rejected']=substr($_REQUEST['message'], $C['maxmessage']);
	if(preg_match('/&[^;]{0,8}$/', $U['message']) && preg_match('/^([^;]{0,8};)/', $U['rejected'], $match)){
		$U['message'].=$match[0];
		$U['rejected']=preg_replace("/^$match[0]", '', $U['rejected']);
	}
	if($U['rejected']){
		$U['rejected']=trim($U['rejected']);
		$U['rejected']=htmlspecialchars($U['rejected']);
	}
	$U['message']=htmlspecialchars($U['message']);
	$U['message']=preg_replace("/\r\n/", '<br>', $U['message']);
	$U['message']=preg_replace("/\n/", '<br>', $U['message']);
	$U['message']=preg_replace("/\r/", '<br>', $U['message']);
	if($_REQUEST['multi']=='on'){
		$U['message']=preg_replace('/<br>(<br>)+/', '<br><br>', $U['message']);
		$U['message']=preg_replace('/<br><br>$/', '<br>', $U['message']);
	}else{
		$U['message']=preg_replace('/<br>/', ' ', $U['message']);
	}
	$U['message']=trim($U['message']);
	$U['message']=preg_replace('/\s+/', ' ', $U['message']);
	$U['delstatus']=$U['status'];
	$U['recipient']='';
	if($_REQUEST['sendto']=='*'){
		$U['poststatus']='1';
		$U['displaysend']="$U[displayname] - ";
	}elseif($_REQUEST['sendto']=='?' && $U['status']>=3){
		$U['poststatus']='3';
		$U['displaysend']="[M] $U[displayname] - ";
	}elseif($_REQUEST['sendto']=='#' && $U['status']>=5){
		$U['poststatus']='5';
		$U['displaysend']="[Staff] $U[displayname] - ";
	}elseif($_REQUEST['sendto']=='&' && $U['status']>=6){
		$U['poststatus']='6';
		$U['displaysend']="[Admin] $U[displayname] - ";
	}else{// known nick in room?
		$ignored=get_ignored();
		$ignore=false;
		foreach($ignored as $ign){
			if($ign['by']==$U['nickname'] && $ign['ignored']==$_REQUEST['sendto'] || ($ign['by']==$_REQUEST['sendto'] && $ign['ignored']==$U['nickname'])){
				$ignore=true;
				break;
			}
		}
		if(!$ignore){
			foreach($P as $chatter){
				if($_REQUEST['sendto']==$chatter[0]){
					$U['recipient']=$chatter[0];
					$U['displayrecp']=style_this($chatter[0], $chatter[2]);
					break;
				}
			}
		}
		if(!empty($U['recipient'])){
			$U['poststatus']='9';
			$U['delstatus']='9';
			$U['displaysend']="[$U[displayname] to $U[displayrecp]] - ";
		}else{// nick left already or ignores us
			$U['message']='';
			$U['rejected']='';
		}
	}
	if(isSet($U['poststatus'])){
		apply_filter();
		create_hotlinks();
		if(add_message()) update_session();
	}
}

function apply_filter(){
	global $C, $I, $U, $memcached, $mysqli;
	if($U['poststatus']!=9 && preg_match('~^/me~i', $U['message'])){
		$U['displaysend']=substr($U['displaysend'], 0, -2);
		$U['message']=preg_replace("~^/me~i", '', $U['message']);
	}
	if($C['memcached']) $filters=$memcached->get("$C[dbname]-$C[prefix]filter");
	if(!$C['memcached'] || $memcached->getResultCode()!=Memcached::RES_SUCCESS){
		$filters=array();
		$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]filter`");
		while($filter=mysqli_fetch_array($result, MYSQLI_ASSOC)) $filters[]=$filter;
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]filter", $filters);
	}
	foreach($filters as $filter){
		if($U['poststatus']!=9) $U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		elseif(!$filter['allowinpm']) $U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		if($count>0 && $filter['kick']){
			kick_chatter(array($U['nickname']), '', false);
			send_error("$I[kicked]");
		}
	}
}

function create_hotlinks(){
	global $C, $U;
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$U['message']=preg_replace('~(\w*://[^\s<>]+)~i', "<<$1>>", $U['message']);
	// 2. valid URLs without scheme:
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server/path given
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server:port given
	$U['message']=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~i', "<<$1>>", $U['message']); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$U['message']=preg_replace('~((?:[a-z0-9\-]+\.)*[a-z0-9]{16}\.onion)(?![^<>]*>)~i', "<<$1>>", $U['message']);// *.onion
	$U['message']=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-\.]|$)(?![^<>]*>)~i', "<<$1>>", $U['message']);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$U['message']=preg_replace_callback('/<<([^<>]+)>>/', function ($matches){if(strpos($matches[1], '://')==false){ return "<a href=\"http://$matches[1]\" target=\"_blank\">$matches[1]</a>";}else{ return "<a href=\"$matches[1]\" target=\"_blank\">$matches[1]</a>"; }}, $U['message']);
	if($C['imgembed']) $U['message']=preg_replace_callback('/\[img\]\s?<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/i', function ($matched){ return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\"><img src=\"$matched[1]\"></a><br>");}, $U['message']);
	if($C['forceredirect']) $U['message']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $U['message']);
	if(preg_match_all('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', $U['message'], $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~', $match)){
				$U['message']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $U['message']);
				break;
			}
		}
	}
}

function add_message(){
	global $U;
	if(empty($U['message'])) return false;
	$newmessage=array(
		'postdate'	=>time(),
		'postid'	=>$U['postid'],
		'poststatus'=>$U['poststatus'],
		'poster'	=>$U['nickname'],
		'recipient'	=>$U['recipient'],
		'text'		=>$U['displaysend'].style_this($U['message'], $U['fontinfo']),
		'delstatus'	=>$U['delstatus']
	);
	write_message($newmessage);
	return true;
}

function add_system_message($mes){
	$sysmessage=array(
		'postdate'	=>time(),
		'postid'	=>substr(rand(), -6),
		'poststatus'	=>1,
		'poster'	=>'',
		'recipient'	=>'',
		'text'		=>$mes,
		'delstatus'	=>9
	);
	write_message($sysmessage);
}

function write_message($message){
	global $C, $H, $mysqli;
	$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]messages` (`postdate`, `postid`, `poststatus`, `poster`, `recipient`, `text`, `delstatus`) VALUES (?, ?, ?, ?, ?, ?, ?)");
	mysqli_stmt_bind_param($stmt, 'iiisssi', $message['postdate'], $message['postid'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	$limit=$C['keeplimit']*$C['messagelimit'];
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]messages` WHERE `id` NOT IN (SELECT `id` FROM (SELECT `id` FROM `$C[prefix]messages` ORDER BY `postdate` DESC LIMIT ?) t )");
	mysqli_stmt_bind_param($stmt, 'i', $limit);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($C['sendmail'] && $message['poststatus']<9){
		$subject='New Chat message';
		$headers="From: $C[mailsender]\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		$body="<html>$H[begin_body]$message[text]</body></html>";
		mail($C['mailreceiver'], $subject, $body, $headers);
	}
}

function clean_room(){
	global $C, $mysqli;
	mysqli_query($mysqli, "DELETE FROM `$C[prefix]messages`");
	$sysmessage=array(
		'postdate'	=>time(),
		'postid'	=>substr(rand(), -6),
		'poster'	=>'',
		'recipient'	=>'',
		'poststatus'	=>1,
		'text'		=>sprintf(get_setting('msgclean'), $C['chatname']),
		'delstatus'	=>9
	);
	write_message($sysmessage);
}

function clean_selected(){
	global $C, $mysqli;
	if(isSet($_REQUEST['mid'])){
		foreach($_REQUEST['mid'] as $mid) $mids[$mid]=1;
	}
	$result=mysqli_query($mysqli, "SELECT `postdate`, `postid` FROM `$C[prefix]messages` ORDER BY `postdate` DESC");
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]messages` WHERE `postdate`=? AND `postid`=?");
	while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
		if(isSet($mids[$temp['postdate'].$temp['postid']])){
			mysqli_stmt_bind_param($stmt, 'ii', $temp['postdate'], $temp['postid']);
			mysqli_stmt_execute($stmt);
		}
	}
	mysqli_stmt_close($stmt);
}

function del_all_messages($nick){
	global $C, $mysqli;
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]messages` WHERE `poster`=?");
	mysqli_stmt_bind_param($stmt, 's', $nick);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

function del_last_message(){
	global $C, $U, $mysqli;
	$stmt=mysqli_prepare($mysqli, "DELETE FROM `$C[prefix]messages` WHERE `poster`=? ORDER BY `postdate` DESC LIMIT 1");
	mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

function print_messages($delstatus=''){
	global $C, $U, $mysqli;
	$dateformat=get_setting('dateformat');
	mysqli_query($mysqli, "DELETE FROM `$C[prefix]messages` WHERE `postdate`<='".(time()-60*$C['messageexpire'])."'");
	if(!empty($delstatus)){
		$stmt=mysqli_prepare($mysqli, "SELECT `postdate`, `postid`, `text` FROM `$C[prefix]messages` WHERE ".
		"`id` IN (SELECT * FROM (SELECT `id` FROM `$C[prefix]messages` WHERE `poststatus`='1' ORDER BY `postdate` DESC LIMIT ?) AS t) ".
		"OR (`poststatus`>'1' AND (`poststatus`<? OR `poster`=?) ) ORDER BY `postdate` DESC");
		mysqli_stmt_bind_param($stmt, 'iis', $C['messagelimit'], $U['status'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $message['postdate'], $message['postid'], $message['text']);
		while(mysqli_stmt_fetch($stmt)){
			echo "<input type=\"checkbox\" name=\"mid[]\" id=\"$message[postdate]$message[postid]\" value=\"$message[postdate]$message[postid]\"><label for=\"$message[postdate]$message[postid]\">&nbsp;$message[text]</label><br>";
		}
	}else{
		$stmt=mysqli_prepare($mysqli, "SELECT `postdate`, `text` FROM `$C[prefix]messages` WHERE (".
		"`id` IN (SELECT * FROM (SELECT `id` FROM `$C[prefix]messages` WHERE `poststatus`='1' ORDER BY `postdate` DESC LIMIT ?) AS t) ".
		"OR (`poststatus`>'1' AND `poststatus`<=?) ".
		"OR (`poststatus`='9' AND ( (`poster`=? AND `recipient` NOT IN (SELECT * FROM (SELECT `ignored` FROM `$C[prefix]ignored` WHERE `by`=?) AS t) ) OR `recipient`=?) )".
		") AND `poster` NOT IN (SELECT * FROM (SELECT `ignored` FROM `$C[prefix]ignored` WHERE `by`=?) AS t) ORDER BY `postdate` DESC");
		mysqli_stmt_bind_param($stmt, 'iissss', $C['messagelimit'], $U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $message['postdate'], $message['text']);
		if(!isSet($_COOKIE[$C['cookiename']]) && !$C['forceredirect']) $injectRedirect=true; else $injectRedirect=false;
		if(!$U['embed'] || !isSet($_COOKIE[$C['cookiename']])) $removeEmbed=true; else $removeEmbed=false;
		while(mysqli_stmt_fetch($stmt)){
			if($injectRedirect){
				$message['text']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $message['text']);
			}
			if($removeEmbed){
				$message['text']=preg_replace_callback("/<img src=\"(.*?(?=\"))\">/", function ($matched){ return $matched[1];}, $message['text']);
			}
			if($U['timestamps']) echo '<small>'.date($dateformat, $message['postdate']).' - </small>';
			echo "$message[text]<br>";
		}
	}
	mysqli_stmt_close($stmt);
}

// this and that

function get_ignored(){
	global $C, $memcached, $mysqli;
	if($C['memcached']) $ignored=$memcached->get("$C[dbname]-$C[prefix]ignored");
	if(!$C['memcached'] || $memcached->getResultCode()!=Memcached::RES_SUCCESS){
		$ignored=array();
		$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]ignored`");
		while($tmp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			$ignored[]=$tmp;
		}
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]ignored", $ignored);
	}
	return $ignored;
}

function valid_admin(){
	global $U;
	if(isSet($_REQUEST['session'])){
		check_session();
	}
	elseif(isSet($_REQUEST['nick']) && isSet($_REQUEST['pass'])){
		create_session(true);
	}
	if(isSet($U['status']) && $U['status']>=7) return true;
	else return false;
}

function valid_nick($nick){
	global $C;
	return preg_match("/^[a-z0-9]{1,$C[maxname]}$/i", $nick);
}

function valid_pass($pass){
	global $C;
	return preg_match('/^.{'.$C['minpass'].',}$/', $pass);
}

function cleanup_nick($nick){
	$nick=preg_replace('/\s+/', '', $nick);
	return $nick;
}

function get_timeout($lastpost, $status){ // lastpost, status
	global $C;
	if($status>2) $expire=$C['sessionexpire'];
	else $expire=$C['guestsexpire'];
	$s=($lastpost+60*$expire)-time();
	$m=$s/60;$m=floor($m);$s-=$m*60;
	$h=$m/60;$h=floor($h);$m-=$h*60;
	$s=substr('0'.$s, -2, 2);
	if($h>0){
		$m=substr('0'.$m, -2, 2);
		return "$h:$m:$s";
	}
	return "$m:$s";
}

function print_colours(){
	global $C, $I;
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	$colours=array('Beige'=>'F5F5DC', 'Black'=>'000000', 'Blue'=>'0000FF', 'BlueViolet'=>'8A2BE2', 'Brown'=>'A52A2A', 'Cyan'=>'00FFFF', 'DarkBlue'=>'00008B', 'DarkGreen'=>'006400', 'DarkRed'=>'8B0000', 'DarkViolet'=>'9400D3', 'DeepSkyBlue'=>'00BFFF', 'Gold'=>'FFD700', 'Grey'=>'808080', 'Green'=>'008000', 'HotPink'=>'FF69B4', 'Indigo'=>'4B0082', 'LightBlue'=>'ADD8E6', 'LightGreen'=>'90EE90', 'LimeGreen'=>'32CD32', 'Magenta'=>'FF00FF', 'Olive'=>'808000', 'Orange'=>'FFA500', 'OrangeRed'=>'FF4500', 'Purple'=>'800080', 'Red'=>'FF0000', 'RoyalBlue'=>'4169E1', 'SeaGreen'=>'2E8B57', 'Sienna'=>'A0522D', 'Silver'=>'C0C0C0', 'Tan'=>'D2B48C', 'Teal'=>'008080', 'Violet'=>'EE82EE', 'White'=>'FFFFFF', 'Yellow'=>'FFFF00', 'YellowGreen'=>'9ACD32');
	$greybg=greyval($C['colbg']);
	foreach($colours as $name=>$colour){
		if(abs($greybg-greyval($colour))>75) echo "<option value=\"$colour\" style=\"color:#$colour\">$I[$name]</option>";
	}
}

function greyval($colour){
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function get_style($styleinfo){
	$fbold=preg_match('/(<i?bi?>|:bold)/', $styleinfo);
	$fitalic=preg_match('/(<b?ib?>|:italic)/', $styleinfo);
	$fsmall=preg_match('/(size="-1"|:smaller)/', $styleinfo);
	preg_match('/(#.{6})/', $styleinfo, $match);
	if(isSet($match[0])) $fcolour=$match[0];
	preg_match('/face=\'([^"]+)\'/', $styleinfo, $match);
	if(isSet($match[1])) $fface=$match[1];
	preg_match('/font-family:([^;]+);/', $styleinfo, $match);
	if(isSet($match[1])) $sface=$match[1];
	if(isSet($fface)){
		$sface=$fface;
		$sface=preg_replace('/^/', "'", $sface);
		$sface=preg_replace('/$/', "'", $sface);
		$sface=preg_replace('/,/', "','", $sface);
	}elseif(isSet($sface)){
		$fface=$sface;
		$fface=preg_replace("/'/", '', $fface);
	}
	$fstyle='';
	if(isSet($fcolour)) $fstyle.="color:$fcolour;";
	if(isSet($sface)) $fstyle.="font-family:$sface;";
	if($fsmall) $fstyle.='font-size:smaller;';
	if($fitalic) $fstyle.='font-style:italic;';
	if($fbold) $fstyle.='font-weight:bold;';
	return $fstyle;
}

function style_this($text, $styleinfo){
	$fbold=preg_match('/(<i?bi?>|:bold)/', $styleinfo);
	$fitalic=preg_match('/(<b?ib?>|:italic)/', $styleinfo);
	$fsmall=preg_match('/(size="-1"|:smaller)/', $styleinfo);
	preg_match('/(#.{6})/', $styleinfo, $match);
	if(isSet($match[0])) $fcolour=$match[0];
	preg_match('/face=\'([^"]+)\'/', $styleinfo, $match);
	if(isSet($match[1])) $fface=$match[1];
	preg_match('/font-family:([^;]+);/', $styleinfo, $match);
	if(isSet($match[1])) $sface=$match[1];
	if(isSet($fface)){
		$sface=$fface;
		$sface=preg_replace('/^/', "'", $sface);
		$sface=preg_replace('/$/', "'", $sface);
		$sface=preg_replace('/,/', "','", $sface);
	}elseif(isSet($sface)){
		$fface=$sface;
		$fface=preg_replace("/'/", '', $fface);
	}
	$fstyle='';
	if(isSet($fcolour)) $fstyle.="color:$fcolour;";
	if(isSet($sface)) $fstyle.="font-family:$sface;";
	if($fsmall) $fstyle.='font-size:smaller;';
	if($fitalic) $fstyle.='font-style:italic;';
	if($fbold) $fstyle.='font-weight:bold;';
	$fstart='<font';
	if(!isSet($fcolour)) $fstart.=" color=\"$fcolour\"";
	if(isSet($fface)) $fstart.=" face=\"$fface\"";
	if($fsmall) $fstart.=" size=\"-1\"";
	if(!empty($fstyle)) $fstart.=" style=\"$fstyle\"";
	$fstart.='>';
	if($fbold) $fstart.='<b>';
	if($fitalic) $fstart.='<i>';
	$fend='';
	if($fitalic) $fend.='</i>';
	if($fbold) $fend.='</b>';
	$fend.='</font>';
	return "$fstart$text$fend";
}

function init_chat(){
	global $C, $H, $I, $memcached, $mysqli;
	$suwrite='';
	if(!$C['memcached'] || !$num_tables=$memcached->get("$C[dbname]-$C[prefix]num-tables")){
		$tables=array("$C[prefix]captcha", "$C[prefix]filter", "$C[prefix]ignored", "$C[prefix]members", "$C[prefix]messages", "$C[prefix]notes", "$C[prefix]sessions", "$C[prefix]settings");
		$num_tables=0;
		$result=mysqli_query($mysqli, 'SHOW TABLES');
		while($tmp=mysqli_fetch_array($result, MYSQLI_NUM)){
			if(in_array($tmp[0],$tables)) ++$num_tables;
		}
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]num-tables", $num_tables, 60);
	}
	if($num_tables>=7){
		$suwrite=$I['initdbexist'];
		$result=mysqli_query($mysqli, "SELECT * FROM `$C[prefix]members` WHERE `status`='8'");
		if(mysqli_num_rows($result)>0){
			$suwrite=$I['initsuexist'];
		}
	}elseif(!valid_nick($_REQUEST['sunick'])){
		$suwrite=sprintf($I['invalnick'], $C['maxname']);
	}elseif(!valid_pass($_REQUEST['supass'])){
		$suwrite=sprintf($I['invalpass'], $C['minpass']);
	}elseif($_REQUEST['supass']!==$_REQUEST['supassc']){
		$suwrite=$I['noconfirm'];
	}else{
		mysqli_multi_query($mysqli, 	"CREATE TABLE IF NOT EXISTS `$C[prefix]captcha` (`id` int(10) unsigned NOT NULL, `time` int(10) unsigned NOT NULL, `code` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]filter` (`id` tinyint(3) unsigned NOT NULL, `match` tinytext NOT NULL, `replace` text NOT NULL, `allowinpm` tinyint(1) unsigned NOT NULL, `regex` tinyint(1) unsigned NOT NULL, `kick` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]ignored` (`id` int(10) unsigned NOT NULL, `ignored` tinytext NOT NULL, `by` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]members` (`id` tinyint(3) unsigned NOT NULL, `nickname` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `passhash` tinytext NOT NULL, `status` tinyint(3) unsigned NOT NULL, `refresh` tinyint(3) unsigned NOT NULL, `colour` tinytext NOT NULL, `bgcolour` tinytext NOT NULL, `fontface` tinytext NOT NULL, `fonttags` tinytext NOT NULL, `boxwidth` tinyint(3) unsigned NOT NULL, `boxheight` tinyint(3) unsigned NOT NULL, `notesboxheight` tinyint(3) unsigned NOT NULL, `notesboxwidth` tinyint(3) unsigned NOT NULL, `regedby` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `lastlogin` int(10) unsigned NOT NULL, `timestamps` tinyint(1) unsigned NOT NULL, `embed` tinyint(1) unsigned NOT NULL, `incognito` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]messages` (`id` int(10) unsigned NOT NULL, `postdate` int(10) unsigned NOT NULL, `postid` int(10) unsigned NOT NULL, `poststatus` tinyint(3) unsigned NOT NULL, `poster` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `recipient` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `text` text NOT NULL, `delstatus` tinyint(3) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]notes` (`id` int(10) unsigned NOT NULL, `type` tinytext NOT NULL, `lastedited` int(10) unsigned NOT NULL, `editedby` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `text` text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]sessions` (`id` int(10) unsigned NOT NULL, `session` tinytext NOT NULL, `nickname` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `displayname` text NOT NULL, `status` tinyint(3) unsigned NOT NULL, `refresh` tinyint(3) unsigned NOT NULL, `fontinfo` tinytext NOT NULL, `style` text NOT NULL, `lastpost` int(10) unsigned NOT NULL, `passhash` tinytext NOT NULL, `postid` int(10) unsigned NOT NULL, `boxwidth` tinyint(3) unsigned NOT NULL, `boxheight` tinyint(3) unsigned NOT NULL, `useragent` text NOT NULL, `kickmessage` text NOT NULL, `bgcolour` tinytext NOT NULL, `notesboxheight` tinyint(3) unsigned NOT NULL, `notesboxwidth` tinyint(3) unsigned NOT NULL, `entry` int(10) unsigned NOT NULL, `timestamps` tinyint(1) unsigned NOT NULL, `embed` tinyint(1) unsigned NOT NULL, `incognito` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"CREATE TABLE IF NOT EXISTS `$C[prefix]settings` (`id` tinyint(3) unsigned NOT NULL, `setting` tinytext NOT NULL, `value` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; ".
						"ALTER TABLE `$C[prefix]captcha` ADD UNIQUE KEY `id` (`id`); ".
						"ALTER TABLE `$C[prefix]filter` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]ignored` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]members` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]messages` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]notes` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]sessions` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]settings` ADD PRIMARY KEY (`id`); ".
						"ALTER TABLE `$C[prefix]filter` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]ignored` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]members` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]messages` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]notes` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]sessions` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; ".
						"ALTER TABLE `$C[prefix]settings` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('guestaccess','0'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('globalpass',''); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('englobalpass','0'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('captcha','0'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('dateformat','m-d H:i:s'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('rulestxt', '1. YOUR_RULS<br>2. YOUR_RULES'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgenter','%s entered the chat.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgexit','%s left the chat.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgmemreg','%s is now a registered member.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgsureg','%s is now a registered applicant.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgkick','%s has been kicked.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgmultikick','%s have been kicked.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgallkick','All chatters have been kicked.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('msgclean','%s has been cleaned.'); ".
						"INSERT INTO `$C[prefix]settings` (`setting`,`value`) VALUES ('dbversion','$C[dbversion]');");
		while(mysqli_next_result($mysqli)) {;}
		$reg=array(
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>md5(sha1(md5($_REQUEST['sunick'].$_REQUEST['supass']))),
			'status'	=>8,
			'refresh'	=>$C['defaultrefresh'],
			'colour'	=>$C['coltxt'],
			'bgcolour'	=>$C['colbg'],
			'boxwidth'	=>$C['boxwidth'],
			'boxheight'	=>$C['boxheight'],
			'notesboxwidth'	=>$C['notesboxwidth'],
			'notesboxheight'=>$C['notesboxheight'],
			'timestamps'	=>$C['timestamps'],
			'embed'		=>$C['embed'],
			'incognito'	=>false
		);
		$stmt=mysqli_prepare($mysqli, "INSERT INTO `$C[prefix]members` (`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `timestamps`, `embed`, `incognito`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		mysqli_stmt_bind_param($stmt, 'ssiissiiiiiii', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['timestamps'], $reg['embed'], $reg['incognito']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$suwrite=$I['susuccess'];
	}
	print_start();
	echo "<center><h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo "<$H[form]>".hidden('action', 'setup').submit($I['initgosetup']).'</form>';
	print_credits();
	print_end();
}

function update_db(){
	global $C, $mysqli;
	$dbversion=get_setting('dbversion');
	if($dbversion<$C['dbversion']){
		if($dbversion<2){
			mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `$C[prefix]ignored` (`id` int(10) unsigned NOT NULL, `ignored` tinytext NOT NULL, `by` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			mysqli_query($mysqli, "ALTER TABLE `$C[prefix]ignored` ADD PRIMARY KEY (`id`)");
			mysqli_query($mysqli, "ALTER TABLE `$C[prefix]ignored` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT");
		}
		if($dbversion<3){
			mysqli_query($mysqli, "INSERT INTO `$C[prefix]settings` (`setting`, `value`) VALUES ('rulestxt', '1. YOUR_RULS<br>2. YOUR_RULES')");
		}
		if($dbversion<4){
			mysqli_query($mysqli, "ALTER TABLE `$C[prefix]members` ADD `incognito` TINYINT(1) UNSIGNED NOT NULL");
			mysqli_query($mysqli, "ALTER TABLE `$C[prefix]sessions` ADD `incognito` TINYINT(1) UNSIGNED NOT NULL");
		}
		if($dbversion<5){
			mysqli_query($mysqli, "INSERT INTO `$C[prefix]settings` (`setting`, `value`) VALUES ('globalpass', '')");
		}
		if($dbversion<6){
			mysqli_query($mysqli, "INSERT INTO `$C[prefix]settings` (`setting`, `value`) VALUES ('dateformat', 'm-d H:i:s')");
		}
		if($dbversion<7){
			mysqli_query($mysqli, "ALTER TABLE `$C[prefix]captcha` ADD `code` TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
		}
		if($dbversion<8){
			mysqli_query($mysqli, "INSERT INTO `$C[prefix]settings` (`setting`, `value`) VALUES ('captcha', '0')");
			mysqli_query($mysqli, "INSERT INTO `$C[prefix]settings` (`setting`, `value`) VALUES ('englobalpass', '0')");
			$ga=get_setting('guestaccess');
			if($ga==-1){
				update_setting('guestaccess', 0);
				update_setting('englobalpass', 1);
			}elseif($ga==4){
				update_setting('guestaccess', 1);
				update_setting('englobalpass', 2);
			}
		}
		update_setting('dbversion', $C['dbversion']);
		send_update();
	}
}

function update_messages(){
	global $C;
	update_setting('msgenter', $_REQUEST['msgenter']);
	update_setting('msgexit', $_REQUEST['msgexit']);
	update_setting('msgmemreg', $_REQUEST['msgmemreg']);
	if($C['suguests']) update_setting('msgsureg', $_REQUEST['msgsureg']);
	update_setting('msgkick', $_REQUEST['msgkick']);
	update_setting('msgmultikick', $_REQUEST['msgmultikick']);
	update_setting('msgallkick', $_REQUEST['msgallkick']);
	update_setting('msgclean', $_REQUEST['msgclean']);
}

function get_setting($setting){
	global $C, $memcached, $mysqli;
	if(!$C['memcached'] || !$value=$memcached->get("$C[dbname]-$C[prefix]settings-$setting")){
		$stmt=mysqli_prepare($mysqli, "SELECT `value` FROM `$C[prefix]settings` WHERE `setting`=?");
		mysqli_stmt_bind_param($stmt, 's', $setting);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $value);
		mysqli_stmt_fetch($stmt);
		mysqli_stmt_close($stmt);
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]settings-$setting", $value);
	}
	return $value;
}

function update_setting($setting, $value){
	global $C, $memcached, $mysqli;
	$stmt=mysqli_prepare($mysqli, "UPDATE `$C[prefix]settings` SET `value`=? WHERE `setting`=?");
	mysqli_stmt_bind_param($stmt, 'ss', $value, $setting);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]settings-$setting", $value);
}

// configuration, defaults and internals

function load_fonts(){
	global $F;
	$F=array(
		'Arial'			=>" face='Arial,Helvetica,sans-serif'",
		'Book Antiqua'		=>" face='Book Antiqua,MS Gothic'",
		'Comic'			=>" face='Comic Sans MS,Papyrus'",
		'Comic small'		=>" face='Comic Sans MS,Papyrus' size=\"-1\"",
		'Courier'		=>" face='Courier New,Courier,monospace'",
		'Cursive'		=>" face='Cursive,Papyrus'",
		'Fantasy'		=>" face='Fantasy,Futura,Papyrus'",
		'Garamond'		=>" face='Garamond,Palatino,serif'",
		'Georgia'		=>" face='Georgia,Times New Roman,Times,serif'",
		'Serif'			=>" face='MS Serif,New York,serif'",
		'System'		=>" face='System,Chicago,sans-serif'",
		'Times New Roman'	=>" face='Times New Roman,Times,serif'",
		'Verdana'		=>" face='Verdana,Geneva,Arial,Helvetica,sans-serif'",
		'Verdana small'		=>" face='Verdana,Geneva,Arial,Helvetica,sans-serif' size=\"-1\""
	);
}

function load_html(){
	global $C, $H, $I;
	$H=array(// default HTML
		'begin_body'	=>"<body bgcolor=\"#$C[colbg]\" text=\"#$C[coltxt]\" link=\"#$C[collnk]\" alink=\"#$C[colact]\" vlink=\"#$C[colvis]\">",
		'form'		=>"form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\" style=\"margin:0;padding:0;\"",
		'meta_html'	=>"<title>$C[chatname]</title><meta name=\"robots\" content=\"noindex,nofollow\"><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><meta http-equiv=\"Pragma\" content=\"no-cache\"><meta http-equiv=\"Cache-Control\" content=\"no-cache\"><meta http-equiv=\"expires\" content=\"0\">"
	);
	$H=$H+array(
		'backtologin'	=>"<$H[form] target=\"_parent\">".submit($I['backtologin'], ' style="background-color:#004400;color:#FFFFFF;"').'</form>',
		'backtochat'	=>"<$H[form]>".hidden('action', 'view').@hidden('session', $_REQUEST['session']).submit($I['backtochat'], ' style="background-color:#004400;color:#FFFFFF;"').'</form>'
	);
}

function check_db(){
	global $C, $I, $memcached, $mysqli;
	$mysqli=mysqli_connect($C['dbhost'], $C['dbuser'], $C['dbpass'], $C['dbname']);
	if(mysqli_connect_errno($mysqli)){
		if($_REQUEST['action']=='setup'){
			die($I['nodbsetup']);
		}else{
			die($I['nodb']);
		}
	}
	if($C['memcached']){
		$memcached=new Memcached();
		$memcached->addServer($C['memcachedhost'], $C['memcachedport']);
	}
}

function load_lang(){
	global $C, $I, $L;
	$L=array(
		'de'	=>'Deutsch',
		'en'	=>'English'
	);
	if(isSet($_REQUEST['lang']) && array_key_exists($_REQUEST['lang'], $L)){
		$C['lang']=$_REQUEST['lang'];
		setcookie('language', $C['lang']);
	}elseif(isSet($_COOKIE['language']) && array_key_exists($_COOKIE['language'], $L)){
		$C['lang']=$_COOKIE['language'];
	}
	include('lang_en.php'); //always include English
	if($C['lang']!=='en') include("lang_$C[lang].php"); //replace with translation if available
}

function load_config(){
	global $C;
	$C=array(
		'version'	=>'1.9.2', // Script version
		'dbversion'	=>8, // Database version
		'showcredits'	=>false, // Allow showing credits
		'colbg'		=>'000000', // Background colour
		'coltxt'	=>'FFFFFF', // Default text colour
		'collnk'	=>'0000FF', // Link colour
		'colvis'	=>'B33CB4', // Visited link colour
		'colact'	=>'FF0033', // Clicked link colour
		'sessionexpire'	=>60, // Minutes until a member session expires
		'guestsexpire'	=>15, // Minutes until a guest session expires
		'kickpenalty'	=>10, // Minutes a nickname is blocked when it got kicked
		'entrywait'	=>120, // Seconds to wait in the waiting room after login
		'chatname'	=>'My Chat', // Chat Name
		'messageexpire'	=>14400, // Minutes until a message expires
		'messagelimit'	=>150, // Max messages displayed
		'keeplimit'	=>3, // Amount of messages to keep in the database (multiplied with max messages displayed) - increase if you have many private messages
		'defaultrefresh'=>30, // Seconds to refresh the messages
		'maxmessage'	=>2000, // Longest number of characters for a message
		'maxname'	=>20, // Longest number of chatacters for a name
		'minpass'	=>5, // Shortest number of chatacters for a password
		'boxwidth'	=>40, // Default post box width
		'boxheight'	=>3, // Default post box height
		'notesboxwidth'	=>80, // Default notes box width
		'notesboxheight'=>30, // Default notes box height
		'dbhost'	=>'p:localhost', // Database host
		'dbuser'	=>'www-data', // Database user
		'dbpass'	=>'YOUR_DB_PASS', // Database password
		'dbname'	=>'public_chat', // Database
		'prefix'	=>'', // Prefix - Set this to a unique value for every chat, if you have more than 1 chats on the same database or domain
		'memcached'	=>false, // Enable/disable memcached caching true/false - needs php5-memcached and a memcached server.
		'memcachedhost'	=>'localhost', // Memcached server
		'memcachedport'	=>'11211', // Memcached server
		'captchachars'	=>'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // Characters used for captcha generation
		'captchatime'	=>600, // Seconds until the captcha expires
		'dismemcaptcha'	=>false, // Disable captcha for members? true/false
		'embed'		=>true, // Default for displaying embedded imgs or turning them into links true/false
		'imgembed'	=>true, // Allow image embedding in chat using [img] tag? true/false
		'suguests'	=>false, // Adds option to add applicants. They will have a reserved nick protected with a password, but don't count as member true/false
		'timestamps'	=>true, // Display timestamps in front of the messages by default true/false
		'incognito'	=>true, // Allow mods and admins to be invisable true/false
		'forceredirect'	=>false, // Force redirect script or only use when no cookies available? true/false
		'msglogout'	=>false, // Add a message on member logout
		'msglogin'	=>true, // Add a message on member login
		'msgkick'	=>true, // Add a message when kicking someone
		'memkick'	=>true, // Let a member kick guests if no mod is present
		'sendmail'	=>false, // Send mail on new message - only activate on low traffic chat or your inbox will fill up very fast!
		'mailsender'	=>'www-data <www-data@localhost>', // Send mail using this e-Mail address
		'mailreceiver'	=>'Webmaster <webmaster@localhost>', // Send mail to this e-Mail address
		'redirect'	=>"$_SERVER[SCRIPT_NAME]?action=redirect&url=", // Redirect script default: "$_SERVER[SCRIPT_NAME]?action=redirect&url="
		'lang'		=>'en' // Default language
	);
	$C=$C+array(
		'cookiename'	=>"$C[prefix]chat_session" // Cookie name storing the session information
	);
}
?>
