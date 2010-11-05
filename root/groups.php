<?php
/**
*
* @package Groups/Contributers page
* @version $Id: groups.php,v 0034 02:07 17/02/2010 kenny Exp $
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';  // Remember and change this to refelct your site
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/groups');

$user_id	= request_var('u', 0);
$group_id	= request_var('g', 0);

/**
* Config settings - these are some settings you can use to change what features you want to enable and what you don't want to display :)
*/
// Set to false if you don't want the group rank image to be displayed
$group_rank_img = true;

// Set to true if you want the group rank title to be displayed
$group_rank_title = false;

// Set to false if you don't want the group to be displayed
$group_desc = true;

/**
*  End: Config settings
*/

// Support/Release Topic - http://www.sixstringmods.co.uk/viewtopic.php?f=20&t=344

// Output page
page_header($user->lang['GROUP_TITLE']);

// I would like to thank the wiki many times over <3 - http://wiki.phpbb.com/Template_Syntax
$sql = 'SELECT * FROM ' . GROUPS_TABLE . '
	WHERE group_type <> 2
	AND group_id > 3
	AND group_id <> 6
	AND group_name <> "NEWLY_REGISTERED" 	
	ORDER BY group_name';
$result = $db->sql_query($sql);

while($groups = $db->sql_fetchrow($result))
{

	// Grab rank information for later
	$ranks = $cache->obtain_ranks();

	// Do we have a Group Rank?
	if ($groups['group_rank'])
	{
		if (isset($ranks['special'][$groups['group_rank']]))
		{
			$rank_title = $ranks['special'][$groups['group_rank']]['rank_title'];
		}
		$rank_img = (!empty($ranks['special'][$groups['group_rank']]['rank_image'])) ? '<img src="' . $config['ranks_path'] . '/' . $ranks['special'][$groups['group_rank']]['rank_image'] . '" alt="' . $ranks['special'][$groups['group_rank']]['rank_title'] . '" title="' . $ranks['special'][$groups['group_rank']]['rank_title'] . '" /><br />' : '';
		$rank_img_src = (!empty($ranks['special'][$groups['group_rank']]['rank_image'])) ? $config['ranks_path'] . '/' . $ranks['special'][$groups['group_rank']]['rank_image'] : '';
	}
	else
	{
		$rank_title = '';
		$rank_img = '';
		$rank_img_src = '';
	}

	$template->assign_block_vars('groups', array(
		'GROUP_ID'				=> $groups['group_id'],
		'GROUP_NAME'			=> ($groups['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $groups['group_name']] : $groups['group_name'],
		'GROUP_DESC'			=> generate_text_for_display($groups['group_desc'], $groups['group_desc_uid'], $groups['group_desc_bitfield'], $groups['group_desc_options']),
		'GROUP_COLOUR'			=> $groups['group_colour'],
		'GROUP_RANK'			=> $rank_title,

		'RANK_IMG'				=> $rank_img,
		'RANK_IMG_SRC'			=> $rank_img_src,

		'U_VIEW_GROUP'			=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group&amp;g=' . $groups['group_id']),

		'S_SHOW_RANK'			=> true,
		'S_SHOW_RANK_IMG'		=> $group_rank_img,
		'S_SHOW_GROUP_RANK'		=> $group_rank_title,
		'S_SHOW_GROUP_DESC'		=> $group_desc,
	));

	// Grab the leaders - always, on every page...
	$lsql = 'SELECT u.user_id, u.username, u.username_clean, u.user_colour, u.group_id, ug.group_leader
	FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . " ug
		WHERE ug.group_id = " . $groups['group_id'] . "
			AND u.user_id = ug.user_id
			AND ug.group_leader = 1
			ORDER BY ug.group_leader DESC, u.username_clean";
	$lresult = $db->sql_query($lsql);

	while($leaders = $db->sql_fetchrow($lresult))
	{
		$template->assign_block_vars('groups.leaders', array(
			'USERNAME'			=> $leaders['username'],
			'USERNAME_FULL'		=> get_username_string('full', $leaders['user_id'], $leaders['username'], $leaders['user_colour']),
			'U_VIEW_PROFILE'	=> get_username_string('profile', $leaders['user_id'], $leaders['username']),
			'S_GROUP_DEFAULT'	=> ($leaders['group_id'] == $group_id) ? true : false,
			'USER_ID'			=> $leaders['user_id'],
		));
	}
	$db->sql_freeresult($lresult);

	// We have the leaders, so lets find other peeps from the group
	$msql = 'SELECT u.user_id, u.username, u.username_clean, u.user_colour, u.group_id, ug.group_leader, ug.group_leader
	FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . " ug
		WHERE ug.group_id = " . $groups['group_id'] . "
			AND u.user_id = ug.user_id
			AND ug.group_leader = 0
			ORDER BY u.username_clean";
	$mresult = $db->sql_query($msql);

	while($members = $db->sql_fetchrow($mresult))
	{
		$template->assign_block_vars('groups.members', array(
			'USER_ID'			=> $members['user_id'],
			'USERNAME'			=> $members['username'],
			'USERNAME_FULL'		=> get_username_string('full', $members['user_id'], $members['username'], $members['user_colour']),
			'U_VIEW_PROFILE'	=> get_username_string('profile', $members['user_id'], $members['username']),
			'S_GROUP_DEFAULT'	=> ($members['group_id'] == $group_id) ? true : false,
		));
	}
	$db->sql_freeresult($mresult);
}
$db->sql_freeresult($result);

// Set up the Navlinks for the forums navbar
$template->assign_block_vars('navlinks', array(
    'FORUM_NAME' 		=> $user->lang['GROUPS'],
    'U_VIEW_FORUM'  	=> append_sid("{$phpbb_root_path}groups.$phpEx"))
);

// Set the template for the page
$template->set_filenames(array(
	'body' => 'groups_body.html')
);

// Assign specific vars
$template->assign_vars(array(
	'L_GROUPS_TEXT'		=> $user->lang['GROUPS_TEXT'],
));

page_footer();

?>