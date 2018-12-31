<?php

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("newpoints_start", "newpoints_pin_page");
$plugins->add_hook("newpoints_default_menu", "newpoints_pin_menu");
$plugins->add_hook("forumdisplay_start", "newpoints_pin_expire");


function newpoints_pin_info()
{
	return array(
		"name"			=> "Pin your thread",
		"description"	=> "Pay with credits to pin your thread.",
		"website"		=> "https://developement.design/",
		"author"		=> "AmazOuz, D&D Team",
		"authorsite"	=> "https://developement.design/",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}


function newpoints_pin_install()
{
	global $db;
	$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `sticky_threads` TEXT NOT NULL;");
	
	newpoints_add_setting('newpoints_pin_price', 'newpoints_pin', 'Price', 'Price to pay for sticking a thread.', 'text', '20', 1);
	newpoints_add_setting('newpoints_pin_duration', 'newpoints_pin', 'Duration', 'How many days will remain the thread as sticky.', 'text', '7', 2);
	
	$db->write_query("CREATE TABLE `".TABLE_PREFIX."newpoints_pin` (
	  `id` bigint(30) UNSIGNED NOT NULL auto_increment,
	  `expiry` DATETIME NOT NULL,
	  `tid` int(10) NOT NULL default '0',
	  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM");
	
	rebuild_settings();
}


function newpoints_pin_is_installed()
{
	global $db;
	if($db->field_exists('sticky_threads', 'users'))
	{
		return true;
	}
	return false;
}


function newpoints_pin_uninstall()
{
	global $db;
	$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP `sticky_threads`;");
	
	newpoints_remove_settings("'newpoints_pin_price','newpoints_pin_duration'");
	rebuild_settings();
	
	if($db->table_exists('newpoints_pin'))
	{
		$db->drop_table('newpoints_pin');
	}
	
}


function newpoints_pin_activate()
{
	global $db, $mybb;
	
	newpoints_add_template('newpoints_pin', '
<html>
<head>
<title>{$lang->newpoints} - Sticky my thread</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top" width="180">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
</tr>
{$options}
</table>
</td>
<td valign="top">
<form action="newpoints.php?action=pin&pin_action=buy" method="POST">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="{$colspan}"><strong>Pin my thread</strong></td>
</tr>
<tr>
	<td class="trow1" colspan="{$colspan}">Pick your thread and get better presence in the forums. The duration of the sticky thread is {$duration} days, the price is therefore {$price} Credits<br><br>{$inline_errors}<strong>Select the thread you want to pin:</strong><br><br>
		<select name="thread">
			{$own_threads}
		</select>
		<input class="button" type="submit" name="submit" value="Buy">
	</td>
</tr>
</table>
</form><br>
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>My pinned threads</strong></td>
</tr>
<tr>
<td class="tcat" width="70%"><strong>Thread</strong></td>
<td class="tcat" width="30%"><strong>Expiry date</strong></td>
</tr>
{$pinned_threads}
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>');
	
	newpoints_add_template('newpoints_pin_thread', '<tr>
        <td class="trow1">{$pinned_thread}</td>
        <td class="trow1">{$expiry_date}</td>
    </tr>');
	
	
	
	newpoints_add_template('newpoints_pin_empty', '
<tr>
<td class="trow1" colspan="2">You currently have no pinned thread.</td>
</tr>');
	
}


function newpoints_pin_deactivate()
{
	global $db, $mybb;
	
	newpoints_remove_templates("'newpoints_pin','newpoints_pin_thread','newpoints_pin_empty'");
	
}


function newpoints_pin_menu(&$menu)
{
	global $mybb;
	
	if ($mybb->input['action'] == 'pin')
		$menu[] = "&raquo; <a href=\"{$mybb->settings['bburl']}/newpoints.php?action=pin\">Sticky thread</a>";
	else
		$menu[] = "<a href=\"{$mybb->settings['bburl']}/newpoints.php?action=pin\">Sticky thread</a>";
}


function newpoints_pin_page()
{
	global $mybb, $db, $lang, $cache, $theme, $header, $templates, $plugins, $headerinclude, $footer, $options, $inline_errors;
	
	if (!$mybb->user['uid'])
		return;
		
	if ($mybb->input['action'] == "pin")
	{	
		if (!empty($mybb->input['pin_action']) and $mybb->input['pin_action'] == 'buy')
        {
            if (isset($mybb->input['thread']))
            {
                $query = $db->simple_select('threads', '*', 'uid=\''.intval($mybb->user['uid']).'\' and tid=\''.intval($mybb->input['thread']).'\'');
                while($t = $db->fetch_array($query))
                {
                    $own_thread = true;
                    $tid = $t['tid'];
                }
                $query2 = $db->simple_select('newpoints_pin', '*', 'tid=\''.intval($mybb->input['thread']).'\'');
                while($s = $db->fetch_array($query2))
                {
                    $already = true;
                }
                if ($own_thread == true & $already == false)
                {
                    if ($mybb->user['newpoints'] >= $mybb->settings['newpoints_pin_price'])
                    {
                        $mybb->user['newpoints'] = $mybb->user['newpoints'] - $mybb->settings['newpoints_pin_price'];
                        $pinned = unserialize($mybb->user['sticky_threads']);
                        if (!is_array($pinned))
                        {
                            $pinned = array($tid);
                        }
                        else 
                        {
                            array_push($pinned, $tid);
                            var_dump($pinned);
                        }
                        $db->write_query("INSERT INTO ".TABLE_PREFIX."newpoints_pin(tid, expiry) VALUES(".$tid.", NOW() + INTERVAL ".$mybb->settings['newpoints_pin_duration']." DAY)");
                        $db->write_query("UPDATE ".TABLE_PREFIX."users SET newpoints = ".$mybb->user['newpoints']." WHERE uid = ".$mybb->user['uid']);
                        $db->write_query("UPDATE ".TABLE_PREFIX."users SET sticky_threads = '".serialize($pinned)."' WHERE uid = ".$mybb->user['uid']);
                        $db->write_query("UPDATE ".TABLE_PREFIX."threads SET sticky = 1 WHERE tid = ".$tid);
                        header('Location:newpoints.php?action=pin&result=success');
                    }
                    else
                    {
                        header('Location:newpoints.php?action=pin&result=credits');
                    }
                }
                else
                {
                    header('Location:newpoints.php?action=pin&result=error');
                }
            }
            else
            {
                header('Location:newpoints.php?action=pin&result=error');
            }
            
        }
        else
        {
            if (!empty($mybb->input['result']) and $mybb->input['result'] == 'credits')
            {
                $inline_errors = "<strong style='color:red;'>Oops! You do not have enough credits.</strong><br><br>";
            }
            if (!empty($mybb->input['result']) and $mybb->input['result'] == 'error')
            {
                $inline_errors = "<strong style='color:red;'>Ow! The thread is already pinned, if you feel it's an error then please contact the support.</strong><br><br>";
            }
            elseif (!empty($mybb->input['result']) and $mybb->input['result'] == 'success')
            {
                $inline_errors = "<strong style='color:#2ecc71;'>Yay! Your thread was successfully pinned.</strong><br><br>";;
            }
            
            $own_threads = "";
            $query = $db->simple_select('threads', '*', 'uid=\''.intval($mybb->user['uid']).'\'');
            while($t = $db->fetch_array($query))
            {
                $own_threads .= "<option value='".$t['tid']."'>".htmlspecialchars($t['subject'])."</option>";
            }
            $price = $mybb->settings['newpoints_pin_price'];
            $duration = $mybb->settings['newpoints_pin_duration'];
            $pinned = unserialize($mybb->user['sticky_threads']);
            if (!is_array($pinned))
            {
                $pinned = array();
            }
            if (empty($pinned) or !$pinned)
            {
                eval("\$pinned_threads = \"".$templates->get('newpoints_pin_empty')."\";");
            }
            else
            {
                $pinned_threads = "";
                foreach($pinned as $tid)
                {
                    $query = $db->simple_select('threads', '*', 'tid=\''.intval($tid).'\'');
                    while($t = $db->fetch_array($query))
                    {
                        $pinned_thread = htmlspecialchars($t['subject']);
                    }
                    $query2 = $db->simple_select('newpoints_pin', '*', 'tid=\''.intval($tid).'\'');
                    while($s = $db->fetch_array($query2))
                    {
                        $expiry_date = $s['expiry'];
                    }
                    eval("\$pinned_threads .= \"".$templates->get('newpoints_pin_thread')."\";");
                }
            }
            eval("\$page = \"".$templates->get('newpoints_pin')."\";");
            output_page($page);
        }
		
	}
}

function newpoints_pin_expire()
{
    global $mybb, $db;
    $expired = array();
    $query = $db->simple_select('newpoints_pin', '*', 'expiry < NOW()');
    while ($thread = $db->fetch_array($query))
    {
        array_push($expired, $thread['tid']);
    }
    foreach ($expired as $t)
    {
        $db->write_query("DELETE FROM ".TABLE_PREFIX."newpoints_pin WHERE tid = ".$t);
        $db->write_query("UPDATE ".TABLE_PREFIX."threads SET sticky = 0 WHERE tid = ".$t);
        $query2 = $db->simple_select('users', '*', 'sticky_threads LIKE \'%"'.$t.'"%\'');
        while ($user = $db->fetch_array($query2))
        {
            $sticky_threads = unserialize($user['sticky_threads']);
            $sticky_threads = array_diff($sticky_threads, [$t]);
            $uid = $user['uid'];
        }
        $db->write_query("UPDATE ".TABLE_PREFIX."users SET sticky_threads = '".unserialize($sticky_threads)."' WHERE uid = ".$uid);
    }
    
}





?>